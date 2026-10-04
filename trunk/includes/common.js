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
//  Path: /includes/common.js
//
/******************************* General Functions ****************************************/
function addLoadEvent(func) {
  var oldonload = window.onload;
  if (typeof window.onload != 'function') {
    window.onload = func;
  } else {
    window.onload = function() {
	  if (oldonload) { oldonload(); }
	  func();
	};
  }
}

function addUnloadEvent(func) {
  var oldonunload = window.onunload;
  if (typeof window.onunload != 'function') {
    window.onunload = func;
  } else {
	window.onunload = function() {
	  if (oldonunload) { oldonunload(); }
	  func();
	};
  }
}

// BOS - set up ajax session refresh timer to stay logged in if the browser is active
var sessionClockID = 0; // start a session clock to stay logged in
function refreshSessionClock() {
  if (sessionClockID) {
    window.clearTimeout(sessionClockID);
    fetch('index.php?module=phreedom&page=ajax&op=refresh_session');
  }
  sessionClockID = window.setTimeout("refreshSessionClock()", 300000); // set to 5 minutes
}

function clearSessionClock() {
  if (sessionClockID) {
    window.clearTimeout(sessionClockID);
    sessionClockID = 0;
  }
}
// EOS - set up ajax session refresh timer to stay logged in if the browser is active

function submit_wait() {
  document.getElementById("wait_msg").style.display = "block";
  return true; // allow form to submit
}

function clearField(field_name, text_value) {
	document.getElementById(field_name).style.color = '';
	if (document.getElementById(field_name).value == text_value) {
		document.getElementById(field_name).value       = '';
	}
}

function setField(field_name, text_value) {
  if (document.getElementById(field_name).value == '' || document.getElementById(field_name).value == text_value) {
	document.getElementById(field_name).style.color = inactive_text_color;
	document.getElementById(field_name).value       = text_value;
  } else {
	document.getElementById(field_name).style.color = '';
  }
}

function activeField(field, text_value) {
  if (field.value == text_value) {
	  field.style.color = '';
	  field.value       = '';
  }
	}

function inactiveField(field, text_value) {
  if (field.value == '' || field.value == text_value) {
	  field.style.color = inactive_text_color;
	  field.value       = text_value;
  } else {
	  field.style.color = '';
  }
}

function removeElement(parentDiv, childDiv) {
  if (childDiv == parentDiv) {
    return false;
  } else if (document.getElementById(childDiv)) {
    var child  = document.getElementById(childDiv);
    var parent = document.getElementById(parentDiv);
    parent.removeChild(child);
  } else {
    return false;
  }
}

function insertValue(dVal, value) {
  if (!document.getElementById(dVal)) return;
  if (document.getElementById(dVal) && value) {
	document.getElementById(dVal).value = value;
	document.getElementById(dVal).style.color = '';
  } else {
	document.getElementById(dVal).value = '';
  }
}

function setCheckedValue(radioObj, newValue) {
  if (!radioObj) return;
  var radioLength = radioObj.length;
  if (radioLength == undefined) {
	radioObj.checked = (radioObj.value == newValue.toString());
	return;
  }
  for (var i = 0; i < radioLength; i++) {
	radioObj[i].checked = false;
	if(radioObj[i].value == newValue.toString()) {
	  radioObj[i].checked = true;
	}
  }
}

// Numeric functions
function d2h(d, padding) {
	if (isNaN(d)) d = parseInt(d);
    var hex = Number(d).toString(16);
    padding = typeof (padding) === "undefined" || padding === null ? padding = 2 : padding;
    while (hex.length < padding) hex = "0" + hex;
    return hex;
}

function h2d(h) {
  return parseInt(h, 16);
}

