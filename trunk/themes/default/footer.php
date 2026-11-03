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
//  Path: /themes/default/footer.php
//
// The page footer: the logo (FOOTER_LOGO replaces it), the company, period and
// version line, and the page's timing. layout.php includes it in global scope.
// The two copyright lines credit FreeBooks and the PhreeBooks code it is based
// on; a scheme's replacement keeps both. A dark scheme shows the FreeBooks
// logo's dark version; a FOOTER_LOGO is drawn for a light page, so it carries
// fb-footer-logo, which freebooks.css lightens under a dark scheme.
if (defined('FOOTER_LOGO')) {
  $image_path  = FOOTER_LOGO;
  $image_class = 'fb-footer-logo';
} else {
  $image_path  = DIR_WS_ADMIN . 'modules/phreedom/images/' . (html_color_mode() ? 'freebooks_logo_dark.png' : 'freebooks_logo.png');
  $image_class = '';
}
?>
  <div style="clear:both;text-align:center;padding-bottom:1.5em">
  <img class="<?php echo $image_class; ?>" src="<?php echo $image_path; ?>" alt="FreeBooks" style="display:block;margin:0 auto 2px;height:80px;width:auto">
  <?php
  $footer_info  = COMPANY_NAME.' | '.TEXT_ACCOUNTING_PERIOD.': '.CURRENT_ACCOUNTING_PERIOD.' | FreeBooks ('.MODULE_PHREEDOM_VERSION.') ';
  if ($module <> 'phreedom') $footer_info .= '(' . $module . ' ' . constant('MODULE_' . strtoupper($module) . '_STATUS') . ') ';
  $footer_info .= '<br />' . TEXT_COPYRIGHT . ' &copy;' . date('Y') . ' Bill Jones';
  $footer_info .= ' (' . (int)(1000 * (microtime(true) - PAGE_EXECUTION_START_TIME)) . ' ms) ' . $db->count_queries . ' SQLs (' . (int)($db->total_query_time * 1000).' ms)';
  $footer_info .= '<br />Portions Copyright &copy;2008-2014 PhreeSoft';
  echo '<span style="font-size:9px">' . $footer_info . '</span>';
  ?>
  </div>
