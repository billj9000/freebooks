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
//  Path: /modules/phreebooks/pages/journal/js_include.php
//
?>
<script type="text/javascript">
var image_delete_msg  = '<?php echo GL_DELETE_GL_ROW; ?>';
var text_increased    = '<?php echo GL_ACCOUNT_INCREASED; ?>';
var text_decreased    = '<?php echo GL_ACCOUNT_DECREASED; ?>';
var journalID         = '<?php echo JOURNAL_ID; ?>';
var securityLevel     = <?php echo $security_level; ?>;
<?php echo js_calendar_init($cal_gl); ?>

<?php echo $js_gl_array; ?>

function init() {
<?php if ($_REQUEST['action'] == 'edit') echo '  EditJournal(' . $oID . ');' . chr(10); ?>
  document.getElementById("purchase_invoice_id").focus();
}

function check_form() {
  var error = 0;
  var error_message = "<?php echo JS_ERROR; ?>";
  var bal_total = cleanCurrency(document.getElementById('balance_total').value);
  if (bal_total != 0) {
    error_message += "<?php echo GL_ERROR_OUT_OF_BALANCE; ?>";
    error = 1;
  }
  for (var i = 1; i <= ((document.getElementById("item_table").rows.length - 1) / 2); i++) {
    if (!updateDesc(i)) {
      error_message += "<?php echo GL_ERROR_BAD_ACCOUNT; ?>";
      error = 1;
      break;
    }
  }
  var todo = document.getElementById('action').value;
  if (document.getElementById('id').value != "" && document.getElementById('recur_id').value > 0) {
    switch (todo) {
      case 'delete':
        message = '<?php echo GL_ERROR_RECUR_DEL_ROLL_REQD; ?>';
        break;
      default:
      case 'save':
        message = '<?php echo GL_ERROR_RECUR_ROLL_REQD; ?>';
    }
    if (confirm(message)) {
      document.getElementById('recur_frequency').value = '1';
    } else {
      document.getElementById('recur_frequency').value = '0';
    }
  }
  if (document.getElementById('purchase_invoice_id').value == "" && document.getElementById('recur_id').value > 0) {
    error_message += "<?php echo GL_ERROR_NO_REFERENCE; ?>";
    error = 1;
  }
  if (error == 1) {
    alert(error_message);
    return false;
  }
  return true;
}

