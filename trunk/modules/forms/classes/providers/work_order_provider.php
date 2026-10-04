<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/forms/classes/providers/work_order_provider.php
//
/**
 * Forms — work order provider.
 *
 * Port of extensions/Modules/work_orders/classes/wo_builder.php, the
 * special_class behind the Work Order travelller. See docs/forms-design.md §5.
 *
 * A work order is one wo_journal_main row, so build() takes its ids. The line
 * table is the routing: one row per step, joined to the task that defines it.
 *
 * This is the only shipped form carrying an image and a barcode, so the record
 * exposes the SKU three ways — 'sku' for text, 'bar_code' for the symbology and
 * 'image_with_path' for the picture. They are the same string; keeping the names
 * distinct is what lets the template bind each to the right element.
 *
 * Two legacy defects are corrected rather than reproduced, both of which lose
 * data silently; see buildRefLists() and §12.3.1.
 *
 * A provider sits ABOVE the seam, so database access here is correct and
 * expected; it is the returned Document that must be free of it.
 */

namespace FreeBooks\Forms\Provider;

use FreeBooks\Forms\Document\{
    Context, Document, DocumentProvider,
    LayoutDocument, PageSetup, Record, Template, TypedValue
};

final class WorkOrderDocumentProvider implements DocumentProvider
{
    public function __construct(
        private readonly \queryFactory $db,
        private readonly Template      $template,
        private readonly Context       $context = new Context(),
        private readonly string        $title = 'Work Order',
        private readonly PageSetup     $page = new PageSetup(),
    ) {}

    /** @param array{record_ids?:list<int|string>} $params wo_journal_main ids */
    public function build(array $params): Document
    {
        $records = [];
        foreach ($params['record_ids'] ?? [] as $id) {
            $record = $this->buildRecord((int)$id);
            if ($record !== null) $records[] = $record;
        }
        return new LayoutDocument(
            title: $this->title,
            template: $this->template,
            records: $records,
            context: $this->context,
            page: $this->page,
        );
    }

    /** One work order. Mirrors wo_builder::load_query_results(). */
    private function buildRecord(int $id): ?Record
    {
        if (!$id) return null;
        $row = $this->db->Execute("select * from " . TABLE_WO_JOURNAL_MAIN . " where id = " . $id)->fields;
        if (!$row) return null;

        $f = [];
        foreach ($row as $k => $v) {
            $f[$k] = function_exists('db_prepare_input') ? db_prepare_input($v) : $v;
        }

        $steps = $this->loadSteps($id);

        $skuId = (int)($row['sku_id'] ?? 0);
        $f['bom_list'] = $this->buildBomList($skuId, (float)($row['qty'] ?? 0));

        // The item being built. 'sku' and 'bar_code' hold the same string; the
        // template binds one to a text box and the other to a barcode element.
        $inv = $skuId
            ? $this->db->Execute("select sku, image_with_path, description_sales, upc_code from "
                . TABLE_INVENTORY . " where id = " . $skuId)->fields
            : [];
        $f['sku']             = $inv['sku'] ?? '';
        $f['bar_code']        = $inv['sku'] ?? '';
        $f['image_with_path'] = $inv['image_with_path'] ?? '';
        $f['description']     = $inv['description_sales'] ?? '';
        $f['upc_code']        = $inv['upc_code'] ?? '';

        $refs = $this->buildRefLists($id, $steps);
        $f   += $refs;

        // The shipped template addresses some header fields by their qualified
        // column name. Alias rather than rename: an author who wrote the bare
        // name must keep working too.
        foreach (['id', 'closed', 'priority', 'sku', 'post_date', 'qty'] as $col) {
            if (isset($f[$col])) $f['wo_journal_main.' . $col] = $f[$col];
        }

        $rows = [];
        foreach ($steps as $step) $rows[] = $this->typeLine($step);

        return new Record(
            values: $this->typeValues($f),
            tables: ['items' => $rows],
            currency: null,   // a work order carries no money
            key: (string)$id,
        );
    }

