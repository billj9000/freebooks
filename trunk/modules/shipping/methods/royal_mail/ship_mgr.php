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
//  Path: /modules/shipping/methods/royal_mail/ship_mgr.php
//
load_method_language(DIR_FS_MODULES . 'shipping/methods/', 'royal_mail');
define('RM_TRACKING_URL', 'https://www.royalmail.com/track-your-item#/tracking-results/');
?>
<h1><?php echo MODULE_SHIPPING_ROYAL_MAIL_TEXT_TITLE; ?></h1>
<table class="ui-widget" style="border-style:none;width:100%">
  <tr>
    <td><?php echo html_button_field('ship_royal_mail', SHIPPING_SHIP_PACKAGE,
      'onclick="window.open(\'index.php?module=shipping&amp;page=popup_label_mgr&amp;method=royal_mail\',\'popup_label_mgr\',\'width=820,height=720,resizable=1,scrollbars=1,top=50,left=50\')"'); ?>
    </td>
    <td><?php echo html_button_field('manifest_royal_mail', RM_MANIFEST_OPEN_BTN,
      'onclick="window.open(\'index.php?module=shipping&amp;page=rm_manifest\',\'rm_manifest\',\'width=700,height=600,resizable=1,scrollbars=1,top=80,left=100\')"'); ?>
    </td>
  </tr>
</table>
<table class="ui-widget" style="border-collapse:collapse;width:100%">
  <thead class="ui-widget-header">
    <tr>
      <th colspan="7"><?php echo MODULE_SHIPPING_ROYAL_MAIL_TEXT_TITLE . ' &mdash; ' . gen_locale_date($date); ?></th>
    </tr>
    <tr>
      <th><?php echo SHIPPING_TEXT_SHIPMENT_ID;  ?></th>
      <th><?php echo SHIPPING_TEXT_REFERENCE_ID; ?></th>
      <th><?php echo SHIPPING_TEXT_SERVICE;      ?></th>
      <th><?php echo SHIPPING_TEXT_TRACKING_NUM; ?></th>
      <th><?php echo RM_TEXT_RECIPIENT;               ?></th>
      <th><?php echo SHIPPING_TEXT_COST;         ?></th>
      <th><?php echo TEXT_ACTION;               ?></th>
    </tr>
  </thead>
  <tbody class="ui-widget-content">
<?php
$rows = $db->Execute("SELECT * FROM " . TABLE_SHIPPING_LOG
  . " WHERE carrier = 'royal_mail' AND ship_date LIKE '" . db_input($date) . "%'"
  . " ORDER BY ship_date DESC");
if ($rows->RecordCount() > 0) {
  $odd = true;
  while (!$rows->EOF) {
    $f       = $rows->fields;
    $svcCode = $f['method'];
    $svcName = defined('royal_mail_' . $svcCode) ? constant('royal_mail_' . $svcCode) : $svcCode;
    echo '  <tr class="' . ($odd ? 'odd' : 'even') . '">' . chr(10);
    echo '    <td align="center">' . $f['shipment_id'] . '</td>' . chr(10);
    echo '    <td align="center">' . htmlspecialchars($f['ref_id']) . '</td>' . chr(10);
    echo '    <td align="center">' . htmlspecialchars($svcName) . '</td>' . chr(10);
    echo '    <td align="center"><a target="_blank" href="' . RM_TRACKING_URL . urlencode($f['tracking_id']) . '">'
       . htmlspecialchars($f['tracking_id']) . '</a></td>' . chr(10);
    echo '    <td>' . htmlspecialchars($f['recipient_name']) . '</td>' . chr(10);
    echo '    <td align="right">' . ($f['cost'] ? $currencies->format_full($f['cost']) : '&nbsp;') . '</td>' . chr(10);
    echo '    <td align="right" nowrap="nowrap">';
    echo html_icon('actions/document-print.png', TEXT_PRINT, 'small',
      'onclick="window.open(\'index.php?module=shipping&amp;page=popup_label_mgr&amp;action=view&amp;method=royal_mail&amp;date='
      . $date . '&amp;labels=' . urlencode($f['tracking_id'])
      . '\',\'label_mgr\',\'width=820,height=720,resizable=1,scrollbars=1,top=50,left=50\')"') . chr(10);
    echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small',
      'onclick="if(confirm(\'' . SHIPPING_DELETE_CONFIRM . '\')) window.open(\'index.php?module=shipping&amp;page=popup_label_mgr&amp;method=royal_mail&amp;sID='
      . $f['shipment_id'] . '&amp;action=delete\',\'popup_label_mgr\',\'width=820,height=720,resizable=1,scrollbars=1,top=50,left=50\')"') . chr(10);
    echo '    </td>';
    echo '  </tr>' . chr(10);
    $odd = !$odd;
    $rows->MoveNext();
  }
} else {
  echo '  <tr><td align="center" colspan="7">' . SHIPPING_NO_SHIPMENTS . '</td></tr>' . chr(10);
}
?>
  </tbody>
</table>