// date functions
function cleanDate(sDate) { // converts date from locale to mysql friendly yyyy-mm-dd
  var tmpArray = new Array();
  var keys  = date_format.split(date_delimiter);
  var parts = sDate.split(date_delimiter);
  for (var i=0; i<keys.length; i++) {
	switch (keys[i]) {
	  case 'Y': tmpArray[0] = parts[i]; break;
	  case 'm': tmpArray[1] = parts[i]; break;
	  case 'd': tmpArray[2] = parts[i]; break;
	}
  }
  return tmpArray.join('-');
}

function formatDate(sDate) { // converts date from mysql friendly yyyy-mm-dd to locale specific
  var tmpArray = new Array();
  var keys  = date_format.split(date_delimiter);
  var parts = sDate.split('-');
  for (var i=0; i<keys.length; i++) {
	switch (keys[i]) {
	  case 'Y': tmpArray[i] = parts[0]; break;
	  case 'm': tmpArray[i] = parts[1]; break;
	  case 'd': tmpArray[i] = parts[2]; break;
	}
  }
  return tmpArray.join(date_delimiter);
}

// Currency translation functions
function cleanCurrency(amount) {
  amount = amount.replace(new RegExp("["+thousands_point+"]", "g"), '');
  amount = amount.replace(new RegExp("["+decimal_point+"]", "g"), '.');
  return amount;
}

function formatCurrency(amount) { // convert to expected currency format
  // amount needs to be a string type with thousands separator ',' and decimal point dot '.'
  var factor  = Math.pow(10, decimal_places);
  var adj     = Math.pow(10, (decimal_places+2)); // to fix rounding (i.e. .1499999999 rounding to 0.14 s/b 0.15)
  var numExpr = parseFloat(amount);
  if (isNaN(numExpr)) return amount;
  numExpr     = Math.round((numExpr * factor) + (1/adj));
  var minus   = (numExpr < 0) ? '-' : '';
  numExpr     = Math.abs(numExpr);
  var decimal = (numExpr % factor).toString();
  while (decimal.length < decimal_places) decimal = '0' + decimal;
  var whole   = Math.floor(numExpr / factor).toString();
  for (var i = 0; i < Math.floor((whole.length-(1+i))/3); i++)
    whole = whole.substring(0,whole.length-(4*i+3)) + thousands_point + whole.substring(whole.length-(4*i+3));
  if (decimal_places > 0) {
    return minus + whole + decimal_point + decimal;
  } else {
	return minus + whole;
  }
}

function formatPrecise(amount) { // convert to expected currency format with the additional precision
  // amount needs to be a string type with thousands separator ',' and decimal point dot '.'
  var factor = Math.pow(10, decimal_precise);
  var numExpr = parseFloat(amount);
  if (isNaN(numExpr)) return amount;
  numExpr = Math.round(numExpr * factor);
  var minus = (numExpr < 0) ? '-' : '';
  numExpr = Math.abs(numExpr);
  var decimal = (numExpr % factor).toString();
  while (decimal.length < decimal_precise) decimal = '0' + decimal;
  var whole = Math.floor(numExpr / factor).toString();
  for (var i = 0; i < Math.floor((whole.length-(1+i))/3); i++)
    whole = whole.substring(0,whole.length-(4*i+3)) + thousands_point + whole.substring(whole.length-(4*i+3));
  if (decimal_precise > 0) {
    return minus + whole + decimal_point + decimal;
  } else {
	return minus + whole;
  }
}

function AlertError(MethodName,e)  {
  if (e.description == null) { alert(MethodName + " Exception: " + e.message); }
  else {  alert(MethodName + " Exception: " + e.description); }
}

// Chart functions
chartProps = new Object();
if (typeof google !== 'undefined') {
  google.load('visualization', '1.0', {'packages':['corechart']});
  google.setOnLoadCallback(drawChart);
}
function drawChart() {}

function phreedomChart() {
  var modID = chartProps.modID;
  var func  = chartProps.func;
  var d0    = chartProps.d0;
  fetchXml('index.php?module=phreedom&page=ajax&op=phreedom&action=chart&modID='+modID+'&fID='+func+'&d0='+d0, phreedomChartResp);
}

