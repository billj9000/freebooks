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
//  Path: /modules/phreebooks/pages/admin/js_include.php
//
?>
<script type="text/javascript">
function init() {
  var coaTable = new simpleDatatables.DataTable('#coa_table', dataTables_i18n);
  var applyCoaRowClasses = function() {
    document.querySelectorAll('#coa_table tbody tr').forEach(function(tr) {
      var rcEl = tr.querySelector('[data-rc]');
      if (rcEl) tr.classList.add(rcEl.getAttribute('data-rc'));
    });
  };
  coaTable.on('datatable.init',   applyCoaRowClasses);
  coaTable.on('datatable.page',   applyCoaRowClasses);
  coaTable.on('datatable.sort',   applyCoaRowClasses);
  coaTable.on('datatable.search', applyCoaRowClasses);
  new simpleDatatables.DataTable('#tax_c_table',      dataTables_i18n);
  new simpleDatatables.DataTable('#tax_v_table',      dataTables_i18n);
  new simpleDatatables.DataTable('#tax_auth_c_table', dataTables_i18n);
  new simpleDatatables.DataTable('#tax_auth_v_table', dataTables_i18n);
}

function check_form() {
  return true;
}

function loadPopUp(action, id) {
  switch(action) {
    case 'chart_of_accounts_new':    action = 'new';    subject = 'chart_of_accounts'; break;
    case 'tax_auths_new':            action = 'new';    subject = 'tax_auths';         break;
    case 'tax_auths_vend_new':       action = 'new';    subject = 'tax_auths_vend';    break;
    case 'tax_rates_new':            action = 'new';    subject = 'tax_rates';         break;
    case 'tax_rates_vend_new':       action = 'new';    subject = 'tax_rates_vend';    break;
    case 'chart_of_accounts_edit':   action = 'edit';   subject = 'chart_of_accounts'; break;
    case 'tax_auths_edit':           action = 'edit';   subject = 'tax_auths';         break;
    case 'tax_auths_vend_edit':      action = 'edit';   subject = 'tax_auths_vend';    break;
    case 'tax_rates_edit':           action = 'edit';   subject = 'tax_rates';         break;
    case 'tax_rates_vend_edit':      action = 'edit';   subject = 'tax_rates_vend';    break;
    case 'chart_of_accounts_delete': action = 'delete'; subject = 'chart_of_accounts'; break;
    case 'tax_auths_delete':         action = 'delete'; subject = 'tax_auths';         break;
    case 'tax_auths_vend_delete':    action = 'delete'; subject = 'tax_auths_vend';    break;
    case 'tax_rates_delete':         action = 'delete'; subject = 'tax_rates';         break;
    case 'tax_rates_vend_delete':    action = 'delete'; subject = 'tax_rates_vend';    break;
  }
  window.open("index.php?module=phreedom&page=popup_setup&topic="+module+"&subject="+subject+"&action="+action+"&sID="+id,"popup_setup","width=500,height=550,resizable=1,scrollbars=1,top=150,left=200");
}

function subjectDelete(subject, id) {
  document.getElementById('subject').value = subject;
  submitSeq(id, 'delete');
}
</script>
