<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft      (www.PhreeSoft.com)       |
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
//  Path: /install/pages/main/js_include.php
//

?>
<script type="text/javascript">
<!--

function langSelect() {
  var index = document.getElementById('lang').selectedIndex;
  var lang  = document.getElementById('lang').options[index].value;
  location.href = 'index.php?lang='+lang;
}

// Which charts the shipped demonstration data can be loaded against. The demo
// records name US account numbers literally, so a chart numbered any other way
// would take them and post every one of them nowhere.
//
// The installer refuses the combination on its own either way; this only says
// so before the form is submitted rather than after.
var chartAllowsDemo = <?php
  $chart_demo = array();
  if (function_exists('phreebooks_charts')) {
    // Named apart from pre_process.php's own $chart_file/$chart_info: this
    // file is included into the same scope, after it has run.
    foreach (array_keys(phreebooks_charts()) as $js_chart_file) {
      $js_chart = phreebooks_chart_read($js_chart_file);
      $chart_demo[$js_chart_file] = ($js_chart && $js_chart['demo']) ? 1 : 0;
    }
  }
  echo json_encode($chart_demo);
?>;

function chartSelect() {
  var chart = document.getElementById('chart');
  var demo  = document.getElementById('company_demo');
  if (!chart || !demo) return;
  var allowed = chartAllowsDemo[chart.value] !== 0;
  demo.disabled = !allowed;
  if (!allowed) demo.value = '0';
}

// -->
</script>