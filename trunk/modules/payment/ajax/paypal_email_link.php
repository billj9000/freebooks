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
//  Path: /modules/payment/ajax/paypal_email_link.php
//
// Emails a PayPal payment link to a customer using FreeBooks' PHPMailer infrastructure.
// Sends HTML when EMAIL_USE_HTML + ADMIN_EXTRA_EMAIL_FORMAT == 'HTML'; plain text otherwise.
// Attaches a PDF copy of the linked Sales Invoice when fb_invoice_id is provided.

/**************   Check user security   *****************************/
$security_level = validate_ajax_user();

/**************   Read and validate inputs   ************************/
$email       = isset($_POST['email'])       ? trim($_POST['email'])                   : '';
$url         = isset($_POST['url'])         ? trim($_POST['url'])                     : '';
$description = isset($_POST['description']) ? trim(strip_tags($_POST['description'])) : '';
$invoice_id  = isset($_POST['invoice_id'])  ? (int)$_POST['invoice_id']              : 0;

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['success' => false, 'error' => 'A valid email address is required.']);
  die;
}
$_parsed_url  = parse_url($url);
$_parsed_host = isset($_parsed_url['host']) ? strtolower($_parsed_url['host']) : '';
$_pp_hosts    = ['www.paypal.com', 'paypal.com', 'www.sandbox.paypal.com', 'sandbox.paypal.com'];
if (!$url || ($_parsed_url['scheme'] ?? '') !== 'https' || !in_array($_parsed_host, $_pp_hosts, true)) {
  echo json_encode(['success' => false, 'error' => 'Invalid payment link.']);
  die;
}

/**************   Load language strings   ***************************/
gen_pull_language('payment', 'paypal');

/**************   Generate PDF attachment (best-effort)   ***********/
$attachments_list = [];
$temp_pdf_path    = null;
if ($invoice_id > 0) {
  $pdf_data = _paypal_build_invoice_pdf($invoice_id);
  if (is_array($pdf_data) && !empty($pdf_data['pdf'])) {
    $temp_dir  = DIR_FS_MY_FILES . $_SESSION['company'] . '/temp/';
    $temp_name = 'invoice_' . $invoice_id . '_' . time() . '.pdf';
    $temp_pdf_path = $temp_dir . $temp_name;
    if (@file_put_contents($temp_pdf_path, $pdf_data['pdf']) !== false) {
      $attachments_list['file'] = $temp_pdf_path;
    } else {
      $temp_pdf_path = null; // failed to write; skip attachment
    }
  }
}

/**************   Build email   *************************************/
$from_name    = defined('STORE_NAME') ? STORE_NAME : 'FreeBooks';
$from_address = defined('EMAIL_FROM') ? EMAIL_FROM : '';

$subject_tpl  = defined('MODULE_PAYMENT_PAYPAL_EMAIL_SUBJECT') ? MODULE_PAYMENT_PAYPAL_EMAIL_SUBJECT : 'Payment link from %s';
$subject      = sprintf($subject_tpl, $from_name);
if ($description) $subject .= ' — ' . $description;

$intro     = defined('MODULE_PAYMENT_PAYPAL_EMAIL_INTRO')    ? MODULE_PAYMENT_PAYPAL_EMAIL_INTRO    : 'Please use the link below to pay securely by card or digital wallet — no PayPal account required.';
$expires   = defined('MODULE_PAYMENT_PAYPAL_EMAIL_EXPIRES')  ? MODULE_PAYMENT_PAYPAL_EMAIL_EXPIRES  : 'Please pay within 3 hours — this link will expire after that. If it has expired, please contact us for a new one.';
$btn_label = defined('MODULE_PAYMENT_PAYPAL_EMAIL_BTN')      ? MODULE_PAYMENT_PAYPAL_EMAIL_BTN      : 'Pay Now';
$alt_link  = defined('MODULE_PAYMENT_PAYPAL_EMAIL_ALT_LINK') ? MODULE_PAYMENT_PAYPAL_EMAIL_ALT_LINK : 'Or copy this link into your browser:';
$contact   = defined('MODULE_PAYMENT_PAYPAL_EMAIL_CONTACT')  ? MODULE_PAYMENT_PAYPAL_EMAIL_CONTACT  : 'If you have any questions, please contact us.';

$safe_url  = htmlspecialchars($url, ENT_QUOTES);
$safe_from = htmlspecialchars($from_name, ENT_QUOTES);
$safe_desc = $description ? htmlspecialchars($description, ENT_QUOTES) : '';

// validate_send_mail() uses this as HTML body when EMAIL_USE_HTML is on,
// or strip_tags() it automatically for plain-text delivery.
$body = '<html><body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333;margin:0;padding:20px">'
      . ($safe_desc ? '<p>' . htmlspecialchars($intro) . ' <strong>' . $safe_desc . '</strong></p>' : '<p>' . htmlspecialchars($intro) . '</p>')
      . '<p style="text-align:center;margin:28px 0">'
      .   '<a href="' . $safe_url . '" style="background:#003087;color:#ffffff;padding:14px 32px;'
      .   'border-radius:6px;text-decoration:none;font-size:16px;font-weight:bold;display:inline-block">'
      .   htmlspecialchars($btn_label) . '</a>'
      . '</p>'
      . '<p style="font-size:12px;color:#888;font-style:italic">' . htmlspecialchars($expires) . '</p>'
      . '<p style="font-size:12px;color:#555">' . htmlspecialchars($alt_link) . '<br>'
      .   '<a href="' . $safe_url . '" style="color:#003087">' . $safe_url . '</a></p>'
      . '<p>' . htmlspecialchars($contact) . '</p>'
      . '<p>Thank you,<br>' . $safe_from . '</p>'
      . '</body></html>';

$result = validate_send_mail('', $email, $subject, $body, $from_name, $from_address, [], $attachments_list);

if ($temp_pdf_path) @unlink($temp_pdf_path);

if ($result === false) {
  echo json_encode(['success' => false, 'error' => 'Email could not be sent.']);
  die;
}

echo json_encode(['success' => true]);
die;

// ---------------------------------------------------------------------------
// Best-effort helper: generate a PDF for the given FreeBooks invoice ID, from
// whichever document is filed under 'cust:inv'. Returns
// ['pdf'=>..., 'filename'=>...] or false if it cannot be found or rendered.
// ---------------------------------------------------------------------------
function _paypal_build_invoice_pdf($fb_invoice_id) {
  global $db, $messageStack;
  try {
    if (!defined('TABLE_PHREEFORM')) {
      require_once(DIR_FS_MODULES . 'forms/config.php');
    }
    $r = $db->Execute("SELECT id FROM " . TABLE_PHREEFORM
      . " WHERE doc_group = 'cust:inv' AND (doc_ext = 'frm' OR doc_ext = 'rpt')"
      . " ORDER BY doc_title LIMIT 1");
    if ($r->EOF) return false;

    // Through Forms, and only Forms. The PhreeForm fallback that used to sit
    // here could not have run on any migrated company — get_report_details()
    // refuses a native definition outright — and this id is looked up BY GROUP
    // every call, so which format the stock invoice is in changes the moment
    // somebody edits it, with nothing here to notice. It was a fallback that
    // fired exactly when it could not work.
    if (!defined('MODULE_FORMS_STATUS')) return false;
    require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
    return forms_render_to_string((int)$r->fields['id'], [$fb_invoice_id]) ?: false;
  } catch (\Throwable $e) {
    error_log('paypal_email_link: PDF generation failed for invoice ' . $fb_invoice_id . ': ' . $e->getMessage());
    return false;
  }
}
?>
