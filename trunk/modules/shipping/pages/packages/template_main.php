<?php
//  Path: /modules/shipping/pages/packages/template_main.php

$vol_divisor = defined('SHIPPING_VOL_WEIGHT_DIVISOR') ? (float)SHIPPING_VOL_WEIGHT_DIVISOR : 5000;
$wt_labels  = ['LBS' => 'lb', 'KGS' => 'Kg'];
$dim_labels = ['IN'  => 'in', 'CM'  => 'cm'];
$wt_unit    = defined('SHIPPING_DEFAULT_WEIGHT_UNIT')  ? ($wt_labels[SHIPPING_DEFAULT_WEIGHT_UNIT]   ?? SHIPPING_DEFAULT_WEIGHT_UNIT)  : '';
$dim_unit   = defined('SHIPPING_DEFAULT_PKG_DIM_UNIT') ? ($dim_labels[SHIPPING_DEFAULT_PKG_DIM_UNIT] ?? SHIPPING_DEFAULT_PKG_DIM_UNIT) : '';
$curr_sym   = trim($currencies->currencies[DEFAULT_CURRENCY]['symbol_left'] . $currencies->currencies[DEFAULT_CURRENCY]['symbol_right']);

$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=shipping&page=packages&list=1', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']  = false;
$toolbar->icon_list['save']['show']  = false;
$toolbar->icon_list['print']['show'] = false;
if ($security_level > 1) {
  $toolbar->add_icon('new', 'onclick="submitToDo(\'new\')"', $order = 10);
}
if ($security_level > 3 && $pkgID) {
  $toolbar->icon_list['delete']['params'] = 'onclick="submitToDo(\'delete\')"';
} else {
  $toolbar->icon_list['delete']['show'] = false;
}
$toolbar->add_help('09.00.00');
echo $toolbar->build_toolbar();
?>
<h1><?php echo SHPKG_HEADING_TITLE; ?></h1>

<div style="height:19px">
  <?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . SHPKG_HEADING_TITLE); ?>
  <div style="float:right"><?php echo $query_split->display_links(); ?></div>
</div>

