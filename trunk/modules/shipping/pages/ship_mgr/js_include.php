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
//  Path: /modules/shipping/pages/ship_mgr/js_include.php
//
?>
<script type="text/javascript">
<?php echo js_calendar_init($cal_ship); ?>

var _modalInstance = null;
var _modalAction   = '';
var _modalMethod   = '';

function init() {
  var firstPane = document.querySelector('#shippingtabsContent .tab-pane');
  if (firstPane) firstPane.classList.add('show', 'active');
}

function check_form() {
  return true;
}

function loadPopUp(method, action, id) {
  window.open('index.php?module=shipping&page=popup_tracking&method=' + method + '&action=' + action + '&sID=' + id,
    'popup_tracking', 'width=500,height=350,resizable=1,scrollbars=1,top=150,left=200');
}

function submitAction(module_id, action) {
  document.getElementById('module_id').value = module_id;
  submitToDo(action, true);
}

function submitShipSequence(module_id, rowSeq, todo) {
  document.getElementById('module_id').value = module_id;
  submitSeq(rowSeq, todo);
}

function calendarPage() {
  location.href = 'index.php?module=shipping&page=ship_mgr&search_date=' + document.ship_mgr.search_date.value;
}

function getDialog(method, template) {
  if (!method || !template) return;
  fetch('index.php?module=shipping&page=ajax&op=shipping&action=form&method=' + method + '&template=' + template)
    .then(function(r) { return r.text(); })
    .then(fillDialog)
    .catch(function(e) { alert('Ajax Error: ' + e); });
}

function fillDialog(xmlText) {
  var parser = new DOMParser();
  var xml    = parser.parseFromString(xmlText, 'text/xml');
  var getText = function(tag) {
    var el = xml.getElementsByTagName(tag)[0];
    return el ? el.textContent : '';
  };
  var msg = getText('message');
  if (msg) alert(msg);
  _modalAction = getText('action');
  _modalMethod = getText('method');
  document.getElementById('shipping_dialog').innerHTML = getText('html');
  var modalEl = document.getElementById('shipping_modal');
  var width = getText('width');
  if (width) modalEl.querySelector('.modal-dialog').style.maxWidth = parseInt(width) + 'px';
  _modalInstance = new bootstrap.Modal(modalEl);
  _modalInstance.show();
}

function submitModalAction() {
  document.getElementById('action').value    = _modalAction;
  document.getElementById('module_id').value = _modalMethod;
  if (_modalInstance) _modalInstance.hide();
  document.getElementById('ship_mgr').submit();
}
</script>
