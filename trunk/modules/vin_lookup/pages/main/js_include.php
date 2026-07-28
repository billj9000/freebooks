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
//  Path: /modules/vin_lookup/pages/main/js_include.php
//
?>
<script>
function init() {}

// Copy the VIN to clipboard (button lives inside the htmx-swapped #vin_results fragment)
function copyVin(btn) {
  var val = document.getElementById('vin-value');
  if (!val) return;
  navigator.clipboard.writeText(val.textContent.trim()).then(function() {
    btn.textContent = '<?php echo addslashes(VIN_BTN_COPY); ?> ✓';
    setTimeout(function() { btn.textContent = '<?php echo addslashes(VIN_BTN_COPY); ?>'; }, 2000);
  });
}
</script>
