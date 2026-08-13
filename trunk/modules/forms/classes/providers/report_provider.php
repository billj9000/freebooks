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
// +-----------------------------------------------------------------+
//  Path: /modules/forms/classes/providers/report_provider.php
//
/**
 * Forms — builds a TableDocument from a catalog dataset.
 *
 * The reports half of the module: 30 of the 56 shipped documents are
 * query-driven reports (§7), and this serves all of them.
 *
 * ── What changes from PhreeForm ──────────────────────────────────────────────
 * BuildDataArray() returned a tagged 2-D array where $Data[$row][0] held 'd',
 * 'g:<label>' or 'r:<title>' and every consumer re-parsed those prefixes. Row
 * meaning is a RowKind here, so a renderer asks what a row IS instead of
 * inspecting a string.
 *
 * More consequentially, PhreeForm accumulated its totals from FORMATTED text:
 *
 *     $Seq[$key]['grptotal'] += $currencies->clean_value(
 *         ProcessData($myrow[...], $TableCtl['processing']));
 *
 * — it formatted each value for display, then parsed the formatting back off to
 * get a number. That round-trip is lossy in both directions: a column formatted
 * with `rnd2d` totals its ROUNDED values, and a column whose hint yields a
 * non-numeric string contributes zero silently. Totals here sum the raw typed
 * values, so the total is of the data rather than of its presentation. Where a
 * legacy report rounded per row before summing, the new total can therefore
 * differ by a penny or two — correctly, and only for such columns.
 */

namespace FreeBooks\Forms\Provider;

use FreeBooks\Forms\Catalog\{Catalog, Filter, QueryBuilder, Sort};
use FreeBooks\Forms\Document\{
    Context, Document, DocumentProvider, Format, PageSetup, ReportColumn, ReportStyle, Row,
    RowKind, TableDocument, TypedValue, ValueType
};

final class ReportDocumentProvider implements DocumentProvider
{
    /**
     * @param list<ReportColumn> $columns   in display order
     * @param list<Filter>       $filters
     * @param list<Sort>         $sorts
     * @param array<string,string> $resolvedColumns column key => the dataset
     *   field key holding the id it is resolved FROM. These columns are not
     *   selected; the provider fills them in after the query (§4.3.1). The key
     *   convention is <id field>_name, which is what ReportConverter produces
     *   from a `branch` or `rep_id` processing hint.
     */
    public function __construct(
        private readonly \queryFactory $db,
        private readonly Catalog       $catalog,
        private readonly string        $datasetId,
        private readonly array         $columns,
        private readonly string        $title      = 'Report',
        private readonly ?string       $groupBy    = null,   // field key to break on
        private readonly ?Format       $groupFormat = null,
        private readonly array         $filters    = [],
        private readonly array         $sorts      = [],
        private readonly bool          $totalsOnly = false,
        private readonly Context       $context    = new Context(),
        private readonly PageSetup     $page       = new PageSetup(),
        private readonly ?string       $filterText = null,
        private readonly array         $resolvedColumns = [],
        private readonly ReportStyle   $style      = new ReportStyle(),
    ) {}

    /** @param array<string,mixed> $params */
    public function build(array $params): Document
    {
        $dataset = $this->catalog->get($this->datasetId);
        $filters = array_merge($this->filters, $params['filters'] ?? []);

        // Select the displayed columns plus the group field, which is needed to
        // detect breaks even when it is not shown.
        $keys = [];
        foreach ($this->columns as $column) {
            // A resolved column has no dataset field of its own; what the query
            // must fetch is the id it is derived from.
            $keys[$this->resolvedColumns[$column->key] ?? $column->key] = true;
        }
        if ($this->groupBy !== null) {
            $keys[$this->resolvedColumns[$this->groupBy] ?? $this->groupBy] = true;
        }

        $sorts = $this->sorts;
        // A group only breaks correctly on ordered rows, so the group field
        // leads the sort unless the caller already sorted by it. PhreeForm left
        // this to the report author and produced repeated group headings when
        // they got it wrong.
        if ($this->groupBy !== null) {
            $groupSortKey = $this->resolvedColumns[$this->groupBy] ?? $this->groupBy;
            $alreadyFirst = isset($sorts[0]) && $sorts[0]->fieldKey === $groupSortKey;
            if (!$alreadyFirst) array_unshift($sorts, new Sort($groupSortKey));
        }

        $query  = (new QueryBuilder())->build($dataset, array_keys($keys), $filters, $sorts);
        $result = $this->db->ExecutePrepared($query->sql, $query->params);

        // Buffer, then resolve, then build. Resolution has to be BATCHED — the
        // whole point of doing it here rather than in the renderer — so the ids
        // must all be known before the first row is turned into cells.
        // PhreeForm looked each one up inside its render loop, which on the
        // Re-order Worksheet is 986 queries to print one vendor column.
        $rawRows = [];
        while (!$result->EOF) { $rawRows[] = $result->fields; $result->MoveNext(); }
        $resolved = $this->resolveNames($rawRows);

        $rows          = [];
        $groupTotals   = $this->zeroTotals();
        $reportTotals  = $this->zeroTotals();
        $currentGroup      = null;   // the normalised break key
        $currentGroupValue = null;   // the value it came from, for the label
        $haveGroup         = false;

        foreach ($rawRows as $raw) {
            foreach ($this->resolvedColumns as $columnKey => $idKey) {
                $id = $raw[$idKey] ?? null;
                $raw[$columnKey] = $resolved[$columnKey][(string)($id ?? '')] ?? '';
            }

            if ($this->groupBy !== null) {
                // Break on the id, label with the name. When the group is a
                // resolved column these differ, and only the id is safe to
                // break on: resolveNames() gives every unknown id the company
                // name, so breaking on the name would fold all of them — and
                // any two contacts sharing a short_name — into one group.
                $break = $raw[$this->resolvedColumns[$this->groupBy] ?? $this->groupBy] ?? null;
                $value = $raw[$this->groupBy] ?? null;
                // Compare as strings. A nullable column mixes NULL and '' —
                // Sales By SKU has 33 of one and 416 of the other — and MySQL
                // sorts them adjacent, so a strict comparison breaks the blank
                // run in two and prints the same empty group heading twice.
                // Casting collapses them, as PhreeForm's loose `<>` did.
                //
                // What it does NOT inherit is that comparison's other half:
                // `<>` between two numeric strings compares NUMERICALLY, so
                // PhreeForm silently merged the SKUs '0001' and '1', and '1e2'
                // with '100'. Those are different products and get different
                // groups here.
                $key = $break === null ? '' : (string)$break;
                if ($haveGroup && $key !== $currentGroup) {
                    $rows[]      = $this->totalRow(RowKind::GroupTotal, $groupTotals, $this->groupLabel($currentGroupValue, $dataset));
                    $groupTotals = $this->zeroTotals();
                }
                $currentGroup      = $key;
                $currentGroupValue = $value;   // the label keeps the original
                $haveGroup         = true;
            }

            $cells = [];
            foreach ($this->columns as $column) {
                $field = $dataset->field($column->key);
                $cells[$column->key] = new TypedValue($raw[$column->key] ?? null, $field?->type ?? ValueType::Text);
                if ($column->total) {
                    $v = $raw[$column->key] ?? null;
                    if (is_numeric($v)) $reportTotals[$column->key] += (float)$v;
                    if (is_numeric($v)) $groupTotals[$column->key]  += (float)$v;
                }
            }
            // totalonly reports collect the arithmetic and show only the breaks.
            if (!$this->totalsOnly) $rows[] = new Row(RowKind::Data, $cells);
        }

        if ($haveGroup) {
            $rows[] = $this->totalRow(RowKind::GroupTotal, $groupTotals, $this->groupLabel($currentGroupValue, $dataset));
        }
        if ($this->hasTotals()) {
            $rows[] = $this->totalRow(RowKind::ReportTotal, $reportTotals, $this->title);
        }

        return new TableDocument(
            title:      $this->title,
            columns:    $this->columns,
            rows:       $rows,
            context:    $this->context,
            page:       $this->page,
            filterText: $this->filterText,
            style:      $this->style,
        );
    }

