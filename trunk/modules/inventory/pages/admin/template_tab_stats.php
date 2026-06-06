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
//  Path: /modules/inventory/pages/admin/template_tab_stats.php
//
?>
<div class="tab-pane fade<?php echo ($active_tab === 'stats' ? ' show active' : ''); ?>" id="tab_stats" role="tabpanel">
<?php if (!empty($install->tables)): ?>
  <fieldset class="mt-2 mb-2" style="font-size:11px; border:1px solid #a6c9e2; border-radius:4px; padding:8px">
    <legend><?php echo TEXT_TABLE_STATS; ?></legend>
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
    echo '  <td>'                     . $result->fields['Name']                                                . '</td>' . chr(10);
    echo '  <td class="text-center">' . $result->fields['Engine']                                             . '</td>' . chr(10);
    echo '  <td class="text-center">' . $result->fields['Rows']                                               . '</td>' . chr(10);
    echo '  <td class="text-center">' . $result->fields['Collation']                                          . '</td>' . chr(10);
    echo '  <td class="text-center">' . ($result->fields['Data_length'] + $result->fields['Index_length'])    . '</td>' . chr(10);
    echo '  <td class="text-center">' . $result->fields['Auto_increment']                                     . '</td>' . chr(10);
    echo '</tr>' . chr(10);
    $odd = !$odd;
  }
?>
        </tbody>
      </table>
  </fieldset>
<?php endif; ?>
</div>
