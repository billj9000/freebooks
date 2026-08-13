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
//  Path: /modules/forms/classes/providers/pos_provider.php
//
/**
 * Forms — point-of-sale receipt provider.
 *
 * Port of phreepos/classes/pos_builder.php, the special_class behind the POS
 * Receipt Slip. See docs/forms-design.md §5 and §7.
 *
 * A receipt is one journal entry, so build() takes journal_main ids. The line
 * table is two lists concatenated — sold items, then the payments that settled
 * them — because the receipt prints them in one running column.
 *
 * Three values the legacy pre-formatted in the data layer are typed here
 * instead, which is the whole point of the seam:
 *
 *   - invoice_discount was a string like "-12.5%". It is a fraction, and the
 *     renderer's percent format prints it.
 *   - tax_text glued the tax lines together with $currencies->format_full().
 *     The taxes are a typed list; see $taxLines below for why one joined string
 *     survives alongside it.
 *   - payment_method resolved a card brand to 'Visa *1234'. The brand and the
 *     last four are separate values, joined the same way for the shipped form.
 *
 * A provider sits ABOVE the seam, so database access here is correct and
 * expected; it is the returned Document that must be free of it.
 */

namespace FreeBooks\Forms\Provider;

use FreeBooks\Forms\Document\{
    Context, Currency, Document, DocumentProvider,
    LayoutDocument, PageSetup, Record, Template, TypedValue
};

final class PosDocumentProvider implements DocumentProvider
{
    /** @var array<int|string,float> taxable id => rate as a fraction (0.20) */
    private array $taxRates;

    public function __construct(
        private readonly \queryFactory $db,
        private readonly Template      $template,
        private readonly Context       $context = new Context(),
        private readonly string        $title = 'Receipt',
        private readonly PageSetup     $page = new PageSetup(),
        ?array $taxRates = null,
    ) {
        // pos_builder's constructor pulls these from ord_calculate_tax_drop_down('c')
        if ($taxRates === null) {
            $taxRates = [];
            if (function_exists('ord_calculate_tax_drop_down')) {
                foreach (ord_calculate_tax_drop_down('c') as $rate) {
                    $taxRates[$rate['id']] = $rate['rate'] / 100;
                }
            }
        }
        $this->taxRates = $taxRates;
    }

    /** @param array{record_ids?:list<int|string>} $params journal_main ids */
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

    /** One receipt. Mirrors pos_builder::load_query_results(). */
    private function buildRecord(int $id): ?Record
    {
        if (!$id) return null;
        $row = $this->db->Execute("select * from " . TABLE_JOURNAL_MAIN . " where id = " . $id)->fields;
        if (!$row) return null;

        $f = [];
        foreach ($row as $k => $v) {
            $f[$k] = function_exists('db_prepare_input') ? db_prepare_input($v) : $v;
        }

        $lineItems = [];
        $f = array_merge($f, $this->loadItemDetails($id, $lineItems));
        $payments  = $this->loadPaymentDetails($id);
        $f = array_merge($f, $this->loadAccountDetails((int)($row['bill_acct_id'] ?? 0)));

        // rep_id is stored as a contact id but printed as a short name; the
        // cashier's full name comes along for receipts that greet by name.
        $f['rep_id'] = $f['rep_name'] = '';
        if (!empty($row['rep_id'])) {
            $rep = $this->db->Execute("select short_name, contact_first, contact_last from "
                . TABLE_CONTACTS . " where id = " . (int)$row['rep_id'])->fields;
            if ($rep) {
                $f['rep_id']   = $rep['short_name'];
                $f['rep_name'] = trim(($rep['contact_first'] ?? '') . ' ' . ($rep['contact_last'] ?? ''));
            }
        }

        if (function_exists('calculate_terms_due_dates')) {
            $terms = calculate_terms_due_dates($row['post_date'] ?? '', $row['terms'] ?? '');
            $f['payment_due_date'] = $terms['net_date'] ?? '';
        }

        $f['balance_due'] = (float)($row['total_amount'] ?? 0) - (float)($f['total_paid'] ?? 0);
        // What the till rounded away: the sum of the lines plus tax, less any
        // discount, will differ from the amount actually charged wherever the
        // smallest coin is bigger than the smallest unit of account.
        $f['rounded_of']  = (float)($row['total_amount'] ?? 0)
                          - ((float)($f['inv_subtotal_w_tax'] ?? 0) - (float)($f['discount'] ?? 0));

        // Sold lines first, then payment lines, in one table — the receipt
        // prints them as a single column. A payment row carries none of the
        // item keys and vice versa, so the two shapes coexist.
        $rows = [];
        foreach ($lineItems as $line) {
            if (!isset($line['invoice_qty'])) continue;   // SO lines not on this receipt
            $rows[] = $this->typeLine($line);
        }
        foreach ($payments as $line) {
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

    /** Sold lines, per-line tax and discount, the subtotal and the tax summary. */
    private function loadItemDetails(int $id, array &$lineItems): array
    {
        $subtotal        = 0.0;
        $subtotalWithTax = 0.0;
        $discount        = 0.0;
        $totalPaid       = 0.0;
        $paymentDetail   = '';
        $taxes           = [];

        $res = $this->db->Execute("select * from " . TABLE_JOURNAL_ITEM . " where ref_id = " . $id);
        while (!$res->EOF) {
            $r     = $res->fields;
            $index = !empty($r['so_po_item_ref_id']) ? $r['so_po_item_ref_id'] : $r['id'];
            $price = (float)$r['credit_amount'] + (float)$r['debit_amount'];
            $rate  = (float)($this->taxRates[$r['taxable']] ?? 0);
            $qty   = (float)$r['qty'];

            switch ($r['gl_type']) {
                case 'sos':
                case 'por':
                    $full = (float)$r['full_price'];
                    $unit = $qty ? $price / $qty : 0.0;
                    // Only a genuine markdown counts: an item sold at or above
                    // its list price shows no discount rather than a negative one.
                    $line = $lineItems[$index] ?? [];
                    $line['invoice_full_price']  = $r['full_price'];
                    $line['invoice_unit_price']  = $unit;
                    $line['invoice_discount']    = ($full != 0 && $unit < $full) ? ($unit / $full) - 1 : null;
                    $line['invoice_qty']         = $r['qty'];
                    $line['invoice_sku']         = $r['sku'];
                    $line['invoice_description'] = $r['description'];
                    $line['invoice_serial_num']  = $r['serialize_number'];
                    $line['invoice_line_tax']    = $rate * $price;
                    $line['invoice_price']       = $price;
                    $line['invoice_price_w_tax'] = (1 + $rate) * $price;
                    $lineItems[$index] = $line;
                    $subtotal        += $price;
                    $subtotalWithTax += (1 + $rate) * $price;
                    break;
                case 'tax':
                    $taxes[] = ['description' => $r['description'], 'amount' => $price];
                    break;
                case 'dsc':
                    $discount = $price;
                    break;
                case 'ttl':
                    $paymentDetail = self::describePayment((string)$r['description']);
                    $totalPaid     = (float)$r['debit_amount'];
                    break;
            }
            $res->MoveNext();
        }

        return [
            'invoice_subtotal'   => $subtotal,
            'inv_subtotal_w_tax' => $subtotalWithTax,
            'discount'           => $discount,
            'total_paid'         => $totalPaid,
            'payment_detail'     => $paymentDetail,
            // The shipped receipt prints one text block, so the joined string
            // stays; the typed list beside it is what a Forms-native template
            // would bind to, and what CSV and HTML can lay out properly.
            'tax_text'           => self::joinTaxLines($taxes),
            'taxes'              => $taxes,
        ];
    }

    /** Contact and the telephone/website fields that live on the main address. */
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
            if (substr((string)$r['type'], 1, 1) === 'm') {
                $out['bill_telephone2'] = $r['telephone2'];
                $out['bill_telephone3'] = $r['telephone3'];
                $out['bill_telephone4'] = $r['telephone4'];
                $out['bill_website']    = $r['website'];
            }
            $res->MoveNext();
        }
        return $out;
    }

