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
      . '<td><input type="text" class="form-control form-control-sm" maxlength="255" name="item[' . $id . '][d]" value="' . htmlspecialchars($account['qb_value']) . '"' . $disabled . '></td>'
      . '<td><input type="text" class="form-control form-control-sm" maxlength="255" name="item[' . $id . '][f]" value="' . htmlspecialchars($account['qb_foreign']) . '"' . $disabled . '></td></tr>';
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
// and credit memos together; the import files keep date order.
function quickbooks_sync_preview(array $documents, quickbooks_builder $builder): string {
  if (!$documents) return '<div class="alert alert-info py-2 mb-0">' . htmlspecialchars(QUICKBOOKS_SYNC_NO_DOCUMENTS) . '</div>';
  usort($documents, fn($a, $b) => [$b['doc']['date'], $b['id']] <=> [$a['doc']['date'], $a['id']]);
  $to_check = 0;
  $counts = ['invoice' => [], 'credit_memo' => []];
  $rows = '';
  foreach ($documents as $entry) {
    $doc = $entry['doc'];
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
    $rows .= '<tr' . ($notes ? ' class="table-warning"' : '') . '>'
      . '<td>' . htmlspecialchars($doc['doc_type'] === 'invoice' ? QUICKBOOKS_SYNC_TYPE_INVOICE : QUICKBOOKS_SYNC_TYPE_CREDIT) . '</td>'
      . '<td>' . htmlspecialchars($doc['number']) . '</td>'
      . '<td class="text-nowrap">' . htmlspecialchars(gen_locale_date($doc['date'])) . '</td>'
      . '<td>' . htmlspecialchars($doc['customer_name']) . '</td>'
      . '<td class="text-nowrap">' . htmlspecialchars($country) . '</td>'
      . '<td class="text-end text-nowrap">' . htmlspecialchars($doc['currency'] . ' ' . number_format($doc['source_total'], 2)) . '</td>'
      . '<td class="text-end">' . number_format($doc['total_tax'], 2) . '</td>'
      . '<td class="text-end">' . number_format($doc['computed_total'], 2) . '</td>'
      . '<td class="text-end">' . ($doc['rounding_applied'] ? sprintf('%+.2f', $doc['rounding_applied']) : '&ndash;') . '</td>'
      . '<td>' . $status . '</td></tr>';
  }
  $summary = sprintf(QUICKBOOKS_SYNC_SUMMARY, count($documents), $to_check);
  foreach ($counts as $doc_type => $docs) {
    $files = count($builder->file_parts($docs));
    if ($files > 1) $summary .= ' ' . sprintf($doc_type === 'invoice' ? QUICKBOOKS_SYNC_PARTS_INVOICES : QUICKBOOKS_SYNC_PARTS_CREDITS, $files);
  }
  return '<p class="mb-2">' . htmlspecialchars($summary) . '</p>'
    . '<div class="table-responsive"><table class="table table-borderless table-hover table-sm mb-0">'
    . '<thead class="table-dark"><tr>'
    . '<th>' . QUICKBOOKS_SYNC_COL_TYPE . '</th><th>' . QUICKBOOKS_SYNC_COL_NUMBER . '</th><th>' . QUICKBOOKS_SYNC_COL_DATE . '</th>'
    . '<th>' . QUICKBOOKS_SYNC_COL_DOC_CUSTOMER . '</th><th>' . QUICKBOOKS_SYNC_COL_COUNTRY . '</th>'
    . '<th class="text-end">' . QUICKBOOKS_SYNC_COL_FB_TOTAL . '</th><th class="text-end">' . QUICKBOOKS_SYNC_COL_VAT . '</th>'
    . '<th class="text-end">' . QUICKBOOKS_SYNC_COL_QB_TOTAL . '</th>'
    . '<th class="text-end">' . QUICKBOOKS_SYNC_COL_ROUNDING . '</th><th>' . QUICKBOOKS_SYNC_COL_STATUS . '</th>'
    . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
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
