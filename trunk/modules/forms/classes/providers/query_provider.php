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
//  Path: /modules/forms/classes/providers/query_provider.php
//
/**
 * Forms — builds a LayoutDocument from a catalog dataset.
 *
 * The counterpart to the hand-written providers: where EntryDocumentProvider
 * ports one legacy special_class, this serves every form that PhreeForm drove
 * straight from SQL — the Purchase Order, Sales Order, Quotes, Credit Memos and
 * the Deposit Slip. Together they are the two halves of §5.
 *
 * ── Shape ────────────────────────────────────────────────────────────────────
 * A dataset joining a header to its lines is one-to-many, so the query returns
 * one row PER LINE and the rows must be folded back into records. The fold is
 * driven by a break field, exactly as PhreeForm's formbreakfield was: rows
 * sharing a value become one document, its header values taken from the first
 * row and its line items from all of them.
 *
 * Two things make that fold less obvious than it looks, and both are handled
 * here rather than left to the caller:
 *
 *   1. **Joins multiply rows.** A vendor with more than one address on file
 *      makes the dataset's address_book join return each line more than once.
 *      The legacy form dealt with this by filtering
 *      address_book.type — a *data* concern the caller must state, since only
 *      the caller knows which address a given document wants. What the provider
 *      does guarantee is that duplicate LINES are not emitted: line rows are
 *      de-duplicated on the line's own identity.
 *
 *   2. **Ids are not names.** rep_id and admin_id identify a user, and turning
 *      one into a name is a query — which the renderer may not make (§4.3.1),
 *      and which the Transactions dataset deliberately cannot express, because
 *      reaching `users` from the dataset behind most documents would hand every
 *      report author a route into it. Resolution therefore happens HERE, in one
 *      batched lookup, and the document carries the finished name.
 */

namespace FreeBooks\Forms\Provider;

use FreeBooks\Forms\Catalog\{Catalog, Filter, QueryBuilder, Sort};
use FreeBooks\Forms\Document\{
    AfterRenderHook, Context, Currency, Document, DocumentProvider, LayoutDocument,
    PageSetup, Record, Template, TypedValue, ValueType
};

final class QueryDocumentProvider implements DocumentProvider
{
    /**
     * @param list<string> $lineItemTables tables whose columns form line items
     * @param list<Filter> $filters        conditions every query carries
     * @param list<Sort>   $sorts
     */
    public function __construct(
        private readonly \queryFactory $db,
        private readonly Catalog       $catalog,
        private readonly string        $datasetId,
        private readonly Template      $template,
        private readonly string        $breakField     = 'id',
        private readonly array         $lineItemTables = ['journal_item'],
        private readonly Context       $context        = new Context(),
        private readonly string        $title          = 'Document',
        private readonly PageSetup     $page           = new PageSetup(),
        private readonly array         $filters        = [],
        private readonly array         $sorts          = [],
        /** ['table'=>…,'field'=>…,'key'=>…]: the counter delivery increments */
        private readonly ?array        $printedField   = null,
        /** Field key holding the line's own id, used to de-duplicate rows. */
        private readonly string        $lineKeyField   = 'item_id',
        /** Field keys holding a user id, resolved to a display name. */
        private readonly array         $userFields     = ['rep_id', 'admin_id'],
    ) {}

