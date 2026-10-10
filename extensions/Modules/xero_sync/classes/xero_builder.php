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
//  Path: /modules/xero_sync/classes/xero_builder.php
//
// Turns a sales invoice or credit memo, in the shape the api module's invoices and credit_memos
// resources return, into a Xero document, then into a row set for Xero's Sales Invoices import CSV
// or a body for Xero's Invoices or CreditNotes API. It reads no database and makes no calls, so
// tests/run_fixtures.php can check it against the golden fixtures alone.
//
// The mapping array holds:
//   accounts          FreeBooks GL account => Xero account code, or ['domestic' => .., 'foreign' => ..]
//   default_account   Xero account for a GL account the mapping does not list
//   rounding_account  Xero account for the Rounding Adjustment line
//   contacts          FreeBooks customer name => Xero contact name, where they differ
//   tax_types         FreeBooks tax_rate_id => Xero tax rate NAME (not its API code)
//   no_vat_type       Xero tax rate name written for an untaxed line (default "No VAT")
//
// Every amount is rounded with PHP round(x, 2), half away from zero, which is how FreeBooks rounds
// tax, and only where a figure is fixed: a line amount, a line's tax, and the totals.
//

class xero_sync_error extends RuntimeException {}

class xero_builder {

  // Xero's Sales Invoices import template, column for column.
  const CSV_HEADERS = [
    'ContactName', 'EmailAddress',
    'POAddressLine1', 'POAddressLine2', 'POAddressLine3', 'POAddressLine4',
    'POCity', 'PORegion', 'POPostalCode', 'POCountry',
    'InvoiceNumber', 'Reference', 'InvoiceDate', 'DueDate', 'Total',
    'InventoryItemCode', 'Description', 'Quantity', 'UnitAmount', 'Discount',
    'AccountCode', 'TaxType', 'TaxAmount',
    'TrackingName1', 'TrackingOption1', 'TrackingName2', 'TrackingOption2',
    'Currency', 'BrandingTheme',
  ];

  // The marker for an untaxed line. It is not a Xero tax rate name: the CSV and the API both write
  // the no_vat_type rate in its place.
  const NO_TAX = 'NONE';

  private array $mapping;

  public function __construct(array $mapping) {
    $this->mapping = $mapping + [
      'accounts' => [], 'default_account' => '', 'rounding_account' => '',
      'contacts' => [], 'tax_types' => [], 'no_vat_type' => '',
    ];
  }

  // A blank cell leaves some imported lines with no tax rate selected at all, which Xero then flags
  // on the invoice, so an untaxed line always names a rate.
  public function no_vat_type(): string {
    return (string)$this->mapping['no_vat_type'] !== '' ? (string)$this->mapping['no_vat_type'] : 'No VAT';
  }

  // The Xero account for a FreeBooks GL account. A split entry uses its foreign side only when the
  // document is known to be foreign; an unknown country ($is_domestic null) counts as domestic.
  public function account_for(string $gl_account, ?bool $is_domestic = null): string {
    if (!array_key_exists($gl_account, $this->mapping['accounts'])) return (string)$this->mapping['default_account'];
    $entry = $this->mapping['accounts'][$gl_account];
    if (!is_array($entry)) return (string)$entry;
    $domestic = (string)($entry['domestic'] ?? '');
    $foreign  = (string)($entry['foreign']  ?? '');
    if ($is_domestic === false) return $foreign  !== '' ? $foreign  : ($domestic !== '' ? $domestic : (string)$this->mapping['default_account']);
    return $domestic !== '' ? $domestic : ($foreign !== '' ? $foreign : (string)$this->mapping['default_account']);
  }

  // The Xero tax rate name for a FreeBooks tax_rate_id. 0 is always untaxed. Any other id the
  // mapping does not list returns null, so the caller can flag it rather than guess.
  public function tax_type_for($tax_rate_id): ?string {
    $id = (int)$tax_rate_id;
    if ($id === 0) return self::NO_TAX;
    $name = $this->mapping['tax_types'][(string)$id] ?? null;
    return ($name === null || $name === '') ? null : (string)$name;
  }

