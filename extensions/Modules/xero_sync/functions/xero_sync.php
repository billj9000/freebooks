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
//  Path: /modules/xero_sync/functions/xero_sync.php
//
// Markup the module's pages draw, shared with the htmx requests that redraw parts of them.
//

require_once(DIR_FS_MODULES . 'xero_sync/functions/xero_lock.php');

// The account mapping rows. Each account posts as account[<id>][d] (its Xero code) and
// account[<id>][f] (its foreign code).
function xero_sync_account_rows(array $accounts, bool $can_save): string {
  if (!$accounts) return '<tr><td colspan="3" class="text-muted">' . htmlspecialchars(XERO_SYNC_NONE_USED) . '</td></tr>';
  $disabled = $can_save ? '' : ' disabled';
  $html = '';
  foreach ($accounts as $account) {
    $id = htmlspecialchars($account['id']);
    $description = $account['description'] !== '' ? $account['description'] : XERO_SYNC_NOT_IN_CHART;
    $html .= '<tr><td>' . $id . ' : ' . htmlspecialchars($description) . '</td>'
      . '<td><input type="text" class="form-control form-control-sm" maxlength="255" list="xs-xero-accounts" name="account[' . $id . '][d]" value="' . htmlspecialchars($account['xero_value']) . '"' . $disabled . '></td>'
      . '<td><input type="text" class="form-control form-control-sm" maxlength="255" list="xs-xero-accounts" name="account[' . $id . '][f]" value="' . htmlspecialchars($account['xero_foreign']) . '"' . $disabled . '></td></tr>';
  }
  return $html;
}

// One contact name row; blank names make the empty row an added name starts from.
function xero_sync_contact_row(string $name, string $xero_name, bool $can_save): string {
  $disabled = $can_save ? '' : ' disabled';
  return '<tr>'
    . '<td><input type="text" class="form-control form-control-sm" maxlength="255" name="contact_name[]" value="' . htmlspecialchars($name) . '"' . $disabled . '></td>'
    . '<td><input type="text" class="form-control form-control-sm" maxlength="255" name="contact_xero[]" value="' . htmlspecialchars($xero_name) . '"' . $disabled . '></td></tr>';
}

// A document's problems, as the lines of its Status cell: unmapped accounts and tax rates, then its
// warnings. Empty when there is nothing to check.
function xero_sync_problems(array $doc): array {
  $problems = [];
  if ($doc['unmapped_accounts'])  $problems[] = sprintf(XERO_SYNC_STATUS_ACCOUNTS, implode(', ', $doc['unmapped_accounts']));
  if ($doc['unmapped_tax_rates']) $problems[] = sprintf(XERO_SYNC_STATUS_TAX, implode(', ', $doc['unmapped_tax_rates']));
  return array_merge($problems, $doc['warnings']);
}

