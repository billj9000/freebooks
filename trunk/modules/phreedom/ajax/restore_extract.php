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
//  Path: /modules/phreedom/ajax/restore_extract.php
//
// Extracts a full company backup archive (bu-*) to my_files/{company}/.
// Returns JSON {status:'ok', sql_file:'db-company-date.sql'} so the
// caller can proceed to import the SQL via restore_chunk.
//
// Replaying a backup rewrites the whole company: the backup permission at full level.
$security_level = validate_ajax_user(SECURITY_ID_BACKUP);
// A restore step changes the database: only as a POST, which index.php has
// checked for its csrf_token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); die; }
if ($security_level < 4) {
  header('Content-Type: application/json');
  echo json_encode(array('status'=>'error','message'=>ERROR_NO_PERMISSION)); exit;
}

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

/**
 * An archive entry may be written into the company directory only as a plain
 * relative path, and never as something the web server would run.
 */
function restore_path_is_plain($entry) {
  $entry = (string)$entry;
  if ($entry === '' || $entry[0] === '/' || strpos($entry, '\\') !== false || strpos($entry, ':') !== false || strpos($entry, "\0") !== false) return false;
  foreach (explode('/', rtrim($entry, '/')) as $part) if ($part === '..' || $part === '.' || $part === '') return false;
  return true;
}
function restore_entry_is_safe($entry) {
  return restore_path_is_plain($entry) && !preg_match('/(\.php|\.phtml|\.phar|\.htaccess)$/i', rtrim((string)$entry, '/'));
}

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
    if (!restore_entry_is_safe($entry)) continue;
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
  // Only plain files and directories under this company's own directory: a link,
  // a path outside it or a script the web server would run is refused.
  exec('tar -tvjf ' . escapeshellarg($filepath) . ' 2>/dev/null', $listing, $ret);
  if ($ret !== 0) {
    echo json_encode(array('status'=>'error','message'=>'Cannot read tar archive')); exit;
  }
  foreach ($listing as $line) {
    if ($line === '' || ($line[0] !== '-' && $line[0] !== 'd')) {
      echo json_encode(array('status'=>'error','message'=>'Archive holds a link or special file')); exit;
    }
  }
  exec('tar -tjf ' . escapeshellarg($filepath) . ' 2>/dev/null', $names, $ret);
  foreach ($names as $name) {
    if (strpos($name, $company_name . '/') !== 0 || !restore_path_is_plain($name)) {
      echo json_encode(array('status'=>'error','message'=>'Archive entry outside the company directory: ' . $name)); exit;
    }
  }
  // Exclude config.php (environment-specific, must not be overwritten)
  exec('tar -xjf ' . escapeshellarg($filepath)
    . ' -C ' . escapeshellarg($parent_dir)
    . ' --exclude=' . escapeshellarg($company_name . '/config.php')
    . " --exclude='*.php' --exclude='*.phtml' --exclude='*.phar' --exclude='*.htaccess'"
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
