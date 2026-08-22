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
//  Path: /modules/work_orders/pages/main/js_include.php
//
?>
<script type="text/javascript">
<!--
// pass some php variables
var fetchDetails  = false;
var nothing_to_print = '<?php echo WO_NOTHING_TO_PRINT; ?>';
<?php echo js_calendar_init($cal_date); ?>

// required function called with every page load
function init() {
  <?php $__action = $_REQUEST['action'] ?? ''; if ($__action <> 'new' && $__action <> 'edit' && $__action <> 'build') { // set focus for main window
	echo "  document.getElementById('search_text').focus();";
	echo "  document.getElementById('search_text').select();";
  } ?>
<?php
  // Print of a work order that HAD been changed saves it and then comes back here
  // as action=edit&print=1, so the record printed is still the one in front of
  // them. Print of an unchanged one never posts at all.
  if (!$error && isset($_GET['print']) && $__action == 'edit') echo '  printWOrder(' . (int)$id . ');';
  if (!$error && $__action == 'new')   echo '  document.getElementById("sku").focus();';
  // Watch for changes, so Print can leave an unchanged work order alone. Only
  // the new/edit form has one to watch; the list page has no work_orders form.
  // Both are rendered filled by the server, so the snapshot can be taken here.
  // Not after a failed post: what is on screen is unsaved work, and a snapshot
  // of it would grey out the Save that has to happen next.
  if (!$error && ($__action == 'new' || $__action == 'edit')) {
	echo '  fbDocInit(\'work_orders\');' . "\n";
	echo '  fbDocBaselineSoon();' . "\n";
  }
?>
}

// Print does not save. A work order nobody has changed is printed as it stands -
// no post, no reload, and no write access needed, which is why a read-only user
// gets the button. Only a record that has never been saved, or one with changes
// the person is entitled to save, still goes through the server first.
function printDocument() {
  var id = document.getElementById('id').value;
  if ((id == '' || id == '0') && !fbDocCanSave()) { // nothing stored, no right to store it
	alert(nothing_to_print);
	return;
  }
  if (id != '' && id != '0' && (!fbDocCanSave() || !fbDocModified())) {
	printWOrder(id);
	return;
  }
  submitToDo('print');
}

// Saving a work order nobody changed would rewrite the same values. The Save icon
// is greyed for it (fbDocSyncIcons); this is the refusal itself, for a browser
// that shows the icon anyway.
function saveDocument() {
  if (!fbDocNeedsSaving()) return;
  submitToDo('save');
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

function deleteWO(id) {
  location.href = 'index.php?module=work_orders&page=main&action=delete&id='+id;
}

function InventoryList(rowCnt) {
  var storeID = document.getElementById('store_id').value;
  var sku     = document.getElementById('sku').value;
  window.open("index.php?module=inventory&page=popup_inv&type=v&f1=ma&rowID="+rowCnt+"&storeID="+storeID+"&cID=0&search_text="+sku,"inventory","width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
}

function loadSkuDetails(iID, rowCnt) {
  var qty, bID, sku;
  if (!rowCnt) return;
  // check to see if there is a sku present
  if (!iID) sku = document.getElementById('sku').value;
  if (sku == text_search) return;
  bID = document.getElementById('store_id').value;
  qty = document.getElementById('qty').value;
  fetchXml('index.php?module=inventory&page=ajax&op=inv_details&fID=skuDetails&bID='+bID+'&cID=0&qty='+qty+'&iID='+iID+'&sku='+sku+'&rID='+rowCnt, fillInventory);
}

function fillInventory(xml) {
  if (!xml) return;
  var iID = xmlText(xml, "id");
  var sku = xmlText(xml, "sku");
  if (!iID) return;
  document.getElementById('sku_id').value = iID;
  document.getElementById('sku').value    = sku;
  fetchXml('index.php?module=work_orders&page=ajax&op=load_task_list&iID='+iID, fillTaskList);
  fetchBOMList();
}

function fillTaskList(xml) { // call back function
  if (!xml) return;
  document.getElementById('wo_id').value    = xmlText(xml, "WOid");
  document.getElementById('wo_title').value = xmlText(xml, "WOTitle");
  var imgURL = xmlText(xml, "ImageURL");
  if (imgURL) {
    document.getElementById("image_here").innerHTML = '<img src="'+imgURL+'" alt="ALT" title="TITLE" height="64" />';
  } else {
    document.getElementById("image_here").innerHTML = '&nbsp;';
  }
  while (document.getElementById('table_tasks').rows.length) document.getElementById("table_tasks").deleteRow(-1);
  var msg = xmlText(xml, "Message");
  if (msg) { alert(msg); return; }
  var odd = true;
  var tbody = document.getElementById('table_tasks');
  Array.from(xml.getElementsByTagName("Task")).forEach(function(task) {
    var row = document.createElement("TR");
    row.setAttribute('class', odd ? 'odd' : 'even');
    var td = document.createElement("TD");
    td.setAttribute('align', 'center');
    td.innerHTML = xmlText(task, "Step");
    row.appendChild(td);
    td = document.createElement("TD");
    td.innerHTML = xmlText(task, "Task_name");
    row.appendChild(td);
    td = document.createElement("TD");
    td.innerHTML = xmlText(task, "Description");
    row.appendChild(td);
    tbody.appendChild(row);
    odd = !odd;
  });
}

function printWOrder(id) { // request funtion
  fetchXml('index.php?module=work_orders&page=ajax&op=load_wo_detail&id='+id, fillwoDetail);
}

function fillwoDetail(xml) { // call back function
  if (!xml) return;
  var id = xmlText(xml, "id");
  // gen_print_form_url() decides which engine prints and ends the query string
  // at "xmin=", so the id and the email hints append the same way they always
  // did. The hints have to come after it for that reason.
  var details = "<?php echo gen_print_form_url('inv:wo', 'wo_journal_main.id', 'EQUAL'); ?>"+id;
  if (xmlText(xml, "sEmail")) details += "&sEmail="+xmlText(xml, "sEmail");
  if (xmlText(xml, "sName"))  details += "&sName=" +xmlText(xml, "sName");
  if (xmlText(xml, "rEmail")) details += "&rEmail="+xmlText(xml, "rEmail");
  if (xmlText(xml, "rName"))  details += "&rName=" +xmlText(xml, "rName");
  var printWin = window.open(details,"reportFilter","width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
  printWin.focus();
}

// ajax pair to fetch whether there are enough parts to build number of sku requested.
function fetchBOMList() { // request funtion
  var skuID = document.getElementById('sku_id').value;
  var qty   = document.getElementById('qty').value;
  if (skuID && qty) {
    fetchXml('index.php?module=work_orders&page=ajax&op=load_bom_list&skuID='+skuID+'&qty='+qty, fillBOMList);
  }
}

function fillBOMList(xml) {
  if (!xml) return;
  var shortage = xmlText(xml, "shortage");
  if (shortage != 'none') {
    alert('<?php echo WO_INSUFFICIENT_INVENTORY; ?>'+"\n"+shortage);
  }
}

// -->
</script>