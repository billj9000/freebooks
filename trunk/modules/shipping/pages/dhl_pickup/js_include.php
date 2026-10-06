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
//  Path: /modules/shipping/pages/dhl_pickup/js_include.php
//
?>
<script type="text/javascript">
function init() {}
function check_form() { return true; }

function dhlPickupDay(day) {
  if (day) location.href = 'index.php?module=shipping&page=dhl_pickup&date=' + encodeURIComponent(day);
}

function dhlPickupSubmit(action, pickupId) {
  var form = document.getElementById('dhl_pickup');
  form.querySelector('[name=action]').value    = action;
  form.querySelector('[name=pickup_id]').value = pickupId;
  form.submit();
}
</script>