function phreedomChartResp(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  var title   = xmlText(xml, "title");
  var modalEl = document.getElementById(chartProps.divID + '_modal');
  if (modalEl) document.getElementById(chartProps.divID + '_modal_title').textContent = title;
  var data = new google.visualization.DataTable();
  data.addColumn('string', xmlText(xml, "label_text"));
  data.addColumn('number', xmlText(xml, "value_text"));
  var rowCnt = parseInt(xmlText(xml, "rowCnt"));
  var divID  = document.getElementById(chartProps.divID);
  data.addRows(rowCnt);
  rowCnt = 0;
  Array.from(xml.getElementsByTagName("chartData")).forEach(function(item) {
    data.setCell(rowCnt, 0, xmlText(item, "string"));
    data.setCell(rowCnt, 1, parseFloat(xmlText(item, "number")));
    rowCnt++;
  });
  var options = {'title':title, 'width':xmlText(xml, "width"), 'height':xmlText(xml, "height")};
  var chart;
  switch (xmlText(xml, "type")) {
    default:
    case 'pie':    chart = new google.visualization.PieChart(divID);    break;
    case 'bar':    chart = new google.visualization.BarChart(divID);    break;
    case 'column': chart = new google.visualization.ColumnChart(divID); break;
    case 'guage':  chart = new google.visualization.Guage(divID);       break;
    case 'line':   chart = new google.visualization.LineChart(divID);   break;
  }
  chart.draw(data, options);
  if (modalEl) new bootstrap.Modal(modalEl).show();
}

// ******************** functions used for combo box scripting ***********************************
var fActiveMenu = false;
var oOverMenu   = false;

if (document.images) { //pre-load images
 img_on      = new Image();
 img_on.src  = combo_image_on;
 img_off     = new Image();
 img_off.src = combo_image_off;
}

document.onmouseup = mouseSelect(0); // turns off the combo box if not hovering over

function mouseSelect(e) {
	if (fActiveMenu) {
		if (oOverMenu == false) {
			oOverMenu = false;
			document.getElementById(fActiveMenu).style.display = "none";
			fActiveMenu = false;
			return false;
		}
		return false;
	}
	return true;
}

function dropDownData(id, text) {
  this.id   = id;
  this.text = text;
}

function buildDropDown(selElement, data, defValue) {
  // build the dropdown
  for (var i=0; i<data.length; i++) {
	newOpt = document.createElement("option");
	newOpt.text = data[i].text;
	document.getElementById(selElement).options.add(newOpt);
	document.getElementById(selElement).options[i].value = data[i].id;
  }
  if (defValue != false) document.getElementById(selElement).value = defValue;
}

function htmlComboBox(name, values, defaultVal, parameters, width, onChange) {
  var field;
  field = '<input type="text" name="' + name + '" id="' + name + '" value="' + defaultVal + '" ' + parameters + '>';
  field += '<image name="imgName' + name + '" id="imgName' + name + '" src="' + icon_path + '16x16/phreebooks/pull_down_inactive.gif" height="16" width="16" align="absmiddle" style="border:none;" onMouseOver="handleOver(\'imgName' + name + '\'); return true;" onMouseOut="handleOut(\'imgName' + name + '\'); return true;" onclick="JavaScript:cbMmenuActivate(\'' + name + '\', \'combodiv' + name + '\', \'combosel' + name + '\', \'imgName' + name + '\')">';
  field += '<div id="combodiv' + name + '" style="position:absolute; display:none; top:0px; left:0px; z-index:5000" onmouseover="javascript:oOverMenu=\'combodiv' + name + '\';" onmouseout="javascript:oOverMenu=false;">';
  field += '<select size="10" id="combosel' + name + '" style="width:' + width + '; border-style:none" onclick="JavaScript:textSet(\'' + name + '\', this.value); ' + onChange + ';" onkeypress="JavaScript:comboKey(\'' + name + '\', this, event);">';
  field += '</select></div>';
  return field;
}