    /**
     * The tenders that settled this sale — one row each, so a split payment
     * prints as two lines.
     *
     * @return list<array<string,mixed>>
     */
    private function loadPaymentDetails(int $id): array
    {
        $rows = [];
        $res  = $this->db->Execute("select * from " . TABLE_JOURNAL_ITEM
            . " where ref_id = " . $id . " and gl_type in ('pmt', 'chk')");
        while (!$res->EOF) {
            $r = $res->fields;
            // Cash in and change out land in different columns; one is always zero.
            $rows[] = [
                'payment_amount' => (float)$r['debit_amount'] != 0.0
                                    ? (float)$r['debit_amount'] : (float)$r['credit_amount'],
                'payment_method' => self::describePayment((string)$r['description']),
            ];
            $res->MoveNext();
        }
        return $rows;
    }

    /**
     * A stored payment description is 'gateway:brand:number'. Card numbers are
     * masked before they reach the record, so the receipt can only ever show
     * the last four digits.
     *
     * pos_builder read the brand from segment 1 and the mask from segment 2,
     * where entry_builder read both from segment 2. They cannot both be right;
     * each is kept as it was, and the difference is recorded in §12.3.1 rather
     * than guessed at, because a receipt naming the wrong card brand is worse
     * than one naming none.
     */
    private static function describePayment(string $desc): string
    {
        $parts = explode(':', $desc);
        $brand  = $parts[1] ?? '';
        $masked = $parts[2] ?? '';
        if (str_contains($masked, '****')) {
            $prefix = match (substr($brand, 0, 1)) {
                '4' => 'Visa *', '5' => 'MC *', '3' => 'AMX *', default => '',
            };
            return $prefix . substr($masked, -4);
        }
        return $brand;
    }

    /** @param list<array{description:string,amount:float}> $taxes */
    private static function joinTaxLines(array $taxes): string
    {
        if (!$taxes) return '';
        $out = [];
        foreach ($taxes as $t) {
            $amount = isset($GLOBALS['currencies'])
                ? $GLOBALS['currencies']->format_full($t['amount'])
                : $t['amount'];
            $out[] = $t['description'] . ' - ' . $amount;
        }
        return implode("\n", $out);
    }

    // ── typing ───────────────────────────────────────────────────────────────

    private const MONEY = [
        'total_amount', 'total_paid', 'balance_due', 'invoice_subtotal', 'inv_subtotal_w_tax',
        'sales_tax', 'freight', 'discount', 'deposit_amount', 'rounded_of',
    ];
    private const DATES = ['post_date', 'payment_due_date', 'terminal_date', 'close_date', 'ship_date'];

    private const MONEY_LINE = [
        'invoice_price', 'invoice_unit_price', 'invoice_full_price', 'invoice_price_w_tax',
        'invoice_line_tax', 'payment_amount',
    ];
    private const NUMERIC_LINE = ['invoice_qty'];
    private const PERCENT_LINE = ['invoice_discount'];

    /** @param array<string,mixed> $f @return array<string,TypedValue> */
    private function typeValues(array $f): array
    {
        $out = [];
        foreach ($f as $k => $v) {
            if ($k === 'taxes') continue;   // a typed list, not a scalar value
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
                in_array($k, self::PERCENT_LINE, true) => TypedValue::decimal($v),
                default                                => new TypedValue($v),
            };
        }
        return $out;
    }
}
