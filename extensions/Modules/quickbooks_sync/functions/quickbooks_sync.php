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
//  Path: /modules/quickbooks_sync/functions/quickbooks_sync.php
//
// Markup the module's pages draw, shared with the htmx requests that redraw parts of them.
//

require_once(DIR_FS_MODULES . 'quickbooks_sync/functions/quickbooks_lock.php');

// The item mapping rows. Each account posts as item[<id>][d] (its item) and item[<id>][f] (its
// item for foreign documents).
function quickbooks_sync_item_rows(array $accounts, bool $can_save): string {
  if (!$accounts) return '<tr><td colspan="3" class="text-muted">' . htmlspecialchars(QUICKBOOKS_SYNC_NONE_USED) . '</td></tr>';
  $disabled = $can_save ? '' : ' disabled';
  $html = '';
  foreach ($accounts as $account) {
    $id = htmlspecialchars($account['id']);
    $description = $account['description'] !== '' ? $account['description'] : QUICKBOOKS_SYNC_NOT_IN_CHART;
    $html .= '<tr><td>' . $id . ' : ' . htmlspecialchars($description) . '</td>'
      . '<td><input type="text" class="form-control form-control-sm" maxlength="255" list="qs-qb-items" name="item[' . $id . '][d]" value="' . htmlspecialchars($account['qb_value']) . '"' . $disabled . '></td>'
      . '<td><input type="text" class="form-control form-control-sm" maxlength="255" list="qs-qb-items" name="item[' . $id . '][f]" value="' . htmlspecialchars($account['qb_foreign']) . '"' . $disabled . '></td></tr>';
  }
  return $html;
}

// One customer name row; blank names make the empty row an added name starts from.
function quickbooks_sync_customer_row(string $name, string $qb_name, bool $can_save): string {
  $disabled = $can_save ? '' : ' disabled';
  return '<tr>'
    . '<td><input type="text" class="form-control form-control-sm" maxlength="255" name="customer_name[]" value="' . htmlspecialchars($name) . '"' . $disabled . '></td>'
    . '<td><input type="text" class="form-control form-control-sm" maxlength="255" name="customer_qb[]" value="' . htmlspecialchars($qb_name) . '"' . $disabled . '></td></tr>';
}

// A document's problems, as the lines of its Status cell: unmapped accounts and tax rates, then its
// warnings. Empty when there is nothing to check.
function quickbooks_sync_problems(array $doc): array {
  $problems = [];
  if ($doc['unmapped_accounts'])  $problems[] = sprintf(QUICKBOOKS_SYNC_STATUS_ACCOUNTS, implode(', ', $doc['unmapped_accounts']));
  if ($doc['unmapped_tax_rates']) $problems[] = sprintf(QUICKBOOKS_SYNC_STATUS_TAX, implode(', ', $doc['unmapped_tax_rates']));
  return array_merge($problems, $doc['warnings']);
}