// The preview table for a list of built documents (xero_source::documents()). With $send
// (xero_sync_send_context()), it also shows where each document stands with Xero, with a box to
// tick it for sending and the Release and Forget actions.
function xero_sync_preview(array $documents, ?array $send = null): string {
  if (!$documents) return '<div class="alert alert-info py-2 mb-0">' . htmlspecialchars(XERO_SYNC_NO_DOCUMENTS) . '</div>';
  // Newest first, invoices and credit memos together; the CSV file keeps its own order.
  usort($documents, fn($a, $b) => [$b['doc']['date'], $b['id']] <=> [$a['doc']['date'], $a['id']]);
  $tracking = $send ? xero_sync_tracking(array_column($documents, 'id')) : [];
  $to_check = 0;
  $rows = '';
  foreach ($documents as $entry) {
    $doc = $entry['doc'];
    $id = (int)$entry['id'];
    $problems = xero_sync_problems($doc);
    if ($problems) $to_check++;
    $country = $doc['country'] !== '' ? $doc['country'] : '?';
    if ($doc['is_domestic'] === true)  $country = sprintf(XERO_SYNC_DOMESTIC, $country);
    if ($doc['is_domestic'] === false) $country = sprintf(XERO_SYNC_FOREIGN, $country);
    if ($problems) {
      $status = '<span class="text-danger">' . implode('<br>', array_map('htmlspecialchars', $problems)) . '</span>';
    } elseif ($doc['rounding_applied']) {
      $status = htmlspecialchars(sprintf(XERO_SYNC_STATUS_ROUNDED, sprintf('%+.2f', $doc['rounding_applied'])));
    } else {
      $status = htmlspecialchars(XERO_SYNC_STATUS_OK);
    }
    $row = $tracking[$id] ?? null;
    $rows .= '<tr' . ($problems ? ' class="table-warning"' : '') . '>'
      . ($send ? '<td><div id="xs-c-' . $id . '">' . xero_sync_pick_cell($id, $row, xero_sync_is_blocked($doc), $send, true) . '</div></td>' : '')
      . '<td>' . htmlspecialchars($doc['doc_type'] === 'invoice' ? XERO_SYNC_TYPE_INVOICE : XERO_SYNC_TYPE_CREDIT) . '</td>'
      . '<td>' . htmlspecialchars($doc['number']) . '</td>'
      . '<td class="text-nowrap">' . htmlspecialchars(gen_locale_date($doc['date'])) . '</td>'
      . '<td>' . htmlspecialchars($doc['contact_name']) . '</td>'
      . '<td class="text-nowrap">' . htmlspecialchars($country) . '</td>'
      . '<td class="text-end text-nowrap">' . htmlspecialchars($doc['currency'] . ' ' . number_format($doc['source_total'], 2)) . '</td>'
      . '<td class="text-end">' . number_format($doc['computed_total'], 2) . '</td>'
      . '<td class="text-end">' . ($doc['rounding_applied'] ? sprintf('%+.2f', $doc['rounding_applied']) : '&ndash;') . '</td>'
      . '<td>' . $status . '</td>'
      . ($send ? '<td><div id="xs-x-' . $id . '">' . xero_sync_xero_cell($id, $row, xero_sync_doc_hash($doc), $send) . '</div></td>' : '')
      . '</tr>';
  }
  $html = '<p class="mb-2">' . htmlspecialchars(sprintf(XERO_SYNC_SUMMARY, count($documents), $to_check)) . '</p>'
    . '<div class="table-responsive"><table class="table table-borderless table-hover table-sm mb-0">'
    . '<thead class="table-dark"><tr>'
    . ($send ? '<th></th>' : '')
    . '<th>' . XERO_SYNC_COL_TYPE . '</th><th>' . XERO_SYNC_COL_NUMBER . '</th><th>' . XERO_SYNC_COL_DATE . '</th>'
    . '<th>' . XERO_SYNC_COL_CONTACT . '</th><th>' . XERO_SYNC_COL_COUNTRY . '</th>'
    . '<th class="text-end">' . XERO_SYNC_COL_FB_TOTAL . '</th><th class="text-end">' . XERO_SYNC_COL_XERO_TOTAL . '</th>'
    . '<th class="text-end">' . XERO_SYNC_COL_ROUNDING . '</th><th>' . XERO_SYNC_COL_STATUS . '</th>'
    . ($send ? '<th>' . XERO_SYNC_COL_XERO . '</th>' : '')
    . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
  if ($send && $send['can_send']) {
    $html .= '<div class="mt-2 d-flex align-items-center gap-2">'
      . '<button type="button" class="btn btn-primary btn-sm" hx-post="' . xero_sync_send_url() . '"'
      . ' hx-vals="' . htmlspecialchars(json_encode(['step' => 'start'])) . '" hx-include="#xs-preview [name=\'send_ids[]\']"'
      . ' hx-target="#xs-progress" hx-swap="innerHTML">' . htmlspecialchars(XERO_SYNC_BTN_SEND) . '</button>'
      . '<div id="xs-progress"></div></div>';
  } elseif ($send) {
    $html .= '<p class="text-muted small mt-2 mb-0">' . htmlspecialchars($send['why_not']) . '</p>';
  }
  return $html;
}

// Who may do what with Xero on the export page, for a user at $security_level on Xero Sync.
function xero_sync_send_context(int $security_level): array {
  require_once(DIR_FS_MODULES . 'xero_sync/classes/xero_connection.php');
  $connected = (new xero_connection())->is_connected();
  return [
    'can_send'    => $connected && $security_level >= 3,
    'can_release' => $security_level >= 4,
    'why_not'     => $connected ? XERO_SYNC_SEND_NEEDS_EDIT : XERO_SYNC_SEND_NOT_CONNECTED,
  ];
}

function xero_sync_send_url(): string {
  return html_href_link(FILENAME_DEFAULT, 'module=xero_sync&amp;page=ajax&amp;op=send', 'SSL');
}

