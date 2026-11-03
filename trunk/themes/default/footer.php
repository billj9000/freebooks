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
// fb-footer-logo, which freebooks.css lightens under a dark scheme. A scheme
// that follows the browser's light or dark setting gets both versions, and the
// browser shows the one for its setting.
$logo_dir  = DIR_WS_ADMIN . 'modules/phreedom/images/';
$logo_mode = html_color_scheme();
?>
  <div style="clear:both;text-align:center;padding-bottom:1.5em">
<?php if (defined('FOOTER_LOGO')) { ?>
  <img class="fb-footer-logo" src="<?php echo FOOTER_LOGO; ?>" alt="FreeBooks" style="display:block;margin:0 auto 2px;height:80px;width:auto">
<?php } else { ?>
  <picture>
<?php   if ($logo_mode == 'auto') { ?>
    <source srcset="<?php echo $logo_dir; ?>freebooks_logo_dark.png" media="(prefers-color-scheme: dark)">
<?php   } ?>
    <img src="<?php echo $logo_dir . ($logo_mode == 'dark' ? 'freebooks_logo_dark.png' : 'freebooks_logo.png'); ?>" alt="FreeBooks" style="display:block;margin:0 auto 2px;height:80px;width:auto">
  </picture>
<?php } ?>
  <?php
  $footer_info  = COMPANY_NAME.' | '.TEXT_ACCOUNTING_PERIOD.': '.CURRENT_ACCOUNTING_PERIOD.' | FreeBooks ('.MODULE_PHREEDOM_VERSION.') ';
  if ($module <> 'phreedom') $footer_info .= '(' . $module . ' ' . constant('MODULE_' . strtoupper($module) . '_STATUS') . ') ';
  $footer_info .= '<br />' . TEXT_COPYRIGHT . ' &copy;' . date('Y') . ' Bill Jones';
  $footer_info .= ' (' . (int)(1000 * (microtime(true) - PAGE_EXECUTION_START_TIME)) . ' ms) ' . $db->count_queries . ' SQLs (' . (int)($db->total_query_time * 1000).' ms)';
  $footer_info .= '<br />Portions Copyright &copy;2008-2014 PhreeSoft';
  echo '<span style="font-size:9px">' . $footer_info . '</span>';
  ?>
  </div>
