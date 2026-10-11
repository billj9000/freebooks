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
//  Path: /modules/xero_sync/functions/xero_lock.php
//
// The lock on documents sent to Xero, and the two fingerprints a sent document keeps:
//   record_hash  the FreeBooks fields Xero holds or the tax treatment rests on: the customer, both
//                addresses in full, the billing email, number, date, reference, currency and every
//                line except cost of goods. A sent document may not be saved with these changed.
//   doc_hash     the document as built for Xero, which also reflects the mapping and options. When
//                it differs from what was sent, the export page shows the document as changed.
// Cost of goods lines are left out because FreeBooks re-posts them by itself when an earlier
// purchase changes, and Xero is not sent them.
//

// A send that has not finished within this many seconds is taken to have stopped part way, and
// no longer locks the document or blocks another send.
const XERO_SYNC_CLAIM_SECONDS = 300;

function xero_sync_record_hash(int $id): string {
  global $db;
  $main = $db->Execute("SELECT journal_id, purchase_invoice_id, post_date, purch_order_id, bill_acct_id,
      bill_primary_name, bill_contact, bill_address1, bill_address2, bill_city_town, bill_state_province,
      bill_postal_code, bill_country_code, bill_email, ship_primary_name, ship_contact, ship_address1,
      ship_address2, ship_city_town, ship_state_province, ship_postal_code, ship_country_code,
      currencies_code, currencies_value, freight, freight_tax_id, discount, sales_tax, total_amount
    FROM " . TABLE_JOURNAL_MAIN . " WHERE id = " . $id);
  if ($main->EOF) return '';
  $lines = [];
  $result = $db->Execute("SELECT gl_type, sku, qty, description, debit_amount, credit_amount, gl_account, taxable
    FROM " . TABLE_JOURNAL_ITEM . " WHERE ref_id = " . $id . " AND gl_type <> 'cog'");
  while (!$result->EOF) {
    $lines[] = json_encode(array_values($result->fields));
    $result->MoveNext();
  }
  sort($lines);
  return hash('sha256', json_encode([array_values($main->fields), $lines]));
}

function xero_sync_doc_hash(array $doc): string {
  return hash('sha256', json_encode($doc, JSON_PRESERVE_ZERO_FRACTION));
}

// The tracking row for a document, or null when it has never been sent or tried.
function xero_sync_doc_row(int $id): ?array {
  global $db;
  $result = $db->Execute("SELECT * FROM " . TABLE_XERO_SYNC_DOC . " WHERE id = " . $id);
  return $result->EOF ? null : $result->fields;
}

function xero_sync_is_locked(array $row): bool {
  if ($row['state'] === 'sent') return true;
  return $row['state'] === 'sending' && strtotime((string)$row['claimed_at']) > time() - XERO_SYNC_CLAIM_SECONDS;
}

// The document lock hook (gen_document_lock_answers in trunk's common_functions.php).
function xero_sync_document_lock(string $event, int $id, int $journal_id): string {
  if ($journal_id !== 12 && $journal_id !== 13) return '';
  $row = xero_sync_doc_row($id);
  if (!$row || !xero_sync_is_locked($row)) return '';
  gen_pull_language('xero_sync');
  $when = $row['sent_at'] ? gen_locale_date(substr($row['sent_at'], 0, 10)) : '';
  switch ($event) {
    case 'badge':
      return XERO_SYNC_BADGE;
    case 'save':
      return xero_sync_record_hash($id) === $row['record_hash'] ? '' : sprintf(XERO_SYNC_LOCK_SAVE, $when);
    case 'delete':
      return sprintf(XERO_SYNC_LOCK_DELETE, $when);
  }
  return '';
}
