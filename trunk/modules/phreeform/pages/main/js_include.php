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
//  Path: /modules/phreeform/pages/main/js_include.php
//
?>
<script type="text/javascript">
function init() {
<?php
  if ($toggle_list) echo $toggle_list;
?>
}

function check_form() {
  return true;
}

function ReportPopup(action, rID) {
  var message;
  if (!rID) rID = 0;
  if (!action) return false;
  switch (action) {
    case 'copy':
    case 'rename':
      if (action == 'rename') {
        message = '<?php echo PHREEFORM_JS_RPT_RENAME; ?>';
      } else {
        message = '<?php echo PHREEFORM_JS_RPT_COPY; ?>';
      }
      var name = prompt(message, '');
      if (!name) {
        alert('<?php echo PHREEFORM_JS_RPT_BLANK; ?>');
        break;
      }
      document.getElementById('newName').value = name;
    case 'export':
      document.getElementById('rowSeq').value  = rID;
      submitToDo(action, true);
      break;
    default:
      window.open("index.php?module=phreeform&page=popup_build&action="+action+"&rID="+rID,"popup_build","menubar=1,toolbar=1,width=1000,height=800,resizable=1,scrollbars=1,top=100,left=100");
      break;
  }
}

function ReportGenPopup(rID, type) {
  if (!rID) return false;
  window.open("index.php?module=phreeform&page=popup_gen&rID="+rID+"&action=open","popup_gen","width=900,height=650,resizable=1,scrollbars=1,top=150,left=200");
}

function fetch_doc(id) {
  document.getElementById("id").value = id;
  fetchXml('index.php?module=phreeform&page=ajax&op=load_doc_details&id='+id, fillDocDetails);
}

function fillDocDetails(xml) {
  if (!xml) return;
  document.getElementById("rightColumn").innerHTML = xmlText(xml, "htmlContents");
}

function docAction(action) {
  var id = document.getElementById("id").value;
  if (!id) return;
  if (action == 'edit' || action == 'check_in') {
    window.open("index.php?module=phreeform&page=popup_new_doc&id="+id,"new_doc","width=850px,height=550px,resizable=1,scrollbars=1,top=150,left=100");
  }
  fetchXml('index.php?module=phreeform&page=ajax&op=doc_operation&action='+action+'&id='+id, docActionResp);
}

function docActionResp(xml) {
  if (!xml) return;
  if (xmlText(xml, "msg")) alert(xmlText(xml, "msg"));
  fetch_doc(xmlText(xml, "docID"));
}

function dirAction(action) {
  var id = document.getElementById("id").value;
  if (!id) return;
  fetchXml('index.php?module=phreeform&page=ajax&op=dir_operation&action='+action+'&id='+id, dirActionResp);
}

function dirActionResp(xml) {
  if (!xml) return;
  if (xmlText(xml, "message")) alert(xmlText(xml, "message"));
  fetch_doc(xmlText(xml, "docID"));
}
</script>
