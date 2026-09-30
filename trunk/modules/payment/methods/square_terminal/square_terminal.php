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
  // Square takes payments only in its location's currency, and refuses any
  // other (update() warns when that is not the company's). So an entry in
  // another currency is stopped here, before the terminal or a request is tried.
  public $multi_currency = false;
  public $code        = 'square_terminal';
  public $title       = MODULE_PAYMENT_SQUARE_TERMINAL_TEXT_TITLE;
  public $description = MODULE_PAYMENT_SQUARE_TERMINAL_TEXT_DESCRIPTION;
  public $sort_order  = 10;
  const SQUARE_VERSION = '2024-01-17';
  private $last_error = '';
  public function get_last_error() { return $this->last_error; }

  public function __construct() {
    parent::__construct();
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN', 'default' => '',           'text' => MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID',  'default' => '',           'text' => MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_DEVICE_ID',    'default' => '',           'text' => MODULE_PAYMENT_SQUARE_TERMINAL_DEVICE_ID_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX',      'default' => 'Test',       'text' => MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_SEND',        'default' => 'Countdown',     'text' => MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_SEND_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_DEFAULT_PMT_TYPE', 'default' => 'CARD_PRESENT',  'text' => MODULE_PAYMENT_SQUARE_TERMINAL_DEFAULT_PMT_TYPE_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_PRINT',       'default' => 'Off',           'text' => MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_PRINT_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_EMAIL',     'default' => '',              'text' => MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_EMAIL_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_WEBHOOK_KEY',      'default' => '',              'text' => MODULE_PAYMENT_SQUARE_TERMINAL_WEBHOOK_KEY_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_SQUARE_TERMINAL_WEBHOOK_URL',      'default' => '',              'text' => MODULE_PAYMENT_SQUARE_TERMINAL_WEBHOOK_URL_DESC];
    $this->request_types = [
      'link'    => MODULE_PAYMENT_SQUARE_TERMINAL_REQUEST_LINK,
      'invoice' => MODULE_PAYMENT_SQUARE_TERMINAL_REQUEST_INVOICE,
    ];
  }

  // Saves the settings, then asks Square which currency the location takes. The
  // settings posted are the ones checked: the constants still hold the old ones.
  function update() {
    global $messageStack;
    parent::update();
    $token    = trim((string)($_POST['module_payment_square_terminal_access_token'] ?? ''));
    $location = trim((string)($_POST['module_payment_square_terminal_location_id'] ?? ''));
    $sandbox  = ($_POST['module_payment_square_terminal_sandbox'] ?? '') === 'Test';
    if ($token === '' || $location === '') return;
    $base = $sandbox ? 'https://connect.squareupsandbox.com/v2/' : 'https://connect.squareup.com/v2/';
    $data = $this->square_request('locations/' . rawurlencode($location), null, 'GET', $base, $token);
    $currency = $data['location']['currency'] ?? '';
    if ($currency === '') {
      $messageStack->add(sprintf(MODULE_PAYMENT_SQUARE_TERMINAL_CURRENCY_UNKNOWN, $this->last_error), 'caution');
    } elseif ($currency !== DEFAULT_CURRENCY) {
      $messageStack->add(sprintf(MODULE_PAYMENT_SQUARE_TERMINAL_CURRENCY_WARNING, $currency, DEFAULT_CURRENCY), 'error');
    }
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
      // The total on screen is in the ENTRY's currency, so the terminal has to be
      // told which one - it was charging every amount as the company's (#63).
      . '  var curEl = document.getElementById("currencies_code");' . "\n"
      . '  var cur = curEl ? curEl.value : "";' . "\n"
      . '  fetch("index.php?module=phreebooks&page=ajax&op=sq_term_create&"+new URLSearchParams({amount:raw,payment_type:ptype,currency:cur})).then(function(r){return r.json();}).then(function(r) {' . "\n"
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
      . '    var printUrl = "' . gen_print_form_url('cust:inv', 'journal_main.id', 'EQUAL') . '" + encodeURIComponent(pendingPrint);' . "\n"
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
    if (!$body) { $this->refund_error = $curl_error ?: 'No response from Square'; return false; }
    $data = json_decode($body, true);
    if (isset($data['errors'])) {
      $e = $data['errors'][0];
      $this->refund_error = ($e['detail'] ?? $e['code'] ?? 'Unknown Square error');
      return false;
    }
    return $data['refund']['id'] ?? true;
  }

  // ─── Requests for payment ─────────────────────────────────────────────────

  // Ask the customer to pay $amount of the invoice $invoice_id, by a Square
  // payment link or by an invoice Square emails them. Both are backed by a
  // Square order, and the order's id is what the request is recorded under:
  // the payment that settles either one carries it, so the webhook needs one
  // lookup for both. See payment::create_request().
  function create_request($type, $invoice_id, $amount, $currency, $email) {
    global $db;
    $inv = $db->Execute("SELECT purchase_invoice_id, bill_primary_name, total_amount, currencies_value FROM " . TABLE_JOURNAL_MAIN
      . " WHERE id = " . (int)$invoice_id . " AND journal_id = 12");
    if ($inv->EOF) {
      $this->last_error = MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_INVOICE;
      return false;
    }
    $location = defined('MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID') ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID) : '';
    if ($location === '') {
      $this->last_error = MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_LOCATION;
      return false;
    }
    $description = 'Invoice ' . $inv->fields['purchase_invoice_id'];
    // Items are listed only when the whole invoice is asked for; any other
    // amount is one line, since items adding up to more than is asked would
    // bill the customer for all of them.
    $rate  = (float)$inv->fields['currencies_value'] ?: 1.0;
    $whole = abs(round((float)$amount, 2) - round((float)$inv->fields['total_amount'] * $rate, 2)) < 0.005;
    if (!$whole) $description = sprintf(MODULE_PAYMENT_SQUARE_TERMINAL_PART_PAYMENT, $description);
    $order = $this->square_order($whole ? $invoice_id : 0, $amount, $currency, $rate, $description, $location);

    switch ($type) {
      case 'link':
        // The buyer's email is not filled in for them: Square refuses the whole
        // link over an address it does not like, and the customer types it anyway.
        $made = $this->square_request('online-checkout/payment-links', [
          'idempotency_key'    => bin2hex(random_bytes(16)),
          'description'        => $description,
          'order'              => $order,
        ]);
        $link = $made['payment_link'] ?? null;
        if (!$link || empty($link['order_id']) || empty($link['url'])) return false;
        $this->record_request('link', $link['order_id'], $invoice_id, $amount, $currency, $link['url'], null, $link['id'] ?? null);
        return ['external_id' => $link['order_id'], 'url' => $link['url'], 'sent' => false];

      case 'invoice':
        $customer_id = $this->square_customer($inv->fields['bill_primary_name'], $email);
        if (!$customer_id) return false;
        $order['customer_id'] = $customer_id;
        $made = $this->square_request('orders', ['idempotency_key' => bin2hex(random_bytes(16)), 'order' => $order]);
        $order_id = $made['order']['id'] ?? '';
        if ($order_id === '') return false;
        $made = $this->square_request('invoices', [
          'idempotency_key' => bin2hex(random_bytes(16)),
          'invoice' => [
            'location_id'       => $location,
            'order_id'          => $order_id,
            'primary_recipient' => ['customer_id' => $customer_id],
            'payment_requests'  => [['request_type' => 'BALANCE', 'due_date' => date('Y-m-d')]],
            'delivery_method'   => 'EMAIL',
            'accepted_payment_methods' => ['card' => true, 'square_gift_card' => false, 'bank_account' => false,
                                           'buy_now_pay_later' => false, 'cash_app_pay' => false],
            'title'             => $description,
          ],
        ]);
        $invoice = $made['invoice'] ?? null;
        if (!$invoice || empty($invoice['id'])) return false;
        $this->record_request('invoice', $order_id, $invoice_id, $amount, $currency, null, null, $invoice['id']);
        $published = $this->square_request('invoices/' . rawurlencode($invoice['id']) . '/publish', [
          'idempotency_key' => bin2hex(random_bytes(16)),
          'version'         => (int)$invoice['version'],
        ]);
        if (empty($published['invoice']['id'])) {
          // Square holds it as a draft the customer never saw; it cannot be paid.
          $db->Execute("UPDATE " . TABLE_PAYMENT_REQUESTS . " SET status = 'failed'"
            . " WHERE method = '" . db_input($this->code) . "' AND external_id = '" . db_input($order_id) . "'");
          return false;
        }
        $url = $published['invoice']['public_url'] ?? '';
        if ($url !== '') {
          $db->Execute("UPDATE " . TABLE_PAYMENT_REQUESTS . " SET url = '" . db_input($url) . "'"
            . " WHERE method = '" . db_input($this->code) . "' AND external_id = '" . db_input($order_id) . "'");
        }
        return ['external_id' => $order_id, 'url' => $url ?: null, 'sent' => true];
    }
    return false;
  }

  // Withdraw an open request: delete a payment link, or cancel an invoice
  // Square has not been paid. Either way the customer can no longer pay it.
  // See payment::cancel_request().
  function cancel_request($request) {
    $ref = (string)($request['external_ref'] ?? '');
    if ($ref === '') return false;
    if ($request['request_type'] === 'link') {
      return $this->square_request('online-checkout/payment-links/' . rawurlencode($ref), null, 'DELETE') !== false;
    }
    if ($request['request_type'] === 'invoice') {
      $read = $this->square_request('invoices/' . rawurlencode($ref), null, 'GET');
      if (!in_array($read['invoice']['status'] ?? '', ['UNPAID', 'SCHEDULED'], true)) return false;
      return $this->square_request('invoices/' . rawurlencode($ref) . '/cancel',
        ['version' => (int)$read['invoice']['version']]) !== false;
    }
    return false;
  }

  // Email the customer a Square payment link. See payment::email_request().
  function email_request($request, $email) {
    $url  = (string)($request['url'] ?? '');
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if ($request['request_type'] !== 'link' || parse_url($url, PHP_URL_SCHEME) !== 'https'
        || !in_array($host, ['square.link', 'sandbox.square.link'], true)) {
      $this->last_error = MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_BAD_LINK;
      return false;
    }
    return $this->email_payment_link($request, $email, [
      'subject'  => MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_SUBJECT,
      'intro'    => MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_INTRO,
      'button'   => MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_BTN,
      'expires'  => '',
      'alt_link' => MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_ALT_LINK,
      'contact'  => MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_CONTACT,
    ], '#006aff');
  }

  /**
   * The Square order for a request: the invoice's lines when $invoice_id is
   * given, else one line of $description. Its total is always exactly $amount.
   * Square counts money in the currency's smallest unit, so every figure is
   * converted once, here, and the lines are closed to $amount with a rounding
   * line or the discount. A gap too big to be rounding means the lines do not
   * describe what is asked, and the order falls back to the one line.
   *
   * A function of its own so that what it produces can be checked without
   * creating anything on Square.
   */
  function square_order($invoice_id, $amount, $currency, $rate, $description, $location) {
    global $currencies;
    $dp     = (int)($currencies->currencies[$currency]['decimal_places'] ?? 2);
    $minor  = function ($x) use ($dp) { return (int)round((float)$x * pow(10, $dp)); };
    $money  = function ($m) use ($currency) { return ['amount' => $m, 'currency' => $currency]; };
    $want   = $minor($amount);
    $single = [['name' => $description, 'quantity' => '1', 'base_price_money' => $money($want)]];

    $items = [];
    $sum = $discount = 0;
    if ((int)$invoice_id > 0) {
      $read = $this->invoice_lines($invoice_id, $rate);
      foreach ($read['lines'] as $line) {
        // A whole quantity is sent as it is; a fractional one as a single line
        // at its total, since Square would round the unit price times it again.
        $q = $line['qty'];
        if (abs($q - round($q)) < 0.00001) {
          $qty = (int)round($q); $unit = $minor($line['unit']); $name = $line['name'];
        } else {
          $qty = 1; $unit = $minor($line['unit'] * $q);
          $name = $line['name'] . ' (x' . rtrim(rtrim(number_format($q, 4, '.', ''), '0'), '.') . ')';
        }
        $items[] = ['name' => mb_substr($name, 0, 500), 'quantity' => (string)$qty, 'base_price_money' => $money($unit)];
        $sum += $unit * $qty;
      }
      $discount = $minor($read['discount']);
    }
    $gap = $want - ($sum - $discount);
    if (!$items || abs($gap) > 5) {
      $items = $single; $discount = 0;
    } elseif ($gap > 0) {
      $items[] = ['name' => 'Rounding adjustment', 'quantity' => '1', 'base_price_money' => $money($gap)];
    } elseif ($gap < 0) {
      $discount -= $gap;
    }
    $order = ['location_id' => $location, 'reference_id' => mb_substr($description, 0, 40), 'line_items' => $items];
    if ($discount > 0) {
      $order['discounts'] = [['uid' => 'fb-discount', 'name' => 'Discount', 'type' => 'FIXED_AMOUNT',
                              'amount_money' => $money($discount), 'scope' => 'ORDER']];
    }
    return $order;
  }

  // The Square customer with $email, added when there is none, as an id; or
  // false. A Square invoice must be addressed to a customer Square knows.
  private function square_customer($name, $email) {
    if ($email !== '') {
      $found = $this->square_request('customers/search', ['query' => ['filter' => ['email_address' => ['exact' => $email]]], 'limit' => 1]);
      if ($found === false) return false;
      if (!empty($found['customers'][0]['id'])) return $found['customers'][0]['id'];
    }
    $made = $this->square_request('customers', [
      'idempotency_key' => bin2hex(random_bytes(16)),
      'company_name'    => mb_substr((string)$name, 0, 255),
      'email_address'   => $email,
    ]);
    if (empty($made['customer']['id'])) {
      $this->last_error = sprintf(MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_CUSTOMER, $this->last_error);
      return false;
    }
    return $made['customer']['id'];
  }

  // A call to the Square API: the decoded answer, or false with the reason in
  // get_last_error(). $base and $token default to this company's settings;
  // update() passes the ones just posted.
  function square_request($path, $body = null, $method = 'POST', $base = null, $token = null) {
    $token = $token ?? (defined('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN') ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN) : '');
    if ($token === '') { $this->last_error = MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_TOKEN; return false; }
    $ch = curl_init(($base ?? $this->square_base_url()) . $path);
    $opts = [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST  => $method,
      CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Square-Version: ' . self::SQUARE_VERSION],
      CURLOPT_TIMEOUT        => 20,
    ];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    curl_setopt_array($ch, $opts);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false || $raw === '') { $this->last_error = curl_error($ch) ?: 'No response from Square'; return false; }
    $data = json_decode($raw, true);
    if (!empty($data['errors']) || $code >= 400) {
      $e = $data['errors'][0] ?? [];
      $this->last_error = 'Square: ' . ($e['detail'] ?? $e['code'] ?? ('HTTP ' . $code));
      error_log('square_terminal: ' . $method . ' ' . $path . ' failed: ' . $this->last_error);
      return false;
    }
    return $data;
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
      'Square-Version: ' . self::SQUARE_VERSION,
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
    if (!$body) return false;
    $data = json_decode($body, true);
    if (!isset($data['payment']['status']) || $data['payment']['status'] !== 'COMPLETED') return false;
    return $data['payment']['receipt_number'] ?? $payment_id;
  }
}
?>
