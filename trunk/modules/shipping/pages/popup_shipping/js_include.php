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
//  Path: /modules/shipping/pages/popup_shipping/js_include.php
//
// This file runs only for direct (non-htmx) popup access.
// For the normal modal path, all JS lives in orders/js_include.php.
?>
<script type="text/javascript">
<!--
var action   = '<?php echo $_REQUEST['action']; ?>';
var formName = '<?php echo $_GET['form'] ?? ''; ?>';
<?php echo js_calendar_init($cal_ship); ?>

function init() {
  switch (action) {
    case 'rates':
      break;
    default:
      // pre-populate To fields from opener (legacy popup path)
      if (!window.opener) break;
      var fields = [
        ['ship_to_city',         'ship_city_town'],
        ['ship_to_state',        'ship_state_province'],
        ['ship_to_postal_code',  'ship_postal_code'],
        ['pkg_weight',           'weight'],
        ['pkg_total',            'total'],
        ['pkg_item_count',       'item_count'],
      ];
      fields.forEach(function(pair) {
        var src = window.opener.document.getElementById(pair[1]);
        var dst = document.getElementById(pair[0]);
        if (src && dst) dst.value = src.value;
      });
      var srcCountry = window.opener.document.getElementById('ship_country_code');
      var dstCountry = document.getElementById('ship_to_country_code');
      if (srcCountry && dstCountry) dstCountry.value = srcCountry.value;
  }
}

function check_form() { return true; }

// Legacy popup: write rate back to opener and close
function setReturnRate(rate, method, service) {
  if (window.opener) {
    window.opener.document.getElementById('freight').value      = rate;
    window.opener.document.getElementById('ship_carrier').value = method;
    window.opener.buildFreightDropdown();
    window.opener.document.getElementById('ship_service').value = service;
    window.opener.updateTotalPrices();
    self.close();
  }
}
// -->
</script>
