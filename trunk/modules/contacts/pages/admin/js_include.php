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
//  Path: /modules/contacts/pages/admin/js_include.php
//
?>
<script type="text/javascript">
function init() {
  new simpleDatatables.DataTable('#dept_table',       dataTables_i18n);
  new simpleDatatables.DataTable('#dept_type_table',  dataTables_i18n);
  new simpleDatatables.DataTable('#proj_cost_table',  dataTables_i18n);
  new simpleDatatables.DataTable('#proj_phase_table', dataTables_i18n);
  new simpleDatatables.DataTable('#tab_table',        dataTables_i18n);
  new simpleDatatables.DataTable('#field_table',      dataTables_i18n);
}

function check_form() {
  return true;
}

function loadPopUp(action, id) {
  switch (action) {
    case 'departments_new':       action = 'new';    subject = 'departments';    break;
    case 'dept_types_new':        action = 'new';    subject = 'dept_types';     break;
    case 'project_costs_new':     action = 'new';    subject = 'project_costs';  break;
    case 'project_phases_new':    action = 'new';    subject = 'project_phases'; break;
    case 'departments_edit':      action = 'edit';   subject = 'departments';    break;
    case 'dept_types_edit':       action = 'edit';   subject = 'dept_types';     break;
    case 'project_costs_edit':    action = 'edit';   subject = 'project_costs';  break;
    case 'project_phases_edit':   action = 'edit';   subject = 'project_phases'; break;
    case 'departments_delete':    action = 'delete'; subject = 'departments';    break;
    case 'dept_types_delete':     action = 'delete'; subject = 'dept_types';     break;
    case 'project_costs_delete':  action = 'delete'; subject = 'project_costs';  break;
    case 'project_phases_delete': action = 'delete'; subject = 'project_phases'; break;
    case 'fields_new':            action = 'new';    subject = 'contact_fields'; break;
    case 'tabs_new':              action = 'new';    subject = 'contact_tabs';   break;
    case 'fields_edit':           action = 'edit';   subject = 'contact_fields'; break;
    case 'tabs_edit':             action = 'edit';   subject = 'contact_tabs';   break;
    case 'fields_delete':         action = 'delete'; subject = 'contact_fields'; break;
    case 'tabs_delete':           action = 'delete'; subject = 'contact_tabs';   break;
  }
  window.open('index.php?module=phreedom&page=popup_setup&topic=' + module + '&subject=' + subject + '&action=' + action + '&sID=' + id, 'popup_setup', 'width=500,height=550,resizable=1,scrollbars=1,top=150,left=200');
}

function subjectDelete(subject, id) {
  document.getElementById('subject').value = subject;
  submitSeq(id, 'delete');
}
</script>
