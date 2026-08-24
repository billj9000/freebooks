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
/**************   page specific initialization  *************************/
$method       = $_GET['method'] ?? '';
$date         = $_GET['date']   ?? '';
$label_list   = $_GET['labels'] ?? '';
$file_path    = SHIPPING_DEFAULT_LABEL_DIR . $method . '/' . str_replace('-', '/', $date) . '/';
$browser_path = SHIPPING_DEFAULT_LABEL_WS  . $method . '/' . str_replace('-', '/', $date) . '/';
$labels       = explode(':', $label_list);
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