  public function contact_for(string $customer_name): string {
    return (string)($this->mapping['contacts'][$customer_name] ?? $customer_name);
  }

  /**
   * Builds the Xero document for one record.
   *
   * $doc_type is 'invoice' or 'credit_note'. $opts holds due_days, rounding_tolerance,
   * home_country, country_source ('shipping' or 'billing') and use_payment_reference.
   *
   * Xero taxes each line on its own and rounds per line, where FreeBooks totals by tax authority and
   * rounds once, so the two can land a penny apart. The record's saved total is authoritative: the
   * document predicts the tax Xero will charge, and closes any gap with a Rounding Adjustment line
   * against its own account rather than nudging a real line.
   *
   * A discount is taxed by giving the Discount line the same tax rate as the product lines, so
   * Xero's per-line engine taxes the negative amount too, which gives FreeBooks' tax on the
   * discounted total. Shrinking each product line's own tax instead makes the prediction disagree
   * with what Xero computes, and Xero rejects the import. Freight is never discounted (FreeBooks
   * excludes it from the discounted base), so only the product lines decide the rate; when they use
   * more than one, the discount cannot be apportioned and stays untaxed with a warning.
   */
  public function build_document(array $record, string $doc_type, array $opts): array {
    $opts += ['due_days' => 30, 'rounding_tolerance' => 0.01, 'home_country' => '',
              'country_source' => 'shipping', 'use_payment_reference' => false];
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
        $warnings[] = XERO_SYNC_WARN_NO_COUNTRY;
      }
    }

    // FreeBooks stores every amount in the home currency; currency_rate converts it to the document
    // currency. Xero takes each figure as being in the document's currency, so every amount is
    // converted here.
    $rate = (float)($record['currency_rate'] ?? 0) ?: 1.0;
    $predicted_tax = 0.0;
    $product_tax_rates = []; // "tax_rate_id|rate" => [tax_rate_id, rate], over the taxed product lines

    foreach (($record['lines'] ?? []) as $item) {
      $gl_account = trim((string)($item['gl_account'] ?? ''));
      if (!array_key_exists($gl_account, $this->mapping['accounts'])) $unmapped_accounts[$gl_account !== '' ? $gl_account : '(blank)'] = true;

      $tax_rate_id = (int)($item['taxable'] ?? 0);
      $tax_type = $this->tax_type_for($tax_rate_id);
      if ($tax_rate_id && $tax_type === null) {
        $unmapped_tax_rates[(string)$tax_rate_id] = true;
        $tax_type = self::NO_TAX;
      }

      $qty = (float)($item['qty'] ?? 0) ?: 1.0;
      $unit_amount = round((float)($item['unit_price'] ?? 0) * $rate, 2);
      $rate_pct = (float)($item['tax_rate'] ?? 0);
      if ($rate_pct && $tax_type !== self::NO_TAX) {
        // From the rounded unit amount the line is written with, not the record's own amount, which
        // can differ by a penny once the unit price is rounded.
        $predicted_tax += round($qty * $unit_amount * $rate_pct / 100, 2);
        $product_tax_rates[$tax_rate_id . '|' . $rate_pct] = [$tax_rate_id, $rate_pct];
      }

      $lines[] = [
        'description'  => self::first_text($item['description'] ?? null, $item['sku'] ?? null, XERO_SYNC_LINE_NO_DESCRIPTION),
        'quantity'     => $qty,
        'unit_amount'  => $unit_amount,
        'account_code' => $this->account_for($gl_account, $is_domestic),
        'tax_type'     => $tax_type,
        'item_code'    => (string)($item['sku'] ?? ''),
      ];
    }

    $freight = (float)($record['freight'] ?? 0) * $rate;
    if ($freight) {
      $freight_gl = trim((string)($record['freight_gl_account'] ?? ''));
      if (!array_key_exists($freight_gl, $this->mapping['accounts'])) $unmapped_accounts[$freight_gl !== '' ? $freight_gl : '(blank)'] = true;
      $freight_tax_id = (int)($record['freight_taxable'] ?? 0);
      $freight_tax_type = $this->tax_type_for($freight_tax_id);
      if ($freight_tax_id && $freight_tax_type === null) {
        $unmapped_tax_rates[(string)$freight_tax_id] = true;
        $freight_tax_type = self::NO_TAX;
      }
      $freight_rate_pct = (float)($record['freight_tax_rate'] ?? 0);
      $freight_amount = round($freight, 2);
      if ($freight_rate_pct && $freight_tax_type !== self::NO_TAX) {
        $predicted_tax += round($freight_amount * $freight_rate_pct / 100, 2);
      }
      $lines[] = [
        'description'  => XERO_SYNC_LINE_FREIGHT,
        'quantity'     => 1,
        'unit_amount'  => $freight_amount,
        'account_code' => $this->account_for($freight_gl, $is_domestic),
        'tax_type'     => $freight_tax_type,
        'item_code'    => '',
      ];
    }

    $discount = (float)($record['discount'] ?? 0) * $rate;
    if ($discount) {
      $discount_gl = trim((string)($record['discount_gl_account'] ?? ''));
      if (!array_key_exists($discount_gl, $this->mapping['accounts'])) $unmapped_accounts[$discount_gl !== '' ? $discount_gl : '(blank)'] = true;
      $discount_amount = -round($discount, 2);
      $discount_tax_type = self::NO_TAX;
      if (count($product_tax_rates) === 1) {
        [$disc_tax_id, $disc_rate_pct] = reset($product_tax_rates);
        $discount_tax_type = $this->tax_type_for($disc_tax_id) ?? self::NO_TAX;
        if ($discount_tax_type !== self::NO_TAX) {
          $predicted_tax += round($discount_amount * $disc_rate_pct / 100, 2);
        }
      } elseif (count($product_tax_rates) > 1) {
        $warnings[] = XERO_SYNC_WARN_DISCOUNT_MIXED;
      }
      $lines[] = [
        'description'  => XERO_SYNC_LINE_DISCOUNT,
        'quantity'     => 1,
        'unit_amount'  => $discount_amount,
        'account_code' => $this->account_for($discount_gl, $is_domestic),
        'tax_type'     => $discount_tax_type,
        'item_code'    => '',
      ];
    }

    $subtotal = 0.0;
    foreach ($lines as $line) $subtotal += $line['quantity'] * $line['unit_amount'];
    $subtotal       = round($subtotal, 2);
    $computed_total = round($subtotal + $predicted_tax, 2);
    $source_total   = round((float)($record['total'] ?? 0) * $rate, 2);
    $diff           = round($source_total - $computed_total, 2);

    $rounding_applied = 0.0;
    if ($diff) {
      $tolerance = (float)$opts['rounding_tolerance'];
      if (abs($diff) > $tolerance) {
        $warnings[] = sprintf(XERO_SYNC_WARN_TOTAL_MISMATCH, sprintf('%+.2f', $diff), sprintf('%.2f', $tolerance));
      }
      $lines[] = [
        'description'  => XERO_SYNC_LINE_ROUNDING,
        'quantity'     => 1,
        'unit_amount'  => $diff,
        'account_code' => (string)$this->mapping['rounding_account'],
        'tax_type'     => self::NO_TAX,
        'item_code'    => '',
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
        $warnings[] = XERO_SYNC_WARN_NO_PAYMENT_REF;
      }
    }

    $date = (string)($record['date'] ?? '');
    $due_date = $date;
    $parsed = self::parse_date($date);
    if ($parsed) {
      $due_date = $parsed->modify('+' . (int)$opts['due_days'] . ' days')->format('Y-m-d');
    } else {
      $warnings[] = sprintf(XERO_SYNC_WARN_BAD_DATE, $date);
    }

    // A credit memo is built in positive terms, like an invoice, so its totals read the same. Xero's
    // import has no credit note upload, so the CSV writes a credit as an invoice with negative
    // quantities; the API payload turns them back.
    if ($doc_type === 'credit_note') {
      foreach ($lines as &$line) $line['quantity'] = -$line['quantity'];
      unset($line);
    }

    $unmapped_accounts  = array_map('strval', array_keys($unmapped_accounts));
    $unmapped_tax_rates = array_map('strval', array_keys($unmapped_tax_rates));
    sort($unmapped_accounts, SORT_STRING);
    sort($unmapped_tax_rates, SORT_STRING);

    return [
      'doc_type'           => $doc_type,
      'number'             => (string)($record[$doc_type === 'invoice' ? 'invoice_number' : 'credit_memo_number'] ?? ''),
      'reference'          => $reference,
      'date'               => $date,
      'due_date'           => $due_date,
      'contact_name'       => $this->contact_for((string)($record['customer_name'] ?? '')),
      'email'              => (string)($record['bill_email'] ?? ''),
      'address1'           => (string)($record['bill_address1'] ?? ''),
      'address2'           => (string)($record['bill_address2'] ?? ''),
      'city'               => (string)($record['bill_city'] ?? ''),
      'region'             => (string)($record['bill_state'] ?? ''),
      'postal_code'        => (string)($record['bill_postal_code'] ?? ''),
      'billing_country'    => (string)($record['bill_country'] ?? ''),
      'currency'           => (string)($record['currency'] ?? ''),
      'lines'              => $lines,
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

  // The CSV rows for a set of documents, headers first. Discount (a per-line percentage in Xero's
  // template) stays blank because the discount is its own line; TaxAmount stays blank for Xero to
  // compute from the tax rate. Total is negated for a credit note, to match its negative lines.
  public function csv_rows(array $documents): array {
    $rows = [self::CSV_HEADERS];
    foreach ($documents as $doc) {
      $total = $doc['doc_type'] === 'credit_note' ? -$doc['source_total'] : $doc['source_total'];
      foreach ($doc['lines'] as $line) {
        $rows[] = [
          $doc['contact_name'], $doc['email'],
          $doc['address1'], $doc['address2'], '', '',
          $doc['city'], $doc['region'], $doc['postal_code'], $doc['billing_country'],
          $doc['number'], $doc['reference'], $doc['date'], $doc['due_date'],
          sprintf('%.2f', $total),
          $line['item_code'], $line['description'], self::format_quantity($line['quantity']),
          sprintf('%.2f', $line['unit_amount']), '',
          $line['account_code'], $line['tax_type'] === self::NO_TAX ? $this->no_vat_type() : $line['tax_type'], '',
          '', '', '', '',
          $doc['currency'], '',
        ];
      }
    }
    return $rows;
  }

  // RFC 4180: CRLF line ends, and a field is quoted only when it holds a comma, a quote or a line
  // break. fputcsv() also quotes any field containing a space, which Xero accepts but which makes
  // the file harder to compare.
  public function csv_text(array $documents): string {
    $out = '';
    foreach ($this->csv_rows($documents) as $row) {
      $out .= implode(',', array_map(function ($field) {
        $field = (string)$field;
        return strpbrk($field, ",\"\r\n") === false ? $field : '"' . str_replace('"', '""', $field) . '"';
      }, $row)) . "\r\n";
    }
    return $out;
  }

  /**
   * The body for Xero's Invoices or CreditNotes API.
   *
   * $tax_name_to_code maps each of the organisation's tax rate names to its TaxType code, from
   * GET /TaxRates. $known_item_codes lists the organisation's item codes, from GET /Items: the API
   * rejects a whole line whose ItemCode Xero does not hold, so a line names its item only when the
   * code is known. Total and TaxAmount are never sent; Xero computes both from the lines.
   *
   * Throws xero_sync_error when a line's tax rate name is not one of the organisation's.
   */
  public function payload(array $doc, array $tax_name_to_code, array $known_item_codes): array {
    $is_credit = $doc['doc_type'] === 'credit_note';
    $sign = $is_credit ? -1 : 1;
    $known = array_flip(array_map('strval', $known_item_codes));

    $line_items = [];
    foreach ($doc['lines'] as $line) {
      $name = $line['tax_type'] === self::NO_TAX ? $this->no_vat_type() : $line['tax_type'];
      if (!array_key_exists($name, $tax_name_to_code)) {
        throw new xero_sync_error(sprintf(XERO_SYNC_ERR_TAX_RATE_NOT_FOUND, $name));
      }
      $item = [
        'Description' => $line['description'],
        'Quantity'    => $line['quantity'] * $sign,
        'UnitAmount'  => $line['unit_amount'],
        'AccountCode' => $line['account_code'],
        'TaxType'     => $tax_name_to_code[$name],
      ];
      if ($line['item_code'] !== '' && isset($known[$line['item_code']])) $item['ItemCode'] = $line['item_code'];
      $line_items[] = $item;
    }

    $contact = ['Name' => $doc['contact_name']];
    if ($doc['email'] !== '') $contact['EmailAddress'] = $doc['email'];
    if ($doc['address1'] !== '' || $doc['city'] !== '' || $doc['postal_code'] !== '') {
      $contact['Addresses'] = [[
        'AddressType'  => 'STREET',
        'AddressLine1' => $doc['address1'],
        'AddressLine2' => $doc['address2'],
        'City'         => $doc['city'],
        'Region'       => $doc['region'],
        'PostalCode'   => $doc['postal_code'],
        'Country'      => $doc['billing_country'],
      ]];
    }

    $payload = [
      'Type'            => $is_credit ? 'ACCRECCREDIT' : 'ACCREC',
      'Contact'         => $contact,
      'Date'            => $doc['date'],
      'LineItems'       => $line_items,
      'LineAmountTypes' => 'Exclusive',
      'Reference'       => $doc['reference'],
      'Status'          => 'DRAFT',
    ];
    if ($doc['currency'] !== '') $payload['CurrencyCode'] = $doc['currency'];
    if ($is_credit) {
      $payload['CreditNoteNumber'] = $doc['number'];
    } else {
      $payload['InvoiceNumber'] = $doc['number'];
      $payload['DueDate'] = $doc['due_date'];
    }
    return $payload;
  }

  // The POST /Items bodies for the document's item codes Xero does not hold yet, one per code. The
  // items are untracked (no inventory asset account), so FreeBooks stays the only record of stock;
  // they exist so a line can name its item. Xero caps Name at 50 characters and Description at 4000.
  public function items_to_create(array $doc, array $known_item_codes): array {
    $known = array_flip(array_map('strval', $known_item_codes));
    $items = [];
    foreach ($doc['lines'] as $line) {
      $code = $line['item_code'];
      if ($code === '' || isset($known[$code]) || isset($items[$code])) continue;
      $items[$code] = [
        'Code'         => $code,
        'Name'         => mb_substr($line['description'], 0, 50),
        'Description'  => mb_substr($line['description'], 0, 4000),
        'SalesDetails' => ['UnitPrice' => $line['unit_amount'], 'AccountCode' => $line['account_code']],
      ];
    }
    return array_values($items);
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

  // A whole quantity is written without decimals; a fractional one (metres, say) as it is.
  private static function format_quantity($qty): string {
    $qty = (float)$qty;
    return $qty == floor($qty) ? (string)(int)$qty : (string)$qty;
  }

  private static function first_text(...$values): string {
    foreach ($values as $value) {
      if ($value !== null && (string)$value !== '') return (string)$value;
    }
    return '';
  }
}