function OpenGLList() {
  window.open("index.php?module=phreebooks&page=popup_journal&list=1&form=journal","gl_open","width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
}

function OpenRecurList(currObj) {
  window.open("index.php?module=phreebooks&page=popup_recur&jID="+journalID,"recur","width=400,height=300,resizable=1,scrollbars=1,top=150,left=200");
}

function verifyCopy() {
  var id = document.getElementById('id').value;
  if (!id) {
    alert('<?php echo GL_JS_CANNOT_COPY; ?>');
    return;
  }
  if (confirm('<?php echo GL_JS_COPY_CONFIRM; ?>')) {
    document.getElementById('recur_id').value        = '0';
    document.getElementById('recur_frequency').value = '0';
    submitToDo('copy');
  }
}

function EditJournal(rID) {
  fetchXml('index.php?module=phreebooks&page=ajax&op=load_record&rID='+rID, processEditJournal);
}

function processEditJournal(xml) {
  var DebitOrCredit;
  xml = parseXml(xml);
  if (!xml) return;
  document.getElementById('auto_complete').checked = false;
  document.getElementById('id').value        = xmlText(xml, "id");
  if (xmlText(xml, "purchase_invoice_id")) document.getElementById('purchase_invoice_id').value = xmlText(xml, "purchase_invoice_id");
  document.getElementById('post_date').value = xmlText(xml, "post_date");
  document.getElementById('recur_id').value  = xmlText(xml, "recur_id");
  document.getElementById('store_id').value  = xmlText(xml, "store_id");
  if (xmlText(xml, "attach_exist") == 1) {
    document.getElementById('show_attach').style.display = '';
  }
  while (document.getElementById("item_table").rows.length > 0) document.getElementById("item_table").deleteRow(-1);
  var id = xmlText(xml, "id");
  if (id && securityLevel < 3) removeElement('tb_main_0', 'tb_icon_save');
  removeElement('tb_main_0', 'tb_icon_recur');
  var jIndex = 1;
  Array.from(xml.getElementsByTagName("items")).forEach(function(item) {
    var rowCnt = addGLRow();
    document.getElementById('id_'+jIndex).value     = xmlText(item, "id");
    document.getElementById('acct_'+jIndex).value   = xmlText(item, "gl_account");
    if (xmlText(item, "description")) document.getElementById('desc_'+jIndex).value = xmlText(item, "description");
    document.getElementById('debit_'+jIndex).value  = formatCurrency(xmlText(item, "debit_amount"));
    document.getElementById('credit_'+jIndex).value = formatCurrency(xmlText(item, "credit_amount"));
    DebitOrCredit = (xmlText(item, "debit_amount") != 0) ? 'd' : 'c';
    formatRow(jIndex, DebitOrCredit);
    jIndex++;
  });
  updateBalance();
  if (xmlText(xml, "closed") == '1') alert('<?php echo WARNING_ENTRY_RECONCILED; ?>');
}

function downloadAttachment() {
  document.getElementById('action').value = 'dn_attach';
  document.getElementById('action').form.submit();
}

function glProperties(id, description, asset) {
  this.id          = id;
  this.description = description;
  this.asset       = asset;
}

function addGLRow(debit, credit, description) {
  if (!debit)       debit       = '';
  if (!credit)      credit      = '';
  if (!description) description = '';
  var tbody  = document.getElementById("item_table");
  var newRow = tbody.insertRow(-1);
  var rowCnt = (newRow.rowIndex + 1) / 2;
  newRow.innerHTML =
    '<td class="text-center">' +
      '<input type="hidden" name="id_'+rowCnt+'" id="id_'+rowCnt+'" value="">' +
      '<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="if(confirm(\''+image_delete_msg+'\')) removeGLRow('+rowCnt+');">×<\/button>' +
    '<\/td>' +
    '<td><select name="acct_'+rowCnt+'" id="acct_'+rowCnt+'" class="form-select form-select-sm" onchange="updateDesc('+rowCnt+')">' +
    '<\/select><\/td>' +
    '<td><input type="text" name="desc_'+rowCnt+'" id="desc_'+rowCnt+'" class="form-control form-control-sm" maxlength="64" value="'+description+'"><\/td>' +
    '<td><input type="text" name="debit_'+rowCnt+'" id="debit_'+rowCnt+'" class="form-control form-control-sm text-end" maxlength="12" value="'+debit+'" onchange="formatRow('+rowCnt+', \'d\')"><\/td>' +
    '<td><input type="text" name="credit_'+rowCnt+'" id="credit_'+rowCnt+'" class="form-control form-control-sm text-end" maxlength="12" value="'+credit+'" onchange="formatRow('+rowCnt+', \'c\')"><\/td>';
  var sel = document.getElementById('acct_' + rowCnt);
  for (var i = 0; i < js_gl_array.length; i++) {
    var opt    = document.createElement("option");
    opt.value  = js_gl_array[i].id;
    opt.text   = js_gl_array[i].description;
    sel.options.add(opt);
  }
  var infoRow = tbody.insertRow(-1);
  infoRow.className = 'table-warning';
  infoRow.innerHTML = '<td colspan="3">&nbsp;<\/td><td colspan="2" id="msg_'+rowCnt+'">&nbsp;<\/td>';
  document.getElementById("acct_" + rowCnt).focus();
  return rowCnt;
}

function removeGLRow(delRowCnt) {
  var tbody = document.getElementById("item_table");
  var N = tbody.rows.length / 2;
  for (var i = delRowCnt; i < N; i++) {
    document.getElementById('acct_'  + i).value = document.getElementById('acct_'  + (i+1)).value;
    document.getElementById('desc_'  + i).value = document.getElementById('desc_'  + (i+1)).value;
    document.getElementById('debit_' + i).value = document.getElementById('debit_' + (i+1)).value;
    document.getElementById('credit_'+ i).value = document.getElementById('credit_'+ (i+1)).value;
    document.getElementById('id_'    + i).value = document.getElementById('id_'    + (i+1)).value;
  }
  tbody.deleteRow(-1);
  tbody.deleteRow(-1);
  updateBalance(true);
}

function showAction(rowID, DebitOrCredit) {
  var acct      = document.getElementById('acct_'+rowID).value;
  var textValue = ' ';
  for (var i = 0; i < js_gl_array.length; i++) {
    if (js_gl_array[i].id == acct) {
      textValue = ((js_gl_array[i].asset == '1' && DebitOrCredit == 'd') || (js_gl_array[i].asset == '0' && DebitOrCredit == 'c'))
        ? text_increased : text_decreased;
      break;
    }
  }
  if (document.getElementById('debit_'+rowID).value == '' && document.getElementById('credit_'+rowID).value == '') {
    textValue = ' ';
  }
  document.getElementById('msg_' + rowID).textContent = textValue;
}

function formatRow(rowID, DebitOrCredit) {
  var temp;
  showAction(rowID, DebitOrCredit);
  if (DebitOrCredit == 'd') {
    if (document.getElementById('debit_'+rowID).value != '') {
      temp = cleanCurrency(document.getElementById('debit_'+rowID).value);
      document.getElementById('debit_'+rowID).value  = formatCurrency(temp);
      document.getElementById('credit_'+rowID).value = '';
    }
  } else {
    if (document.getElementById('credit_'+rowID).value != '') {
      temp = cleanCurrency(document.getElementById('credit_'+rowID).value);
      document.getElementById('credit_'+rowID).value = formatCurrency(temp);
      document.getElementById('debit_'+rowID).value  = '';
    }
  }
  updateBalance();
}

function updateBalance(skipAutoComplete) {
  var debit_total = 0;
  var credit_total = 0;
  var description = '';
  for (var i = 1; i <= (document.getElementById('item_table').rows.length / 2); i++) {
    var temp;
    temp = parseFloat(cleanCurrency(document.getElementById('debit_'+i).value));
    if (!isNaN(temp)) debit_total += temp;
    temp = parseFloat(cleanCurrency(document.getElementById('credit_'+i).value));
    if (!isNaN(temp)) credit_total += temp;
    description = document.getElementById('desc_'+i).value;
  }
  var debit = 0, credit = 0;
  if (!skipAutoComplete && debit_total != credit_total && document.getElementById('auto_complete').checked) {
    if (debit_total > credit_total) { credit = debit_total - credit_total; credit_total += credit; }
    if (debit_total < credit_total) { debit = credit_total - debit_total;  debit_total  += debit;  }
    addGLRow(debit ? formatCurrency(debit) : '', credit ? formatCurrency(credit) : '', description);
  }
  var balance_total = debit_total - credit_total;
  document.getElementById('debit_total').value   = formatCurrency(String(debit_total));
  document.getElementById('credit_total').value  = formatCurrency(String(credit_total));
  document.getElementById('balance_total').value = formatCurrency(String(balance_total));
  document.getElementById('balance_total').style.color = (document.getElementById('balance_total').value == formatted_zero) ? '' : 'red';
}

function updateDesc(rowID) {
  var acct = document.getElementById('acct_'+rowID).value;
  var DebitOrCredit = '';
  if (document.getElementById('debit_'+rowID).value != '')  DebitOrCredit = 'd';
  else if (document.getElementById('credit_'+rowID).value != '') DebitOrCredit = 'c';
  showAction(rowID, DebitOrCredit);
  return true;
}
</script>
