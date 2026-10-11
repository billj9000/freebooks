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
//  Path: /modules/quickbooks_sync/classes/quickbooks_builder.php
//
// Turns a sales invoice or credit memo, in the shape the api module's invoices and credit_memos
// resources return, into a QuickBooks Online document, then into a body for the Invoice or
// CreditMemo API of a company that charges VAT (the non-US tax model). It reads no database and
// makes no calls, so tests/run_fixtures.php can check it against the fixtures alone.
//
// The mapping array holds:
//   items             FreeBooks GL account => QuickBooks item name, or ['domestic' => .., 'foreign' => ..]
//   default_item      QuickBooks item for a GL account the mapping does not list
//   rounding_item     QuickBooks item for the Rounding Adjustment line
//   discount_account  QuickBooks account for the discount line; blank uses QuickBooks' own default
//   customers         FreeBooks customer name => QuickBooks customer name, where they differ
//   tax_codes         FreeBooks tax_rate_id => QuickBooks tax code NAME
//   no_vat_code       QuickBooks tax code name written for an untaxed line (default "No VAT")
//
// A QuickBooks sales line names an item, and the item decides the income account, so each mapped
// GL account has an item of its own rather than each SKU: one SKU can sell to the domestic or the
// foreign account. The SKU is written into the line's description.
//
// Every amount is rounded with PHP round(x, 2), half away from zero, which is how FreeBooks rounds
// tax, and only where a figure is fixed: a line amount, a tax code's tax, and the totals.
//

class quickbooks_sync_error extends RuntimeException {}

class quickbooks_builder {

  // The marker for an untaxed line. It is not a QuickBooks tax code name: the payload writes the
  // no_vat_code code in its place.
  const NO_TAX = 'NONE';

  // QuickBooks' limit on DocNumber.
  const NUMBER_MAX = 21;

  private array $mapping;

  public function __construct(array $mapping) {
    $this->mapping = $mapping + [
      'items' => [], 'default_item' => '', 'rounding_item' => '', 'discount_account' => '',
      'customers' => [], 'tax_codes' => [], 'no_vat_code' => '',
    ];
  }

  // A company that charges VAT needs a tax code on every line, so an untaxed line always names one.
  public function no_vat_code(): string {
    return (string)$this->mapping['no_vat_code'] !== '' ? (string)$this->mapping['no_vat_code'] : 'No VAT';
  }

  // The QuickBooks item for a FreeBooks GL account. A split entry uses its foreign side only when
  // the document is known to be foreign; an unknown country ($is_domestic null) counts as domestic.
  public function item_for(string $gl_account, ?bool $is_domestic = null): string {
    if (!array_key_exists($gl_account, $this->mapping['items'])) return (string)$this->mapping['default_item'];
    $entry = $this->mapping['items'][$gl_account];
    if (!is_array($entry)) return (string)$entry;
    $domestic = (string)($entry['domestic'] ?? '');
    $foreign  = (string)($entry['foreign']  ?? '');
    if ($is_domestic === false) return $foreign  !== '' ? $foreign  : ($domestic !== '' ? $domestic : (string)$this->mapping['default_item']);
    return $domestic !== '' ? $domestic : ($foreign !== '' ? $foreign : (string)$this->mapping['default_item']);
  }

  // The QuickBooks tax code name for a FreeBooks tax_rate_id. 0 is always untaxed. Any other id the
  // mapping does not list returns null, so the caller can flag it rather than guess.
  public function tax_code_for($tax_rate_id): ?string {
    $id = (int)$tax_rate_id;
    if ($id === 0) return self::NO_TAX;
    $name = $this->mapping['tax_codes'][(string)$id] ?? null;
    return ($name === null || $name === '') ? null : (string)$name;
  }

  public function customer_for(string $customer_name): string {
    return (string)($this->mapping['customers'][$customer_name] ?? $customer_name);
  }

