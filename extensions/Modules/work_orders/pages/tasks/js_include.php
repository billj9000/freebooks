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
//  Path: /modules/work_orders/pages/tasks/js_include.php
//

?>
<script type="text/javascript">
// pass some php variables
var securityLevel = <?php echo $security_level; ?>;

// required function called with every page load
function init() {
  document.getElementById('search_text').focus();
  document.getElementById('search_text').select();
}

function check_form() {
  var error = 0;
  var error_message = "<?php echo JS_ERROR; ?>";

  if (error == 1) {
	alert(error_message);
	return false;
  } else {
	return true;
  }
}

function deleteItem(id) {
  submitPost('index.php?module=work_orders&page=tasks', {action: 'delete', cID: id});
}

function clearTask() {
  document.getElementById('id').value          = '0';
  document.getElementById('task_name').value   = '';
  document.getElementById('description').value = '';
  document.getElementById('ref_doc').value     = '';
  document.getElementById('ref_spec').value    = '';
  document.getElementById('dept_id').value     = '';
  document.getElementById('job_time').value    = '';
  document.getElementById('job_unit').value    = '1';
  document.getElementById('mfg').value         = '0';
  document.getElementById('qa').value          = '0';
  document.getElementById('data_entry').value  = '0';
  document.getElementById('erp_entry').value   = '0';
}

// ajax pair to fetch item info
function editTask(id) { // request funtion
  fetchXml('index.php?module=work_orders&page=ajax&op=load_task&id='+id, fillTask);
}

function fillTask(xml) { // call back function
  if (!xml) return;
  var id = xmlText(xml, "id");
  document.getElementById('id').value          = id;
  document.getElementById('task_name').value   = xmlText(xml, "task_name");
  document.getElementById('description').value = xmlText(xml, "description");
  document.getElementById('ref_doc').value     = xmlText(xml, "ref_doc")  || '';
  document.getElementById('ref_spec').value    = xmlText(xml, "ref_spec") || '';
  document.getElementById('dept_id').value     = xmlText(xml, "dept_id")  || '';
  document.getElementById('job_time').value    = xmlText(xml, "job_time");
  document.getElementById('job_unit').value    = xmlText(xml, "job_unit");
  document.getElementById('mfg').value         = xmlText(xml, "mfg");
  document.getElementById('qa').value          = xmlText(xml, "qa");
  document.getElementById('data_entry').value  = xmlText(xml, "data_entry");
  document.getElementById('erp_entry').value   = xmlText(xml, "erp_entry");
  // turn off some icons
  if (id && securityLevel < 3) removeElement('tb_main_0', 'tb_icon_save');
}
// end ajax pair

</script>