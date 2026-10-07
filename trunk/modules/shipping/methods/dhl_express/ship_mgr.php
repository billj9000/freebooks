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
//  Path: /modules/shipping/methods/dhl_express/ship_mgr.php
//
load_method_language(DIR_FS_MODULES . 'shipping/methods/', 'dhl_express');
require_once(DIR_FS_MODULES . 'shipping/methods/dhl_express/dhl_express.php');
require_once(DIR_FS_MODULES . 'shipping/functions/shipping.php');
shipping_ensure_schema();
$dhl_popup = 'width=820,height=720,resizable=1,scrollbars=1,top=50,left=50';
?>
<h1><?php echo MODULE_SHIPPING_DHL_EXPRESS_TEXT_TITLE; ?></h1>
<table class="table table-sm table-borderless mb-2 w-100">
  <tr>
    <td><?php echo html_button_field('ship_dhl_express', SHIPPING_SHIP_PACKAGE,
      'onclick="window.open(\'index.php?module=shipping&amp;page=popup_label_mgr&amp;method=dhl_express\',\'popup_label_mgr\',\'' . $dhl_popup . '\')"'); ?>
    </td>
    <td><?php echo html_button_field('pickup_dhl_express', DHL_PICKUP_BOOK_BTN,
      'onclick="window.open(\'index.php?module=shipping&amp;page=dhl_pickup&amp;date=' . $date . '\',\'dhl_pickup\',\'' . $dhl_popup . '\')"'); ?>
    </td>
  </tr>
</table>
<table class="table table-sm table-hover table-borderless mb-2 w-100">
  <thead class="table-dark">
    <tr>
      <th colspan="6"><?php echo MODULE_SHIPPING_DHL_EXPRESS_TEXT_TITLE . ' &mdash; ' . gen_locale_date($date); ?></th>
    </tr>
    <tr>
      <th><?php echo SHIPPING_TEXT_SHIPMENT_ID;  ?></th>
      <th><?php echo SHIPPING_TEXT_REFERENCE_ID; ?></th>
      <th><?php echo SHIPPING_TEXT_SERVICE;      ?></th>
      <th><?php echo SHIPPING_TEXT_TRACKING_NUM; ?></th>
      <th><?php echo DHL_PICKUP_PARCELS;         ?></th>
      <th><?php echo TEXT_ACTION;                ?></th>
    </tr>
  </thead>
  <tbody>
<?php
$rows = $db->Execute("SELECT * FROM " . TABLE_SHIPPING_LOG
  . " WHERE carrier = 'dhl_express' AND ship_date LIKE '" . db_input($date) . "%'"
  . " ORDER BY ship_date DESC");
if ($rows->RecordCount() > 0) {
  while (!$rows->EOF) {
    $f       = $rows->fields;
    $svcName = defined('dhl_express_' . $f['method']) ? constant('dhl_express_' . $f['method']) : $f['method'];
    $pkgData = json_decode((string)($f['package_data'] ?? ''), true);
    $parcels = '';
    if (!empty($pkgData['packages'])) {
      $kg = 0;
      foreach ($pkgData['packages'] as $pkg) $kg += (float)$pkg['weight'];
      $parcels = count($pkgData['packages']) . ' / ' . round($kg, 2) . ' kg';
    }
    echo '  <tr>' . chr(10);
    echo '    <td align="center">' . (int)$f['shipment_id'] . '</td>' . chr(10);
    echo '    <td align="center">' . htmlspecialchars($f['ref_id']) . '</td>' . chr(10);
    echo '    <td align="center">' . htmlspecialchars($svcName) . '</td>' . chr(10);
    echo '    <td align="center"><a target="_blank" href="' . dhl_express_TRACKING_URL . urlencode($f['tracking_id']) . '">'
       . htmlspecialchars($f['tracking_id']) . '</a></td>' . chr(10);
    echo '    <td align="center">' . htmlspecialchars($parcels) . '</td>' . chr(10);
    echo '    <td align="right" nowrap="nowrap">';
    echo html_icon('actions/document-print.png', TEXT_PRINT, 'small',
      'onclick="window.open(\'index.php?module=shipping&amp;page=popup_label_mgr&amp;action=view&amp;method=dhl_express&amp;date='
      . $date . '&amp;labels=' . urlencode($f['tracking_id']) . '\',\'label_mgr\',\'' . $dhl_popup . '\')"');
    echo '</td>' . chr(10);
    echo '  </tr>' . chr(10);
    $rows->MoveNext();
  }
} else {
  echo '  <tr><td align="center" colspan="6">' . SHIPPING_NO_SHIPMENTS . '</td></tr>' . chr(10);
}
?>
  </tbody>
</table>
<table class="table table-sm table-hover table-borderless mb-2 w-100">
  <thead class="table-dark">
    <tr><th colspan="4"><?php echo DHL_PICKUP_BOOKED_HEADING; ?></th></tr>
    <tr>
      <th><?php echo DHL_PICKUP_CONFIRMATION; ?></th>
      <th><?php echo DHL_PICKUP_READY . ' &ndash; ' . DHL_PICKUP_CLOSE; ?></th>
      <th><?php echo DHL_PICKUP_LOCATION; ?></th>
      <th><?php echo TEXT_STATUS; ?></th>
    </tr>
  </thead>
  <tbody>
<?php
$pickups = $db->Execute("SELECT * FROM " . TABLE_SHIPPING_PICKUPS
  . " WHERE carrier = 'dhl_express' AND pickup_date = '" . db_input($date) . "' ORDER BY id");
if ($pickups->EOF) {
  echo '  <tr><td align="center" colspan="4">' . DHL_PICKUP_NONE . '</td></tr>' . chr(10);
}
while (!$pickups->EOF) {
  $f = $pickups->fields;
  echo '  <tr>' . chr(10);
  echo '    <td><code>' . htmlspecialchars($f['confirmation']) . '</code></td>' . chr(10);
  echo '    <td>' . htmlspecialchars($f['ready_time'] . ($f['close_time'] !== '' ? ' – ' . $f['close_time'] : '')) . '</td>' . chr(10);
  echo '    <td>' . htmlspecialchars($f['location']) . '</td>' . chr(10);
  echo '    <td>' . ($f['status'] === 'booked' ? DHL_PICKUP_STATUS_BOOKED : DHL_PICKUP_STATUS_CANCELLED) . '</td>' . chr(10);
  echo '  </tr>' . chr(10);
  $pickups->MoveNext();
}
?>
  </tbody>
</table>