  /**
   * Builds the QuickBooks document for one record.
   *
   * $doc_type is 'invoice' or 'credit_memo'. $opts holds due_days, rounding_tolerance,
   * home_country, country_source ('shipping' or 'billing') and use_payment_reference.
   *
   * The document states its VAT rather than leaving QuickBooks to work it out: one tax line per tax
   * code, from the code's taxable net. FreeBooks takes a discount as a percentage off every product
   * line, so each code's net loses the same share of its product lines, and freight keeps its full
   * amount. FreeBooks rounds its tax once over the whole document where a tax line is rounded per
   * code, so the two can differ by a penny: the record's saved tax is authoritative, and a
   * difference within the rounding tolerance is taken into the largest tax line. Any gap left
   * between the record's saved total and the document's is closed with a Rounding Adjustment line.
   */
  public function build_document(array $record, string $doc_type, array $opts): array {
    $opts += ['due_days' => 30, 'rounding_tolerance' => 0.01, 'home_country' => '',
              'country_source' => 'shipping', 'use_payment_reference' => false];
    $tolerance = (float)$opts['rounding_tolerance'];
    $warnings = [];
    $unmapped_accounts = [];
    $unmapped_tax_rates = [];
    $lines = [];

    $country = $this->resolve_country($record, (string)$opts['country_source']);
    $home_country = strtoupper(trim((string)$opts['home_country']));
    $is_domestic = null;
    if ($home_country !== '') {
      if ($country !== '') {
        $is_domestic = ($country === $home_country);
      } else {
        $warnings[] = QUICKBOOKS_SYNC_WARN_NO_COUNTRY;
      }
    }

    // FreeBooks stores every amount in the home currency; currency_rate converts it to the document
    // currency. QuickBooks takes each figure as being in the document's currency, so every amount is
    // converted here.
    $rate = (float)($record['currency_rate'] ?? 0) ?: 1.0;

    foreach (($record['lines'] ?? []) as $item) {
      $gl_account = trim((string)($item['gl_account'] ?? ''));
      $lines[] = $this->line('product', self::describe($item), (float)($item['qty'] ?? 0) ?: 1.0,
        (float)($item['unit_price'] ?? 0) * $rate, $gl_account, (int)($item['taxable'] ?? 0),
        (float)($item['tax_rate'] ?? 0), $is_domestic, $unmapped_accounts, $unmapped_tax_rates);
    }

    $freight = (float)($record['freight'] ?? 0) * $rate;
    if ($freight) {
      $lines[] = $this->line('freight', QUICKBOOKS_SYNC_LINE_FREIGHT, 1, $freight,
        trim((string)($record['freight_gl_account'] ?? '')), (int)($record['freight_taxable'] ?? 0),
        (float)($record['freight_tax_rate'] ?? 0), $is_domestic, $unmapped_accounts, $unmapped_tax_rates);
    }

    $product_net = 0.0;
    foreach ($lines as $line) if ($line['kind'] === 'product') $product_net += $line['amount'];
    $discount = round((float)($record['discount'] ?? 0) * $rate, 2);
    $discount_share = ($discount && $product_net) ? $discount / $product_net : 0.0;

    // Each tax code's taxable net and percentage. Two FreeBooks rates mapped to one code at
    // different percentages cannot both be right in QuickBooks, so that is flagged.
    $bases = [];
    foreach ($lines as $line) {
      if ($line['tax_code'] === self::NO_TAX) continue;
      $code = $line['tax_code'];
      $base = $line['kind'] === 'product' ? $line['amount'] * (1 - $discount_share) : $line['amount'];
      if (!isset($bases[$code])) {
        $bases[$code] = ['net' => 0.0, 'percent' => $line['tax_percent']];
      } elseif ($bases[$code]['percent'] != $line['tax_percent']) {
        $warnings[] = sprintf(QUICKBOOKS_SYNC_WARN_CODE_RATES, $code);
      }
      $bases[$code]['net'] += $base;
    }
    $tax_lines = [];
    foreach ($bases as $code => $base) {
      $tax_lines[] = [
        'tax_code' => $code,
        'percent'  => $base['percent'],
        'net'      => round($base['net'], 2),
        'amount'   => round($base['net'] * $base['percent'] / 100, 2),
      ];
    }

    $computed_tax = round(array_sum(array_column($tax_lines, 'amount')), 2);
    $saved_tax    = isset($record['tax']) ? round((float)$record['tax'] * $rate, 2) : $computed_tax;
    $tax_diff     = round($saved_tax - $computed_tax, 2);
    if ($tax_diff) {
      $largest = null;
      foreach ($tax_lines as $i => $tax_line) {
        if ($tax_line['percent'] && ($largest === null || abs($tax_line['amount']) > abs($tax_lines[$largest]['amount']))) $largest = $i;
      }
      if ($largest !== null && abs($tax_diff) <= $tolerance) {
        $tax_lines[$largest]['amount'] = round($tax_lines[$largest]['amount'] + $tax_diff, 2);
      } else {
        $warnings[] = sprintf(QUICKBOOKS_SYNC_WARN_TAX_MISMATCH, sprintf('%+.2f', $tax_diff), sprintf('%.2f', $tolerance));
      }
    }
    $total_tax = round(array_sum(array_column($tax_lines, 'amount')), 2);

    $subtotal       = round(array_sum(array_column($lines, 'amount')) - $discount, 2);
    $computed_total = round($subtotal + $total_tax, 2);
    $source_total   = round((float)($record['total'] ?? 0) * $rate, 2);
    $diff           = round($source_total - $computed_total, 2);

    $rounding_applied = 0.0;
    if ($diff) {
      if (abs($diff) > $tolerance) {
        $warnings[] = sprintf(QUICKBOOKS_SYNC_WARN_TOTAL_MISMATCH, sprintf('%+.2f', $diff), sprintf('%.2f', $tolerance));
      }
      $lines[] = [
        'kind'        => 'rounding',
        'description' => QUICKBOOKS_SYNC_LINE_ROUNDING,
        'quantity'    => 1,
        'unit_amount' => $diff,
        'amount'      => $diff,
        'item'        => (string)$this->mapping['rounding_item'],
        'tax_code'    => self::NO_TAX,
        'tax_percent' => 0.0,
      ];
      $rounding_applied = $diff;
    }

    // An invoice can carry the reference of the receipt that paid it (a transaction id or cheque
    // number) in place of its PO number. A credit memo has no such reference.
    $reference = (string)($record['reference'] ?? '');
    if ($opts['use_payment_reference'] && $doc_type === 'invoice') {
      $payment_reference = (string)($record['payment_reference'] ?? '');
      if ($payment_reference !== '') {
        $reference = $payment_reference;
      } else {
        $warnings[] = QUICKBOOKS_SYNC_WARN_NO_PAYMENT_REF;
      }
    }

    $number = (string)($record[$doc_type === 'invoice' ? 'invoice_number' : 'credit_memo_number'] ?? '');
    if (mb_strlen($number) > self::NUMBER_MAX) $warnings[] = sprintf(QUICKBOOKS_SYNC_WARN_NUMBER_LONG, self::NUMBER_MAX);

    $date = (string)($record['date'] ?? '');
    $due_date = $date;
    $parsed = self::parse_date($date);
    if ($parsed) {
      $due_date = $parsed->modify('+' . (int)$opts['due_days'] . ' days')->format('Y-m-d');
    } else {
      $warnings[] = sprintf(QUICKBOOKS_SYNC_WARN_BAD_DATE, $date);
    }

    $unmapped_accounts  = array_map('strval', array_keys($unmapped_accounts));
    $unmapped_tax_rates = array_map('strval', array_keys($unmapped_tax_rates));
    sort($unmapped_accounts, SORT_STRING);
    sort($unmapped_tax_rates, SORT_STRING);

    return [
      'doc_type'           => $doc_type,
      'number'             => $number,
      'reference'          => $reference,
      'date'               => $date,
      'due_date'           => $due_date,
      'customer_name'      => $this->customer_for((string)($record['customer_name'] ?? '')),
      'email'              => (string)($record['bill_email'] ?? ''),
      'bill_address'       => self::address($record, 'bill_'),
      'ship_address'       => self::address($record, 'ship_'),
      'currency'           => (string)($record['currency'] ?? ''),
      'exchange_rate'      => round(1 / $rate, 6),
      'lines'              => $lines,
      'discount'           => $discount ? ['amount' => $discount, 'account' => (string)$this->mapping['discount_account']] : null,
      'tax_lines'          => $tax_lines,
      'total_tax'          => $total_tax,
      'source_total'       => $source_total,
      'computed_total'     => $computed_total,
      'rounding_applied'   => $rounding_applied,
      'unmapped_accounts'  => $unmapped_accounts,
      'unmapped_tax_rates' => $unmapped_tax_rates,
      'country'            => $country,
      'is_domestic'        => $is_domestic,
      'warnings'           => $warnings,
    ];
  }

