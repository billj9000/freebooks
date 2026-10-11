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
//  Path: /modules/quickbooks_sync/ajax/send.php
//
// The export page's htmx requests to QuickBooks, by step:
//   start    takes the ticked document ids and starts sending them
//   one      sends the first id of the queue, redraws its cells, and asks for the next
//   release  releases a sent document
//   forget   unlinks a released or failed document from the copy QuickBooks holds
// Sending is one document per request, so no request runs long and a refused document does not
// stop the rest.
//
$security_level = validate_ajax_user(SECURITY_ID_QUICKBOOKS_SYNC);
gen_pull_language('quickbooks_sync');
require_once(DIR_FS_MODULES . 'quickbooks_sync/classes/quickbooks_sender.php');

$send = quickbooks_sync_send_context($security_level);
$fail = function (string $message) {
  echo '<span class="text-danger">' . htmlspecialchars($message) . '</span>';
  die;
};
$ids_from = fn($list) => array_values(array_filter(array_map('intval', is_array($list) ? $list : explode(',', (string)$list))));

// The two cells of a document's row, swapped in out of band.
$cells = function (int $id, ?array $row, ?string $doc_hash) use ($send): string {
  return '<div id="qs-c-' . $id . '" hx-swap-oob="true">' . quickbooks_sync_pick_cell($id, $row, false, $send, false) . '</div>'
    . '<div id="qs-x-' . $id . '" hx-swap-oob="true">' . quickbooks_sync_qb_cell($id, $row, $doc_hash, $send) . '</div>';
};

switch ($_POST['step'] ?? '') {
  case 'start':
  case 'one':
    if (!$send['can_send']) $fail($send['why_not']);
    $queue  = $ids_from($_POST['step'] === 'start' ? ($_POST['send_ids'] ?? []) : ($_POST['queue'] ?? ''));
    $counts = $_POST['step'] === 'start' ? ['total' => count($queue), 'sent' => 0, 'failed' => 0]
                                         : ['total' => (int)($_POST['total'] ?? 0), 'sent' => (int)($_POST['sent'] ?? 0), 'failed' => (int)($_POST['failed'] ?? 0)];
    if (!$queue && $_POST['step'] === 'start') $fail(QUICKBOOKS_SYNC_SEND_NONE);
    $out = '';
    $wait = 0;
    if ($_POST['step'] === 'one' && $queue) {
      $id = array_shift($queue);
      try {
        $row = (new quickbooks_sender())->send($id);
        if (($row['state'] ?? '') === 'sent') $counts['sent']++; else $counts['failed']++;
        $out .= $cells($id, $row, $row['doc_hash'] ?? null);
      } catch (quickbooks_rate_limit $e) {
        // QuickBooks' limit is per company: wait as long as it asks, then send the same document.
        array_unshift($queue, $id);
        $wait = $e->retry_after;
      } catch (quickbooks_sync_error $e) {
        $counts['failed']++;
        $out .= $cells($id, null, null);
      }
    }
    if ($queue) {
      $vals = ['step' => 'one', 'queue' => implode(',', $queue)] + $counts;
      $done = $counts['sent'] + $counts['failed'];
      $out .= '<span class="spinner-border spinner-border-sm me-1" role="status"></span>'
        . htmlspecialchars($wait ? sprintf(QUICKBOOKS_SYNC_SEND_WAITING, $wait, $done, $counts['total'])
                                 : sprintf(QUICKBOOKS_SYNC_SEND_PROGRESS, $done + 1, $counts['total']))
        . '<span hx-post="' . quickbooks_sync_send_url() . '" hx-trigger="load' . ($wait ? ' delay:' . $wait . 's' : '') . '"'
        . ' hx-target="#qs-progress" hx-swap="innerHTML" hx-vals="' . htmlspecialchars(json_encode($vals)) . '"></span>';
    } else {
      $out .= htmlspecialchars(sprintf(QUICKBOOKS_SYNC_SEND_DONE, $counts['sent'], $counts['failed']));
    }
    echo $out;
    die;

  case 'release':
  case 'forget':
    if (!$send['can_release']) $fail(ERROR_NO_PERMISSION);
    $id = (int)($_POST['id'] ?? 0);
    $done = $_POST['step'] === 'release' ? quickbooks_sender::release($id) : quickbooks_sender::forget($id);
    $row = quickbooks_sync_doc_row($id);
    $doc_hash = null;
    if ($row) {
      $entry = (new quickbooks_source())->document(quickbooks_sender::DOC_TYPES[(int)$row['journal_id']] ?? 'invoice', $id);
      $doc_hash = $entry ? quickbooks_sync_doc_hash($entry['doc']) : null;
    }
    // The tick box is redrawn out of band; the QuickBooks cell is this request's target.
    echo '<div id="qs-c-' . $id . '" hx-swap-oob="true">' . quickbooks_sync_pick_cell($id, $row, false, $send, false) . '</div>'
      . quickbooks_sync_qb_cell($id, $row, $doc_hash, $send)
      . ($done ? '' : '<br><span class="text-danger small">' . htmlspecialchars(QUICKBOOKS_SYNC_ACTION_STALE) . '</span>');
    die;
}
http_response_code(400);
die;