function cbMmenuActivate(idEdit, idMenu, idSel, idImg) {
  if (fActiveMenu) return mouseSelect(0);
//alert('idEdit = '+idEdit+' and idMenu = '+idMenu+' and idSel = '+idSel+' and idImg = '+idImg);
  oEdit = document.getElementById(idEdit);
  oMenu = document.getElementById(idMenu);
  oSel  = document.getElementById(idSel);
  oImg  = document.getElementById(idImg);
  nTop  = oEdit.offsetTop + oEdit.offsetHeight;
  nLeft = oEdit.offsetLeft;
  while (oEdit.offsetParent != document.body) {
    oEdit  = oEdit.offsetParent;
	nTop  += oEdit.offsetTop;
	nLeft += oEdit.offsetLeft;
  }
  oMenu.style.display = "";
  oMenu.style.top     = nTop + 'px';
  oMenu.style.left    = (nLeft - oSel.offsetWidth + oImg.offsetLeft + oImg.offsetWidth) + 'px';
  fActiveMenu = idMenu;
  document.getElementById(idSel).value = document.getElementById(idEdit).value;
  document.getElementById(idSel).focus();
  return false;
}

function textSet(idEdit, text) {
  document.getElementById(idEdit).value = text;
  oOverMenu = false;
  mouseSelect(0);
  document.getElementById(idEdit).focus();
}

function comboKey(idEdit, idSel, e) {
  var keyPressed;
  if(window.event) {
	keyPressed = window.event.keyCode; // IE hack
  } else {
	keyPressed = e.which; // standard method
  }
  if (keyPressed == 13 || keyPressed == 32) {
	textSet(idEdit,idSel.value);
  } else if (keyPressed == 27) {
	mouseSelect(0);
	document.getElementById(idEdit).focus();
  }
}

function handleOver(idImg) {
 if (document.images) document.getElementById(idImg).src=img_on.src;
}

function handleOut(idImg) {
 if (document.images) document.getElementById(idImg).src=img_off.src;
}

// ***************** START function to build html strings ******************************************
function buildIcon(imagePath, alt, params) {
    var image_html = '<img src="' + imagePath + '" alt="' + alt + '" title="' + alt + '" ' + params + ' />';
    return image_html;
}

// ***************** START has-this-document-been-changed tracking *********************************
//
// A document page (an order, an invoice, a receipt) may be opened just to look
// at it or to print it, and opening it must not rewrite it. Every caller that
// wants to know whether the person actually changed anything asks fbDocModified().
//
// The answer comes from comparing the form against a snapshot of itself rather
// than from watching for events, because most of what fills these forms never
// fires one: the AJAX loaders write straight into the fields with insertValue(),
// and so do the contact, inventory and terms pickers when a popup hands a value
// back. A signature over the whole form sees all of it, whoever wrote it.
//
// The snapshot cannot be taken at load: the form is empty then, and an AJAX
// response fills it a moment later. fbDocBaselineSoon() is therefore called at
// the end of each fill routine and defers to the end of the current task, and it
// gives up the moment the person touches the form, so a late-arriving response
// can never be mistaken for the state they started from.
//
// With no snapshot at all fbDocModified() answers true, and every caller saves as
// though the document changed - saving something unchanged is wasteful, but
// treating an edit as unchanged would throw the edit away.
//
var fb_doc_form     = null;  // the form being watched, once fbDocInit has run
var fb_doc_baseline = null;  // its signature as opened; null until established
var fb_doc_touched  = false; // has the person typed in, or clicked inside, it
// The two controls the answer itself is carried in, which would otherwise make
// every document look changed the moment it was asked about.
var fb_doc_ignore   = {'action': true, 'doc_modified': true};