  /**
   * The body for QuickBooks' Invoice or CreditMemo API.
   *
   * $refs holds the QuickBooks ids the document's names resolve to:
   *   customer_id  the customer's id
   *   items        item name => id
   *   accounts     account name => id, for the discount account
   *   tax_codes    tax code name => ['id' => .., 'rates' => [['id' => .., 'percent' => ..], ..]],
   *                the rates being the code's sales tax rates
   *
   * The discount is QuickBooks' own discount line, after the product lines and before freight, so
   * it reduces the products alone, as FreeBooks' discount does. TxnTaxDetail states each code's tax:
   * QuickBooks wants every rate of a code listed, so a code with more than one rate has its tax
   * divided between them by percentage. A credit memo is written in positive amounts, like an
   * invoice.
   *
   * Throws quickbooks_sync_error when a name has no id, or the number is too long for QuickBooks.
   */
  public function payload(array $doc, array $refs): array {
    if (mb_strlen($doc['number']) > self::NUMBER_MAX) {
      throw new quickbooks_sync_error(sprintf(QUICKBOOKS_SYNC_ERR_NUMBER_LONG, $doc['number'], self::NUMBER_MAX));
    }
    $refs += ['customer_id' => '', 'items' => [], 'accounts' => [], 'tax_codes' => []];

    $sales = [];
    $after = [];
    foreach ($doc['lines'] as $line) {
      if (!isset($refs['items'][$line['item']])) {
        throw new quickbooks_sync_error(sprintf(QUICKBOOKS_SYNC_ERR_ITEM_NOT_FOUND, $line['item']));
      }
      $entry = [
        'Description'         => $line['description'],
        'Amount'              => $line['amount'],
        'DetailType'          => 'SalesItemLineDetail',
        'SalesItemLineDetail' => [
          'ItemRef'    => ['value' => (string)$refs['items'][$line['item']]],
          'Qty'        => $line['quantity'],
          'UnitPrice'  => $line['unit_amount'],
          'TaxCodeRef' => ['value' => (string)$this->tax_code_ref($line['tax_code'], $refs)['id']],
        ],
      ];
      if ($line['kind'] === 'product') $sales[] = $entry; else $after[] = $entry;
    }
    if ($doc['discount']) {
      $detail = ['PercentBased' => false];
      $account = $doc['discount']['account'];
      if ($account !== '') {
        if (!isset($refs['accounts'][$account])) {
          throw new quickbooks_sync_error(sprintf(QUICKBOOKS_SYNC_ERR_ACCOUNT_NOT_FOUND, $account));
        }
        $detail['DiscountAccountRef'] = ['value' => (string)$refs['accounts'][$account]];
      }
      $sales[] = ['Amount' => $doc['discount']['amount'], 'DetailType' => 'DiscountLineDetail', 'DiscountLineDetail' => $detail];
    }

    $tax_lines = [];
    foreach ($doc['tax_lines'] as $tax_line) {
      $code  = $this->tax_code_ref($tax_line['tax_code'], $refs);
      $rates = $code['rates'] ?? [];
      if (!$rates) {
        if ($tax_line['amount']) throw new quickbooks_sync_error(sprintf(QUICKBOOKS_SYNC_ERR_TAX_CODE_NO_RATE, $tax_line['tax_code']));
        continue;
      }
      $percents = array_sum(array_map(fn($r) => (float)$r['percent'], $rates));
      $left = $tax_line['amount'];
      foreach (array_values($rates) as $i => $r) {
        $last = $i === count($rates) - 1;
        $amount = $last ? round($left, 2)
                        : ($percents ? round($tax_line['amount'] * (float)$r['percent'] / $percents, 2) : 0.0);
        $left -= $amount;
        $tax_lines[] = [
          'Amount'        => $amount,
          'DetailType'    => 'TaxLineDetail',
          'TaxLineDetail' => [
            'TaxRateRef'       => ['value' => (string)$r['id']],
            'PercentBased'     => true,
            'TaxPercent'       => count($rates) === 1 ? $tax_line['percent'] : (float)$r['percent'],
            'NetAmountTaxable' => $tax_line['net'],
          ],
        ];
      }
    }

    $payload = [
      'CustomerRef'          => ['value' => (string)$refs['customer_id']],
      'DocNumber'            => $doc['number'],
      'TxnDate'              => $doc['date'],
      'Line'                 => array_merge($sales, $after),
      'GlobalTaxCalculation' => 'TaxExcluded',
      'TxnTaxDetail'         => ['TotalTax' => $doc['total_tax'], 'TaxLine' => $tax_lines],
    ];
    if ($doc['discount']) $payload['ApplyTaxAfterDiscount'] = true;
    if ($doc['doc_type'] === 'invoice') $payload['DueDate'] = $doc['due_date'];
    if ($doc['reference'] !== '') $payload['PrivateNote'] = $doc['reference'];
    if ($doc['email'] !== '') $payload['BillEmail'] = ['Address' => $doc['email']];
    if ($address = self::physical_address($doc['bill_address'])) $payload['BillAddr'] = $address;
    if ($address = self::physical_address($doc['ship_address'])) $payload['ShipAddr'] = $address;
    if ($doc['currency'] !== '') {
      $payload['CurrencyRef'] = ['value' => $doc['currency']];
      if ($doc['exchange_rate'] != 1) $payload['ExchangeRate'] = $doc['exchange_rate'];
    }
    return $payload;
  }

