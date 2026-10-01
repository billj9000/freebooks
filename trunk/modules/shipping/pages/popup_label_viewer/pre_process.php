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
//  Path: /modules/shipping/pages/popup_label_viewer/pre_process.php
//
$security_level = validate_user(0, true);
/**************   page specific initialization  *************************/
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_WORKING . 'functions/shipping.php');
/**************   page specific initialization  *************************/
// All three build file paths: an installed method, a Y-m-d date and bare label names.
$method       = (string)($_GET['method'] ?? '');
$date         = (string)($_GET['date']   ?? '');
$label_list   = (string)($_GET['labels'] ?? '');
if (!shipping_method_is_valid($method) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  $method = ''; $date = ''; $label_list = '';
}
$file_path    = SHIPPING_DEFAULT_LABEL_DIR . $method . '/' . str_replace('-', '/', $date) . '/';
$browser_path = SHIPPING_DEFAULT_LABEL_WS  . $method . '/' . str_replace('-', '/', $date) . '/';
$labels       = array_filter(explode(':', $label_list), function ($l) { return (bool)preg_match('/^[A-Za-z0-9_\-]+$/', $l); });
if (empty($labels)) die('No labels were passed to label_viewer!');

// Email address params (passed from label manager or popup_tracking)
$email_to_name   = db_prepare_input($_GET['to_name']   ?? '');
$email_to_addr   = db_prepare_input($_GET['to_email']  ?? '');
$email_from_name = db_prepare_input($_GET['from_name'] ?? (defined('COMPANY_NAME')  ? COMPANY_NAME  : ''));
$email_from_addr = db_prepare_input($_GET['from_email']?? (defined('COMPANY_EMAIL') ? COMPANY_EMAIL : ''));

// Build list of label items (url + filesystem path for each label file)
$content_list = array();
foreach ($labels as $one_label) {
  $cnt = 0;
  while (true) {
    $label = $one_label . ($cnt > 0 ? '-' . $cnt : '');
    if (is_file($file_path . $label . '.pdf')) {
      $content_list[] = array('type' => 'pdf', 'url' => $browser_path . $label . '.pdf',
                              'path' => $file_path . $label . '.pdf');
    } elseif (is_file($file_path . $label . '.lpt')) {
      $content_list[] = array('type' => 'lpt',
        'url' => html_href_link(FILENAME_DEFAULT,
          'module=shipping&page=popup_label_image&action=notify&date=' . $date .
          '&method=' . $method . '&label=' . $label, 'SSL'),
        'path' => '');
    } elseif (is_file($file_path . $label . '.gif')) {
      $content_list[] = array('type' => 'gif', 'url' => $browser_path . $label . '.gif',
                              'path' => $file_path . $label . '.gif');
    } else {
      break;
    }
    $cnt++;
  }
}

switch ($_REQUEST['action'] ?? '') {
  case 'email':
    // Mailing labels out needs the shipping manager permission, from the page's own POST.
    $email_ok = ($_SESSION['admin_security'][SECURITY_ID_SHIPPING_MANAGER] ?? 0) >= 1 && $_SERVER['REQUEST_METHOD'] === 'POST';
    $to_name    = db_prepare_input($_POST['to_name']          ?? '');
    $to_email   = db_prepare_input($_POST['to_email']         ?? '');
    $from_name  = db_prepare_input($_POST['from_name']        ?? '');
    $from_email = db_prepare_input($_POST['from_email']       ?? '');
    $cc_email   = db_prepare_input($_POST['cc_email']         ?? '');
    $bcc_email  = db_prepare_input($_POST['bcc_email']        ?? '');
    $subject    = db_prepare_input($_POST['message_subject']  ?? SHIPPING_LABEL_EMAIL_SUBJECT);
    $body       = db_prepare_input($_POST['message_body']     ?? SHIPPING_LABEL_EMAIL_BODY);
    $block = array();
    if ($cc_email)  $block['EMAIL_CC_ADDRESS']  = $cc_email;
    if ($bcc_email) $block['EMAIL_BCC_ADDRESS'] = $bcc_email;
    $attachments = array();
    foreach ($content_list as $item) {
      if ($item['type'] === 'pdf' && $item['path']) $attachments[] = array('file' => $item['path']);
    }
    foreach (array($to_email, $from_email) as $address) {
      if (!filter_var($address, FILTER_VALIDATE_EMAIL)) $email_ok = false;
    }
    foreach (array($cc_email, $bcc_email) as $list) {
      if ($list === '') continue;
      foreach (preg_split('/[,;]\s*/', $list) as $address) {
        if (!filter_var(trim($address), FILTER_VALIDATE_EMAIL)) $email_ok = false;
      }
    }
    if (!$email_ok) {
      header('Content-Type: application/json');
      echo json_encode(['status' => 'error']);
      exit;
    }
    $ok = empty($attachments) ? true : validate_send_mail($to_name ?: $to_email, $to_email, $subject, $body,
      $from_name, $from_email, $block, $attachments);
    header('Content-Type: application/json');
    echo json_encode($ok ? ['status' => 'ok'] : ['status' => 'error']);
    exit;
}

$custom_html      = true;
$include_header   = false;
$include_footer   = false;
$include_template = 'template_main.php';
?>
