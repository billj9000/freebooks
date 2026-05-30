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
//  Path: /modules/phreedom/pages/main/js_include.php
//
?>
<script>
function init() {}
function check_form() { return true; }

function box_edit(boxId) {
  document.getElementById(boxId+'_prop').style.display = '';
  document.getElementById(boxId+'_can').style.display  = '';
  document.getElementById(boxId+'_add').style.display  = 'none';
}

function box_cancel(boxId) {
  document.getElementById(boxId+'_prop').style.display = 'none';
  document.getElementById(boxId+'_can').style.display  = 'none';
  document.getElementById(boxId+'_add').style.display  = '';
}

var plusIcon  = '<?php echo DIR_WS_ICONS; ?>16x16/actions/list-add.png';
var minusIcon = '<?php echo DIR_WS_ICONS; ?>16x16/actions/list-remove.png';

function min_box(boxId) {
  var body = document.getElementById(boxId+'_body');
  var exp  = document.getElementById(boxId+'_exp');
  if (body.style.display === 'none') {
    body.style.display = '';
    exp.src   = minusIcon;
    exp.title = '<?php echo TEXT_COLLAPSE; ?>';
  } else {
    body.style.display = 'none';
    exp.src   = plusIcon;
    exp.title = '<?php echo TEXT_EXPAND; ?>';
  }
}

function del_box(boxId) {
  if (confirm('<?php echo JS_CTL_PANEL_DELETE_BOX; ?>')) {
    document.getElementById(boxId+'_action').value = 'delete';
    document.getElementById(boxId+'_frm').submit();
  }
  return false;
}

function move_box(boxId, direction) {
  document.getElementById(boxId+'_action').value = direction;
  document.getElementById(boxId+'_frm').submit();
}

function del_index(boxId, index) {
  if (confirm('<?php echo JS_CTL_PANEL_DELETE_IDX; ?>')) {
    document.getElementById(boxId+'_rId').value = index;
    document.getElementById(boxId+'_frm').submit();
  }
  return false;
}
</script>
