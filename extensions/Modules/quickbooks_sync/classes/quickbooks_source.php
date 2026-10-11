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
//  Path: /modules/quickbooks_sync/classes/quickbooks_source.php
//
// Reads sales invoices and credit memos through the api module's invoices and credit_memos
// resources and builds each into a QuickBooks document. The resources work out what a record
// alone does not hold (each line's tax percentage, the freight line's effective tax rate, the
// freight account and the paying receipt's reference), so this module reads them the same way
// the REST API does. The api module's files are used directly; it need not be installed.
//

require_once(DIR_FS_MODULES . 'api/defaults.php');
require_once(DIR_FS_MODULES . 'api/classes/api_query.php');
require_once(DIR_FS_MODULES . 'api/classes/resources/invoices.php');
require_once(DIR_FS_MODULES . 'api/classes/resources/credit_memos.php');
require_once(DIR_FS_MODULES . 'quickbooks_sync/classes/quickbooks_builder.php');
require_once(DIR_FS_MODULES . 'quickbooks_sync/classes/quickbooks_settings.php');

class quickbooks_source {

  const INVOICE_FIELDS = [
    'id', 'invoice_number', 'reference', 'payment_reference', 'date', 'customer_id',
    'customer_name', 'total', 'tax', 'freight', 'freight_taxable', 'freight_tax_rate',
    'freight_gl_account', 'discount', 'currency', 'currency_rate', 'closed',
    'bill_address1', 'bill_address2', 'bill_city', 'bill_state', 'bill_postal_code',
    'bill_country', 'bill_email', 'ship_country', 'lines',
  ];

  const CREDIT_MEMO_FIELDS = [
    'id', 'credit_memo_number', 'reference', 'date', 'customer_id', 'customer_name',
    'total', 'tax', 'freight', 'freight_taxable', 'freight_tax_rate', 'freight_gl_account',
    'discount', 'currency', 'currency_rate', 'closed',
    'bill_address1', 'bill_address2', 'bill_city', 'bill_state', 'bill_postal_code',
    'bill_country', 'bill_email', 'ship_country', 'lines',
  ];

  private quickbooks_builder $builder;
  private array $options;

  public function __construct() {
    $this->builder = new quickbooks_builder(quickbooks_settings::mapping());
    $this->options = quickbooks_settings::options();
  }

  public function builder(): quickbooks_builder { return $this->builder; }

  // The documents of one type ('invoice' or 'credit_memo') dated from $from to $to inclusive
  // (Y-m-d), in date order. Each entry is ['id' => journal_main id, 'doc' => the built document].
  public function documents(string $doc_type, string $from, string $to): array {
    return $this->build($doc_type, [['date', 'between', [$from, $to]]]);
  }

  // One document by its journal_main id, or null when there is no such document of that type.
  public function document(string $doc_type, int $id): ?array {
    $documents = $this->build($doc_type, [['id', '=', $id]]);
    return $documents[0] ?? null;
  }

  private function build(string $doc_type, array $filters): array {
    [$resource, $fields] = $doc_type === 'invoice'
      ? [new api_resource_invoices(), self::INVOICE_FIELDS]
      : [new api_resource_credit_memos(), self::CREDIT_MEMO_FIELDS];
    $params = [
      'fields'            => json_encode($fields),
      'filters'           => json_encode($filters),
      'order_by'          => 'id asc',
      'limit_page_length' => API_MAX_PAGE_LENGTH,
    ];
    $documents = [];
    $start = 0;
    do {
      $params['limit_start'] = $start;
      $page = api_query::run($resource, $params);
      foreach ($page['data'] as $record) {
        $documents[] = ['id' => (int)$record['id'], 'doc' => $this->builder->build_document($record, $doc_type, $this->options)];
      }
      $start += API_MAX_PAGE_LENGTH;
    } while ($page['data'] && $start < $page['meta']['total']);
    // Paged by id, which is unique, so no document is skipped or repeated between pages; then put
    // in date order.
    usort($documents, fn($a, $b) => [$a['doc']['date'], $a['id']] <=> [$b['doc']['date'], $b['id']]);
    return $documents;
  }
}
