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
//  Path: /modules/forms/classes/providers/entry_provider.php
//
/**
 * Forms — journal entry document provider (Invoice, Packing Slip, Quote, …).
 *
 * Port of phreebooks/classes/entry_builder.php, the special_class behind four
 * shipped forms. See docs/forms-design.md §5 and §7.
 *
 * This is a faithful transcription of the legacy domain logic, not a redesign:
 * line tax, backorder reconciliation, discounts, payment masking and carrier
 * resolution all produce financial figures on customer-facing documents, so
 * behaviour is held constant and verified by diffing against a PhreeForm
 * baseline (docs/tools/diff_forms_provider.php).
 *
 * What DOES change is the shape of the output:
 *
 *   - Values are addressed BY NAME. entry_builder::load_query_results()
 *     returned a positional array ordered by the template's Data elements, so
 *     reordering the template silently misaligned the data. Bindings are names.
 *   - Values keep their RAW type. The legacy left these raw and let the render
 *     layer format, which is the behaviour we want; TypedValue makes it explicit
 *     so a CSV renderer can emit 272.2545 where a PDF renderer emits €272.25.
 *   - Line items are keyed by field name rather than r0..rN positional keys.
 *   - The printed-counter side effect is declared as an AfterRenderHook for the
 *     caller to run on delivery, instead of being fired from inside the PDF
 *     builder where a preview or a diff run would trigger it.
 *
 * A provider sits ABOVE the seam, so database access here is correct and
 * expected; it is the returned Document that must be free of it.
 */

namespace FreeBooks\Forms\Provider;

use FreeBooks\Forms\Document\{
    AfterRenderHook, Context, Currency, Document, DocumentProvider,
    LayoutDocument, PageSetup, Record, Template, TypedValue
};

final class EntryDocumentProvider implements DocumentProvider
{
    /** @var array<int|string,float>  taxable id => rate as a fraction (0.20) */
    private array $taxRates;
    /** @var array<int|string,string> taxable id => label ("20%") */
    private array $taxLabels;

    public function __construct(
        private readonly \queryFactory $db,
        private readonly Template      $template,
        private readonly Context       $context = new Context(),
        private readonly string        $title = 'Invoice',
        private readonly PageSetup     $page = new PageSetup(),
        /** e.g. ['table' => 'journal_main', 'field' => 'printed'] or null */
        private readonly ?array        $printedField = null,
        ?array $taxRates = null,
        ?array $taxLabels = null,
    ) {
        // entry_builder's constructor pulls these from ord_calculate_tax_drop_down('c')
        if ($taxRates === null || $taxLabels === null) {
            $rates = $labels = [];
            if (function_exists('ord_calculate_tax_drop_down')) {
                foreach (ord_calculate_tax_drop_down('c') as $rate) {
                    $rates[$rate['id']]  = $rate['rate'] / 100;
                    $labels[$rate['id']] = $rate['rate'] . '%';
                }
            }
            $taxRates  ??= $rates;
            $taxLabels ??= $labels;
        }
        $this->taxRates  = $taxRates;
        $this->taxLabels = $taxLabels;
    }

