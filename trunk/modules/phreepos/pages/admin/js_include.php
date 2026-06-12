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
//  Path: /modules/phreepos/pages/admin/js_include.php
//
?>
<script type="text/javascript">
function init() {
  new simpleDatatables.DataTable('#tills_table',             dataTables_i18n);
  new simpleDatatables.DataTable('#other_transactions_table', dataTables_i18n);
}

function check_form() {
  return true;
}

function loadPopUp(action, id) {
  var subject;
  switch (action) {
    case 'tills_new':                action = 'new';    subject = 'tills'; break;
    case 'tills_edit':               action = 'edit';   subject = 'tills'; break;
    case 'tills_delete':             action = 'delete'; subject = 'tills'; break;
    case 'other_transactions_new':   action = 'new';    subject = 'other_transactions'; break;
    case 'other_transactions_edit':  action = 'edit';   subject = 'other_transactions'; break;
    case 'other_transactions_delete':action = 'delete'; subject = 'other_transactions'; break;
  }
  window.open('index.php?module=phreedom&page=popup_setup&topic=' + module + '&subject=' + subject + '&action=' + action + '&sID=' + id, 'popup_setup', 'width=1000,height=700,resizable=1,scrollbars=1,top=150,left=200');
}

function subjectDelete(subject, id) {
  document.getElementById('subject').value = subject;
  submitSeq(id, 'delete');
}
</script>
