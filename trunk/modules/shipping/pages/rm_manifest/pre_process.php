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
//  Path: /modules/shipping/pages/rm_manifest/pre_process.php
//
$security_level = validate_user(SECURITY_ID_SHIPPING_MANAGER);
gen_pull_language('contacts');
load_method_language(DIR_FS_MODULES . 'shipping/methods/', 'royal_mail');
load_specific_method('shipping', 'royal_mail');
require_once(DIR_FS_WORKING . 'defaults.php');

// A manifest's PDF is kept beside that day's labels as manifest-<id>.pdf. Until it is
// fetched a manifest-<id>.pending file stands in for it, so a manifest whose PDF is not
// ready when this window closes is fetched the next time the page opens. A pending
// file holding "failed <reference>" is a manifest Click & Drop could not complete.
$manifest_dir = SHIPPING_DEFAULT_LABEL_DIR . 'royal_mail/';
$today        = date('Y-m-d');
$new_ids      = array();

function rm_manifest_path($date, $id, $ext) {
  global $manifest_dir;
  return $manifest_dir . str_replace('-', '/', $date) . '/manifest-' . $id . '.' . $ext;
}

/** The recent manifests, newest first: [id, date, time, state (ready|waiting|failed), error]. */
function rm_manifest_list($limit = 10) {
  global $manifest_dir;
  $list = array();
  foreach (glob($manifest_dir . '[0-9][0-9][0-9][0-9]/[0-9][0-9]/[0-9][0-9]/manifest-*.{pdf,pending}', GLOB_BRACE) ?: array() as $f) {
    if (!preg_match('~(\d{4})/(\d{2})/(\d{2})/manifest-([0-9a-f-]{36})\.(pdf|pending)$~', $f, $m)) continue;
    $id = $m[4];
    if ($m[5] === 'pending' && isset($list[$id])) continue;
    $body = $m[5] === 'pending' ? trim((string)file_get_contents($f)) : '';
    $list[$id] = array('id' => $id, 'date' => "$m[1]-$m[2]-$m[3]", 'time' => filemtime($f),
      'state' => $m[5] === 'pdf' ? 'ready' : (strpos($body, 'failed') === 0 ? 'failed' : 'waiting'),
      'error' => strpos($body, 'failed') === 0 ? trim(substr($body, 6)) : '');
  }
  usort($list, function ($a, $b) { return $b['time'] <=> $a['time']; });
  return array_slice($list, 0, $limit);
}

/** One manifest by id and date, when this company holds it; null otherwise. */
function rm_manifest_find($id, $date) {
  if (!royal_mail::isManifestId($id) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
  $id = strtolower($id);
  if (is_file(rm_manifest_path($date, $id, 'pdf'))) return array('id' => $id, 'date' => $date, 'state' => 'ready', 'error' => '');
  $pending = rm_manifest_path($date, $id, 'pending');
  if (!is_file($pending)) return null;
  $body = trim((string)file_get_contents($pending));
  return array('id' => $id, 'date' => $date, 'state' => strpos($body, 'failed') === 0 ? 'failed' : 'waiting',
    'error' => strpos($body, 'failed') === 0 ? trim(substr($body, 6)) : '');
}

/** Ask Click & Drop about a waiting manifest, saving its PDF once it is ready. */
function rm_manifest_check(array $m) {
  if ($m['state'] !== 'waiting') return $m;
  $status = (new royal_mail())->manifestStatus($m['id']);
  if ($status === false) return $m;
  $pending = rm_manifest_path($m['date'], $m['id'], 'pending');
  if ($status['manifest'] === 'Failed') {
    file_put_contents($pending, trim('failed ' . $status['error']));
    return array_merge($m, array('state' => 'failed', 'error' => $status['error']));
  }
  if ($status['document'] === 'Completed' && $status['pdf'] !== ''
      && file_put_contents(rm_manifest_path($m['date'], $m['id'], 'pdf'), $status['pdf'])) {
    // The PDF keeps the time the manifest was submitted, which the list shows and sorts by.
    if (is_file($pending)) touch(rm_manifest_path($m['date'], $m['id'], 'pdf'), filemtime($pending));
    @unlink($pending);
    return array_merge($m, array('state' => 'ready'));
  }
  return $m;
}

switch ($_REQUEST['action'] ?? '') {
  case 'manifest':
    validate_security($security_level, 2);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') break;
    $ids = (new royal_mail())->manifestOrders();
    if ($ids === false) break;
    if (!$ids) { $messageStack->add(RM_MANIFEST_NOTHING, 'caution'); break; }
    validate_path($manifest_dir . str_replace('-', '/', $today) . '/');
    foreach ($ids as $id) {
      file_put_contents(rm_manifest_path($today, $id, 'pending'), '');
      gen_add_audit_log(RM_MANIFEST_TITLE, $id);
    }
    $new_ids = $ids;
    $messageStack->add(RM_MANIFEST_SUCCESS, 'success');
    break;
  case 'retry':
    validate_security($security_level, 2);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') break;
    $m = rm_manifest_find((string)($_POST['id'] ?? ''), (string)($_POST['date'] ?? ''));
    if ($m && $m['state'] === 'failed' && (new royal_mail())->retryManifest($m['id'])) {
      file_put_contents(rm_manifest_path($m['date'], $m['id'], 'pending'), '');
      $messageStack->add(RM_MANIFEST_RETRYING, 'success');
    }
    break;
  case 'status':
    // The htmx check of one waiting manifest: answers with its status cell alone.
    $m = rm_manifest_find((string)($_GET['id'] ?? ''), (string)($_GET['date'] ?? ''));
    if (!$m) { http_response_code(404); exit; }
    require(DIR_FS_WORKING . 'pages/rm_manifest/status_cell.php');
    echo rm_manifest_status_cell(rm_manifest_check($m));
    exit;
}
require_once(DIR_FS_WORKING . 'pages/rm_manifest/status_cell.php');
$manifests = rm_manifest_list();

// Today's Royal Mail shipments from local shipping log
$shipments = array();
$sql_result = $db->Execute("SELECT * FROM " . TABLE_SHIPPING_LOG
  . " WHERE carrier = 'royal_mail' AND DATE(ship_date) = '" . db_input($today) . "'"
  . " ORDER BY ship_date DESC");
while (!$sql_result->EOF) {
  $shipments[] = $sql_result->fields;
  $sql_result->MoveNext();
}

$include_header   = true;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', RM_MANIFEST_TITLE);
?>
