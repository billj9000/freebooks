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
//  Path: /modules/import_bank/pages/admin/js_include.php
//
?>
<script type="text/javascript">

function init() {}

function check_form() {
  return true;
}

function loadPopUp(action, id) {
  switch(action) {
    case 'known_transactions_new':     action = 'new';    subject = 'known_transactions'; break;
    case 'known_transactions_edit':    action = 'edit';   subject = 'known_transactions'; break;
    case 'known_transactions_delete':  action = 'delete'; subject = 'known_transactions'; break;
  }
  window.open("index.php?module=phreedom&page=popup_setup&topic="+module+"&subject="+subject+"&action="+action+"&sID="+id,"popup_setup","width=1000,height=700,resizable=1,scrollbars=1,top=150,left=200");
}
function subjectDelete(subject, id) {
  document.getElementById('subject').value = subject;
  submitSeq(id, 'delete');
}
</script>