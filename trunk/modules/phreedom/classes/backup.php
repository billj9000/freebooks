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
//  Path: /modules/phreedom/classes/backup.php
//
define('lnbr', "\n");

class backup {
  public $max_count;
  public $backup_mime;
  public $db_filename;
  public $source_dir;
  public $source_file;
  public $dest_dir;
  public $dest_file;

  function __construct() {
    $this->max_count   = 200; // max 300 to work with BigDump based restore sript
    $this->backup_mime = 'application/zip';
    $this->db_filename = 'db-' . $_SESSION['company'] . '-' . date('Ymd');
    $this->source_dir  = DIR_FS_MY_FILES . $_SESSION['company'] . '/';
    $this->source_file = 'filename.txt';
    $this->dest_dir    = DIR_FS_MY_FILES . 'backups/';
    $this->dest_file   = 'filename.bak';
  }

  function copy_db_table($source_db, $table_list, $type = 'data', $params = '') {
  	global $messageStack;
    $error = false;
	if (is_array($table_list)) foreach($table_list as $table) {
	  if (!$this->dump_db_table($source_db, $table, $type, $params)) return false;
	  $result = $this->db_executeSql($this->source_dir . $this->source_file);
	  if (count($result['errors']) > 0) {
	    return $messageStack->add(SETUP_CO_MGR_ERROR_1,'error');
	  }
	}
	return $error;
  }

  function dump_db_table($db, $table = 'all', $type = 'data', $params = '') {
  	global $messageStack;
	if ($table == 'all') {
	  $tables = array();
	  $table_list = $db->Execute("show tables");
	  while (!$table_list->EOF) {
		$tables[] = array_shift($table_list->fields);
		$table_list->MoveNext();
	  }
	} elseif (!is_array($table)) { // single table
	  $tables = array($table);
	}
	if (!is_dir($this->source_dir)) mkdir($this->source_dir);
	$handle = @fopen($this->source_dir . $this->source_file, 'w');
	if ($handle === false) {
	  $messageStack->add(sprintf(ERROR_ACCESSING_FILE, $this->source_dir . $this->source_file), 'error');
	  return false;
	}
	foreach ($tables as $table) {
	  $query  = '';
	  if ($type == 'structure' || $type == 'both') { // build the table create sql
		$query .= "-- Table structure for table $table" . lnbr;
		$query .= "DROP TABLE IF EXISTS $table;" . lnbr . lnbr;
		$result = $db->Execute("show create table $table");
		$query .= $result->fields['Create Table'] . ";" . lnbr . lnbr;
	  }
	  fwrite($handle, $query);
	  if ($type == 'data' || $type == 'both') {
		// Pull rows in bounded chunks rather than one SELECT * covering the whole table —
		// query_factory's Execute() always buffers the full result set in memory (PDO
		// fetchAll()), so a large table (e.g. a full-history company's journal_item at
		// 200K+ rows) can exceed memory_limit before a single row is written. Chunking
		// keeps memory bounded regardless of table size; the emitted SQL is byte-for-byte
		// identical to the previous single-query version.
		$count_result = $db->Execute('SELECT COUNT(*) AS cnt FROM ' . $table . $params);
		$total_rows   = (int)$count_result->fields['cnt'];
		if ($total_rows > 0) {
		  $chunk_size = 1000;
		  $sql_head   = '';
		  $count      = 0; // rows since the last INSERT statement was opened
		  $row_number = 0;
		  for ($offset = 0; $offset < $total_rows; $offset += $chunk_size) {
			$result      = $db->Execute('SELECT * FROM ' . $table . $params . ' LIMIT ' . $offset . ',' . $chunk_size);
			$chunk_query = '';
			while (!$result->EOF) {
			  $temp_array = $result->fields;
			  if ($sql_head === '') {
				$keys     = array_keys($temp_array);
				$sql_head = "INSERT INTO $table (`" . join('`,`', $keys) . '`) VALUES ' . lnbr;
				$chunk_query .= $sql_head;
			  }
			  $data = array();
			  foreach ($temp_array as $value) {
				$data[] = addslashes($value);
			  }
			  $chunk_query .= "('" . implode("', '", $data) . "')";
			  $row_number++;
			  $count++;
			  if ($row_number == $total_rows) {
				$chunk_query .= ';' . lnbr;
			  } elseif ($count > $this->max_count) {
				$count = 0;
				$chunk_query .= ';' . lnbr . $sql_head;
			  } else {
				$chunk_query .= ',' . lnbr;
			  }
			  $result->MoveNext();
			}
			fwrite($handle, $chunk_query);
		  }
		}
	  }
	  fwrite($handle, lnbr . lnbr);
	}
	fclose($handle);
	return true;
  }

