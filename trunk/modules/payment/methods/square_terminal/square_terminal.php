<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
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
//  Path: /modules/payment/methods/square_terminal/square_terminal.php
//

define('MODULE_PAYMENT_SQUARE_TERMINAL_VERSION', '1.0');
require_once(DIR_FS_MODULES . 'payment/classes/payment.php');

class square_terminal extends payment {
  public $code        = 'square_terminal';
  public $title       = MODULE_PAYMENT_SQUARE_TERMINAL_TEXT_TITLE;
  public $description = MODULE_PAYMENT_SQUARE_TERMINAL_TEXT_DESCRIPTION;
  public $sort_order  = 10;

  public function __construct() {
    parent::__construct();
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN', 'default' => '',           'text' => MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID',  'default' => '',           'text' => MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_DEVICE_ID',    'default' => '',           'text' => MODULE_PAYMENT_SQUARE_TERMINAL_DEVICE_ID_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX',      'default' => 'Test',       'text' => MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_SEND',        'default' => 'Countdown',     'text' => MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_SEND_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_DEFAULT_PMT_TYPE', 'default' => 'CARD_PRESENT',  'text' => MODULE_PAYMENT_SQUARE_TERMINAL_DEFAULT_PMT_TYPE_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_PRINT',       'default' => 'Off',           'text' => MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_PRINT_DESC];
  }

