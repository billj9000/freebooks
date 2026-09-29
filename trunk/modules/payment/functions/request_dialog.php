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
//  Path: /modules/payment/functions/request_dialog.php
//
// The Request payment dialog, for a page that offers it on an invoice. The page
// calls payment_request_dialog() once, and each invoice's button or icon calls
// payRequestOpen(<journal_main id>) in the browser. What the dialog shows comes
// from modules/payment/ajax/request_payment.php.

// Whether the user may ask for payment: the Customer Receipts permission at the
// level that can enter one.
function payment_request_allowed() {
  return defined('SECURITY_ID_CUSTOMER_RECEIPTS')
      && (int)($_SESSION['admin_security'][SECURITY_ID_CUSTOMER_RECEIPTS] ?? 0) >= 2;
}

// The dialog's markup and opener, printed once per page.
function payment_request_dialog() {
  if (!payment_request_allowed()) return '';
  $loading = '<div class="modal-body text-center text-muted py-4">&hellip;</div>';
  return '<div class="modal fade" id="payRequestModal" tabindex="-1" aria-hidden="true">'
       . '<div class="modal-dialog modal-lg"><div class="modal-content" id="pay-request-content">' . $loading . '</div></div></div>'
       . '<script>'
       . 'function payRequestOpen(oID) {'
       . '  var content = document.getElementById("pay-request-content");'
       . '  content.innerHTML = ' . json_encode($loading) . ';'
       . '  bootstrap.Modal.getOrCreateInstance(document.getElementById("payRequestModal")).show();'
       . '  htmx.ajax("GET", "index.php?module=payment&page=ajax&op=request_payment&oID=" + encodeURIComponent(oID),'
       . '            {target: "#pay-request-content", swap: "innerHTML"});'
       . '}'
       . '</script>';
}