// The tracking rows for these document ids, keyed by id.
function xero_sync_tracking(array $ids): array {
  global $db;
  $ids = array_filter(array_map('intval', $ids));
  if (!$ids) return [];
  $rows = [];
  $result = $db->Execute("SELECT * FROM " . TABLE_XERO_SYNC_DOC . " WHERE id IN (" . implode(',', $ids) . ")");
  while (!$result->EOF) {
    $rows[(int)$result->fields['id']] = $result->fields;
    $result->MoveNext();
  }
  return $rows;
}

// A document with an unmapped account or tax rate is not sent: Xero would book it wrongly.
function xero_sync_is_blocked(array $doc): bool {
  return (bool)($doc['unmapped_accounts'] || $doc['unmapped_tax_rates']);
}

// The box that ticks a document for sending. Ticked at first for every document that can be sent;
// after a send, left unticked so a second press does not repeat a failure.
function xero_sync_pick_cell(int $id, ?array $row, bool $blocked, array $send, bool $tick): string {
  $can = $send['can_send'] && !$blocked && !($row && xero_sync_is_locked($row));
  if (!$can) return '<input class="form-check-input" type="checkbox" disabled>';
  return '<input class="form-check-input" type="checkbox" name="send_ids[]" value="' . $id . '"' . ($tick ? ' checked' : '') . '>';
}

// Where the document stands with Xero, and the actions open to the user. $doc_hash is the
// document as it would be built now, which shows whether it has changed since it was sent.
function xero_sync_xero_cell(int $id, ?array $row, ?string $doc_hash, array $send): string {
  $state = $row['state'] ?? '';
  if ($state === 'sending' && !xero_sync_is_locked($row)) $state = 'failed';
  $when = !empty($row['sent_at']) ? gen_locale_date(substr($row['sent_at'], 0, 10)) : '';
  $held = ($row['xero_id'] ?? '') !== '';
  switch ($state) {
    case 'sending':
      $html = htmlspecialchars(XERO_SYNC_X_SENDING);
      break;
    case 'sent':
      $html = '<span class="text-success">' . htmlspecialchars(sprintf(XERO_SYNC_X_SENT, $when)) . '</span>';
      if ($doc_hash !== null && $doc_hash !== $row['doc_hash']) $html .= '<br><span class="text-warning-emphasis">' . htmlspecialchars(XERO_SYNC_X_CHANGED) . '</span>';
      break;
    case 'released':
      $html = htmlspecialchars(XERO_SYNC_X_RELEASED);
      break;
    case 'failed':
      $html = '<span class="text-danger">' . htmlspecialchars(sprintf(XERO_SYNC_X_FAILED, (string)$row['last_error'])) . '</span>';
      break;
    default:
      $html = '<span class="text-muted">' . htmlspecialchars(XERO_SYNC_X_NOT_SENT) . '</span>';
  }
  if ($held && $state !== 'sent' && $state !== 'sending') $html .= '<br><span class="small text-muted">' . htmlspecialchars(XERO_SYNC_X_HELD) . '</span>';
  if ($send['can_release'] && $state === 'sent') {
    $html .= ' ' . xero_sync_action_button($id, 'release', XERO_SYNC_BTN_RELEASE, XERO_SYNC_CONFIRM_RELEASE);
  }
  if ($send['can_release'] && $held && ($state === 'released' || $state === 'failed')) {
    $html .= ' ' . xero_sync_action_button($id, 'forget', XERO_SYNC_BTN_FORGET, XERO_SYNC_CONFIRM_FORGET);
  }
  return $html;
}

function xero_sync_action_button(int $id, string $step, string $label, string $confirm): string {
  return '<button type="button" class="btn btn-outline-secondary btn-sm py-0" hx-post="' . xero_sync_send_url() . '"'
    . ' hx-vals="' . htmlspecialchars(json_encode(['step' => $step, 'id' => $id])) . '"'
    . ' hx-confirm="' . htmlspecialchars($confirm) . '" hx-target="#xs-x-' . $id . '" hx-swap="innerHTML">'
    . htmlspecialchars($label) . '</button>';
}

// The From and To dates of a request, as [from, to] in Y-m-d, or null when either is missing or
// invalid or From is after To.
function xero_sync_date_range(array $request): ?array {
  $dates = [];
  foreach (['date_from', 'date_to'] as $key) {
    $value = (string)($request[$key] ?? '');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))) return null;
    $dates[] = $date->format('Y-m-d');
  }
  return $dates[0] <= $dates[1] ? $dates : null;
}
