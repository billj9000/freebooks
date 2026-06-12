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
//  Path: /modules/shipping/pages/admin/js_include.php
//
?>
<script type="text/javascript">
var _shippingModal = null;
function init() {
  _shippingModal = new bootstrap.Modal(document.getElementById('shipping_dialog_modal'));
  document.getElementById('shipping_dialog_submit').addEventListener('click', function() {
    _shippingModal.hide();
    document.getElementById('action').form.submit();
  });
}

function check_form() {
  return true;
}

function toggleProperties(id) {
  if (document.getElementById(id).style.display == 'none') {
    document.getElementById(id).style.display = '';
  } else {
    document.getElementById(id).style.display = 'none';
  }
}

function getDialog(method, template) {
  if (!method || !template) return;
  fetchXml('index.php?module=shipping&page=ajax&op=shipping&action=form&method='+method+'&template='+template, fillDialog);
}

function fillDialog(xml) {
  if (!xml) return;
  if (xmlText(xml, "message")) alert(xmlText(xml, "message"));
  document.getElementById('action').value = xmlText(xml, "action");
  document.getElementById('shipping_dialog').innerHTML = xmlText(xml, "html");
  _shippingModal.show();
}
</script>