  // One sales line, recording an unmapped account or tax rate as it goes. The unit amount keeps
  // six decimals, so the line amount QuickBooks checks against quantity times unit price is the
  // amount FreeBooks charged.
  private function line(string $kind, string $description, float $qty, float $unit_amount, string $gl_account,
                        int $tax_rate_id, float $tax_percent, ?bool $is_domestic,
                        array &$unmapped_accounts, array &$unmapped_tax_rates): array {
    if (!array_key_exists($gl_account, $this->mapping['items'])) $unmapped_accounts[$gl_account !== '' ? $gl_account : '(blank)'] = true;
    $tax_code = $this->tax_code_for($tax_rate_id);
    if ($tax_rate_id && $tax_code === null) {
      $unmapped_tax_rates[(string)$tax_rate_id] = true;
      $tax_code = self::NO_TAX;
    }
    $unit_amount = round($unit_amount, 6);
    return [
      'kind'        => $kind,
      'description' => $description,
      'quantity'    => $qty,
      'unit_amount' => $unit_amount,
      'amount'      => round($qty * $unit_amount, 2),
      'item'        => $this->item_for($gl_account, $is_domestic),
      'tax_code'    => $tax_code,
      'tax_percent' => $tax_code === self::NO_TAX ? 0.0 : $tax_percent,
    ];
  }

