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
//  Path: /modules/phreebooks/pages/admin/template_tab_tax_rates.php
//
$tax_rates_toolbar = new toolbar;
$tax_rates_toolbar->icon_list['cancel']['show'] = false;
$tax_rates_toolbar->icon_list['open']['show']   = false;
$tax_rates_toolbar->icon_list['save']['show']   = false;
$tax_rates_toolbar->icon_list['delete']['show'] = false;
$tax_rates_toolbar->icon_list['print']['show']  = false;
if ($security_level > 1) $tax_rates_toolbar->add_icon('new', 'onclick="loadPopUp(\'tax_rates_new\', 0)"', $order = 10);
?>
<div class="tab-pane fade<?php echo ($active_tab === 'tax_rates' ? ' show active' : ''); ?>" id="tab_tax_rates" role="tabpanel">
  <?php echo $tax_rates_toolbar->build_toolbar(); ?>
  <h1><?php echo $tax_rates->title; ?></h1>
  <div id="tax_rates_content"><?php echo $tax_rates->build_main_html(); ?></div>
</div>
