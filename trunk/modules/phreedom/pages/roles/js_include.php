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
//  Path: /modules/phreedom/pages/roles/js_include.php
//
?>
<script type="text/javascript">
var delete_pushed = false;

function init() {}

function check_form() {
  return true;
}

function copyItem(id) {
  var adminName = prompt('<?php echo GEN_MSG_COPY_INTRO; ?>', '');
  if (adminName) {
    location.href = 'index.php?module=phreedom&page=roles&action=copy&cID=' + id + '&name=' + adminName;
  } else {
    return false;
  }
}
</script>