// The preview table for built documents (quickbooks_source::documents()), newest first, invoices
// and credit memos together; the import files keep date order. With $send
// (quickbooks_sync_send_context()), it also shows where each document stands with QuickBooks, with a
// box to tick it for sending and the Release and Unlink actions.
function quickbooks_sync_preview(array $documents, quickbooks_builder $builder, ?array $send = null): string {
  if (!$documents) return '<div class="alert alert-info py-2 mb-0">' . htmlspecialchars(QUICKBOOKS_SYNC_NO_DOCUMENTS) . '</div>';
  usort($documents, fn($a, $b) => [$b['doc']['date'], $b['id']] <=> [$a['doc']['date'], $a['id']]);
  $tracking = $send ? quickbooks_sync_tracking(array_column($documents, 'id')) : [];
  $to_check = 0;
  $counts = ['invoice' => [], 'credit_memo' => []];
  $rows = '';
  foreach ($documents as $entry) {
    $doc = $entry['doc'];
    $id = (int)$entry['id'];
    $problems = quickbooks_sync_problems($doc);
    $refusal = $builder->file_refusal($doc);
    if ($refusal === null) $counts[$doc['doc_type']][] = $doc;
    if ($problems || $refusal !== null) $to_check++;
    $country = $doc['country'] !== '' ? $doc['country'] : '?';
    if ($doc['is_domestic'] === true)  $country = sprintf(QUICKBOOKS_SYNC_DOMESTIC, $country);
    if ($doc['is_domestic'] === false) $country = sprintf(QUICKBOOKS_SYNC_FOREIGN, $country);
    $notes = $refusal !== null ? array_merge([$refusal], $problems) : $problems;
    if ($notes) {
      $status = '<span class="text-danger">' . implode('<br>', array_map('htmlspecialchars', $notes)) . '</span>';
    } elseif ($doc['rounding_applied']) {
      $status = htmlspecialchars(sprintf(QUICKBOOKS_SYNC_STATUS_ROUNDED, sprintf('%+.2f', $doc['rounding_applied'])));
    } else {
      $status = htmlspecialchars(QUICKBOOKS_SYNC_STATUS_OK);
    }
    $row = $tracking[$id] ?? null;
    $rows .= '<tr' . ($notes ? ' class="table-warning"' : '') . '>'
      . ($send ? '<td><div id="qs-c-' . $id . '">' . quickbooks_sync_pick_cell($id, $row, quickbooks_sync_is_blocked($doc), $send, true) . '</div></td>' : '')
      . '<td>' . htmlspecialchars($doc['doc_type'] === 'invoice' ? QUICKBOOKS_SYNC_TYPE_INVOICE : QUICKBOOKS_SYNC_TYPE_CREDIT) . '</td>'
      . '<td>' . htmlspecialchars($doc['number']) . '</td>'
      . '<td class="text-nowrap">' . htmlspecialchars(gen_locale_date($doc['date'])) . '</td>'
      . '<td>' . htmlspecialchars($doc['customer_name']) . '</td>'
      . '<td class="text-nowrap">' . htmlspecialchars($country) . '</td>'
      . '<td class="text-end text-nowrap">' . htmlspecialchars($doc['currency'] . ' ' . number_format($doc['source_total'], 2)) . '</td>'
      . '<td class="text-end">' . number_format($doc['total_tax'], 2) . '</td>'
      . '<td class="text-end">' . number_format($doc['computed_total'], 2) . '</td>'
      . '<td class="text-end">' . ($doc['rounding_applied'] ? sprintf('%+.2f', $doc['rounding_applied']) : '&ndash;') . '</td>'
      . '<td>' . $status . '</td>'
      . ($send ? '<td><div id="qs-x-' . $id . '">' . quickbooks_sync_qb_cell($id, $row, quickbooks_sync_doc_hash($doc), $send) . '</div></td>' : '')
      . '</tr>';
  }
  $summary = sprintf(QUICKBOOKS_SYNC_SUMMARY, count($documents), $to_check);
  foreach ($counts as $doc_type => $docs) {
    $files = count($builder->file_parts($docs));
    if ($files > 1) $summary .= ' ' . sprintf($doc_type === 'invoice' ? QUICKBOOKS_SYNC_PARTS_INVOICES : QUICKBOOKS_SYNC_PARTS_CREDITS, $files);
  }
  $html = '<p class="mb-2">' . htmlspecialchars($summary) . '</p>'
    . '<div class="table-responsive"><table class="table table-borderless table-hover table-sm mb-0">'
    . '<thead class="table-dark"><tr>'
    . ($send ? '<th></th>' : '')
    . '<th>' . QUICKBOOKS_SYNC_COL_TYPE . '</th><th>' . QUICKBOOKS_SYNC_COL_NUMBER . '</th><th>' . QUICKBOOKS_SYNC_COL_DATE . '</th>'
    . '<th>' . QUICKBOOKS_SYNC_COL_DOC_CUSTOMER . '</th><th>' . QUICKBOOKS_SYNC_COL_COUNTRY . '</th>'
    . '<th class="text-end">' . QUICKBOOKS_SYNC_COL_FB_TOTAL . '</th><th class="text-end">' . QUICKBOOKS_SYNC_COL_VAT . '</th>'
    . '<th class="text-end">' . QUICKBOOKS_SYNC_COL_QB_TOTAL . '</th>'
    . '<th class="text-end">' . QUICKBOOKS_SYNC_COL_ROUNDING . '</th><th>' . QUICKBOOKS_SYNC_COL_STATUS . '</th>'
    . ($send ? '<th>' . QUICKBOOKS_SYNC_COL_QB . '</th>' : '')
    . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
  if ($send && $send['can_send']) {
    $html .= '<div class="mt-2 d-flex align-items-center gap-2">'
      . '<button type="button" class="btn btn-primary btn-sm" hx-post="' . quickbooks_sync_send_url() . '"'
      . ' hx-vals="' . htmlspecialchars(json_encode(['step' => 'start'])) . '" hx-include="#qs-preview [name=\'send_ids[]\']"'
      . ' hx-target="#qs-progress" hx-swap="innerHTML">' . htmlspecialchars(QUICKBOOKS_SYNC_BTN_SEND) . '</button>'
      . '<div id="qs-progress"></div></div>';
  } elseif ($send) {
    $html .= '<p class="text-muted small mt-2 mb-0">' . htmlspecialchars($send['why_not']) . '</p>';
  }
  return $html;
}

// Who may do what with QuickBooks on the export page, for a user at $security_level on QuickBooks
// Sync.
function quickbooks_sync_send_context(int $security_level): array {
  require_once(DIR_FS_MODULES . 'quickbooks_sync/classes/quickbooks_connection.php');
  $connected = (new quickbooks_connection())->is_connected();
  return [
    'can_send'    => $connected && $security_level >= 3,
    'can_release' => $security_level >= 4,
    'why_not'     => $connected ? QUICKBOOKS_SYNC_SEND_NEEDS_EDIT : QUICKBOOKS_SYNC_SEND_NOT_CONNECTED,
  ];
}

