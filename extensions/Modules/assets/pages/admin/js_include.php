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
//  Path: /modules/assets/pages/admin/js_include.php
//
?>
<script type="text/javascript">

function init() {}

function check_form() {
  return true;
}

function loadPopUp(action, id) {
  switch(action) {
    case 'fields_new':    action = 'new';    subject = 'assets_fields'; break;
    case 'tabs_new':      action = 'new';    subject = 'assets_tabs';   break;
    case 'fields_edit':   action = 'edit';   subject = 'assets_fields'; break;
    case 'tabs_edit':     action = 'edit';   subject = 'assets_tabs';   break;
    case 'fields_delete': action = 'delete'; subject = 'assets_fields'; break;
    case 'tabs_delete':   action = 'delete'; subject = 'assets_tabs';   break;
  }
  window.open("index.php?module=phreedom&page=popup_setup&topic="+module+"&subject="+subject+"&action="+action+"&sID="+id,"popup_setup","width=500,height=550,resizable=1,scrollbars=1,top=150,left=200");
}
function subjectDelete(subject, id) {
  document.getElementById('subject').value = subject;
  submitSeq(id, 'delete');
}
</script>