    /**
     * The routing: the steps of this work order in running order, each joined to
     * the task definition that supplies its description and reference documents.
     *
     * @return list<array<string,mixed>>
     */
    private function loadSteps(int $id): array
    {
        $out = [];
        $res = $this->db->Execute("select i.id, i.step, i.task_name, t.description, i.mfg, i.qa,
              i.complete, i.data_entry, t.ref_doc, t.ref_spec
            from " . TABLE_WO_JOURNAL_ITEM . " i inner join " . TABLE_WO_TASK . " t on i.task_id = t.id
            where i.ref_id = " . $id . " order by i.step");
        while (!$res->EOF) {
            $r = $res->fields;
            $out[] = [
                'id'          => $r['id'],
                'step'        => $r['step'],
                'task_name'   => $r['task_name'],
                'description' => $r['description'],
                'mfg'         => $r['mfg'],
                'qa'          => $r['qa'],
                'complete'    => $r['complete'],
                'data_entry'  => $r['data_entry'],
                'ref_doc'     => $r['ref_doc'],
                'ref_spec'    => $r['ref_spec'],
            ];
            $res->MoveNext();
        }
        return $out;
    }

    /**
     * The bill of materials, scaled to the build quantity.
     *
     * Still one joined string: it prints as a single free-text block on the
     * shipped form, and its header row is part of that block.
     */
    private function buildBomList(int $skuId, float $qty): string
    {
        if (!$skuId) return '';
        $lines = ['Qty - SKU - Description'];
        $res   = $this->db->Execute("select sku, description, qty from " . TABLE_INVENTORY_ASSY_LIST
            . " where ref_id = '" . $this->db->prepare_input((string)$skuId) . "' order by sku");
        while (!$res->EOF) {
            $r = $res->fields;
            $lines[] = ($qty * (float)$r['qty']) . ' - ' . $r['sku'] . ' - ' . $r['description'];
            $res->MoveNext();
        }
        return count($lines) > 1 ? implode(chr(10), $lines) : '';
    }

    /**
     * Reference documents and specifications: those named on the work order
     * itself, plus every one named by a step, de-duplicated and sorted.
     *
     * The order-level specifications come from 'ref_spec', the column the query
     * selects, and every step's documents are merged in whenever a step names
     * any. wo_builder reads 'ref_specs' and guards the step merge with a test
     * that is never satisfied, so it prints at most the order-level ref_doc list
     * (§12.3.1).
     *
     * @param list<array<string,mixed>> $steps
     * @return array{ref_docs:string,ref_specs:string}
     */
    private function buildRefLists(int $id, array $steps): array
    {
        $main  = $this->db->Execute("select ref_doc, ref_spec from " . TABLE_WO_MAIN
            . " where id = " . $id)->fields;
        $docs  = $this->splitRefs((string)($main['ref_doc']  ?? ''));
        $specs = $this->splitRefs((string)($main['ref_spec'] ?? ''));

        foreach ($steps as $step) {
            foreach ($this->splitRefs((string)($step['ref_doc'] ?? '')) as $ref) {
                if (!in_array($ref, $docs, true)) $docs[] = $ref;
            }
            foreach ($this->splitRefs((string)($step['ref_spec'] ?? '')) as $ref) {
                if (!in_array($ref, $specs, true)) $specs[] = $ref;
            }
        }
        sort($docs);
        sort($specs);
        return ['ref_docs' => implode(', ', $docs), 'ref_specs' => implode(', ', $specs)];
    }

    /** @return list<string> */
    private function splitRefs(string $csv): array
    {
        if ($csv === '') return [];
        $out = [];
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part !== '') $out[] = $part;
        }
        return $out;
    }

    // ── typing ───────────────────────────────────────────────────────────────

    private const DATES        = ['post_date', 'close_date', 'required_date'];
    private const NUMERIC      = ['qty', 'qty_scrap', 'qty_stocked'];
    private const NUMERIC_LINE = ['step'];
    private const BOOL_LINE    = ['complete'];

    /** @param array<string,mixed> $f @return array<string,TypedValue> */
    private function typeValues(array $f): array
    {
        $out = [];
        foreach ($f as $k => $v) {
            $bare = str_contains($k, '.') ? substr($k, strrpos($k, '.') + 1) : $k;
            $out[$k] = match (true) {
                in_array($bare, self::DATES, true)   => TypedValue::date($v === null ? null : (string)$v),
                in_array($bare, self::NUMERIC, true) => TypedValue::decimal($v),
                default                              => new TypedValue($v),
            };
        }
        return $out;
    }

    /** @param array<string,mixed> $line @return array<string,TypedValue> */
    private function typeLine(array $line): array
    {
        $out = [];
        foreach ($line as $k => $v) {
            $out[$k] = match (true) {
                in_array($k, self::NUMERIC_LINE, true) => TypedValue::decimal($v),
                in_array($k, self::BOOL_LINE, true)    => TypedValue::bool((bool)$v),
                default                                => new TypedValue($v),
            };
        }
        return $out;
    }
}
