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
//  Path: /modules/quickbooks_sync/classes/quickbooks_sender.php
//
// Sends one invoice or credit memo to QuickBooks and records the result in the doc table. A
// document is claimed before it is sent, so two people sending the same document at once cannot
// make two copies in QuickBooks. A document QuickBooks already holds (one that was released, or
// failed when sent again) is sent with its QuickBooks id, which updates that copy rather than
// adding another.
//

require_once(DIR_FS_MODULES . 'quickbooks_sync/classes/quickbooks_api.php');
require_once(DIR_FS_MODULES . 'quickbooks_sync/classes/quickbooks_source.php');
require_once(DIR_FS_MODULES . 'quickbooks_sync/functions/quickbooks_lock.php');
require_once(DIR_FS_MODULES . 'quickbooks_sync/functions/quickbooks_sync.php');

class quickbooks_sender {

  const DOC_TYPES = [12 => 'invoice', 13 => 'credit_memo'];
  const ENTITIES  = ['invoice' => 'Invoice', 'credit_memo' => 'CreditMemo'];
  const LISTS_SECONDS = 600;

  private quickbooks_api $api;
  private quickbooks_source $source;

  public function __construct(?quickbooks_api $api = null, ?quickbooks_source $source = null) {
    $this->api    = $api ?? new quickbooks_api();
    $this->source = $source ?? new quickbooks_source();
  }