  private function tax_code_ref(string $tax_code, array $refs): array {
    $name = $tax_code === self::NO_TAX ? $this->no_vat_code() : $tax_code;
    if (!isset($refs['tax_codes'][$name])) {
      throw new quickbooks_sync_error(sprintf(QUICKBOOKS_SYNC_ERR_TAX_CODE_NOT_FOUND, $name));
    }
    return $refs['tax_codes'][$name];
  }

  // A product line's description, led by its SKU when it has one.
  private static function describe(array $item): string {
    $sku  = trim((string)($item['sku'] ?? ''));
    $text = trim((string)($item['description'] ?? ''));
    if ($sku === '') return $text !== '' ? $text : QUICKBOOKS_SYNC_LINE_NO_DESCRIPTION;
    return $text !== '' ? $sku . ' - ' . $text : $sku;
  }

  private static function address(array $record, string $prefix): array {
    $address = [];
    foreach (['address1', 'address2', 'city', 'state', 'postal_code', 'country'] as $field) {
      $address[$field] = trim((string)($record[$prefix . $field] ?? ''));
    }
    return $address;
  }

  // QuickBooks' PhysicalAddress, or null for an address with nothing in it. The country is written
  // as FreeBooks holds it, ISO alpha-3, which QuickBooks accepts.
  private static function physical_address(array $address): ?array {
    if (implode('', $address) === '') return null;
    return array_filter([
      'Line1'                  => $address['address1'],
      'Line2'                  => $address['address2'],
      'City'                   => $address['city'],
      'CountrySubDivisionCode' => $address['state'],
      'PostalCode'             => $address['postal_code'],
      'Country'                => $address['country'],
    ], fn($value) => $value !== '');
  }

  // The record's country, from the address country_source names, or the other address when that
  // one is blank: many records have no separate shipping address.
  private function resolve_country(array $record, string $country_source): string {
    [$first, $second] = $country_source === 'billing' ? ['bill_country', 'ship_country'] : ['ship_country', 'bill_country'];
    $country = strtoupper(trim((string)($record[$first] ?? '')));
    if ($country === '') $country = strtoupper(trim((string)($record[$second] ?? '')));
    return $country;
  }

  // A date in Y-m-d form, or null. A date that rolls over (30 February, say) is refused, not moved on.
  private static function parse_date(string $date): ?DateTimeImmutable {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed) return null;
    $errors = DateTimeImmutable::getLastErrors();
    if ($errors && ($errors['warning_count'] || $errors['error_count'])) return null;
    return $parsed;
  }
}