    /** @return array<string,float> */
    /**
     * Turn every referenced contact id into a name, in one query per column.
     *
     * This is the reports-side counterpart of QueryDocumentProvider's user
     * lookup, and it reproduces contacts_get_short_name() including the part
     * that looks like a bug: an id of 0, an empty id, or an id with no contact
     * row all render as COMPANY_ID rather than blank. The Re-order Worksheet
     * relies on it — an item with no vendor is shown as stocked by us.
     *
     * PhreeForm reached this through a hint it called `branch`, which does not
     * resolve a branch at all; the name is historical.
     *
     * @param  list<array<string,mixed>> $rawRows
     * @return array<string,array<string,string>> column key => id => name
     */
    private function resolveNames(array $rawRows): array
    {
        if (!$this->resolvedColumns) return [];

        $company = defined('COMPANY_ID') ? (string)COMPANY_ID : '';
        $table   = defined('TABLE_CONTACTS') ? TABLE_CONTACTS : 'contacts';
        $out     = [];

        foreach ($this->resolvedColumns as $columnKey => $idKey) {
            $ids = [];
            foreach ($rawRows as $raw) {
                $id = $raw[$idKey] ?? null;
                if ($id !== null && $id !== '' && ctype_digit((string)$id) && (int)$id !== 0) {
                    $ids[(string)$id] = true;
                }
            }
            // Every id resolves to the company name unless a contact says
            // otherwise, which gives 0/''/unknown the fallback for free.
            $names = [];
            if ($ids) {
                $ids = array_keys($ids);
                $sql = "SELECT id, short_name FROM $table WHERE id IN ("
                     . implode(', ', array_fill(0, count($ids), '?')) . ')';
                $result = $this->db->ExecutePrepared($sql, $ids);
                while (!$result->EOF) {
                    $names[(string)$result->fields['id']] = (string)$result->fields['short_name'];
                    $result->MoveNext();
                }
            }
            $out[$columnKey] = $names;
            $out[$columnKey][''] = $company;          // absent id
            foreach ($rawRows as $raw) {
                $id = (string)($raw[$idKey] ?? '');
                if (!isset($out[$columnKey][$id])) $out[$columnKey][$id] = $company;
            }
        }
        return $out;
    }

    private function zeroTotals(): array
    {
        $totals = [];
        foreach ($this->columns as $column) if ($column->total) $totals[$column->key] = 0.0;
        return $totals;
    }

    private function hasTotals(): bool
    {
        foreach ($this->columns as $column) if ($column->total) return true;
        return false;
    }

    /** @param array<string,float> $totals */
    private function totalRow(RowKind $kind, array $totals, ?string $label): Row
    {
        $cells = [];
        foreach ($totals as $key => $sum) $cells[$key] = new TypedValue($sum, ValueType::Money);
        return new Row($kind, $cells, $label);
    }

    private function groupLabel(mixed $value, $dataset): string
    {
        // Already resolved if the group is a lookup column — the loop above
        // fills $raw from resolveNames() before reading the label. Any other
        // formatting hint is carried on groupFormat() for a renderer that wants
        // it; none of the shipped reports declare one.
        return (string)($value ?? '');
    }

    public function groupFormat(): ?Format
    {
        return $this->groupFormat;
    }
}