function quickbooks_sync_send_url(): string {
  return html_href_link(FILENAME_DEFAULT, 'module=quickbooks_sync&amp;page=ajax&amp;op=send', 'SSL');
}

// The tracking rows for these document ids, keyed by id.
function quickbooks_sync_tracking(array $ids): array {
  global $db;
  $ids = array_filter(array_map('intval', $ids));
  if (!$ids) return [];
  $rows = [];
  $result = $db->Execute("SELECT * FROM " . TABLE_QUICKBOOKS_SYNC_DOC . " WHERE id IN (" . implode(',', $ids) . ")");
  while (!$result->EOF) {
    $rows[(int)$result->fields['id']] = $result->fields;
    $result->MoveNext();
  }
  return $rows;
}

// A document with an unmapped account or tax rate is not sent: QuickBooks would book it wrongly.
function quickbooks_sync_is_blocked(array $doc): bool {
  return (bool)($doc['unmapped_accounts'] || $doc['unmapped_tax_rates']);
}

// The box that ticks a document for sending. Ticked at first for every document that can be sent;
// after a send, left unticked so a second press does not repeat a failure.
function quickbooks_sync_pick_cell(int $id, ?array $row, bool $blocked, array $send, bool $tick): string {
  $can = $send['can_send'] && !$blocked && !($row && quickbooks_sync_is_locked($row));
  if (!$can) return '<input class="form-check-input" type="checkbox" disabled>';
  return '<input class="form-check-input" type="checkbox" name="send_ids[]" value="' . $id . '"' . ($tick ? ' checked' : '') . '>';
}

// Where the document stands with QuickBooks, and the actions open to the user. $doc_hash is the
// document as it would be built now, which shows whether it has changed since it was sent.
function quickbooks_sync_qb_cell(int $id, ?array $row, ?string $doc_hash, array $send): string {
  $state = $row['state'] ?? '';
  if ($state === 'sending' && !quickbooks_sync_is_locked($row)) $state = 'failed';
  $when = !empty($row['sent_at']) ? gen_locale_date(substr($row['sent_at'], 0, 10)) : '';
  $held = ($row['qb_id'] ?? '') !== '';
  switch ($state) {
    case 'sending':
      $html = htmlspecialchars(QUICKBOOKS_SYNC_X_SENDING);
      break;
    case 'sent':
      $html = '<span class="text-success">' . htmlspecialchars(sprintf(QUICKBOOKS_SYNC_X_SENT, $when)) . '</span>';
      if ($doc_hash !== null && $doc_hash !== $row['doc_hash']) $html .= '<br><span class="text-warning-emphasis">' . htmlspecialchars(QUICKBOOKS_SYNC_X_CHANGED) . '</span>';
      break;
    case 'released':
      $html = htmlspecialchars(QUICKBOOKS_SYNC_X_RELEASED);
      break;
    case 'failed':
      $html = '<span class="text-danger">' . htmlspecialchars(sprintf(QUICKBOOKS_SYNC_X_FAILED, (string)$row['last_error'])) . '</span>';
      break;
    default:
      $html = '<span class="text-muted">' . htmlspecialchars(QUICKBOOKS_SYNC_X_NOT_SENT) . '</span>';
  }
  if ($held && $state !== 'sent' && $state !== 'sending') $html .= '<br><span class="small text-muted">' . htmlspecialchars(QUICKBOOKS_SYNC_X_HELD) . '</span>';
  if ($send['can_release'] && $state === 'sent') {
    $html .= ' ' . quickbooks_sync_action_button($id, 'release', QUICKBOOKS_SYNC_BTN_RELEASE, QUICKBOOKS_SYNC_CONFIRM_RELEASE);
  }
  if ($send['can_release'] && $held && ($state === 'released' || $state === 'failed')) {
    $html .= ' ' . quickbooks_sync_action_button($id, 'forget', QUICKBOOKS_SYNC_BTN_FORGET, QUICKBOOKS_SYNC_CONFIRM_FORGET);
  }
  return $html;
}

function quickbooks_sync_action_button(int $id, string $step, string $label, string $confirm): string {
  return '<button type="button" class="btn btn-outline-secondary btn-sm py-0" hx-post="' . quickbooks_sync_send_url() . '"'
    . ' hx-vals="' . htmlspecialchars(json_encode(['step' => $step, 'id' => $id])) . '"'
    . ' hx-confirm="' . htmlspecialchars($confirm) . '" hx-target="#qs-x-' . $id . '" hx-swap="innerHTML">'
    . htmlspecialchars($label) . '</button>';
}

// The From and To dates of a request, as [from, to] in Y-m-d, or null when either is missing or
// invalid or From is after To.
function quickbooks_sync_date_range(array $request): ?array {
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