  // Sends document $id. Returns its tracking row as it then stands; a refusal from FreeBooks or
  // QuickBooks is recorded in it as state 'failed' with last_error, not thrown. QuickBooks' rate
  // limit is thrown as quickbooks_rate_limit, with the document left as it was, to be sent again
  // after the wait.
  public function send(int $id): array {
    global $db;
    $journal_id = (int)$db->Execute("SELECT journal_id FROM " . TABLE_JOURNAL_MAIN . " WHERE id = " . $id)->fields['journal_id'];
    if (!isset(self::DOC_TYPES[$journal_id])) throw new quickbooks_sync_error(QUICKBOOKS_SYNC_ERR_NOT_A_DOCUMENT);
    $before = quickbooks_sync_doc_row($id);
    if (!$this->claim($id, $journal_id)) return quickbooks_sync_doc_row($id);
    $row = quickbooks_sync_doc_row($id);
    try {
      $entry = $this->source->document(self::DOC_TYPES[$journal_id], $id);
      if (!$entry) throw new quickbooks_sync_error(QUICKBOOKS_SYNC_ERR_NOT_A_DOCUMENT);
      $doc = $entry['doc'];
      if (quickbooks_sync_is_blocked($doc)) {
        throw new quickbooks_sync_error(implode(' ', array_slice(quickbooks_sync_problems($doc), 0, 2)));
      }
      $record_hash = quickbooks_sync_record_hash($id);
      $saved = $this->post($doc, $row['qb_id']);
      $qb_id = (string)($saved['Id'] ?? '');
      if ($qb_id === '') throw new quickbooks_api_error(QUICKBOOKS_SYNC_ERR_REJECTED);
      $this->record($id, [
        'state'       => 'sent',
        'qb_id'       => $qb_id,
        'sent_at'     => date('Y-m-d H:i:s'),
        'sent_by'     => (int)($_SESSION['admin_id'] ?? 0),
        'last_error'  => '',
        'record_hash' => $record_hash,
        'doc_hash'    => quickbooks_sync_doc_hash($doc),
      ]);
    } catch (quickbooks_rate_limit $e) {
      $this->record($id, ['state' => $before['state'] ?? '', 'claimed_at' => 'null']);
      throw $e;
    } catch (Throwable $e) {
      $this->record($id, ['state' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 2000)]);
    }
    return quickbooks_sync_doc_row($id);
  }

  // Marks a sent document as released: it may be changed again, and its next send updates the
  // copy QuickBooks holds.
  public static function release(int $id): bool {
    global $db;
    return (int)$db->Execute("UPDATE " . TABLE_QUICKBOOKS_SYNC_DOC . " SET state = 'released' WHERE id = " . $id . " AND state = 'sent'")->AffectedRows() === 1;
  }

  // Forgets the QuickBooks id, so the next send makes a new document. QuickBooks refuses a number
  // already in use, even by a voided document, so that send succeeds only once the old document is
  // deleted in QuickBooks. Only for a document that is not locked.
  public static function forget(int $id): bool {
    global $db;
    return (int)$db->Execute("UPDATE " . TABLE_QUICKBOOKS_SYNC_DOC . " SET qb_id = '' WHERE id = " . $id . " AND state IN ('released', 'failed')")->AffectedRows() === 1;
  }

  // Takes the document for this request: false when it is locked (sent, or being sent elsewhere).
  private function claim(int $id, int $journal_id): bool {
    global $db;
    $now = date('Y-m-d H:i:s');
    $db->Execute("INSERT IGNORE INTO " . TABLE_QUICKBOOKS_SYNC_DOC . " (id, journal_id) VALUES (" . $id . ", " . $journal_id . ")");
    return (int)$db->Execute("UPDATE " . TABLE_QUICKBOOKS_SYNC_DOC . " SET state = 'sending', claimed_at = '" . $now . "'
      WHERE id = " . $id . " AND state <> 'sent'
        AND (state <> 'sending' OR claimed_at IS NULL OR claimed_at < '" . date('Y-m-d H:i:s', time() - QUICKBOOKS_SYNC_CLAIM_SECONDS) . "')")->AffectedRows() === 1;
  }

  private function record(int $id, array $fields): void {
    db_perform(TABLE_QUICKBOOKS_SYNC_DOC, $fields, 'update', 'id = ' . $id);
  }

  // Posts the document, creating its customer first when QuickBooks has none of that name. Returns
  // QuickBooks' saved copy. An update reads the copy QuickBooks holds first, for its version
  // (SyncToken), and is refused when a payment or credit has been applied to it: QuickBooks would
  // accept the change and leave the payment against different figures.
  private function post(array $doc, string $qb_id): array {
    $lists = $this->lists();
    $refs = [
      'customer_id' => $this->customer($doc),
      'items'       => $lists['items'],
      'accounts'    => $lists['accounts'],
      'tax_codes'   => $lists['tax_codes'],
    ];
    $payload = $this->source->builder()->payload($doc, $refs);
    $entity = self::ENTITIES[$doc['doc_type']];
    if ($qb_id !== '') {
      $current = $this->api->get(strtolower($entity) . '/' . rawurlencode($qb_id))[$entity] ?? [];
      if (abs((float)($current['TotalAmt'] ?? 0) - (float)($current['Balance'] ?? 0)) > 0.005) {
        throw new quickbooks_sync_error(QUICKBOOKS_SYNC_ERR_PAID);
      }
      $payload = ['Id' => $qb_id, 'SyncToken' => (string)($current['SyncToken'] ?? '')] + $payload;
    }
    return $this->api->post($entity, $payload);
  }

  // The QuickBooks id of the document's customer, found by name or created. Ids already found are
  // kept for the session, per company.
  private function customer(array $doc): string {
    $realm = $this->lists()['realm_id'];
    $name = quickbooks_builder::customer_name($doc['customer_name']);
    $known = $_SESSION['quickbooks_sync_customers'][$realm][$name] ?? null;
    if ($known !== null) return $known;
    $found = $this->api->query_all('Customer', "DisplayName = '" . str_replace(['\\', "'"], ['\\\\', "\\'"], $name) . "'");
    $id = (string)($found[0]['Id'] ?? '');
    if ($id === '') $id = (string)($this->api->post('Customer', $this->source->builder()->customer_payload($doc))['Id'] ?? '');
    if ($id === '') throw new quickbooks_api_error(QUICKBOOKS_SYNC_ERR_REJECTED);
    $_SESSION['quickbooks_sync_customers'][$realm][$name] = $id;
    return $id;
  }

  // The company's items and accounts by name, and its VAT codes by name with their sales rates,
  // read at most every ten minutes.
  private function lists(): array {
    $realm = $this->api->realm_id();
    $cached = $_SESSION['quickbooks_sync_send'] ?? null;
    if ($cached && $cached['realm_id'] === $realm && $cached['at'] > time() - self::LISTS_SECONDS) return $cached;
    $items = [];
    foreach ($this->api->items() as $item) $items[(string)$item['Name']] = (string)$item['Id'];
    $accounts = [];
    foreach ($this->api->accounts() as $account) $accounts[(string)$account['Name']] = (string)$account['Id'];
    $percents = [];
    foreach ($this->api->tax_rates() as $rate) $percents[(string)$rate['Id']] = (float)($rate['RateValue'] ?? 0);
    $tax_codes = [];
    foreach ($this->api->tax_codes() as $code) {
      $rates = [];
      foreach ($code['SalesTaxRateList']['TaxRateDetail'] ?? [] as $detail) {
        $rate_id = (string)($detail['TaxRateRef']['value'] ?? '');
        if ($rate_id !== '') $rates[] = ['id' => $rate_id, 'percent' => $percents[$rate_id] ?? 0.0];
      }
      $tax_codes[(string)$code['Name']] = ['id' => (string)$code['Id'], 'rates' => $rates];
    }
    $_SESSION['quickbooks_sync_send'] = ['realm_id' => $realm, 'at' => time(), 'items' => $items,
                                         'accounts' => $accounts, 'tax_codes' => $tax_codes];
    return $_SESSION['quickbooks_sync_send'];
  }
}
