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
//  Path: /modules/xero_sync/classes/xero_sender.php
//
// Sends one invoice or credit memo to Xero and records the result in the doc table. A document is
// claimed before it is sent, so two people sending the same document at once cannot make two
// copies in Xero. A document Xero already holds (one that was released, or failed when sent again)
// is sent with its Xero id, which updates that copy rather than adding another.
//

require_once(DIR_FS_MODULES . 'xero_sync/classes/xero_api.php');
require_once(DIR_FS_MODULES . 'xero_sync/classes/xero_source.php');
require_once(DIR_FS_MODULES . 'xero_sync/functions/xero_lock.php');
require_once(DIR_FS_MODULES . 'xero_sync/functions/xero_sync.php');

class xero_sender {

  const DOC_TYPES = [12 => 'invoice', 13 => 'credit_note'];
  const LISTS_SECONDS = 600;

  private xero_api $api;
  private xero_source $source;

  public function __construct(?xero_api $api = null, ?xero_source $source = null) {
    $this->api    = $api ?? new xero_api();
    $this->source = $source ?? new xero_source();
  }

  // Sends document $id. Returns its tracking row as it then stands; a refusal from FreeBooks or
  // Xero is recorded in it as state 'failed' with last_error, not thrown.
  public function send(int $id): array {
    global $db;
    $journal_id = (int)$db->Execute("SELECT journal_id FROM " . TABLE_JOURNAL_MAIN . " WHERE id = " . $id)->fields['journal_id'];
    if (!isset(self::DOC_TYPES[$journal_id])) throw new xero_sync_error(XERO_SYNC_ERR_NOT_A_DOCUMENT);
    if (!$this->claim($id, $journal_id)) return xero_sync_doc_row($id);
    $row = xero_sync_doc_row($id);
    try {
      $entry = $this->source->document(self::DOC_TYPES[$journal_id], $id);
      if (!$entry) throw new xero_sync_error(XERO_SYNC_ERR_NOT_A_DOCUMENT);
      $doc = $entry['doc'];
      if ($doc['unmapped_accounts'] || $doc['unmapped_tax_rates']) {
        throw new xero_sync_error(implode(' ', array_slice(xero_sync_problems($doc), 0, 2)));
      }
      $record_hash = xero_sync_record_hash($id);
      $saved = $this->post($doc, $row['xero_id']);
      $is_credit = $doc['doc_type'] === 'credit_note';
      $this->record($id, [
        'state'       => 'sent',
        'xero_id'     => (string)($saved[$is_credit ? 'CreditNoteID' : 'InvoiceID'] ?? $row['xero_id']),
        'xero_status' => (string)($saved['Status'] ?? ''),
        'sent_at'     => date('Y-m-d H:i:s'),
        'sent_by'     => (int)($_SESSION['admin_id'] ?? 0),
        'last_error'  => '',
        'record_hash' => $record_hash,
        'doc_hash'    => xero_sync_doc_hash($doc),
      ]);
    } catch (Throwable $e) {
      $this->record($id, ['state' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 2000)]);
    }
    return xero_sync_doc_row($id);
  }

  // Marks a sent document as released: it may be changed again, and its next send updates the
  // copy Xero holds.
  public static function release(int $id): bool {
    global $db;
    return (int)$db->Execute("UPDATE " . TABLE_XERO_SYNC_DOC . " SET state = 'released' WHERE id = " . $id . " AND state = 'sent'")->AffectedRows() === 1;
  }

  // Forgets the copy Xero holds, so the next send makes a new one. Only for a document that is not
  // locked.
  public static function forget(int $id): bool {
    global $db;
    return (int)$db->Execute("UPDATE " . TABLE_XERO_SYNC_DOC . " SET xero_id = '', xero_status = '' WHERE id = " . $id . " AND state IN ('released', 'failed')")->AffectedRows() === 1;
  }

  // Takes the document for this request: false when it is locked (sent, or being sent elsewhere).
  private function claim(int $id, int $journal_id): bool {
    global $db;
    $now = date('Y-m-d H:i:s');
    $db->Execute("INSERT IGNORE INTO " . TABLE_XERO_SYNC_DOC . " (id, journal_id) VALUES (" . $id . ", " . $journal_id . ")");
    return (int)$db->Execute("UPDATE " . TABLE_XERO_SYNC_DOC . " SET state = 'sending', claimed_at = '" . $now . "'
      WHERE id = " . $id . " AND state <> 'sent'
        AND (state <> 'sending' OR claimed_at IS NULL OR claimed_at < '" . date('Y-m-d H:i:s', time() - XERO_SYNC_CLAIM_SECONDS) . "')")->AffectedRows() === 1;
  }

  private function record(int $id, array $fields): void {
    db_perform(TABLE_XERO_SYNC_DOC, $fields, 'update', 'id = ' . $id);
  }

  // Creates the items Xero does not hold yet, then posts the document. Returns Xero's saved copy.
  private function post(array $doc, string $xero_id): array {
    $lists = $this->lists();
    foreach ($this->source->builder()->items_to_create($doc, $lists['items']) as $item) {
      try {
        $this->api->post('Items', ['Items' => [$item]], 'Items');
        $lists['items'][] = $item['Code'];
      } catch (xero_api_error $e) {
        // A line whose item cannot be made is still sent, without its item code.
      }
    }
    $this->remember_items($lists['items']);
    $payload = $this->source->builder()->payload($doc, $lists['tax'], $lists['items']);
    $is_credit = $doc['doc_type'] === 'credit_note';
    if ($xero_id !== '') {
      // An update leaves the status Xero has, so a document approved there stays approved.
      $payload[$is_credit ? 'CreditNoteID' : 'InvoiceID'] = $xero_id;
      unset($payload['Status']);
    }
    $endpoint = $is_credit ? 'CreditNotes' : 'Invoices';
    return $this->api->post($endpoint, [$endpoint => [$payload]], $endpoint);
  }

  // The organisation's tax rate codes by name and its item codes, read at most every ten minutes.
  private function lists(): array {
    $tenant = $this->api->tenant_id();
    $cached = $_SESSION['xero_sync_send'] ?? null;
    if ($cached && $cached['tenant_id'] === $tenant && $cached['at'] > time() - self::LISTS_SECONDS) return $cached;
    $tax = [];
    foreach ($this->api->tax_rates() as $rate) {
      if (isset($rate['Name'], $rate['TaxType'])) $tax[$rate['Name']] = $rate['TaxType'];
    }
    try {
      $items = array_values(array_filter(array_map(fn($item) => (string)($item['Code'] ?? ''), $this->api->get('Items')['Items'] ?? [])));
    } catch (xero_api_error $e) {
      $items = []; // without the list, lines are sent without item codes
    }
    $_SESSION['xero_sync_send'] = ['tenant_id' => $tenant, 'at' => time(), 'tax' => $tax, 'items' => $items];
    return $_SESSION['xero_sync_send'];
  }

  private function remember_items(array $items): void {
    if (isset($_SESSION['xero_sync_send'])) $_SESSION['xero_sync_send']['items'] = array_values(array_unique($items));
  }
}
