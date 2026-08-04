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
//  Path: /modules/phreebooks/pages/export_profiles/js_include.php
//
?>
<script type="text/javascript">
function init() {
}

function check_form() {
<?php if (in_array($_REQUEST['action'], array('new', 'edit'))) { ?>
  if (!document.getElementById('profile_name').value) {
    alert('<?php echo JS_ERROR . PO_EXPORT_ERROR_NO_NAME; ?>');
    return false;
  }
<?php } ?>
  return true;
}

function deleteProfile(id) {
  location.href = 'index.php?module=phreebooks&page=export_profiles&action=delete&peID=' + id;
}
</script>
