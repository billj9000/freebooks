<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
//  Path: /modules/phreedom/ajax/restore_extract.php
//
// Extracts a full company backup archive (bu-*) to my_files/{company}/.
// Returns JSON {status:'ok', sql_file:'db-company-date.sql'} so the
// caller can proceed to import the SQL via restore_chunk.
//
$security_level = validate_ajax_user();

header('Content-Type: application/json');
header('Cache-Control: no-cache');
@set_time_limit(300);

$backups_dir = DIR_FS_MY_FILES . 'backups/';
$company_dir = DIR_FS_MY_FILES . $_SESSION['company'] . '/';

$fn = preg_replace('/[^A-Za-z0-9_\-\.]/i', '', $_GET['fn'] ?? '');

if (!$fn || !preg_match('/^bu-/i', $fn)) {
  echo json_encode(array('status'=>'error','message'=>'Expected a bu-* full backup filename')); exit;
}
if (!preg_match('/\.(zip|tar\.bz2)$/i', $fn)) {
  echo json_encode(array('status'=>'error','message'=>'Full restore requires .zip or .tar.bz2')); exit;
}

$filepath = $backups_dir . $fn;
if (!file_exists($filepath)) {
  echo json_encode(array('status'=>'error','message'=>'File not found: '.$fn)); exit;
}

if (!is_dir($company_dir)) mkdir($company_dir, 0755, true);

$sql_file = '';

if (preg_match('/\.zip$/i', $fn)) {
  if (!class_exists('ZipArchive')) {
    echo json_encode(array('status'=>'error','message'=>'ZipArchive extension not available')); exit;
  }
  $zip = new ZipArchive();
  if ($zip->open($filepath) !== true) {
    echo json_encode(array('status'=>'error','message'=>'Cannot open zip archive')); exit;
  }
  // Find the SQL dump inside the archive (db-*.sql)
  for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = basename($zip->getNameIndex($i));
    if (preg_match('/^db-.*\.sql$/i', $name)) { $sql_file = $name; }
  }
  // Extract all company files except config.php (environment-specific, must not be overwritten)
  // The archive contains paths relative to the company dir (no company/ prefix)
  $entries = array();
  for ($i = 0; $i < $zip->numFiles; $i++) {
    $entry = $zip->getNameIndex($i);
    if ($entry === 'config.php' || substr($entry, 0, 11) === 'config.php/') continue;
    $entries[] = $entry;
  }
  $zip->extractTo($company_dir, $entries);
  $zip->close();
} elseif (preg_match('/\.tar\.bz2$/i', $fn)) {
  // Archive was created with: tar -jcf archive.tar.bz2 -C my_files/ {company}/
  // So it contains {company}/... paths; extract to my_files/ (parent of company_dir)
  $company_name = basename(rtrim($company_dir, '/'));
  $parent_dir   = dirname(rtrim($company_dir, '/')) . '/';
  $out = array(); $ret = 0;
  // Exclude config.php (environment-specific, must not be overwritten)
  exec('tar -xjf ' . escapeshellarg($filepath)
    . ' -C ' . escapeshellarg($parent_dir)
    . ' --exclude=' . escapeshellarg($company_name . '/config.php')
    . ' 2>&1', $out, $ret);
  if ($ret !== 0) {
    echo json_encode(array('status'=>'error','message'=>'tar extraction failed: ' . implode('; ', $out))); exit;
  }
  // Find the SQL file in the restored company dir
  $found = glob($company_dir . 'db-*.sql');
  if ($found) $sql_file = basename(end($found));
} else {
  echo json_encode(array('status'=>'error','message'=>'Unsupported archive format')); exit;
}

if (!$sql_file) {
  echo json_encode(array('status'=>'error','message'=>'No SQL dump found inside archive')); exit;
}

// Copy the SQL file to the backups dir so restore_chunk can read it from there
$sql_src  = $company_dir . $sql_file;
$sql_dest = $backups_dir . $sql_file;
if (file_exists($sql_src) && !file_exists($sql_dest)) {
  copy($sql_src, $sql_dest);
} elseif (!file_exists($sql_dest)) {
  echo json_encode(array('status'=>'error','message'=>'SQL file missing after extraction')); exit;
}

echo json_encode(array('status'=>'ok','sql_file'=>$sql_file));
exit;
?>
