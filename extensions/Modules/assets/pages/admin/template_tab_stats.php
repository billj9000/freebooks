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
//  Path: /modules/assets/pages/admin/template_tab_stats.php
//
?>
<div class="tab-pane" id="tab_stats">
<?php if (!empty($install->tables)) { ?>
  <table class="table table-sm table-bordered mt-2">
    <thead class="table-dark">
      <tr>
        <th><?php echo TEXT_TABLE_STATS; ?></th>
        <th><?php echo TEXT_TABLE; ?></th>
        <th><?php echo TEXT_ENGINE; ?></th>
        <th><?php echo TEXT_NUM_ROWS; ?></th>
        <th><?php echo TEXT_COLLATION; ?></th>
        <th><?php echo TEXT_SIZE; ?></th>
        <th><?php echo TEXT_NEXT_ID; ?></th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($install->tables as $tablename => $tablesql) {
  $result = $db->Execute("SHOW TABLE STATUS LIKE '" . $tablename . "'"); ?>
      <tr>
        <td><?php echo $result->fields['Name']; ?></td>
        <td class="text-center"><?php echo $result->fields['Engine']; ?></td>
        <td class="text-center"><?php echo $result->fields['Rows']; ?></td>
        <td class="text-center"><?php echo $result->fields['Collation']; ?></td>
        <td class="text-center"><?php echo $result->fields['Data_length'] + $result->fields['Index_length']; ?></td>
        <td class="text-center"><?php echo $result->fields['Auto_increment']; ?></td>
      </tr>
<?php } ?>
    </tbody>
  </table>
<?php } ?>
</div>
