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
// Table rows the settings page draws, shared with the htmx requests that redraw them.
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
