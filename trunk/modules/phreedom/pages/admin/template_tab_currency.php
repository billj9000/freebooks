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
//  Path: /modules/phreedom/pages/admin/template_tab_currency.php
//
$currency_toolbar = new toolbar('currency');
$currency_toolbar->icon_list['cancel']['show'] = false;
$currency_toolbar->icon_list['open']['show']   = false;
$currency_toolbar->icon_list['save']['show']   = false;
$currency_toolbar->icon_list['delete']['show'] = false;
$currency_toolbar->icon_list['print']['show']  = false;
if ($security_level > 1) $currency_toolbar->add_icon('new', 'onclick="loadPopUp(\'currency_new\', 0)"', $order = 10);
if (ENABLE_MULTI_CURRENCY) {
  $currency_toolbar->add_icon('update', 'onclick="tabPage(\'currency\', \'update\')"', $order = 10);
  $currency_toolbar->icon_list['update']['text'] = SETUP_UPDATE_EXC_RATE;
}

?>
<div class="tab-pane fade<?php echo ($active_tab === 'currency' ? ' show active' : ''); ?>" id="tab_currency" role="tabpanel">
  <?php echo $currency_toolbar->build_toolbar(); ?>
  <h1><?php echo $currency->title; ?></h1>
  <div id="currency_content"><?php echo $currency->build_main_html(); ?></div>
</div>