    /**
     * @param array<string,mixed> $params
     *   record_ids  list of break-field values to build documents for
     *   filters     extra filters for this run
     */
    public function build(array $params): Document
    {
        $filters = array_merge($this->filters, $params['filters'] ?? []);
        $ids     = $params['record_ids'] ?? [];
        if ($ids) $filters[] = Filter::in($this->breakField, array_values($ids));

        $dataset = $this->catalog->get($this->datasetId);
        $query   = (new QueryBuilder())->build($dataset, [], $filters, $this->sorts);

        $result = $this->db->ExecutePrepared($query->sql, $query->params);

        // Which field keys belong to a line rather than to the header.
        $itemKeys = $headerKeys = [];
        foreach ($dataset->fields as $key => $field) {
            $isLine = $field->table !== null && in_array($field->table, $this->lineItemTables, true);
            if ($isLine) $itemKeys[$key] = $field; else $headerKeys[$key] = $field;
        }

        /** @var array<string,array{header:array,lines:array,seen:array}> */
        $groups = [];
        while (!$result->EOF) {
            $row = $result->fields;
            $key = (string)($row[$this->breakField] ?? '');

            if (!isset($groups[$key])) {
                $groups[$key] = ['header' => $row, 'lines' => [], 'seen' => []];
            }
            // A joined-in duplicate repeats the same line; count it once.
            $lineId = $row[$this->lineKeyField] ?? null;
            $token  = $lineId !== null ? (string)$lineId : md5(serialize(array_intersect_key($row, $itemKeys)));
            if (!isset($groups[$key]['seen'][$token])) {
                $groups[$key]['seen'][$token] = true;
                $groups[$key]['lines'][]      = $row;
            }
            $result->MoveNext();
        }

        // Preserve the caller's order when explicit ids were given.
        if ($ids) {
            $ordered = [];
            foreach ($ids as $id) {
                if (isset($groups[(string)$id])) $ordered[(string)$id] = $groups[(string)$id];
            }
            $groups = $ordered;
        }

        $names   = $this->resolveUserNames($groups, $headerKeys);
        $records = [];
        $hooks   = [];

        foreach ($groups as $key => $group) {
            $values = [];
            foreach ($headerKeys as $fieldKey => $field) {
                $values[$fieldKey] = new TypedValue($group['header'][$fieldKey] ?? null, $field->type);
            }
            // Each user id gains a companion *_name field the template can bind.
            foreach ($this->userFields as $userField) {
                if (!isset($headerKeys[$userField])) continue;
                $id = $group['header'][$userField] ?? null;
                $values[$userField . '_name'] = TypedValue::text($id === null ? '' : ($names[(string)$id] ?? ''));
            }

            $lines = [];
            foreach ($group['lines'] as $row) {
                $line = [];
                foreach ($itemKeys as $fieldKey => $field) {
                    $line[$fieldKey] = new TypedValue($row[$fieldKey] ?? null, $field->type);
                }
                $lines[] = $line;
            }

            $records[] = new Record(
                values:   $values,
                tables:   ['items' => $lines],
                currency: $this->currencyOf($group['header']),
                key:      $key,
            );

            if ($this->printedField !== null) {
                $hooks[] = new AfterRenderHook('increment_field', [
                    'table' => $this->printedField['table'],
                    'field' => $this->printedField['field'],
                    'key'   => $this->printedField['key'] ?? 'id',
                    'id'    => $key,
                ]);
            }
        }

        return new LayoutDocument(
            title:       $this->title,
            template:    $this->template,
            records:     $records,
            context:     $this->context,
            page:        $this->page,
            afterRender: $hooks,
        );
    }

    /**
     * Look up every referenced user in one query.
     *
     * One batched lookup, rather than a query per field per record from inside
     * the render loop, below the seam.
     *
     * @param array<string,array{header:array,lines:array,seen:array}> $groups
     * @param array<string,mixed> $headerKeys
     * @return array<string,string> user id => display name
     */
    private function resolveUserNames(array $groups, array $headerKeys): array
    {
        $ids = [];
        foreach ($this->userFields as $field) {
            if (!isset($headerKeys[$field])) continue;
            foreach ($groups as $group) {
                $id = $group['header'][$field] ?? null;
                if ($id !== null && $id !== '' && ctype_digit((string)$id)) $ids[(string)$id] = true;
            }
        }
        if (!$ids) return [];

        $ids   = array_keys($ids);
        $table = defined('TABLE_USERS') ? TABLE_USERS : 'users';
        // display_name only, matching pb_get_user_name(). Falling back to
        // admin_name would be friendlier but would differ from documents
        // already printed: where display_name is unset, the Buyer box prints
        // blank, and so must this one.
        $sql   = "SELECT admin_id, display_name FROM $table WHERE admin_id IN ("
               . implode(', ', array_fill(0, count($ids), '?')) . ')';

        $names  = [];
        $result = $this->db->ExecutePrepared($sql, $ids);
        while (!$result->EOF) {
            $row = $result->fields;
            $names[(string)$row['admin_id']] = (string)$row['display_name'];
            $result->MoveNext();
        }
        return $names;
    }

    /**
     * The currency a record was posted in — needed for posted_cur formatting,
     * and per-record rather than per-document.
     *
     * @param array<string,mixed> $header
     */
    private function currencyOf(array $header): ?Currency
    {
        $code = $header['currencies_code'] ?? null;
        if ($code === null || $code === '') {
            return defined('DEFAULT_CURRENCY') ? new Currency(DEFAULT_CURRENCY, 1.0) : null;
        }
        return new Currency((string)$code, (float)($header['currencies_value'] ?? 1));
    }
}
