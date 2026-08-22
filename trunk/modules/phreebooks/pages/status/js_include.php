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
//  Path: /modules/phreebooks/pages/status/js_include.php
//

?>
<script type="text/javascript">
<!--
// pass any php variables generated during pre-process that are used in the javascript functions.
// Include translations here as well.
var journal_ID = '<?php echo JOURNAL_ID; ?>';
var text_selection_count_single  = <?php echo json_encode(STATUS_SELECTION_COUNT_SINGLE); ?>;
var text_selection_count_plural  = <?php echo json_encode(STATUS_SELECTION_COUNT_PLURAL); ?>;
var text_select_unprinted_error  = <?php echo json_encode(STATUS_SELECT_UNPRINTED_ERROR); ?>;

function init() {
  document.getElementById('search_text').focus();
  document.getElementById('search_text').select();
  updateSelectionUI();
}

// --- Sales Invoice Manager: unprinted invoice bulk selection/print ---------
// Selection is kept in sessionStorage (keyed per journal) so it survives
// paging through the list, since printing a large batch of unprinted
// invoices can span more rows than fit on one page.
var INVOICE_SELECTION_KEY = 'pb_invoice_selection_' + journal_ID;

function getInvoiceSelection() {
  try {
    var raw = sessionStorage.getItem(INVOICE_SELECTION_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch (e) {
    return [];
  }
}

function setInvoiceSelection(ids) {
  sessionStorage.setItem(INVOICE_SELECTION_KEY, JSON.stringify(ids));
  updateSelectionUI();
}

function updateSelectionUI() {
  var ids = getInvoiceSelection();
  document.querySelectorAll('.row-select').forEach(function(cb) {
    cb.checked = ids.indexOf(parseInt(cb.value, 10)) !== -1;
  });
  var countEl = document.getElementById('selection_count');
  var printBtn = document.getElementById('btn_print_selected');
  var clearBtn = document.getElementById('btn_clear_selection');
  if (countEl) {
    countEl.textContent = ids.length === 0 ? '' :
      (ids.length === 1 ? text_selection_count_single : text_selection_count_plural.replace('%d', ids.length));
  }
  if (printBtn) printBtn.disabled = ids.length === 0;
  if (clearBtn) clearBtn.style.display = ids.length === 0 ? 'none' : '';
}

function toggleInvoiceSelect(checkbox) {
  var id  = parseInt(checkbox.value, 10);
  var ids = getInvoiceSelection();
  var idx = ids.indexOf(id);
  if (checkbox.checked && idx === -1) ids.push(id);
  else if (!checkbox.checked && idx !== -1) ids.splice(idx, 1);
  setInvoiceSelection(ids);
}

function selectAllUnprinted() {
  var storeField = document.getElementById('store_id');
  var storeParam  = storeField ? ('&store_id=' + encodeURIComponent(storeField.value)) : '';
  fetch('index.php?module=phreebooks&page=ajax&op=unprinted_invoices' + storeParam)
    .then(function(resp) { if (!resp.ok) throw new Error('bad response'); return resp.json(); })
    .then(function(data) { setInvoiceSelection(data.ids || []); })
    .catch(function() { alert(text_select_unprinted_error); });
}

function clearInvoiceSelection() {
  setInvoiceSelection([]);
}

function printSelectedInvoices() {
  var ids = getInvoiceSelection();
  if (!ids.length) return;
  var printWin = window.open("<?php echo gen_print_form_url(POPUP_FORM_TYPE, 'journal_main.id', 'IN_LIST'); ?>" + ids.join(','), "reportFilter", "width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
  printWin.focus();
  clearInvoiceSelection();
}

function check_form() {
  return true;
}

// Insert other page specific functions here.
function deliveryList(oID) {
  window.open("index.php?module=phreebooks&page=popup_delivery&jID="+journal_ID+"&oID="+oID,"inv_delivery","width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
}

function printOrder(id) {
  var printWin = window.open("<?php echo gen_print_form_url(POPUP_FORM_TYPE, 'journal_main.id', 'EQUAL'); ?>"+id,"reportFilter","width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
  printWin.focus();
}

function shipList(oID, carrier) {
  window.open("index.php?module=shipping&page=popup_label_mgr&method="+carrier+"&jID="+journal_ID+"&oID="+oID,"popup_label_mgr","width=800,height=700,resizable=1,scrollbars=1,top=50,left=50");
}


function closeShipment(carrier) {
  window.open("index.php?module=shipping&page=popup_label_mgr&method="+carrier+"&action=close","popup_label_mgr","width=800,height=700,resizable=1,scrollbars=1,top=50,left=50");
}

function loadPopUp(subject, action, id) {
  window.open("index.php?module=shipping&page=popup_tracking&subject="+subject+"&action="+action+"&sID="+id,"popup_tracking","width=500,height=350,resizable=1,scrollbars=1,top=150,left=200");
}

// -->
</script>