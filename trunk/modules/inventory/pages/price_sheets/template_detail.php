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
//  Path: /modules/inventory/pages/price_sheets/template_detail.php
//
echo html_form('pricesheet', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('id', $id);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
if ($security_level > 1) {
  $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'' . (($_REQUEST['action'] == 'new') ? 'save' : 'update') . '\')"';
} else {
  $toolbar->icon_list['save']['show']   = false;
}
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.04.06');
echo $toolbar->build_toolbar();
?>
<!-- Settings card -->
<div class="card fb-card mb-2 mx-auto" style="width:fit-content">
  <div class="card-header py-1 fb-widget-header">
    <?php echo PAGE_TITLE; ?><?php if (!empty($sheet_name)) echo htmlspecialchars($sheet_name) . ' (' . TEXT_REVISION . ' ' . $revision . ')'; ?>
  </div>
  <div class="card-body py-2 px-3" style="font-size:11px">
    <div class="row g-2 align-items-center mb-2">
      <div class="col-auto"><?php echo PRICE_SHEET_NAME; ?></div>
      <div class="col-auto"><?php echo html_input_field('sheet_name', $sheet_name, 'class="form-control form-control-sm"'); ?></div>
      <div class="col-auto"><?php echo TEXT_INACTIVE; ?>&nbsp;<?php echo html_checkbox_field('inactive', '1', ($inactive ?? false) ? ' checked' : ''); ?></div>
      <div class="col-auto"><?php echo TEXT_REVISION; ?>&nbsp;<?php echo html_input_field('revision', $revision, 'readonly class="form-control form-control-sm d-inline-block" style="width:80px"'); ?></div>
    </div>
    <div class="row g-2 align-items-center">
      <div class="col-auto"><?php echo TEXT_EFFECTIVE_DATE; ?>&nbsp;<?php echo html_calendar_field($cal_ps); ?></div>
      <div class="col-auto"><?php echo TEXT_USE_AS_DEFAULT; ?>&nbsp;<?php echo html_checkbox_field('default_sheet', '1', ($default_sheet ?? false) ? ' checked' : ''); ?></div>
    </div>
    <?php if (ENABLE_MULTI_CURRENCY): ?>
    <div class="mt-2 text-center text-danger"><?php echo sprintf(GEN_PRICE_SHEET_CURRENCY_NOTE, $currencies->currencies[DEFAULT_CURRENCY]['title']); ?></div>
    <?php endif; ?>
  </div>
</div>
<!-- Price levels card -->
<div class="card fb-card mb-2 mx-auto" style="width:fit-content">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="pkg-table table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th><?php echo TEXT_LEVEL; ?></th>
            <th><?php echo TEXT_QUANTITY; ?></th>
            <th><?php echo TEXT_SOURCE; ?></th>
            <th><?php echo TEXT_ADJUSTMENT; ?></th>
            <th><?php echo INV_ADJ_VALUE; ?></th>
            <th><?php echo INV_ROUNDING; ?></th>
            <th><?php echo INV_RND_VALUE; ?></th>
            <th><?php echo TEXT_PRICE; ?></th>
          </tr>
        </thead>
        <tbody>
<?php
$price_levels      = explode(';', isset($default_levels) ? $default_levels : '');
$first_source_list = $price_mgr_sources;
array_shift($first_source_list);
array_pop($first_source_list);
for ($i = 0, $j = 1; $i < MAX_NUM_PRICE_LEVELS; $i++, $j++) {
  $level_info = explode(':', isset($price_levels[$i]) ? $price_levels[$i] : '');
  $price   = $currencies->precise($level_info[0] ? $level_info[0] : (($i == 0) ? (isset($full_price) ? $full_price : 0) : 0));
  $qty     = $level_info[1] ? $level_info[1] : $j;
  $src     = $level_info[2] ? $level_info[2] : 0;
  $adj     = $level_info[3] ? $level_info[3] : 0;
  $adj_val = $level_info[4] ? $level_info[4] : '0';
  $rnd     = $level_info[5] ? $level_info[5] : 0;
  $rnd_val = $level_info[6] ? $level_info[6] : '0';
  echo '<tr class="' . ($j % 2 ? 'odd' : 'even') . '">' . chr(10);
  echo '  <td class="text-center">' . $j . '</td>' . chr(10);
  echo '  <td>' . html_input_field('qty_' .     $j, $qty, 'class="form-control form-control-sm text-end" style="width:80px" onchange="updatePrice()"') . '</td>' . chr(10);
  echo '  <td>' . html_pull_down_menu('src_' .  $j, gen_build_pull_down(($i == 0) ? $first_source_list : $price_mgr_sources), $src, 'class="form-select form-select-sm" onchange="updatePrice()"') . '</td>' . chr(10);
  echo '  <td>' . html_pull_down_menu('adj_' .  $j, gen_build_pull_down($price_mgr_adjustments), $adj, 'disabled class="form-select form-select-sm" onchange="updatePrice()"') . '</td>' . chr(10);
  echo '  <td>' . html_input_field('adj_val_' . $j, $currencies->format($adj_val), 'disabled class="form-control form-control-sm text-end" style="width:80px" onchange="updatePrice()"') . '</td>' . chr(10);
  echo '  <td>' . html_pull_down_menu('rnd_' .  $j, gen_build_pull_down($price_mgr_rounding), $rnd, 'disabled class="form-select form-select-sm" onchange="updatePrice()"') . '</td>' . chr(10);
  echo '  <td>' . html_input_field('rnd_val_' . $j, $currencies->precise($rnd_val), 'disabled class="form-control form-control-sm text-end" style="width:80px" onchange="updatePrice()"') . '</td>' . chr(10);
  echo '  <td>' . html_input_field('price_' .   $j, $currencies->format($price), 'class="form-control form-control-sm text-end" style="width:100px" onchange="updatePrice()"') . '</td>' . chr(10);
  echo '</tr>' . chr(10);
}
echo '<script type="text/javascript">initEditForm()</script>' . chr(10);
?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</form>