function fbDocInit(form_name) {
  fb_doc_form     = document.forms[form_name];
  fb_doc_baseline = null;
  fb_doc_touched  = false;
  if (!fb_doc_form) return;
  // Capture phase, so a field's own handler cannot stop these from being seen.
  // A click is watched alongside the two that change a value, because a click is
  // what opens the contact, inventory and terms pickers, and a picker writes its
  // answer back later without an event of any kind.
  //
  // The cost of counting a click too eagerly is only that no snapshot is taken,
  // which reads as changed, so the document is saved as usual. The cost of
  // counting one too late is an edit treated as no edit. So
  // this errs early, every time, and never asks whether a snapshot exists yet.
  var touched = function() { fb_doc_touched = true; fbDocSyncIcons(); };
  fb_doc_form.addEventListener('input',  touched, true);
  fb_doc_form.addEventListener('change', touched, true);
  fb_doc_form.addEventListener('click',  touched, true);
}

function fbDocSignature() {
  if (!fb_doc_form) return null;
  var parts = [];
  for (var i = 0; i < fb_doc_form.elements.length; i++) {
	var el = fb_doc_form.elements[i];
	// === rather than truthy, so a field named after an Object member is not
	// mistaken for one of the two names above.
	if (!el.name || fb_doc_ignore[el.name] === true) continue;
	var value;
	switch (el.type) {
	  case 'checkbox':
	  case 'radio':  value = el.checked ? '1' : '0'; break;
	  case 'button':
	  case 'submit':
	  case 'reset':  continue;
	  default:       value = el.value;
	}
	parts.push(el.name + '\x1f' + value);
  }
  return parts.join('\x1e');
}

function fbDocBaseline() {
  fb_doc_baseline = fbDocSignature();
  fbDocSyncIcons();
}

function fbDocBaselineSoon() {
  if (fb_doc_touched) return;
  window.setTimeout(function() { if (!fb_doc_touched) fbDocBaseline(); }, 0);
}

function fbDocModified() {
  if (fb_doc_baseline === null) return true; // nothing to compare against
  return fbDocSignature() !== fb_doc_baseline;
}

// Has this document ever been written? A form that opened pre-filled from
// somewhere else - a sales order being invoiced, a receipt raised against an
// invoice - has every field set and nothing stored, and asking it whether it
// "changed" is the wrong question: it always needs saving.
function fbDocIsStored() {
  var id = fb_doc_form ? fb_doc_form.elements['id'] : null;
  return !!(id && id.value !== '' && id.value !== '0');
}

function fbDocNeedsSaving() {
  return !fbDocIsStored() || fbDocModified();
}

// Write the answer into the hidden field the server reads, for whichever action
// is about to be submitted. Called from submitToDo(), and by hand from the few
// places that submit the form themselves. Pages without the field are untouched.
function fbDocPublish() {
  var field = document.getElementById('doc_modified');
  if (field) field.value = fbDocModified() ? '1' : '0';
}

// May this person save this document? The Save icon is the answer, because it is
// where every rule about that already lives: the page hides it by security level,
// and the AJAX loaders remove it again once they know the document is an existing
// one (fillOrder, fillBill). Asking the icon is right in cases a value computed
// while rendering cannot be - on an order opened for edit the record is not known
// until the response arrives, long after the page was built.
function fbDocCanSave() {
  return !!document.getElementById('tb_icon_save');
}

// Grey the Save icon while there is nothing to save. The greying is only how the
// refusal is shown; saveDocument() on each page is the refusal itself.
function fbDocSyncIcons() {
  var icon = document.getElementById('tb_icon_save');
  if (!icon) return;
  if (fbDocNeedsSaving()) icon.classList.remove('fb-icon-disabled');
  else                    icon.classList.add('fb-icon-disabled');
}

// ***************** START function to set button pressed ******************************************
function showLoading() {
  document.getElementById('please_wait').style.display = 'block';
}

