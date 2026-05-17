<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
//  Path: /modules/phreedom/ajax/restore_chunk.php
//
// Processes one chunk of a SQL dump file.
// Reads $linespersession lines, executes them via PDO, returns JSON.
//
$security_level = validate_ajax_user();

header('Content-Type: application/json');
header('Cache-Control: no-cache');
@set_time_limit(0);

$linespersession = 3000;
$max_query_lines = 300;
$upload_dir      = DIR_FS_MY_FILES . 'backups/';
$comment_starts  = array('#', '-- ', '/*!', '/*');

// --- validated inputs ---
$fn           = preg_replace('/[^A-Za-z0-9_\-\.]/i', '', $_GET['fn'] ?? '');
$foffset      = max(0, (int)($_GET['foffset']      ?? 0));
$totalqueries = max(0, (int)($_GET['totalqueries'] ?? 0));
$start        = max(1, (int)($_GET['start']        ?? 1));

if (!$fn) { echo json_encode(array('status'=>'error','message'=>'No filename')); exit; }
if (!preg_match('/\.(sql|gz|bz2|zip)$/i', $fn)) {
  echo json_encode(array('status'=>'error','message'=>'Unsupported file type')); exit;
}

$filepath = $upload_dir . $fn;
if (!file_exists($filepath)) {
  echo json_encode(array('status'=>'error','message'=>'File not found: '.$fn)); exit;
}

// --- open file, decompressing if needed ---
$gzipmode       = (bool)preg_match('/\.gz$/i',  $fn);
$bz2mode        = (bool)preg_match('/\.bz2$/i', $fn);
$zipmode        = (bool)preg_match('/\.zip$/i',  $fn);
$extracted_temp = '';

if ($bz2mode || $zipmode) {
  $extracted_temp = $filepath . '.sql';
  if (!file_exists($extracted_temp)) {
    if ($bz2mode && function_exists('bzopen')) {
      $bz    = bzopen($filepath, 'r');
      $tmp_h = fopen($extracted_temp, 'wb');
      while (true) { $c = bzread($bz, 65536); if ($c === false || $c === '') break; fwrite($tmp_h, $c); }
      bzclose($bz); fclose($tmp_h);
    } elseif ($zipmode && class_exists('ZipArchive')) {
      $zip = new ZipArchive();
      if ($zip->open($filepath) === true) {
        $sql_entry = null;
        for ($zi = 0; $zi < $zip->numFiles; $zi++) {
          if (preg_match('/\.sql$/i', basename($zip->getNameIndex($zi)))) { $sql_entry = $zip->getNameIndex($zi); break; }
        }
        if ($sql_entry !== null) {
          $zs    = $zip->getStream($sql_entry);
          $tmp_h = fopen($extracted_temp, 'wb');
          while (!feof($zs)) fwrite($tmp_h, fread($zs, 65536));
          fclose($zs); fclose($tmp_h);
        }
        $zip->close();
      }
    }
  }
  $file = @fopen($extracted_temp, 'rt');
} elseif ($gzipmode) {
  $file = @gzopen($filepath, 'rt');
} else {
  $file = @fopen($filepath, 'rt');
}

if (!$file) {
  echo json_encode(array('status'=>'error','message'=>'Cannot open file: '.$fn)); exit;
}

// --- file size for % progress (not available for gzip) ---
$filesize = 0;
if (!$gzipmode) { fseek($file, 0, SEEK_END); $filesize = ftell($file); }

// --- seek to start offset ---
if ($gzipmode) gzseek($file, $foffset); else fseek($file, $foffset);

// --- open DB connection ---
try {
  $dsn    = 'mysql:host=' . DB_SERVER_HOST . ';dbname=' . DB_DATABASE . ';charset=utf8';
  $dbconn = new PDO($dsn, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
} catch (PDOException $e) {
  if (!$gzipmode) fclose($file); else gzclose($file);
  echo json_encode(array('status'=>'error','message'=>'DB connection failed: '.$e->getMessage())); exit;
}

// --- process lines ---
$query      = '';
$queries    = 0;
$querylines = 0;
$inparents  = false;
$linenumber = $start;
$error_msg  = '';

while ($linenumber < $start + $linespersession || $query !== '') {
  $dumpline = '';
  while (!($gzipmode ? gzeof($file) : feof($file)) && substr($dumpline, -1) !== "\n" && substr($dumpline, -1) !== "\r") {
    $dumpline .= $gzipmode ? gzgets($file, 16384) : fgets($file, 16384);
  }
  if ($dumpline === '') break;

  $dumpline = str_replace(array("\r\n", "\r"), "\n", $dumpline);

  if (!$inparents) {
    $skip = false;
    foreach ($comment_starts as $c) {
      if (trim($dumpline) === '' || strpos($dumpline, $c) === 0) { $skip = true; break; }
    }
    if ($skip) { $linenumber++; continue; }
  }

  $deslashed = str_replace('\\\\', '', $dumpline);
  $quotes    = substr_count($deslashed, "'") - substr_count($deslashed, "\\'");
  if ($quotes % 2 !== 0) $inparents = !$inparents;

  $query .= $dumpline;
  if (!$inparents) $querylines++;

  if ($querylines > $max_query_lines) {
    $error_msg = 'Query too long (over ' . $max_query_lines . ' lines) at line ' . $linenumber;
    break;
  }

  if (preg_match('/;$/', trim($dumpline)) && !$inparents) {
    try {
      $dbconn->exec(trim($query));
    } catch (PDOException $e) {
      $error_msg = 'SQL error at line ' . $linenumber . ': ' . $e->getMessage();
      break;
    }
    $totalqueries++;
    $queries++;
    $query      = '';
    $querylines = 0;
  }
  $linenumber++;
}

$foffset_new = $gzipmode ? gztell($file) : ftell($file);
if ($gzipmode) gzclose($file); else fclose($file);
$dbconn = null;

if ($error_msg) {
  echo json_encode(array('status'=>'error','message'=>$error_msg)); exit;
}

// Reached EOF before filling the session quota = done
$done = ($linenumber < $start + $linespersession);
if ($done && $extracted_temp && file_exists($extracted_temp)) @unlink($extracted_temp);

$pct = ($filesize > 0) ? min(100, (int)($foffset_new / $filesize * 100)) : 0;

echo json_encode(array(
  'status'        => $done ? 'done' : 'continue',
  'foffset'       => $foffset_new,
  'totalqueries'  => $totalqueries,
  'linenumber'    => $linenumber,
  'pct'           => $pct,
  'queries_this'  => $queries,
));
exit;
?>
