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
//  Path: /modules/work_orders/pages/builder/template_tab_hist.php
//
if ($id) {
  $result = $db->Execute("select wo_num, qty, post_date, close_date from " . TABLE_WO_JOURNAL_MAIN . "
    where wo_id = " . $id . " order by post_date desc limit 20");
}
?>
<div class="tab-pane" id="tab_history">
  <div class="fb-fieldset mb-2">
    <div class="fb-fieldset-legend"><?php echo TEXT_HISTORY; ?></div>
    <table class="table table-sm table-bordered" style="width:400px;margin:auto">
     <thead class="table-dark">
      <tr><th colspan="4"><?php echo sprintf(TEXT_WO_HISTORY, LIMIT_HISTORY_RESULTS); ?></th></tr>
      <tr>
        <th><?php echo TEXT_WO_ID; ?></th>
        <th><?php echo TEXT_QUANTITY; ?></th>
        <th><?php echo TEXT_POST_DATE; ?></th>
        <th><?php echo TEXT_CLOSE_DATE; ?></th>
      </tr>
     </thead>
     <tbody>
<?php
  if ($result->fields) {
    $odd = true;
    while (!$result->EOF) {
      echo '<tr class="'.($odd?"odd":"even").'">' . chr(10);
      echo '  <td align="center">' . $result->fields['wo_num'] . '</td>' . chr(10);
      echo '  <td align="center">' . $result->fields['qty'] . '</td>' . chr(10);
      echo '  <td align="center">' . gen_locale_date($result->fields['post_date']) . '</td>' . chr(10);
      echo '  <td align="center">' . gen_locale_date($result->fields['close_date']) . '</td>' . chr(10);
      echo '</tr>' . chr(10);
      $odd = !$odd;
      $result->MoveNext();
    }
  } else {
    echo '<tr><td align="center" colspan="4">' . ACT_NO_RESULTS . '</td></tr>';
  }
?>
     </tbody>
    </table>
  </div>
</div>
