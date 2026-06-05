<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2014 PhreeSoft                       |
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
//  Path: /modules/contacts/pages/admin/template_tab_stats.php
//
?>
<div class="tab-pane fade" id="tab_stats" role="tabpanel">
<?php if (!empty($install->tables)): ?>
  <div class="card fb-card mt-2 mb-2">
    <div class="card-header py-1 fb-widget-header"><?php echo TEXT_TABLE_STATS; ?></div>
    <div class="card-body p-0">
      <table class="pkg-table table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th><?php echo TEXT_TABLE; ?></th>
            <th class="text-center"><?php echo TEXT_ENGINE; ?></th>
            <th class="text-center"><?php echo TEXT_NUM_ROWS; ?></th>
            <th class="text-center"><?php echo TEXT_COLLATION; ?></th>
            <th class="text-center"><?php echo TEXT_SIZE; ?></th>
            <th class="text-center"><?php echo TEXT_NEXT_ID; ?></th>
          </tr>
        </thead>
        <tbody>
<?php
  $odd = true;
  foreach ($install->tables as $tablename => $tablesql) {
    $result = $db->Execute("SHOW TABLE STATUS LIKE '" . $tablename . "'");
    echo '<tr class="' . ($odd ? 'odd' : 'even') . '">' . chr(10);
    echo '  <td>'                . $result->fields['Name']                                               . '</td>' . chr(10);
    echo '  <td class="text-center">' . $result->fields['Engine']                                        . '</td>' . chr(10);
    echo '  <td class="text-center">' . $result->fields['Rows']                                          . '</td>' . chr(10);
    echo '  <td class="text-center">' . $result->fields['Collation']                                     . '</td>' . chr(10);
    echo '  <td class="text-center">' . ($result->fields['Data_length'] + $result->fields['Index_length']) . '</td>' . chr(10);
    echo '  <td class="text-center">' . $result->fields['Auto_increment']                                . '</td>' . chr(10);
    echo '</tr>' . chr(10);
    $odd = !$odd;
  }
?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
</div>