function hideLoading() {
  document.getElementById('please_wait').style.display = 'none';
}

function submitToDo(action, multi_submit) {
  if (!multi_submit) multi_submit = false;
  document.getElementById('action').value = action;
  // Tell the server whether this document was actually changed, so that an action
  // which only passes through it - print, email, take a payment, step to the next
  // one - does not re-post it. One hook here covers every action submitted this
  // way; the few places that submit the form themselves call fbDocPublish().
  fbDocPublish();
  if (!form_submitted && check_form() && !multi_submit) {
	showLoading();
	form_submitted = true;
	document.getElementById('action').form.submit();
  } else if (multi_submit) {
	document.getElementById('action').form.submit();
  }
}

function submitSeq(rowSeq, action, multi_submit) {
  if (!multi_submit) multi_submit = false;
  document.getElementById('rowSeq').value = rowSeq;
  submitToDo(action, multi_submit);
}

function submitSortOrder(sField, sOrder) {
  document.getElementById('sf').value = sField;
  document.getElementById('so').value = sOrder;
  document.getElementById('action').form.submit();
}

function searchPage(get_params) {
  var searchText = document.getElementById('search_text').value;
  location.href = 'index.php?'+get_params+'&search_text='+searchText;
}

function periodPage(get_params) {
  var searchPeriod = document.getElementById('search_period').value;
  location.href = 'index.php?'+get_params+'&search_period='+searchPeriod;
}

function jumpToPage(get_params) {
  var index = document.getElementById('list').selectedIndex;
  var pageNum = document.getElementById('list').options[index].value;
  location.href = 'index.php?'+get_params+'&list='+pageNum;
}

function checkEnter(e) {
  var keycode;
  if(window.event) { // IE
    keycode = event.keyCode;
  } else if (e.which) { // Netscape/Firefox/Opera
    keycode = e.which;
  }
  if (keycode == 13) {
	  document.getElementById('action').value = 'search';
	  document.getElementById('search_text').form.submit();
  }
}

// XML helper: returns text content of the first matching tag within xml/element
function xmlText(xml, tag) {
  var el = xml.getElementsByTagName ? xml.getElementsByTagName(tag)[0] : null;
  return el ? (el.textContent || '') : '';
}

// Fetch an XML endpoint and call callback(parsedDocument) on success
function fetchXml(url, callback) {
  fetch(url)
    .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
    .then(function(t) { callback(new DOMParser().parseFromString(t, 'text/xml')); })
    .catch(function(e) { console.error('fetchXml (' + url + '):', e); alert('Ajax Error: ' + e.message); });
}

// POST to an XML endpoint and call callback(parsedDocument) on success. For a
// request that changes something: index.php checks the csrf_token of every
// POST, which a plain GET from fetchXml() does not carry. params is an object
// of the fields to send.
function postXml(url, params, callback) {
  var body = new URLSearchParams(params || {});
  var meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) body.append('csrf_token', meta.getAttribute('content'));
  fetch(url, { method: 'POST', body: body })
    .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
    .then(function(t) { callback(new DOMParser().parseFromString(t, 'text/xml')); })
    .catch(function(e) { console.error('postXml (' + url + '):', e); alert('Ajax Error: ' + e.message); });
}

// Navigate by POST: build a form with these fields and the page's csrf_token,
// and submit it. For a link that changes something - a delete, a copy - which
// index.php will then accept, where a plain link could have been sent by
// anyone. target names a window to submit into (open it first, by that name).
function submitPost(url, fields, target) {
  var form = document.createElement('form');
  form.method = 'post';
  form.action = url;
  if (target) form.target = target;
  form.style.display = 'none';
  var all = Object.assign({}, fields || {});
  var meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) all.csrf_token = meta.getAttribute('content');
  Object.keys(all).forEach(function(k) {
    var input = document.createElement('input');
    input.type = 'hidden'; input.name = k; input.value = all[k];
    form.appendChild(input);
  });
  document.body.appendChild(form);
  form.submit();
}

