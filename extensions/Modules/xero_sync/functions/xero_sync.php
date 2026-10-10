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
      . '<td><input type="text" class="form-control form-control-sm" maxlength="255" name="account[' . $id . '][d]" value="' . htmlspecialchars($account['xero_value']) . '"' . $disabled . '></td>'
      . '<td><input type="text" class="form-control form-control-sm" maxlength="255" name="account[' . $id . '][f]" value="' . htmlspecialchars($account['xero_foreign']) . '"' . $disabled . '></td></tr>';
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

// The preview table for a list of built documents (xero_source::documents()).
function xero_sync_preview(array $documents): string {
  if (!$documents) return '<div class="alert alert-info py-2 mb-0">' . htmlspecialchars(XERO_SYNC_NO_DOCUMENTS) . '</div>';
  $to_check = 0;
  $rows = '';
  foreach ($documents as $entry) {
    $doc = $entry['doc'];
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
    $rows .= '<tr' . ($problems ? ' class="table-warning"' : '') . '>'
      . '<td>' . htmlspecialchars($doc['doc_type'] === 'invoice' ? XERO_SYNC_TYPE_INVOICE : XERO_SYNC_TYPE_CREDIT) . '</td>'
      . '<td>' . htmlspecialchars($doc['number']) . '</td>'
      . '<td class="text-nowrap">' . htmlspecialchars(gen_locale_date($doc['date'])) . '</td>'
      . '<td>' . htmlspecialchars($doc['contact_name']) . '</td>'
      . '<td class="text-nowrap">' . htmlspecialchars($country) . '</td>'
      . '<td class="text-end text-nowrap">' . htmlspecialchars($doc['currency'] . ' ' . number_format($doc['source_total'], 2)) . '</td>'
      . '<td class="text-end">' . number_format($doc['computed_total'], 2) . '</td>'
      . '<td class="text-end">' . ($doc['rounding_applied'] ? sprintf('%+.2f', $doc['rounding_applied']) : '&ndash;') . '</td>'
      . '<td>' . $status . '</td></tr>';
  }
  return '<p class="mb-2">' . htmlspecialchars(sprintf(XERO_SYNC_SUMMARY, count($documents), $to_check)) . '</p>'
    . '<div class="table-responsive"><table class="table table-borderless table-hover table-sm mb-0">'
    . '<thead class="table-dark"><tr>'
    . '<th>' . XERO_SYNC_COL_TYPE . '</th><th>' . XERO_SYNC_COL_NUMBER . '</th><th>' . XERO_SYNC_COL_DATE . '</th>'
    . '<th>' . XERO_SYNC_COL_CONTACT . '</th><th>' . XERO_SYNC_COL_COUNTRY . '</th>'
    . '<th class="text-end">' . XERO_SYNC_COL_FB_TOTAL . '</th><th class="text-end">' . XERO_SYNC_COL_XERO_TOTAL . '</th>'
    . '<th class="text-end">' . XERO_SYNC_COL_ROUNDING . '</th><th>' . XERO_SYNC_COL_STATUS . '</th>'
    . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
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