  function make_zip($type = 'file', $localname = NULL, $root_folder = '') {
    global $messageStack;
    $error = false;
	if (!class_exists('ZipArchive')) return $messageStack->add(GEN_BACKUP_NO_ZIP_CLASS, 'error');
	$zip = new ZipArchive;
	$res = $zip->open($this->dest_dir . $this->dest_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
	if ($res !== true) return $messageStack->add(GEN_BACKUP_FILE_ERROR . $this->dest_dir, 'error');
	if ($type == 'file') {
	  $zip->addFile($this->source_dir . $this->source_file, $localname ?: basename($this->source_file));
	} else { // compress all
	  $this->addFolderToZip($this->source_dir, $zip, $root_folder);
	}
	$zip->close();
  }

  function addFolderToZip($dir, $zipArchive, $dest_path = NULL) {
    if (!is_dir($dir)) return;
	$files = scandir($dir);
	foreach ($files as $file) {
	  if (is_file($dir . $file)){
		$zipArchive->addFile($dir . $file, $dest_path . $file);
	  } else { // If it's a folder, run the function again!
	    if ($file <> "." && $file <> "..") $this->addFolderToZip($dir . $file . "/", $zipArchive, $dest_path . $file . "/");
	  }
	}
  }

  function unzip_file($file, $dest_path = '') {
    $error = false;
    if (!$dest_path) $dest_path = $this->dest_dir;
	if (!file_exists($file)) $error = true;
	$zip = new ZipArchive;
    $zip->open($file);
    $zip->extractTo($dest_path);
    $zip->close();
    return $error;
  }

  function make_bz2($type = 'file') {
    global $messageStack;
    $error = false;
	unset($output);
	if ($type == 'file') {
	  $this->backup_mime = 'application/x-bz2';
	  exec("nice -n 19 bzip2 -kf " . escapeshellarg($this->source_dir . $this->source_file) . " 2>&1", $output, $res);
	  if ($res == 0) exec("mv " . escapeshellarg($this->source_dir . $this->source_file . ".bz2") . " " . escapeshellarg($this->dest_dir . $this->dest_file) . " 2>&1", $output, $res);
	} else { // compress all
	  $this->backup_mime = 'application/x-tar';
	  exec("nice -n 19 tar -jcf " . escapeshellarg($this->dest_dir . $this->dest_file) . " -C " . escapeshellarg(dirname(rtrim($this->source_dir, "/"))) . " " . escapeshellarg(basename(rtrim($this->source_dir, "/"))) . " 2>&1", $output, $res);
	}
	if ($res > 0) {
	  $messageStack->add(ERROR_COMPRESSION_FAILED . implode(": ", $output), 'error');
	  $error = true;
	}
	return $error;
  }

  function download($path, $filename, $save_source = false) {
    global $messageStack;
	$source_file = $path . $filename;
	clearstatcache(true, $source_file);
	$fsize = @filesize($source_file);
	if ($fsize === false || $fsize == 0) {
	  $messageStack->add(GEN_BACKUP_DOWNLOAD_EMPTY, 'caution');
	  return;
	}
	@set_time_limit(0);
	while (ob_get_level() > 0) ob_end_clean();
	header("Content-Type: " . $this->backup_mime);
	header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
	header("Content-Length: " . $fsize);
	header('Pragma: cache');
	header('Cache-Control: public, must-revalidate, max-age=0');
	header('Connection: close');
	header('Expires: ' . date('r', time() + 60 * 60));
	header('Last-Modified: ' . date('r', time()));
	// stream in chunks instead of file_get_contents() so memory use stays
	// flat regardless of backup size (large company file backups can run
	// into the hundreds of MB)
	$handle = fopen($source_file, 'rb');
	if ($handle !== false) {
	  while (!feof($handle)) {
	    echo fread($handle, 1024 * 1024);
	    flush();
	  }
	  fclose($handle);
	}
	if (!$save_source) @unlink($source_file);
	exit();
  }

  function delete_dir($dir) {
    if (!is_dir($dir)) return;
	$files = scandir($dir);
	foreach ($files as $file) {
	  if ($file == "." || $file == "..") continue;
	  if (is_file($dir . '/' . $file)) {
		unlink($dir . '/' . $file);
	  } else { // it's a directory
	    $subdir = scandir($dir . '/' . $file);
		if (count(is_array($subdir) ? $subdir : []) > 2) $this->delete_dir($dir . '/' . $file); // directory is not empty, recurse
		@rmdir($dir . '/' . $file);
	  }
	}
	rmdir($dir);
  }

  function copy_dir($dir_source, $dir_dest) {
    if (!is_dir($dir_source)) return;
	$files = scandir($dir_source);
	foreach ($files as $file) {
	  if ($file == "." || $file == "..") continue;
	  if (is_file($dir_source . $file)) {
		copy($dir_source . $file, $dir_dest . $file);
	  } else {
		@mkdir($dir_dest . $file);
		$this->copy_dir($dir_source . $file . "/", $dir_dest . $file . "/");
	  }
	}
  }

  function db_executeSql($sql_file, $table_prefix = '') {
//echo 'start SQL execute';
    global $db;
    // All four are built by appending inside the loop below, and $errors is
    // appended to ONLY when a query fails — so on a clean run it stayed
    // undefined and this returned array('errors' => null). PHP 8 made
    // count(null) fatal, which meant copy_db_table() died on the SUCCESS path:
    // creating or copying a company failed with "count(): Argument #1 must be
    // of type Countable|array, null given" precisely when nothing had gone
    // wrong. $results and $string are the same shape of bug one step behind:
    // a run with no executed statement returns null and '' respectively.
    $ignored_count = 0;
    $errors        = array();
    $return_output = array();
    $results       = 0;
    $string        = '';
    // prepare for upgrader processing
    if (!get_cfg_var('safe_mode')) {
      @set_time_limit(1200);
    }
    $lines = file($sql_file);
//echo 'read number of lines = ' . count($lines) . '<br />';
    $newline = '';
    foreach ($lines as $line) {
      $line = trim($line);
      $keep_together = 1; // count of number of lines to treat as a single command

      // split the line into words ... starts at $param[0] and so on.  Also remove the ';' from end of last param if exists
      $param=explode(" ",(substr($line,-1) == ';') ? substr($line,0,strlen($line) - 1) : $line);

      // The following command checks to see if we're asking for a block of commands to be run at once.
      // Syntax: #NEXT_X_ROWS_AS_ONE_COMMAND:6     for running the next 6 commands together (commands denoted by a ;)
      if (substr($line,0,28) == '#NEXT_X_ROWS_AS_ONE_COMMAND:') $keep_together = substr($line,28);
      if (substr($line,0,1) != '#' && substr($line,0,1) != '-' && $line != '') {
          $line_upper=strtoupper($line);
          switch (true) {
          case (substr($line_upper, 0, 21) == 'DROP TABLE IF EXISTS '):
            $line = 'DROP TABLE IF EXISTS ' . $table_prefix . substr($line, 21);
            break;
          case (substr($line_upper, 0, 11) == 'DROP TABLE ' && $param[2] != 'IF'):
            if (!$checkprivs = db_check_database_privs('DROP')) $result=sprintf(REASON_NO_PRIVILEGES,'DROP');
            if (!db_table_exists($param[2]) || gen_not_null($result)) {
              $ignore_line=true;
              $result=(gen_not_null($result) ? $result : sprintf(REASON_TABLE_DOESNT_EXIST,$param[2])); //duplicated here for on-screen error-reporting
              break;
            } else {
              $line = 'DROP TABLE ' . $table_prefix . substr($line, 11);
            }
            break;
          case (substr($line_upper, 0, 13) == 'CREATE TABLE '):
            // check to see if table exists
            $table  = (strtoupper($param[2].' '.$param[3].' '.$param[4]) == 'IF NOT EXISTS') ? $param[5] : $param[2];
            $result = db_table_exists($table);
            if ($result==true) {
              $ignore_line=true;
              $result=sprintf(REASON_TABLE_ALREADY_EXISTS,$table); //duplicated here for on-screen error-reporting
              break;
            } else {
              $line = (strtoupper($param[2].' '.$param[3].' '.$param[4]) == 'IF NOT EXISTS') ? 'CREATE TABLE IF NOT EXISTS ' . $table_prefix . substr($line, 27) : 'CREATE TABLE ' . $table_prefix . substr($line, 13);
            }
            break;
          case (substr($line_upper, 0, 12) == 'INSERT INTO '):
            //check to see if table prefix is going to match
			$param[2] = str_replace("`", '', $param[2]);
            if (!$tbl_exists = db_table_exists($param[2])) $result=sprintf(REASON_TABLE_NOT_FOUND,$param[2]).' CHECK PREFIXES!';
            $line = 'INSERT INTO ' . $table_prefix . substr($line, 12);
            break;
          case (substr($line_upper, 0, 12) == 'ALTER TABLE '):
            $line = 'ALTER TABLE ' . $table_prefix . substr($line, 12);
            break;
          case (substr($line_upper, 0, 13) == 'RENAME TABLE '):
            // RENAME TABLE command cannot be parsed to insert table prefixes, so skip if using prefixes
            if (gen_not_null(DB_PREFIX)) {
              $ignore_line=true;
            }
            break;
          case (substr($line_upper, 0, 7) == 'UPDATE '):
            //check to see if table prefix is going to match
            if (!$tbl_exists = db_table_exists($param[1])) {
              $result=sprintf(REASON_TABLE_NOT_FOUND,$param[1]).' CHECK PREFIXES!';
              $ignore_line=true;
              break;
            } else {
            $line = 'UPDATE ' . $table_prefix . substr($line, 7);
            }
            break;
          case (substr($line_upper, 0, 12) == 'DELETE FROM '):
            $line = 'DELETE FROM ' . $table_prefix . substr($line, 12);
            break;
          case (substr($line_upper, 0, 11) == 'DROP INDEX '):
            // check to see if DROP INDEX command may be safely executed
            if ($result=install_drop_index_command($param)) {
              $ignore_line=true;
              break;
            } else {
              $line = 'DROP INDEX ' . $param[2] . ' ON ' . $table_prefix . $param[4];
            }
            break;
          case (substr($line_upper, 0, 13) == 'CREATE INDEX ' || (strtoupper($param[0])=='CREATE' && strtoupper($param[2])=='INDEX')):
            // check to see if CREATE INDEX command may be safely executed
            if ($result=install_create_index_command($param)) {
              $ignore_line=true;
              break;
            } else {
              if (strtoupper($param[1])=='INDEX') {
                $line = trim('CREATE INDEX ' . $param[2] .' ON '. $table_prefix . implode(' ',array($param[4],$param[5],$param[6],$param[7],$param[8],$param[9],$param[10],$param[11],$param[12],$param[13])) ).';'; // add the ';' back since it was removed from $param at start
              } else {
                $line = trim('CREATE '. $param[1] .' INDEX ' .$param[3]. ' ON '. $table_prefix . implode(' ',array($param[5],$param[6],$param[7],$param[8],$param[9],$param[10],$param[11],$param[12],$param[13])) ); // add the ';' back since it was removed from $param at start
              }
            }
            break;
          case (substr($line_upper, 0, 8) == 'SELECT (' && substr_count($line,'FROM ')>0):
            $line = str_replace('FROM ','FROM '. $table_prefix, $line);
            break;
          case (substr($line_upper, 0, 10) == 'LEFT JOIN '):
            $line = 'LEFT JOIN ' . $table_prefix . substr($line, 10);
            break;
          case (substr($line_upper, 0, 5) == 'FROM '):
            if (substr_count($line,',')>0) { // contains FROM and a comma, thus must parse for multiple tablenames
              $tbl_list = explode(',',substr($line,5));
              $line = 'FROM ';
              foreach($tbl_list as $val) {
                $line .= $table_prefix . trim($val) . ','; // add prefix and comma
              } //end foreach
              if (substr($line,-1)==',') $line = substr($line,0,(strlen($line)-1)); // remove trailing ','
            } else { //didn't have a comma, but starts with "FROM ", so insert table prefix
              $line = str_replace('FROM ', 'FROM '.$table_prefix, $line);
            }//endif substr_count(,)
            break;
          default:
            break;
          } //end switch
        $newline .= $line . ' ';

        if ( substr($line,-1) ==  ';') {
          //found a semicolon, so treat it as a full command, incrementing counter of rows to process at once
          if (substr($newline,-1)==' ') $newline = substr($newline,0,(strlen($newline)-1));
          $lines_to_keep_together_counter++;
          if ($lines_to_keep_together_counter == $keep_together) { // if all grouped rows have been loaded, go to execute.
            $complete_line = true;
            $lines_to_keep_together_counter=0;
          } else {
            $complete_line = false;
          }
        } //endif found ';'

        if ($complete_line) {
          if ($debug==true) echo ((!$ignore_line) ? '<br />About to execute.': 'Ignoring statement. This command WILL NOT be executed.').'<br />Debug info:<br />$ line='.$line.'<br />$ complete_line='.$complete_line.'<br />$ keep_together='.$keep_together.'<br />SQL='.$newline.'<br /><br />';
          // get_magic_quotes_runtime() removed in PHP 7.4; magic quotes no longer exist
          if (trim(str_replace(';','',$newline)) != '' && !$ignore_line) $output=$db->Execute($newline);
          $results++;
          $string .= $newline.'<br />';
          $return_output[]=$output;
          if ($result) $errors[]=$result;
          // reset var's
          $newline = '';
          $keep_together=1;
          $complete_line = false;
          if ($ignore_line) $ignored_count++;
          $ignore_line=false;

          // show progress bar
          global $zc_show_progress;
          if ($zc_show_progress=='yes') {
             $counter++;
             if ($counter/5 == (int)($counter/5)) echo '~ ';
             if ($counter>200) {
               echo '<br /><br />';
               $counter=0;
             }
             @ob_flush();
             @flush();
          }
        } //endif $complete_line
      } //endif ! # or -
    } // end foreach $lines
    return array('queries'=> $results, 'string'=>$string, 'output'=>$return_output, 'ignored'=>($ignored_count), 'errors'=>$errors);
  } //end function

}
?>