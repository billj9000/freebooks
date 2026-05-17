<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft      (www.PhreeSoft.com)       |
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
//  Path: /modules/phreedom/ajax/test_smtp.php
//
$security_level = validate_ajax_user();

header('Content-Type: application/json');

$transport   = trim($_POST['transport']   ?? '');
$server      = trim($_POST['server']      ?? '');
$port        = (int)($_POST['port']       ?? 25);
$encryption  = trim($_POST['encryption']  ?? '0');
$mailbox     = trim($_POST['mailbox']     ?? '');
$password    = trim($_POST['password']    ?? '');
$self_signed = ($_POST['self_signed']     ?? '0') === '1';

if (!in_array($transport, ['smtp', 'smtpauth'])) {
  echo json_encode(['ok' => false, 'message' => 'Test only applies to SMTP transports. Select "SMTP (no authentication)" or "SMTP with authentication" first.']);
  die;
}
if ($server === '') {
  echo json_encode(['ok' => false, 'message' => 'SMTP server address is required.']);
  die;
}

$smtp = new \PHPMailer\PHPMailer\SMTP();
$smtp->do_debug = \PHPMailer\PHPMailer\SMTP::DEBUG_CONNECTION;

$debug_log = [];
$smtp->Debugoutput = function($str, $level) use (&$debug_log) {
  $debug_log[] = trim($str);
};

$stream_options = [];
if ($self_signed) {
  $stream_options = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
}

// For SMTPS (port 465), PHPMailer expects 'ssl://' prepended to the hostname.
// For STARTTLS (port 587), connect plain then upgrade after EHLO.
// For no encryption (port 25), plain connect throughout.
$connect_host = ($encryption === 'ssl') ? 'ssl://' . $server : $server;
$timeout      = 15;

$connected = $smtp->connect($connect_host, $port, $timeout, $stream_options);

if (!$connected) {
  $last = $smtp->getError();
  $msg  = 'Could not connect to ' . htmlspecialchars($server) . ':' . $port . '.';
  if (!empty($last['error']))  $msg .= ' ' . htmlspecialchars($last['error']);
  if (!empty($last['detail'])) $msg .= ' ' . htmlspecialchars($last['detail']);
  if ($encryption === 'ssl') $msg .= ' Ensure the Encryption setting matches the port (SSL/TLS → 465, STARTTLS → 587, None → 25).';
  echo json_encode(['ok' => false, 'message' => $msg, 'log' => $debug_log]);
  die;
}

$hello = (defined('SERVER_ADDRESS') && SERVER_ADDRESS !== '') ? SERVER_ADDRESS : (gethostname() ?: 'localhost');
$smtp->hello($hello);

if ($encryption === '1') {
  // STARTTLS: upgrade the plain connection to TLS now
  if (!$smtp->startTLS()) {
    $last = $smtp->getError();
    $msg  = 'Connected but STARTTLS negotiation failed.';
    if (!empty($last['error']))  $msg .= ' ' . htmlspecialchars($last['error']);
    if (!empty($last['detail'])) $msg .= ' ' . htmlspecialchars($last['detail']);
    $smtp->quit();
    echo json_encode(['ok' => false, 'message' => $msg, 'log' => $debug_log]);
    die;
  }
  $smtp->hello($hello);
}

if ($transport === 'smtpauth') {
  if ($mailbox === '') {
    $smtp->quit();
    echo json_encode(['ok' => false, 'message' => 'SMTP username is required for authenticated connections.']);
    die;
  }
  if (!$smtp->authenticate($mailbox, $password, '', null)) {
    $last = $smtp->getError();
    $msg  = 'Connected but authentication failed.';
    if (!empty($last['error']))  $msg .= ' ' . htmlspecialchars($last['error']);
    if (!empty($last['detail'])) $msg .= ' ' . htmlspecialchars($last['detail']);
    $smtp->quit();
    echo json_encode(['ok' => false, 'message' => $msg, 'log' => $debug_log]);
    die;
  }
  $smtp->quit();
  echo json_encode(['ok' => true, 'message' => 'Connected and authenticated successfully to ' . htmlspecialchars($server) . ':' . $port . '.', 'log' => $debug_log]);
} else {
  $smtp->quit();
  echo json_encode(['ok' => true, 'message' => 'Connected successfully to ' . htmlspecialchars($server) . ':' . $port . ' (no authentication).', 'log' => $debug_log]);
}
die;
?>