  function configure($key) {
    switch ($key) {
      case 'MODULE_PAYMENT_SQUARE_TERMINAL_DEVICE_ID':
        $val = defined($key) ? constant($key) : '';
        $btn        = htmlspecialchars(MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_BTN);
        $creating   = json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_CREATING);
        $instruct   = json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_INSTRUCTIONS);
        $success    = json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_SUCCESS);
        $timeout    = json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_TIMEOUT);
        $errConfig  = json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_ERROR_CONFIG);
        $browseLbl  = json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_BROWSE_BTN);
        $browseLoad = json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_BROWSE_LOADING);
        $browseNone = json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_BROWSE_NONE);
        return html_input_field(strtolower($key), $val)
          . ' <button type="button" class="btn btn-sm btn-secondary" onclick="sqPairStart()">' . $btn . '</button>'
          . ' <button type="button" class="btn btn-sm btn-outline-secondary" onclick="sqBrowseDevices()">Browse Devices</button>'
          . ' <button type="button" class="btn btn-sm btn-outline-danger" onclick="sqUnpair()">Unpair</button>'
          . '<div id="sq_pair_code"  style="font-size:2em;font-weight:bold;margin:4px 0;display:none"></div>'
          . '<div id="sq_pair_status" style="margin-top:4px"></div>'
          . '<div id="sq_browse_list" style="margin-top:6px"></div>'
          . '<script>'
          . 'var sqPairCodeId="",sqPairPollTimer=null,sqPairTimeoutTimer=null;'
          . 'function sqPairStart(){'
          .   'var tok=document.getElementById("module_payment_square_terminal_access_token");'
          .   'var loc=document.getElementById("module_payment_square_terminal_location_id");'
          .   'if(!tok||!tok.value||!loc||!loc.value){'
          .     'document.getElementById("sq_pair_status").innerHTML=' . $errConfig . ';return;'
          .   '}'
          .   'document.getElementById("sq_browse_list").innerHTML="";'
          .   'document.getElementById("sq_pair_status").innerHTML=' . $creating . ';'
          .   'document.getElementById("sq_pair_code").style.display="none";'
          .   'fetch("index.php?module=payment&page=ajax&op=sq_term_pair_start").then(function(r){return r.json();}).then(function(r){'
          .     'if(r.error){document.getElementById("sq_pair_status").innerHTML=r.error;return;}'
          .     'sqPairCodeId=r.code_id;'
          .     'document.getElementById("sq_pair_code").innerText=r.code;'
          .     'document.getElementById("sq_pair_code").style.display="";'
          .     'document.getElementById("sq_pair_status").innerHTML=' . $instruct . ';'
          .     'sqPairPollTimer=setInterval(sqPairPoll,3000);'
          .     'sqPairTimeoutTimer=setTimeout(function(){'
          .       'clearInterval(sqPairPollTimer);'
          .       'document.getElementById("sq_pair_code").style.display="none";'
          .       'document.getElementById("sq_pair_status").innerHTML=' . $timeout . ';'
          .     '},360000);'
          .   '}).catch(function(){document.getElementById("sq_pair_status").innerHTML="Request failed.";});'
          . '}'
          . 'function sqPairPoll(){'
          .   'fetch("index.php?module=payment&page=ajax&op=sq_term_pair_status&code_id="+encodeURIComponent(sqPairCodeId)).then(function(r){return r.json();}).then(function(r){'
          .     'if(r.error){clearInterval(sqPairPollTimer);clearTimeout(sqPairTimeoutTimer);document.getElementById("sq_pair_code").style.display="none";document.getElementById("sq_pair_status").innerHTML=r.error;return;}'
          .     'if(r.status==="PAIRED"){'
          .       'clearInterval(sqPairPollTimer);clearTimeout(sqPairTimeoutTimer);'
          .       'document.getElementById("module_payment_square_terminal_device_id").value=r.device_id;'
          .       'document.getElementById("sq_pair_code").style.display="none";'
          .       'document.getElementById("sq_pair_status").innerHTML=' . $success . ';'
          .     '}'
          .   '});'
          . '}'
          . 'function sqUnpair(){'
          .   'if(!confirm("Unpair the terminal? It will return to the pairing screen."))return;'
          .   'var bl=document.getElementById("sq_pair_status");'
          .   'bl.innerHTML="Unpairing...";'
          .   'fetch("index.php?module=payment&page=ajax&op=sq_term_unpair").then(function(r){return r.json();}).then(function(r){'
          .     'if(r.error){bl.innerHTML="<span style=\'color:red\'>"+r.error+"</span>";return;}'
          .     'if(r.deleted>0){'
          .       'bl.innerHTML="Terminal unpaired. You can now pair a new terminal.";'
          .       'document.getElementById("module_payment_square_terminal_device_id").value="";'
          .     '}else{'
          .       'bl.innerHTML="No paired terminals found.";'
          .     '}'
          .   '}).catch(function(){bl.innerHTML="<span style=\'color:red\'>Request failed.</span>";});'
          . '}'
          . 'function sqBrowseDevices(){'
          .   'var bl=document.getElementById("sq_browse_list");'
          .   'bl.innerHTML=' . $browseLoad . ';'
          .   'fetch("index.php?module=payment&page=ajax&op=sq_term_list_devices").then(function(r){return r.json();}).then(function(r){'
          .     'if(r.error){bl.innerHTML="<span style=\'color:red\'>"+r.error+"</span>";return;}'
          .     'if(!r.devices||!r.devices.length){bl.innerHTML=' . $browseNone . ';return;}'
          .     'var h="<table class=\'table table-sm table-borderless\' style=\'margin-top:4px\'>";'
          .     'h+="<tr><th>ID</th><th>Serial</th><th>Name/Model</th><th>Status</th><th></th></tr>";'
          .     'r.devices.forEach(function(d){'
          .       'h+="<tr>";'
          .       'h+="<td><code>"+d.id+"</code></td>";'
          .       'h+="<td>"+d.serial_number+"</td>";'
          .       'h+="<td>"+(d.name||d.model||"")+"</td>";'
          .       'h+="<td>"+d.status+"</td>";'
          .       'h+="<td><button type=\'button\' class=\'btn btn-xs btn-sm btn-outline-primary\' onclick=\'sqSelectDevice("+JSON.stringify(d.id)+")\'>Use</button></td>";'
          .       'h+="</tr>";'
          .     '});'
          .     'h+="</table>";'
          .     'bl.innerHTML=h;'
          .   '}).catch(function(){bl.innerHTML="<span style=\'color:red\'>Request failed.</span>";});'
          . '}'
          . 'function sqSelectDevice(id){'
          .   'document.getElementById("module_payment_square_terminal_device_id").value=id;'
          .   'document.getElementById("sq_browse_list").innerHTML="";'
          . '}'
          . '</script>';
      case 'MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX':
        $opts = [['id' => 'Test', 'text' => TEXT_TEST], ['id' => 'Production', 'text' => TEXT_PRODUCTION]];
        return html_pull_down_menu(strtolower($key), $opts, defined($key) ? constant($key) : 'Test');
      case 'MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_SEND':
        $opts = [
          ['id' => 'Off',       'text' => 'Off — user clicks Send manually'],
          ['id' => 'Countdown', 'text' => 'Countdown (5s with Cancel)'],
          ['id' => 'On',        'text' => 'On — send immediately'],
        ];
        return html_pull_down_menu(strtolower($key), $opts, defined($key) ? constant($key) : 'Countdown');
      case 'MODULE_PAYMENT_SQUARE_TERMINAL_DEFAULT_PMT_TYPE':
        $opts = [
          ['id' => 'CARD_PRESENT',      'text' => MODULE_PAYMENT_SQUARE_TERMINAL_PMT_TYPE_CARD_PRESENT],
          ['id' => 'MANUAL_CARD_ENTRY', 'text' => MODULE_PAYMENT_SQUARE_TERMINAL_PMT_TYPE_MANUAL],
        ];
        return html_pull_down_menu(strtolower($key), $opts, defined($key) ? constant($key) : 'CARD_PRESENT');
      case 'MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_PRINT':
        $opts = [
          ['id' => 'Off', 'text' => 'Off'],
          ['id' => 'On',  'text' => 'On — open invoice PDF automatically after payment'],
        ];
        return html_pull_down_menu(strtolower($key), $opts, defined($key) ? constant($key) : 'Off');
      default:
        return parent::configure($key);
    }
  }

  function selection() {
    // J20 customer refunds: don't show the terminal UI; show a status message instead.
    if (defined('JOURNAL_ID') && JOURNAL_ID == 20) {
      $js = '<script>' . "\n"
          . 'document.addEventListener("DOMContentLoaded", function() {' . "\n"
          . '  function sqRefundUpdateStatus() {' . "\n"
          . '    var el  = document.getElementById("sq_refund_status");' . "\n"
          . '    if (!el) return;' . "\n"
          . '    var poi  = document.getElementById("purch_order_id");' . "\n"
          . '    var rfid = document.getElementById("refund_payment_id");' . "\n"
          . '    var poiVal  = poi  ? poi.value  : "";' . "\n"
          . '    var rfidVal = rfid ? rfid.value : "";' . "\n"
          . '    if (poiVal && /[^0-9.]/.test(poiVal)) {' . "\n"
          . '      el.textContent = ' . json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_REFUND_DONE) . ' + " " + poiVal;' . "\n"
          . '    } else if (rfidVal) {' . "\n"
          . '      el.textContent = ' . json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_REFUND_PENDING) . ';' . "\n"
          . '    } else {' . "\n"
          . '      el.textContent = "";' . "\n"
          . '    }' . "\n"
          . '  }' . "\n"
          . '  sqRefundUpdateStatus();' . "\n"
          // Intercept programmatic assignment to purch_order_id (fillBill after Open-list select).
          . '  var poiEl = document.getElementById("purch_order_id");' . "\n"
          . '  if (poiEl) {' . "\n"
          . '    var _d = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value");' . "\n"
          . '    Object.defineProperty(poiEl, "value", {' . "\n"
          . '      get: function() { return _d.get.call(this); },' . "\n"
          . '      set: function(v) { _d.set.call(this, v); sqRefundUpdateStatus(); }' . "\n"
          . '    });' . "\n"
          . '  }' . "\n"
          . '});' . "\n"
          . '</script>' . "\n";
      $html = '<div id="sq_refund_status" style="font-style:italic"></div>' . "\n" . $js;
      return ['id' => $this->code, 'page' => $this->title, 'fields' => [['title' => '', 'field' => $html]]];
    }

    // Pass PHP constants into JS via json_encode so they are properly escaped.
    $auto_send   = defined('MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_SEND')        ? MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_SEND        : 'Countdown';
    $default_pmt = defined('MODULE_PAYMENT_SQUARE_TERMINAL_DEFAULT_PMT_TYPE') ? MODULE_PAYMENT_SQUARE_TERMINAL_DEFAULT_PMT_TYPE : 'CARD_PRESENT';
    $auto_print  = defined('MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_PRINT')       ? MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_PRINT       : 'Off';

    $js_vars = '<script>' . "\n"
      . 'var sqTermStatusIdle        = ' . json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_IDLE)        . ';' . "\n"
      . 'var sqTermStatusSending     = ' . json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_SENDING)     . ';' . "\n"
      . 'var sqTermStatusWaiting     = ' . json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_WAITING)     . ';' . "\n"
      . 'var sqTermStatusCompleted   = ' . json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_COMPLETED)   . ';' . "\n"
      . 'var sqTermStatusCanceled    = ' . json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_CANCELED)    . ';' . "\n"
      . 'var sqTermCountdownTemplate = ' . json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_COUNTDOWN_SENDING)  . ';' . "\n"
      . 'var sqTermAutoSend          = ' . json_encode($auto_send)                                        . ';' . "\n"
      . 'var sqTermDefaultPmtType   = ' . json_encode($default_pmt)                                      . ';' . "\n"
      . 'var sqTermAutoPrint        = ' . json_encode($auto_print)                                       . ';' . "\n"
      . 'var sqTermCheckoutId    = "";' . "\n"
      . 'var sqTermPollTimer     = null;' . "\n"
      . 'var sqTermCountdownTimer = null;' . "\n"
      . "\n"

      // Countdown: ticks down from 5, auto-sends at 0, Cancel aborts it.
      . 'function sqTermStartCountdown() {' . "\n"
      . '  var secs = 5;' . "\n"
      . '  function tick() {' . "\n"
      . '    document.getElementById("sq_term_status").innerHTML = sqTermCountdownTemplate.replace("%s", secs);' . "\n"
      . '    if (secs <= 0) { sqTermSend(); return; }' . "\n"
      . '    secs--;' . "\n"
      . '    sqTermCountdownTimer = setTimeout(tick, 1000);' . "\n"
      . '  }' . "\n"
      . '  tick();' . "\n"
      . '}' . "\n"
      . 'function sqTermCancelCountdown() {' . "\n"
      . '  clearTimeout(sqTermCountdownTimer);' . "\n"
      . '  document.getElementById("sq_term_status").innerText = sqTermStatusIdle;' . "\n"
      . '}' . "\n"
      . "\n"

      // Send amount to the terminal device via the server-side AJAX proxy.
      . 'function sqTermSend() {' . "\n"
      . '  var raw = document.getElementById("total").value.replace(/[^0-9.\-]/g, "");' . "\n"
      . '  if (!raw || parseFloat(raw) <= 0) { alert("Please enter a valid total first."); return; }' . "\n"
      . '  var ptypeEl = document.querySelector("input[name=\'sq_term_ptype\']:checked");' . "\n"
      . '  var ptype = ptypeEl ? ptypeEl.value : sqTermDefaultPmtType;' . "\n"
      . '  document.getElementById("sq_term_send").disabled = true;' . "\n"
      . '  document.getElementById("square_terminal_field_0").value = "";' . "\n"
      . '  document.getElementById("sq_term_status").innerText = sqTermStatusSending;' . "\n"
      . '  fetch("index.php?module=phreebooks&page=ajax&op=sq_term_create&"+new URLSearchParams({amount:raw,payment_type:ptype})).then(function(r){return r.json();}).then(function(r) {' . "\n"
      . '    if (r.error) {' . "\n"
      . '      document.getElementById("sq_term_status").innerText = r.error;' . "\n"
      . '      document.getElementById("sq_term_send").disabled = false;' . "\n"
      . '      return;' . "\n"
      . '    }' . "\n"
      . '    sqTermCheckoutId = r.checkout_id;' . "\n"
      . '    document.getElementById("sq_term_status").innerText = sqTermStatusWaiting;' . "\n"
      . '    sqTermPollTimer = setInterval(sqTermPoll, 3000);' . "\n"
      . '  }).catch(function() {' . "\n"
      . '    document.getElementById("sq_term_status").innerText = "Error: request failed";' . "\n"
      . '    document.getElementById("sq_term_send").disabled = false;' . "\n"
      . '  });' . "\n"
      . '}' . "\n"
      . "\n"

      // Poll the checkout until COMPLETED or CANCELED.
      // On COMPLETED: stash the invoice ID in sessionStorage, then save J18.
      // The print popup opens on the subsequent page load (see DOMContentLoaded below)
      // because window.open from a setInterval callback is blocked by popup blockers.
      . 'function sqTermPoll() {' . "\n"
      . '  fetch("index.php?module=phreebooks&page=ajax&op=sq_term_status&checkout_id="+encodeURIComponent(sqTermCheckoutId)).then(function(r){return r.json();}).then(function(r) {' . "\n"
      . '    if (r.error) {' . "\n"
      . '      clearInterval(sqTermPollTimer);' . "\n"
      . '      document.getElementById("sq_term_status").innerText = r.error;' . "\n"
      . '      document.getElementById("sq_term_send").disabled = false;' . "\n"
      . '      return;' . "\n"
      . '    }' . "\n"
      . '    if (r.status === "COMPLETED") {' . "\n"
      . '      clearInterval(sqTermPollTimer);' . "\n"
      . '      document.getElementById("square_terminal_field_0").value = r.payment_id;' . "\n"
      . '      document.getElementById("sq_term_status").innerText = sqTermStatusCompleted;' . "\n"
      . '      var invId = new URLSearchParams(window.location.search).get("oID");' . "\n"
      . '      if (invId) {' . "\n"
      . '        sessionStorage.setItem("sqTermPrintInvId", invId);' . "\n"
      . '        sessionStorage.setItem("sqTermReturnUrl",' . "\n"
      . '          "index.php?module=phreebooks&page=orders&oID=" + encodeURIComponent(invId) + "&jID=12&action=edit");' . "\n"
      . '      }' . "\n"
      . '      submitToDo("save");' . "\n"
      . '    } else if (r.status === "CANCEL_REQUESTED" || r.status === "CANCELED") {' . "\n"
      . '      clearInterval(sqTermPollTimer);' . "\n"
      . '      document.getElementById("sq_term_status").innerText = sqTermStatusCanceled;' . "\n"
      . '      document.getElementById("sq_term_send").disabled = false;' . "\n"
      . '    }' . "\n"
      . '  });' . "\n"
      . '}' . "\n"
      . "\n"

      // On page load:
      //  1. If a pending print is stashed, open the invoice print popup.
      //  2. If a return URL is stashed (post-save redirect), go there immediately so
      //     the user lands on the paid J12 invoice rather than an empty J18 form.
      //     location.replace() keeps the empty J18 out of browser history.
      //  3. If arriving fresh from the invoice Payment button, stash the return URL
      //     and auto-send to the terminal.
      . 'document.addEventListener("DOMContentLoaded", function() {' . "\n"
      . '  var pendingPrint = sessionStorage.getItem("sqTermPrintInvId");' . "\n"
      . '  var returnUrl    = sessionStorage.getItem("sqTermReturnUrl");' . "\n"
      . '  if (pendingPrint) {' . "\n"
      . '    sessionStorage.removeItem("sqTermPrintInvId");' . "\n"
      . '    var printUrl = "index.php?module=phreeform&page=popup_gen&gID=cust:inv&date=a&xfld=journal_main.id&xcr=EQUAL&xmin=" + encodeURIComponent(pendingPrint);' . "\n"
      . '    if (sqTermAutoPrint === "On") printUrl += "&autoprint=1";' . "\n"
      . '    var printWin = window.open(printUrl, "reportFilter", "width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");' . "\n"
      . '    if (printWin) printWin.focus();' . "\n"
      . '  }' . "\n"
      . '  if (returnUrl) {' . "\n"
      . '    sessionStorage.removeItem("sqTermReturnUrl");' . "\n"
      . '    setTimeout(function() { window.location.replace(returnUrl); }, 300);' . "\n"
      . '    return;' . "\n"
      . '  }' . "\n"
      . '  var urlParams = new URLSearchParams(window.location.search);' . "\n"
      . '  if (urlParams.get("action") === "pmt") {' . "\n"
      . '    var sel = document.getElementById("shipper_code");' . "\n"
      . '    if (sel && sel.value === "square_terminal") {' . "\n"
      . '      if (sqTermAutoSend === "On") {' . "\n"
      . '        setTimeout(sqTermSend, 800);' . "\n"
      . '      } else if (sqTermAutoSend === "Countdown") {' . "\n"
      . '        setTimeout(sqTermStartCountdown, 500);' . "\n"
      . '      }' . "\n"
      . '    }' . "\n"
      . '  }' . "\n"
      // Disable Send button when viewing an existing paid receipt.
      // Covers both page-load (field_0 already set) and AJAX-loaded edits via Open list.
      . '  function sqTermCheckSendState() {' . "\n"
      . '    var btn = document.getElementById("sq_term_send");' . "\n"
      . '    var f0  = document.getElementById("square_terminal_field_0");' . "\n"
      . '    if (btn && f0) {' . "\n"
      . '      btn.disabled = !!f0.value;' . "\n"
      . '      btn.title    = f0.value ? ' . json_encode(MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_PAID) . ' : "";' . "\n"
      . '    }' . "\n"
      . '  }' . "\n"
      . '  sqTermCheckSendState();' . "\n"
      . '  var f0El = document.getElementById("square_terminal_field_0");' . "\n"
      . '  if (f0El) {' . "\n"
      . '    var _d2 = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value");' . "\n"
      . '    Object.defineProperty(f0El, "value", {' . "\n"
      . '      get: function() { return _d2.get.call(this); },' . "\n"
      . '      set: function(v) { _d2.set.call(this, v); sqTermCheckSendState(); }' . "\n"
      . '    });' . "\n"
      . '  }' . "\n"
      . '});' . "\n"
      . '</script>';

    $cp_checked  = ($default_pmt === 'CARD_PRESENT')      ? ' checked' : '';
    $man_checked = ($default_pmt === 'MANUAL_CARD_ENTRY') ? ' checked' : '';
    $html = '<input type="hidden" id="square_terminal_field_0" name="square_terminal_field_0" value="">' . "\n"
          . '<div class="mb-1">'
          .   '<div class="form-check">'
          .     '<input class="form-check-input" type="radio" name="sq_term_ptype" id="sq_ptype_card"   value="CARD_PRESENT"      autocomplete="off"' . $cp_checked  . '>'
          .     '<label class="form-check-label" for="sq_ptype_card">'   . htmlspecialchars(MODULE_PAYMENT_SQUARE_TERMINAL_PMT_TYPE_CARD_PRESENT) . '</label>'
          .   '</div>'
          .   '<div class="form-check">'
          .     '<input class="form-check-input" type="radio" name="sq_term_ptype" id="sq_ptype_manual" value="MANUAL_CARD_ENTRY" autocomplete="off"' . $man_checked . '>'
          .     '<label class="form-check-label" for="sq_ptype_manual">' . htmlspecialchars(MODULE_PAYMENT_SQUARE_TERMINAL_PMT_TYPE_MANUAL)       . '</label>'
          .   '</div>'
          . '</div>' . "\n"
          . '<div id="sq_term_status" style="margin-top:8px;margin-bottom:6px;font-style:italic">'
          .   htmlspecialchars(MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_IDLE)
          . '</div>' . "\n"
          . '<button type="button" id="sq_term_send" class="btn btn-primary btn-sm" onclick="sqTermSend()">'
          .   htmlspecialchars(MODULE_PAYMENT_SQUARE_TERMINAL_BTN_SEND)
          . '</button>' . "\n"
          . $js_vars;

    return ['id' => $this->code, 'page' => $this->title, 'fields' => [['title' => '', 'field' => $html]]];
  }

  // Blocks form submission until field_0 is populated by sqTermPoll.
  function javascript_validation() {
    $err = addslashes(MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NOT_CONFIRMED);
    return '  if (payment_method == "square_terminal") {' . "\n"
         . '    if (!document.getElementById("square_terminal_field_0").value) {' . "\n"
         . '      error_message += "' . $err . '\n";' . "\n"
         . '      error = 1;' . "\n"
         . '    }' . "\n"
         . '  }' . "\n";
  }

  // Called inside the DB transaction after the journal entry is written.
  // Verifies the Square payment_id is genuinely COMPLETED before committing.
  // Stores receipt_number:payment_id so purch_order_id gets the short human-readable
  // receipt number while the full payment_id is preserved in the item description.
  function before_process() {
    global $messageStack;
    $payment_id = trim($this->field_0);
    if (!$payment_id) {
      $messageStack->add(MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NOT_CONFIRMED, 'error');
      return true;
    }
    $receipt_number = $this->verify_square_payment($payment_id);
    if ($receipt_number === false) {
      $messageStack->add(MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_VERIFY, 'error');
      return true;
    }
    $this->payment_fields = $receipt_number . ':' . $payment_id;
    return false;
  }

  function refund($payment_id, $amount_minor, $currency) {
    $token = defined('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN') ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN) : '';
    if (!$token || !$payment_id || $amount_minor <= 0) return false;
    $payload = json_encode([
      'idempotency_key' => bin2hex(random_bytes(16)),
      'payment_id'      => $payment_id,
      'amount_money'    => ['amount' => $amount_minor, 'currency' => $currency],
    ]);
    $ch = curl_init($this->square_base_url() . 'refunds');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $payload,
      CURLOPT_HTTPHEADER     => $this->square_headers(),
      CURLOPT_TIMEOUT        => 20,
    ]);
    $body = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    if (!$body) { $this->refund_error = $curl_error ?: 'No response from Square'; return false; }
    $data = json_decode($body, true);
    if (isset($data['errors'])) {
      $e = $data['errors'][0];
      $this->refund_error = ($e['detail'] ?? $e['code'] ?? 'Unknown Square error');
      return false;
    }
    return $data['refund']['id'] ?? true;
  }

  private function square_base_url() {
    $sandbox = defined('MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX') && MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX === 'Test';
    return $sandbox ? 'https://connect.squareupsandbox.com/v2/' : 'https://connect.squareup.com/v2/';
  }

  private function square_headers() {
    $token = defined('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN') ? MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN : '';
    return [
      'Authorization: Bearer ' . $token,
      'Content-Type: application/json',
      'Square-Version: 2024-01-17',
    ];
  }

  // Returns the receipt_number string on success, false on failure.
  private function verify_square_payment($payment_id) {
    $token = defined('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN') ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN) : '';
    if (!$token) return false;
    $ch = curl_init($this->square_base_url() . 'payments/' . urlencode($payment_id));
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => $this->square_headers(),
      CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if (!$body) return false;
    $data = json_decode($body, true);
    if (!isset($data['payment']['status']) || $data['payment']['status'] !== 'COMPLETED') return false;
    return $data['payment']['receipt_number'] ?? $payment_id;
  }
}
?>