<?php $filter_base = html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(['carrier_filter', 'list']), 'SSL'); ?>
<div style="margin-bottom:6px">
  <?php echo SHPKG_TEXT_CARRIER; ?>:&nbsp;
  <select onchange="location.href='<?php echo $filter_base; ?>&list=1&carrier_filter='+encodeURIComponent(this.value)">
  <?php foreach ($carrier_list as $c) { ?>
    <option value="<?php echo htmlspecialchars($c['id']); ?>"<?php echo ($carrier_filter === $c['id']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($c['text']); ?></option>
  <?php } ?>
  </select>
</div>

<!-- ── List ──────────────────────────────────────────────────────────── -->
<table class="ui-widget" style="border-collapse:collapse;width:100%">
  <thead class="ui-widget-header">
    <tr>
      <th style="padding:4px 6px"><?php echo SHPKG_TEXT_CODE; ?></th>
      <th style="padding:4px 6px"><?php echo SHPKG_TEXT_NAME; ?></th>
      <th style="padding:4px 6px"><?php echo SHPKG_TEXT_CARRIER; ?></th>
      <th style="padding:4px 6px;text-align:right"><?php echo SHPKG_TEXT_WEIGHT; ?> (<?php echo $wt_unit; ?>)</th>
      <th style="padding:4px 6px;text-align:right"><?php echo SHPKG_TEXT_MAX_WEIGHT; ?> (<?php echo $wt_unit; ?>)</th>
      <th style="padding:4px 6px;text-align:center"><?php echo SHPKG_TEXT_EXT_DIMS; ?> (<?php echo $dim_unit; ?>)</th>
      <th style="padding:4px 6px;text-align:right"><?php echo SHPKG_TEXT_COST; ?> (<?php echo $curr_sym; ?>)</th>
      <th style="padding:4px 6px;text-align:center"><?php echo TEXT_INACTIVE; ?></th>
      <th style="padding:4px 6px"></th>
    </tr>
  </thead>
  <tbody class="ui-widget-content">
  <?php
  $odd = true;
  while (!$query_result->EOF) {
    $row = $query_result->fields;
    $sel = ($pkgID == $row['id']) ? ' class="ui-state-highlight"' : (' class="' . ($odd ? 'odd' : 'even') . '"');
    $odd = !$odd;
  ?>
    <tr<?php echo $sel; ?> style="cursor:pointer">
      <td style="padding:3px 6px" onclick="submitSeq(<?php echo $row['id']; ?>, 'edit')"><?php echo htmlspecialchars($row['package_code']); ?></td>
      <td style="padding:3px 6px" onclick="submitSeq(<?php echo $row['id']; ?>, 'edit')"><?php echo htmlspecialchars($row['name']); ?></td>
      <td style="padding:3px 6px" onclick="submitSeq(<?php echo $row['id']; ?>, 'edit')"><?php echo htmlspecialchars($carrier_map[$row['carrier']] ?? $row['carrier']); ?></td>
      <td style="padding:3px 6px;text-align:right" onclick="submitSeq(<?php echo $row['id']; ?>, 'edit')"><?php echo $row['weight'] > 0 ? number_format((float)$row['weight'], 1) : ''; ?></td>
      <td style="padding:3px 6px;text-align:right" onclick="submitSeq(<?php echo $row['id']; ?>, 'edit')"><?php echo $row['max_weight'] > 0 ? number_format((float)$row['max_weight'], 1) : ''; ?></td>
      <td style="padding:3px 6px;text-align:center" onclick="submitSeq(<?php echo $row['id']; ?>, 'edit')"><?php
        echo number_format((float)$row['ext_length'], 1) . ' &times; ' . number_format((float)$row['ext_width'], 1) . ' &times; ' . number_format((float)$row['ext_height'], 1);
      ?></td>
      <td style="padding:3px 6px;text-align:right" onclick="submitSeq(<?php echo $row['id']; ?>, 'edit')"><?php echo $currencies->format($row['cost'], false); ?></td>
      <td style="padding:3px 6px;text-align:center" onclick="submitSeq(<?php echo $row['id']; ?>, 'edit')"><?php echo $row['inactive'] == '1' ? TEXT_YES : ''; ?></td>
      <td style="padding:3px 6px;text-align:center">
        <?php if ($security_level > 3) echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="if (confirm(\'' . TEXT_DELETE_ENTRY . '\')) submitSeq(' . $row['id'] . ', \'delete\')"'); ?>
      </td>
    </tr>
  <?php
    $query_result->MoveNext();
  }
  ?>
  </tbody>
</table>

<!-- ── Edit dialog — form lives here so jQuery UI's DOM move keeps it intact -->
<div id="edit-dialog" style="display:none">
<?php
echo html_form('shippkg', FILENAME_DEFAULT, gen_get_all_get_params(['action', 'pkgID']));
echo html_hidden_field('action', '');
echo html_hidden_field('pkgID',  $pkgID ?: '');

$v = $selected_pkg ?: [];
$fv = function($k, $default = '') use ($v) {
  if ($_REQUEST['action'] === 'new') return $default;
  if (in_array($_REQUEST['action'], ['save', 'update']) && isset($_POST[$k])) {
    return htmlspecialchars((string)$_POST[$k]);
  }
  return htmlspecialchars((string)($v[$k] ?? $default));
};
?>
<table style="border-collapse:collapse;width:100%">
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_CODE; ?>&nbsp;*</td>
    <td><?php echo html_input_field('package_code', $fv('package_code'), 'maxlength="20" style="width:180px"'); ?></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_NAME; ?>&nbsp;*</td>
    <td><?php echo html_input_field('name', $fv('name'), 'maxlength="64" style="width:200px"'); ?></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_DESCRIPTION; ?></td>
    <td><?php echo html_input_field('description', $fv('description'), 'maxlength="255" style="width:200px"'); ?></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_CARRIER; ?></td>
    <td><?php echo html_pull_down_menu('carrier', $carrier_list, $fv('carrier', ''), ''); ?></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_WEIGHT; ?> (<?php echo $wt_unit; ?>)</td>
    <td><?php echo html_input_field('weight', $fv('weight', '0'), 'style="width:80px;text-align:right"'); ?></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_MAX_WEIGHT; ?> (<?php echo $wt_unit; ?>)</td>
    <td><?php echo html_input_field('max_weight', $fv('max_weight', '0'), 'style="width:80px;text-align:right"'); ?></td>
  </tr>
  <tr>
    <td colspan="2" class="fieldLabel" style="padding-top:6px"><strong><?php echo SHPKG_TEXT_EXTERNAL; ?> (<?php echo $dim_unit; ?>)</strong></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_EXT_LENGTH; ?></td>
    <td><?php echo html_input_field('ext_length', $fv('ext_length', '0'), 'id="ext_length" style="width:80px;text-align:right"'); ?></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_EXT_WIDTH; ?></td>
    <td><?php echo html_input_field('ext_width',  $fv('ext_width',  '0'), 'id="ext_width"  style="width:80px;text-align:right"'); ?></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_EXT_HEIGHT; ?></td>
    <td><?php echo html_input_field('ext_height', $fv('ext_height', '0'), 'id="ext_height" style="width:80px;text-align:right"'); ?></td>
  </tr>
  <tr>
    <td colspan="2" class="fieldLabel" style="padding-top:6px"><strong><?php echo SHPKG_TEXT_INTERNAL; ?> (<?php echo $dim_unit; ?>)</strong></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_INT_LENGTH; ?></td>
    <td><?php echo html_input_field('int_length', $fv('int_length', '0'), 'id="int_length" style="width:80px;text-align:right"'); ?></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_INT_WIDTH; ?></td>
    <td><?php echo html_input_field('int_width',  $fv('int_width',  '0'), 'id="int_width"  style="width:80px;text-align:right"'); ?></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_INT_HEIGHT; ?></td>
    <td><?php echo html_input_field('int_height', $fv('int_height', '0'), 'id="int_height" style="width:80px;text-align:right"'); ?></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_VOL_WEIGHT; ?> (<?php echo $wt_unit; ?>)</td>
    <td><span id="vol_weight">—</span></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_COST; ?></td>
    <td><?php echo html_input_field('cost', $fv('cost', '0.00'), 'style="width:80px;text-align:right"'); ?></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_SORT_ORDER; ?></td>
    <td><?php echo html_input_field('sort_order', $fv('sort_order', '0'), 'style="width:60px;text-align:right"'); ?></td>
  </tr>
  <tr>
    <td class="fieldLabel"><?php echo SHPKG_TEXT_INACTIVE; ?></td>
    <td><?php echo html_checkbox_field('inactive', '1', ($v['inactive'] ?? '0') == '1'); ?></td>
  </tr>
</table>
</form>
</div><!-- /edit-dialog -->