    /** @param array{record_ids?:list<int|string>} $params */
    public function build(array $params): Document
    {
        $ids     = $params['record_ids'] ?? [];
        $records = [];
        $hooks   = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            $record = $this->buildRecord($id);
            if ($record === null) continue;
            $records[] = $record;
            if ($this->printedField !== null) {
                $hooks[] = new AfterRenderHook('increment_field', [
                    'table' => $this->printedField['table'],
                    'field' => $this->printedField['field'],
                    'id'    => $id,
                ]);
            }
        }
        return new LayoutDocument(
            title: $this->title,
            template: $this->template,
            records: $records,
            context: $this->context,
            page: $this->page,
            afterRender: $hooks,
        );
    }

    /** Build one form's worth of data. Mirrors entry_builder::load_query_results(). */
    private function buildRecord(int $id): ?Record
    {
        $row = $this->db->Execute("select * from " . TABLE_JOURNAL_MAIN . " where id = " . $id)->fields;
        if (!$row) return null;

        // Every journal_main column becomes an addressable value, as the legacy
        // did by assigning them onto $this.
        $f = [];
        foreach ($row as $k => $v) $f[$k] = $v;

        // Order matters: so_po populates line_items and the initial backorder
        // quantity, which load_item_details then reduces.
        // array_merge, not += : a loader's value must WIN over the raw column.
        // loadSoPoDetails replaces so_po_ref_id (a journal_main row id) with the
        // sales order's own reference, exactly as the legacy did by assignment.
        // '+=' preserves the existing key and would silently keep the row id.
        $lineItems = [];
        $f = array_merge($f, $this->loadSoPoDetails((string)($row['so_po_ref_id'] ?? ''), $id, $lineItems));
        $f = array_merge($f, $this->loadItemDetails($id, $lineItems));
        $f = array_merge($f, $this->loadAccountDetails((int)($row['bill_acct_id'] ?? 0)));
        $f = array_merge($f, $this->loadPaymentDetails($id));
        $f = array_merge($f, $this->loadShipmentDetails($id, (string)($row['shipper_code'] ?? ''), (string)($row['purchase_invoice_id'] ?? '')));

        // rep_id is stored as a contact id but displayed as a short name
        $f['rep_id'] = '';
        if (!empty($row['rep_id'])) {
            $rep = $this->db->Execute("select short_name from " . TABLE_CONTACTS
                . " where id = " . (int)$row['rep_id'])->fields;
            $f['rep_id'] = $rep['short_name'] ?? '';
        }

        if (function_exists('calculate_terms_due_dates')) {
            $terms = calculate_terms_due_dates($row['post_date'] ?? '', $row['terms'] ?? '');
            $f['payment_due_date'] = $terms['net_date'] ?? '';
        }
        $f['balance_due'] = (float)($row['total_amount'] ?? 0) - (float)($f['total_paid'] ?? 0);

        // Only rows actually invoiced appear on the document — the legacy skipped
        // sales-order lines with no invoice_qty.
        $rows = [];
        foreach ($lineItems as $line) {
            if (!isset($line['invoice_qty'])) continue;
            $rows[] = $this->typeLine($line);
        }

        return new Record(
            values: $this->typeValues($f),
            tables: ['items' => $rows],
            currency: new Currency(
                (string)($row['currencies_code'] ?? ''),
                (float)($row['currencies_value'] ?? 1),
            ),
            key: (string)$id,
        );
    }

    /** Sales order / purchase order reference plus the ordered line items. */
    private function loadSoPoDetails(string $soPoRefId, int $entryId, array &$lineItems): array
    {
        $out = ['so_post_date' => '', 'so_po_ref_id' => ''];
        if ($soPoRefId === '') return $out;

        $so = $this->db->Execute("select post_date, purchase_invoice_id from " . TABLE_JOURNAL_MAIN
            . " where id = " . (int)$soPoRefId)->fields;
        if ($so) {
            $out['so_post_date'] = $so['post_date'];
            // note: the SO's *reference* replaces the id here, as the legacy did
            $out['so_po_ref_id'] = $so['purchase_invoice_id'];
        }

        $itemIds = [];
        $res = $this->db->Execute("select * from " . TABLE_JOURNAL_ITEM . " where ref_id = " . (int)$soPoRefId);
        while (!$res->EOF) {
            $r = $res->fields;
            if (!empty($r['sku'])) {
                $itemIds[] = $r['id'];
                $qty = (float)$r['qty'];
                $lineItems[$r['id']] = [
                    'order_price'       => $r['credit_amount'],
                    'order_unit_price'  => $qty ? $r['credit_amount'] / $qty : 0,
                    'order_qty'         => $r['qty'],
                    'order_sku'         => $r['sku'],
                    'order_description' => $r['description'],
                    'order_serial_num'  => $r['serialize_number'],
                    'qty_on_backorder'  => $r['qty'],
                ];
            }
            $res->MoveNext();
        }

        // Quantities already shipped on *other* entries reduce the backorder.
        foreach ($itemIds as $itemId) {
            $prior = $this->db->Execute("select sum(qty) as qty_shipped_prior from " . TABLE_JOURNAL_ITEM
                . " where so_po_item_ref_id = " . (int)$itemId . " and ref_id <> " . $entryId)->fields;
            $shipped = (float)($prior['qty_shipped_prior'] ?? 0);
            $lineItems[$itemId]['qty_shipped_prior'] = $prior['qty_shipped_prior'];
            $lineItems[$itemId]['qty_on_backorder']  = max(0, (float)$lineItems[$itemId]['qty_on_backorder'] - $shipped);
        }
        return $out;
    }

    /** Invoiced lines, per-line tax, discount and the subtotal. */
    private function loadItemDetails(int $id, array &$lineItems): array
    {
        $subtotal = 0.0;
        $subtotalWithTax = 0.0;
        $discount = 0;
        $taxList  = [];

        $res = $this->db->Execute("select * from " . TABLE_JOURNAL_ITEM . " where ref_id = " . $id);
        while (!$res->EOF) {
            $r     = $res->fields;
            $index = !empty($r['so_po_item_ref_id']) ? $r['so_po_item_ref_id'] : $r['id'];
            $price = (float)$r['credit_amount'] + (float)$r['debit_amount'];
            $lineTax = (float)($this->taxRates[$r['taxable']] ?? 0);
            $qty   = (float)$r['qty'];

            if ($r['gl_type'] === 'sos' || $r['gl_type'] === 'por') {
                $full = (float)$r['full_price'];
                $line = $lineItems[$index] ?? [];
                $line['item_cnt']             = $r['item_cnt'];
                $line['invoice_full_price']   = $r['full_price'];
                $line['invoice_unit_price']   = $qty ? ($price / $qty) : 0;
                $line['invoice_discount']     = $full == 0 ? 0 : ($full - ($qty ? $price / $qty : 0)) / $full;
                $line['invoice_qty']          = $r['qty'];
                $line['invoice_sku']          = $r['sku'];
                $line['invoice_description']  = $r['description'];
                $line['invoice_serial_num']   = $r['serialize_number'];
                $line['qty_on_backorder']     = max(0, (float)($line['qty_on_backorder'] ?? 0) - $qty);
                $line['invoice_line_tax']     = $lineTax * $price;
                $line['invoice_line_tax_rate']= $this->taxLabels[$r['taxable']] ?? '';
                $line['invoice_price']        = $price;
                $line['invoice_price_w_tax']  = (1 + $lineTax) * $price;
                $lineItems[$index] = $line;
                $subtotal        += $price;
                $subtotalWithTax += (1 + $lineTax) * $price;
            }
            if ($r['gl_type'] === 'tax') {
                // NOTE: legacy formats here, in the data layer. Preserved for
                // byte-compatibility; a native Forms provider would emit a typed
                // list and let the renderer format it.
                $taxList[] = $r['description'] . ' - '
                    . (isset($GLOBALS['currencies']) ? $GLOBALS['currencies']->format_full($price) : $price);
            }
            if ($r['gl_type'] === 'dsc') $discount = $price;
            $res->MoveNext();
        }

        return [
            'invoice_subtotal'   => $subtotal,
            'inv_subtotal_w_tax' => $subtotalWithTax,
            'discount'           => $discount,
            'tax_text'           => $taxList ? implode("\n", $taxList) : '',
        ];
    }

    /** Contact plus its mailing/billing/shipping addresses. */
    private function loadAccountDetails(int $contactId): array
    {
        $out = ['short_name' => '', 'account_number' => '', 'gov_id_number' => '',
                'bill_telephone2' => '', 'bill_telephone3' => '', 'bill_telephone4' => '', 'bill_website' => ''];
        if (!$contactId) return $out;

        $c = $this->db->Execute("select * from " . TABLE_CONTACTS . " where id = " . $contactId)->fields;
        if ($c) {
            $out['short_name']     = $c['short_name'];
            $out['account_number'] = $c['account_number'];
            $out['gov_id_number']  = $c['gov_id_number'];
        }

        $res = $this->db->Execute("select * from " . TABLE_ADDRESS_BOOK . " where ref_id = " . $contactId);
        while (!$res->EOF) {
            $r = $res->fields;
            if (substr((string)$r['type'], 1, 1) === 'm') {   // main
                $out['bill_telephone2'] = $r['telephone2'];
                $out['bill_telephone3'] = $r['telephone3'];
                $out['bill_telephone4'] = $r['telephone4'];
                $out['bill_website']    = $r['website'];
            }
            $res->MoveNext();
        }
        return $out;
    }

    /** Payments applied to this entry, and the most recent payment's details. */
    private function loadPaymentDetails(int $id): array
    {
        $out = [
            'total_paid' => 0, 'payment_method' => '', 'payment_date' => '',
            'payment_deposit_id' => '', 'payment_reference' => '', 'payment_detail' => '',
        ];
        $res = $this->db->Execute("select * from " . TABLE_JOURNAL_ITEM
            . " where so_po_item_ref_id = " . $id . " and gl_type in ('pmt', 'chk')");
        while (!$res->EOF) {
            $r = $res->fields;
            // one of the two is always zero
            $out['total_paid'] += (float)$r['credit_amount'] - (float)$r['debit_amount'];

            $pmt = $this->db->Execute("select post_date, shipper_code, purchase_invoice_id, purch_order_id
                from " . TABLE_JOURNAL_MAIN . " where id = " . (int)$r['ref_id'])->fields;
            if ($pmt) {
                $out['payment_date']       = $pmt['post_date'];
                $out['payment_method']     = $pmt['shipper_code'];
                $out['payment_deposit_id'] = $pmt['purchase_invoice_id'];
                $out['payment_reference']  = $pmt['purch_order_id'];
            }
            $det = $this->db->Execute("select description from " . TABLE_JOURNAL_ITEM
                . " where ref_id = " . (int)$r['ref_id'] . " and gl_type = 'ttl'")->fields;
            $out['payment_detail'] = self::maskPaymentDescription((string)($det['description'] ?? ''));
            $res->MoveNext();
        }
        return $out;
    }

    /** Carrier, service and tracking numbers. */
    private function loadShipmentDetails(int $id, string $shipperCode, string $invoiceRef): array
    {
        $out = ['ship_carrier' => '', 'ship_service' => '', 'tracking_id' => ''];
        $parts   = explode(':', $shipperCode);
        $carrier = $parts[0] ?? '';
        if ($carrier === '') return $out;

        if (function_exists('load_specific_method')) load_specific_method('shipping', $carrier);
        $titleConst = 'MODULE_SHIPPING_' . strtoupper($carrier) . '_TITLE_SHORT';
        $out['ship_carrier'] = defined($titleConst) ? constant($titleConst) : '';
        $svcConst = $carrier . '_' . ($parts[1] ?? '');
        $out['ship_service'] = defined($svcConst) ? constant($svcConst) : '';

        if (defined('TABLE_SHIPPING_LOG') && $invoiceRef !== '') {
            $ref = db_input($invoiceRef);
            $res = $this->db->Execute("SELECT tracking_id FROM " . TABLE_SHIPPING_LOG
                . " WHERE ref_id='" . $ref . "' OR ref_id LIKE '" . $ref . "-%'");
            $tracking = [];
            while (!$res->EOF) { $tracking[] = $res->fields['tracking_id']; $res->MoveNext(); }
            if ($tracking) {
                $out['tracking_id'] = $out['ship_carrier'] . ' ' . $out['ship_service']
                    . ' # ' . implode(', ', $tracking);
            }
        }
        return $out;
    }

    /** Credit-card descriptions are masked to the last four digits. */
    private static function maskPaymentDescription(string $desc): string
    {
        $parts = explode(':', $desc);
        $third = $parts[2] ?? '';
        if (str_contains($third, '****')) {
            $prefix = match (substr($third, 0, 1)) {
                '4' => 'Visa *', '5' => 'MC *', '3' => 'AMX *', default => '',
            };
            return $prefix . substr($third, -4);
        }
        return $third;
    }

    // ── typing ───────────────────────────────────────────────────────────────
    // The legacy passed untyped strings straight through. Naming the type here
    // is what lets one definition render £1,234.50 to PDF and 1234.50 to CSV.

    private const MONEY = [
        'total_amount', 'total_paid', 'balance_due', 'invoice_subtotal', 'inv_subtotal_w_tax',
        'sales_tax', 'freight', 'discount', 'purch_freight', 'deposit_amount',
    ];
    private const DATES = [
        'post_date', 'payment_due_date', 'so_post_date', 'payment_date',
        'terminal_date', 'close_date', 'ship_date',
    ];
    private const MONEY_LINE = [
        'invoice_price', 'invoice_unit_price', 'invoice_full_price', 'invoice_price_w_tax',
        'invoice_line_tax', 'order_price', 'order_unit_price',
    ];
    private const NUMERIC_LINE = ['invoice_qty', 'order_qty', 'qty_on_backorder', 'qty_shipped_prior', 'item_cnt'];

    /** @param array<string,mixed> $f @return array<string,TypedValue> */
    private function typeValues(array $f): array
    {
        $out = [];
        foreach ($f as $k => $v) {
            $out[$k] = match (true) {
                in_array($k, self::MONEY, true) => TypedValue::money($v),
                in_array($k, self::DATES, true) => TypedValue::date($v === null ? null : (string)$v),
                default                         => new TypedValue($v),
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
                in_array($k, self::MONEY_LINE, true)   => TypedValue::money($v),
                in_array($k, self::NUMERIC_LINE, true) => TypedValue::decimal($v),
                default                                => new TypedValue($v),
            };
        }
        return $out;
    }
}