// ajax wrappers
function parseXml(xml) {
  var debug = xmlText(xml, "debug");
  if (debug) alert(debug);
  var error = xmlText(xml, "error");
  if (error) { alert(error); return false; }
  return xml;
}

// ajax pair to reload tab on pages
function tabPage(subject, action, rID) {
  if (!subject) return;
  var url = 'index.php?module=phreedom&page=ajax&op=tab_details&mod='+module+'&subject='+subject+'&action='+action;
  // Changes are posted, so index.php can check their csrf_token; paging is a plain read.
  if (action == 'delete' || action == 'update') postXml(url, {rID: rID || 0}, processTabPage);
  else fetchXml(url+'&rID='+rID, processTabPage);
}

function processTabPage(xml) {
  xml = parseXml(xml);
  if (!xml) return;
  var subject = xmlText(xml, "subject");
  if (!subject) alert('no subject returned');
  var obj = document.getElementById(subject+'_content');
  obj.innerHTML = xmlText(xml, "htmlContents");
  var message = xmlText(xml, "message");
  if (message) alert(message);
}

/************************ Folder Navigation Functions **********************************/
function Toggle(item) {
  obj = document.getElementById(item);
  if (obj == null) {
    visible = false;
  } else {
    visible = (obj.style.display!="none");
  }
  key = document.getElementById("img" + item);
  if (visible) {
    obj.style.display="none";
    key.innerHTML="<img src='"+icon_path+"16x16/places/folder.png' width='16' height='16' hspace='0' vspace='0' border='0' />";
  } else {
    if (obj != null) obj.style.display = "block";
    key.innerHTML = "<img src='"+icon_path+"16x16/actions/document-open.png' width='16' height='16' hspace='0' vspace='0' border='0' />";
  }
  if (document.getElementById('id'))        document.getElementById('id').value          = '';
  if (document.getElementById('doc_title')) document.getElementById('doc_title').value   = '';
  if (document.getElementById('folder_path')) {
    var tempArray = item.split("_");
    strDir = (tempArray[2] == '0') ? '/' : build_path(tempArray[2]);
    document.getElementById('folder_path').value = strDir;
	document.getElementById('parent_id').value   = tempArray[2];
  }
}

function build_path(id) {
  var strTitle;
  strTitle = (id != '0') ? dir_idx[id]+'/' : '/';
  if (lvl_idx[id]) strTitle = build_path(lvl_idx[id]) + strTitle;
  return strTitle;
}

function Expand(tab_type) {
  divs = document.getElementsByTagName("DIV");
  for (var i=0; i<divs.length; i++) {
	div_id = divs[i].id;
	if (div_id.substr(0,3) == tab_type) {
	  divs[i].style.display = "block";
	  key = document.getElementById("img" + div_id);
	  key.innerHTML = "<img src='"+icon_path+"16x16/actions/document-open.png' width='16' height='16' hspace='0' vspace='0' border='0' />";
	}
  }
}

function Collapse(tab_type) {
  divs=document.getElementsByTagName("DIV");
  for (var i=0; i<divs.length; i++) {
	div_id = divs[i].id;
	if (div_id.substr(0,3) == tab_type) {
	  divs[i].style.display="none";
	  key = document.getElementById("img" + div_id);
	  key.innerHTML="<img src='"+icon_path+"16x16/places/folder.png' width='16' height='16' hspace='0' vspace='0' border='0' />";
	}
  }
}

// Inject CSRF token into every htmx POST automatically.
// Reads from the <meta name="csrf-token"> set by template_index.php.
document.addEventListener('htmx:configRequest', function(e) {
  if (e.detail.verb === 'post') {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) e.detail.parameters['csrf_token'] = meta.getAttribute('content');
  }
});
