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
//  Path: /themes/default/layout.php
//
// Everything inside <body> on a page drawn by includes/template_index.php, which
// includes this in global scope so the page template keeps every variable its
// pre_process.php set. A scheme that replaces it must still:
//  - load the two wz_tooltip scripts and print #please_wait, which page scripts show;
//  - include theme_file('menu.php') only when $include_header is true;
//  - put the page inside one <div id="content-wrap">, which the <head> sizes to
//    fill the window, and require $template_path there exactly once;
//  - include theme_file('footer.php') only when $include_footer is true.
?>
  <script type="text/javascript" src="modules/phreedom/includes/wz_tooltip/wz_tooltip.js"></script>
  <script type="text/javascript" src="modules/phreedom/includes/wz_tooltip/tip_balloon.js"></script>
  <div id="please_wait"><p><?php echo html_icon('phreebooks/please_wait.gif', TEXT_PLEASE_WAIT, 'large'); ?></p></div>
  <!-- Menu -->
  <?php if ($include_header) require_once(theme_file('menu.php')); ?><div id="content-wrap">
  <!-- Template -->
  <?php if (is_file($template_path)) { require($template_path); } else throw new RuntimeException('No template file: ' . $template_path); ?>
  </div>
  <!-- Footer -->
  <?php if ($include_footer) require(theme_file('footer.php')); ?>
