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
//  Path: /modules/phreedom/functions/phreedom.php
//

// Resolve a raw password-reset token (as sent in the e-mailed link) to its user row, or false.
// Only the SHA-256 hash of the token is stored, so a DB read never exposes a usable token; the
// row is returned only while the token is unexpired. The caller must still consume the token
// (clear pw_reset_token/pw_reset_expires) once the password is actually changed.
function pw_reset_lookup($token) {
  global $db;
  if (!preg_match('/^[a-f0-9]{64}$/', (string)$token)) return false; // token is bin2hex(random_bytes(32))
  $hash   = hash('sha256', $token);
  $result = $db->Execute("select admin_id, admin_name, admin_email from " . TABLE_USERS . "
    where pw_reset_token = '" . db_input($hash) . "' and pw_reset_expires is not null and pw_reset_expires > UTC_TIMESTAMP()");
  return (!$result->EOF) ? $result->fields : false;
}

/**
 * The security levels a users/roles save may store, as security_id => level.
 * Each level is 0..4 and never above the level the caller holds for that id, so
 * no one can grant more than they have; admin_id 1 is the unrestricted admin.
 * A level the record already holds above the caller's own is one the caller may
 * not touch, so it is kept as it was, even when the form left it out.
 */
function phreedom_clamp_security($requested, $existing = array()) {
  $unrestricted = ((int)$_SESSION['admin_id'] === 1);
  $result = array();
  foreach ((array)$requested as $id => $level) {
    if (!ctype_digit((string)$id)) continue;
    $level = max(0, min(4, (int)$level));
    if (!$unrestricted) {
      $own   = (int)($_SESSION['admin_security'][$id] ?? 0);
      $had   = (int)($existing[$id] ?? 0);
      $level = ($had > $own) ? min(4, $had) : min($level, $own);
    }
    $result[(int)$id] = $level;
  }
  if (!$unrestricted) foreach ((array)$existing as $id => $had) {
    if (!ctype_digit((string)$id) || isset($result[(int)$id])) continue;
    if ((int)$had > (int)($_SESSION['admin_security'][$id] ?? 0)) $result[(int)$id] = max(0, min(4, (int)$had));
  }
  return $result;
}

/**
 * True when the user holds any level above the caller's own, or is admin_id 1.
 * Such a user's password and e-mail (the reset route) are not the caller's to set.
 */
function phreedom_user_outranks_caller($admin_id, $levels) {
  if ((int)$_SESSION['admin_id'] === 1) return false;
  if ((int)$admin_id === 1) return true;
  foreach ((array)$levels as $id => $level) {
    if ((int)$level > (int)($_SESSION['admin_security'][$id] ?? 0)) return true;
  }
  return false;
}

function phreedom_implode_security($levels) {
  $settings = array();
  foreach ($levels as $key => $value) $settings[] = "$key:$value";
  return implode(',', $settings);
}

/**
 * A company's display title, read from its config.php without running it.
 * Running every company's config.php to list them would define each one's
 * DB_SERVER_* constants over the last, and load every company's database
 * credentials into a request that only needs their names.
 * Falls back to the directory name when the title cannot be found.
 */
function company_config_title($company) {
  $source = @file_get_contents(DIR_FS_MY_FILES . $company . '/config.php');
  if ($source === false) return $company;
  $key    = strtolower($company . '_TITLE');
  $tokens = array_values(array_filter(token_get_all($source), function($t) {
    return !is_array($t) || !in_array($t[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT));
  }));
  for ($i = 0; $i + 5 < count($tokens); $i++) {
    if (!is_array($tokens[$i]) || strtolower($tokens[$i][1]) <> 'define' || $tokens[$i+1] !== '(') continue;
    if (!is_array($tokens[$i+2]) || $tokens[$i+2][0] <> T_CONSTANT_ENCAPSED_STRING) continue;
    if (strtolower(substr($tokens[$i+2][1], 1, -1)) <> $key || $tokens[$i+3] !== ',') continue;
    if (!is_array($tokens[$i+4]) || $tokens[$i+4][0] <> T_CONSTANT_ENCAPSED_STRING) continue;
    $literal = $tokens[$i+4][1];
    $inner   = substr($literal, 1, -1);
    return $literal[0] == "'" ? strtr($inner, array("\\\\" => "\\", "\\'" => "'")) : stripcslashes($inner);
  }
  return $company;
}

function load_company_dropdown($include_select = false) {
  $output = array();
  $i = 1;
  $contents = scandir(DIR_FS_MY_FILES);
  foreach ($contents as $file) {
	if ($file <> '.' && $file <> '..' && is_dir(DIR_FS_MY_FILES . $file)) {
	  if (file_exists(DIR_FS_MY_FILES . $file . '/config.txt')) convert_cfg($file);
	  if (file_exists(DIR_FS_MY_FILES . $file . '/config.php')) {
		$output[$i] = array(
		  'text' => company_config_title($file),
		  'file' => $file,
		);
		$i++;
	  }
	}
  }
  if ($include_select) $output[0] = array('text' => TEXT_NONE, 'file' => 'none');
  $the_list = array();
  foreach ($output as $key => $value) {
    $_SESSION['companies'][$key] = $value['file'];
    $the_list[] = array('id' => $key, 'text' => $value['text']);
  }
  return $the_list;
}

function load_language_dropdown($language_directory = 'modules/phreedom/language/') {
  $output   = array();
  $contents = scandir($language_directory);
  foreach ($contents as $lang) {
	if ($lang <> '.' && $lang <> '..' && is_dir($language_directory. $lang) && file_exists($language_directory . $lang . '/language.php')) {
	  if ($config_file = file($language_directory . $lang . '/language.php')) {
	    foreach ($config_file as $line) {
		  if (strstr($line,'\'LANGUAGE\'') !== false) {
		    $start_pos     = strpos($line, ',') + 2;
		    $end_pos       = strpos($line, ')') + 1;
		    $language_name = substr($line, $start_pos, $end_pos - $start_pos);
		    break;
		  }
	    }
	    $output[$lang] = array('id' => $lang, 'text' => $language_name);
	  }
	}
  }
  return $output;
}

function load_theme_dropdown() {
  $include_header  = false;
  $include_calendar= false;
  $output          = array();
  $contents        = scandir(DIR_FS_THEMES);
  foreach ($contents as $value) {
	if ($value <> '.' && $value <> '..' && is_dir(DIR_FS_THEMES . $value)) {
	  if (file_exists(DIR_FS_THEMES . $value . '/config.php')) {
		include(DIR_FS_THEMES . $value . '/config.php');
		$output[$value] = array('id' => $value, 'text' => $theme['name']);
	  }
	}
  }
  return $output;
}

function load_menu_dropdown() {
  $output = array();
  if (file_exists(DIR_FS_ADMIN . DIR_WS_THEMES . 'config.php')) {
	include(DIR_FS_ADMIN . DIR_WS_THEMES . 'config.php');
	foreach ($theme_menu_options as $key => $value) $output[] = array('id' => $key, 'text' => $value);
  }
  return $output;
}

function load_colors_dropdown() {
  $output   = array();
  $contents = scandir(DIR_FS_ADMIN . DIR_WS_THEMES .'/css/');
  foreach ($contents as $color) {
	if ($color <> '.' && $color <> '..' && is_dir(DIR_FS_ADMIN . DIR_WS_THEMES . '/css/'.$color)) {
	  $output[$color] = array('id' => $color, 'text' => $color);
	}
  }
  return $output;
}

function convert_cfg($company) {
  // build the new file
  $lines  = '<?php' . "\n";
  $lines .= "/* config.php */" . "\n";
  $lines .= "define('" . $company . "_TITLE','" . gen_pull_db_config_info($company, 'company_name') . "');" . "\n";
  $lines .= "define('DB_SERVER','"              . gen_pull_db_config_info($company, 'db_server') . "');" . "\n";
  $lines .= "define('DB_SERVER_USERNAME','"     . gen_pull_db_config_info($company, 'db_user') . "');" . "\n";
  $lines .= "define('DB_SERVER_PASSWORD','"     . gen_pull_db_config_info($company, 'db_pw') . "');" . "\n";

  $filename = DIR_FS_ADMIN . 'my_files/' . $company . '/config';
  if (!$handle = @fopen($filename . '.php', 'w')) die('Cannot open file (' . $filename . '.php) for writing, check your permissions. This directory and file needs access from the web server for upgrading PhreeBooks to the latest version.');
  if (!fwrite($handle, $lines)) die('Cannot write to file (' . $filename . '.php), check your permissions.');
  fclose($handle);
  if (!unlink($filename . '.txt')) die('Cannot delete file (' . $filename . '.txt). This file needs to be deleted for security reasons.');
}

function gen_pull_db_config_info($database, $key) {
  $filename = DIR_FS_ADMIN . 'my_files/' . $database . '/config.txt';
  $lines = file($filename);
  for ($x = 0; $x < count($lines); $x++) {
	if (trim(substr($lines[$x], 0, strpos($lines[$x], '='))) == $key) {
	  return trim(substr($lines[$x], strpos($lines[$x],'=') + 1, strpos($lines[$x],';') - strpos($lines[$x],'=') - 1));
	}
  }
  return false;
}

/**************************** admin functions ***********************************************/
function admin_check_versions($module, $prerequisites = NULL) {
  global $messageStack;
  $error = false;
  if (is_array($prerequisites) && !empty($prerequisites)) {
	foreach ($prerequisites as $mod => $version) {
	  $constant = 'MODULE_' . strtoupper($mod) . '_VERSION';
	  // defined() rather than assigning constant() and testing it: an undefined
	  // constant throws in PHP 8, so the module-not-installed case took the
	  // request down instead of reporting itself.
	  if (!defined($constant)) {
	    $messageStack->add(sprintf(ERROR_MODULE_NOT_INSTALLED, $module, $mod),'error');
		$error = true;
	  } else {
	    $cur_rev = (string)constant($constant);
	    // version_compare, because these are version strings and `<` compares
	    // them as text: '1.0.0' < '3.3' is true on its first character, which
	    // fails every prerequisite a module declares.
	    if (version_compare($cur_rev, (string)$version, '<')) {
	      $messageStack->add(sprintf(ERROR_MODULE_VERSION_TOO_LOW, $module, $mod, $version, $cur_rev),'error');
		  $error = true;
	    }
	  }
	}
  }
  return $error;
}

function admin_install_dirs($dirlist, $path = DIR_FS_MY_FILES) {
  global $messageStack;
  $error = false;
  if (is_array($dirlist)) foreach ($dirlist as $dir) {
	if (!file_exists($path . $dir)) {
	  if (!@mkdir($path . $dir, 0755, true)) $error = $messageStack->add(sprintf(ERROR_CANNOT_CREATE_MODULE_DIR, $path . $dir), 'error');
    }
  }
  return $error;
}

function admin_remove_dirs($dirlist, $path = DIR_FS_MY_FILES) {
  global $messageStack;
  $error = false;
  if (is_array($dirlist)) {
    $temp = array_reverse($dirlist);
	foreach($temp as $dir) {
	  if (!is_dir($path . $dir)) continue;
	  if (!@rmdir($path . $dir)) $error = $messageStack->add(sprintf(ERROR_CANNOT_REMOVE_MODULE_DIR, $path . $dir), 'error');
	}
  }
  return $error;
}

function admin_install_tables($tables) {
  global $db, $messageStack;
  $error = false;
  if (is_array($tables) && !empty($tables)) foreach ($tables as $table => $create_table_sql) {
    if (!db_table_exists($table)) {
	  if (!$db->Execute($create_table_sql)) $error = $messageStack->add(sprintf("Error installing table: %s", $table), 'error');
	}
  }
  return $error;
}

function admin_remove_tables($tables) {
  global $db;
  $error = false;
  if (is_array($tables)) foreach($tables as $table) {
	if (db_table_exists($table)) $db->Execute('DROP TABLE ' . $table);
  }
  return $error;
}

function admin_add_report_heading($doc_title, $doc_group) {
  global $db;
  $result = $db->Execute("select id from ".TABLE_PHREEFORM." where doc_group = '$doc_group'");
  if ($result->RecordCount() < 1) {
    $db->Execute("INSERT INTO ".TABLE_PHREEFORM." (parent_id, doc_type, doc_title, doc_group, doc_ext, security, create_date) VALUES 
      (0, '0', '" . $doc_title . "', '".$doc_group."', '0', 'u:0;g:0', now())");
    $id = db_insert_id();
  } else {
    $id = $result->fields['id'];
  }
  return $id;
}

function admin_add_report_folder($parent_id, $doc_title, $doc_group, $doc_ext) {
  global $db;
  $error = false;
  if ($parent_id == '') return true;
  $result = $db->Execute("select id from ".TABLE_PHREEFORM." where doc_group = '$doc_group' and doc_ext = '$doc_ext'");
  if ($result->RecordCount() < 1) {
    $db->Execute("INSERT INTO ".TABLE_PHREEFORM." (parent_id, doc_type, doc_title, doc_group, doc_ext, security, create_date) VALUES 
      (".$parent_id.", '0', '" . $doc_title . "', '".$doc_group."', '".$doc_ext."', 'u:0;g:0', now())");
    $id = db_insert_id();
  } else {
    $id = $result->fields['id'];
  }
  return $error;
}

function admin_add_reports($module, $save_path = null, $paper_size = '') {
  // Computed rather than taken from a module constant. It used to default to
  // PF_DIR_MY_REPORTS, so a core function's SIGNATURE could not be evaluated
  // without PhreeForm loaded — which made deleting that module a fatal here,
  // in phreedom, for every caller.
  if ($save_path === null) {
    $save_path = DIR_FS_MY_FILES . ($_SESSION['company'] ?? '') . '/phreeform/';
  }
  $error = false;
  if (!is_dir(DIR_FS_MODULES . $module)) return $error;
  if (file_exists(DIR_FS_MODULES . $module . '/language/' . $_SESSION['language'] . '/reports/')) {
    $read_path = DIR_FS_MODULES . $module . '/language/' . $_SESSION['language'] . '/reports/';
  } elseif (file_exists(DIR_FS_MODULES . $module . '/language/en_us/reports/')) {
    $read_path = DIR_FS_MODULES . $module . '/language/en_us/reports/';
  } else {
    return $error; // nothing to import
  }
  // A native definition BESIDE the xml wins (design §13.5, decision K): a new
  // install and a new company get Forms definitions from the start, rather than
  // legacy ones converted afresh on every load. The xml stays because it is
  // what Import reads and what an older company already has, and because an
  // extension module may ship only xml — which is why this is per FILE and not
  // per directory.
  // Only where Forms is actually installed. A company without it cannot read a
  // native definition at all, so seeding one would hand it a catalog of
  // documents nothing on the system can open. The installer defines every
  // MODULE_*_STATUS before it seeds reports, so this is true there too.
  $native = DIR_FS_MODULES . 'forms/functions/seed.php';
  if (defined('MODULE_FORMS_STATUS') && file_exists($native)) require_once($native);

  // The IMAGES those definitions name, into the company they are being seeded
  // into. phreebooks_admin::install() copies the logo to
  // DIR_FS_MY_FILES . $_SESSION['company'] — the company the ADMIN is logged
  // into, not the one being created — so a new company got a form whose logo
  // box pointed at a file it did not have, and every invoice printed with an
  // empty rectangle. Silently, because that copy is a @copy.
  //
  // Driven by what the definitions actually reference, so a module that ships a
  // form with its own logo is covered without anything being listed twice.
  $images = array();
  foreach (scandir($read_path) as $file) {
    if (!in_array(strtolower(substr($file, -4)), array('.xml', 'json'))) continue;
    if (preg_match_all('/<filename><!\[CDATA\[([^\]]+)\]\]>|"src"\s*:\s*"([^"]+)"/',
        (string)file_get_contents($read_path . $file), $found, PREG_SET_ORDER)) {
      foreach ($found as $match) {
        $name = basename(trim($match[2] ?? '') !== '' ? $match[2] : $match[1]);
        if ($name !== '') $images[$name] = true;
      }
    }
  }
  if ($images) {
    $image_dest = $save_path . 'images/';
    if (!is_dir($image_dest)) @mkdir($image_dest, 0777, true);
    foreach (array_keys($images) as $name) {
      $source = DIR_FS_MODULES . $module . '/images/' . $name;
      if (is_file($source) && !file_exists($image_dest . $name)) @copy($source, $image_dest . $name);
    }
  }

  $files = scandir($read_path);
  foreach ($files as $file) if (strtolower(substr($file, -4)) == '.xml') {
    $json = substr($file, 0, -4) . '.json';
    if (!function_exists('forms_seed_native')) {
      // No Forms, no seeding. Every shipped definition is native now, and
      // ImportReport() — which used to be the fallback here — goes with
      // PhreeForm. A company without Forms has nothing that can read either.
      continue;
    }
    // A native master beside the xml wins; an xml on its own is CONVERTED on
    // the way in rather than stored verbatim, which is what ImportReport() did
    // and what left every load converting it afresh (decision L).
    $seeded = file_exists($read_path . $json)
            ? forms_seed_native($read_path . $json, $save_path, $paper_size)
            : forms_seed_legacy($read_path . $file, $save_path, $paper_size);
    if (!$seeded) $error = true;
  }
  return $error;
}

/************************ install functions ******************************/
function install_build_co_config_file($company, $key, $value) {
  global $messageStack;
  // The company is a directory name and the key a constant name, both written into PHP source.
  if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$company) || !preg_match('/^[A-Za-z0-9_]+$/', (string)$key)) {
    $messageStack->add(sprintf(MSG_ERROR_CANNOT_WRITE, 'config.php'), 'error');
    return false;
  }
  $filename = DIR_FS_ADMIN . 'my_files/' . $company . '/config.php';
  if (file_exists($filename)) { // update
    $lines = file($filename);
    $found_it = false;
    for ($x = 0; $x < count($lines); $x++) {
	  if (strpos(substr($lines[$x], 0, strpos($lines[$x], ',')), $key)) {
	    $lines[$x] = "define('" . $key . "'," . var_export((string)$value, true) . ");" . "\n";
	    $found_it = true;
	    break;
	  }
    }
    if (!$found_it) $lines[] = "define('" . $key . "'," . var_export((string)$value, true) . ");" . "\n";
  } else { // create the config file, because it doesn't exist
    $lines = array();
    $lines[] = '<?php' . "\n";
    $lines[] = '/* config.php */' . "\n";
    $lines[] = "define('" . $key . "'," . var_export((string)$value, true) . ");" . "\n";
  }
  $line = implode('', $lines);
  if (!$handle = @fopen($filename, 'w')) {
    $messageStack->add(sprintf(MSG_ERROR_CANNOT_WRITE, $filename), 'error');
    return false;
  }
  fwrite($handle, $line);
  fclose($handle);
  return true;
}

/***************************** import/export functions ******************************/
function load_module_xml($module) {
	global $db;
  $error = false;
  $result = trim(file_get_contents(DIR_FS_MODULES . $module . '/' . $module . '.xml'));
  if (!$output = xml_to_object($result)) return false;
  // fix some special cases, multi elements with single entries convert to arrays
  if (is_object($output->Module->Table)) $output->Module->Table = array($output->Module->Table);
  return $output;
}

function build_sample_xml($structure, $db_table) {
  $output = '';
  $temp = $structure->Module->Table;
  foreach ($structure->Module->Table as $table) if ($table->Name == $db_table) {
    $output   .= '<' . $table->TagName . '>' . ' // ' . $table->Description . chr(10);
	foreach ($table->Field as $field) if ($field->CanImport) {
	  $req = ($field->Required) ? ('[' . TEXT_REQUIRED . '] '): '';
	  $output .= '  <' . $field->TagName . '>' . $field->Type . '</' . $field->TagName . '>' . ' // ' . $req . $field->Description . chr(10);
	}
	// check dependent tables and add xml sample
    if (is_object($table->LinkTable)) $table->LinkTable = array($table->LinkTable);
	if (isset($table->LinkTable)) foreach ($table->LinkTable as $subtable) {
      foreach ($temp as $working) if ($subtable->Name == $working->Name) {
	    $output   .= '  <' . $working->TagName . '>' . ' // ' . $working->Description . chr(10);
	    foreach ($working->Field as $field) if ($field->CanImport) {
	      $req = ($field->Required) ? ('[' . TEXT_REQUIRED . '] '): '';
	      $output .= '    <' . $field->TagName . '>' . $field->Type . '</' . $field->TagName . '>' . ' // ' . $req . $field->Description . chr(10);
	    }
        $output   .= '  </' . $working->TagName . '>' . chr(10);
	  }
	}
    $output   .= '</' . $table->TagName . '>' . chr(10);
  }
  return $output;
}

function build_sample_csv($structure, $db_table) {
  $output = '';
  $legend = TEXT_LEGEND . chr(10); // 'Legend:'
  $temp = $structure->Module->Table;
  foreach ($structure->Module->Table as $table) if ($table->Name == $db_table) {
	foreach ($table->Field as $field) {
	  if ($field->CanImport) {
	    $req = ($field->Required) ? ('[' . TEXT_REQUIRED . '] '): '';
	    $output .= $field->TagName . ','; 
	    $legend .= '"' . $field->TagName . ': (' . $field->Type . ') - ' . $req . $field->Description . '"' . chr(10);
	  }
	}
	// check dependent tables and add csv sample based on the max number of entries specified
    if (is_object($table->LinkTable)) $table->LinkTable = array($table->LinkTable);
	if (isset($table->LinkTable)) foreach ($table->LinkTable as $subtable) {
      foreach ($temp as $working) if ($subtable->Name == $working->Name) {
	    for($i = 1; $i <= MAX_IMPORT_CSV_ITEMS; $i++) {
		  foreach ($working->Field as $field) {
	        if ($field->CanImport) {
			  $req = ($field->Required) ? ('[' . TEXT_REQUIRED . '] '): '';
	          $output .= $field->TagName . '_' . $i .','; 
	          if ($i == 1) $legend .= '"' . $field->TagName . '_X: (' . $field->Type . ') - ' . $req . $field->Description . '"' . chr(10);
	        }
		  }
		}
	  }
	}
  }
  return $output . chr(10) . chr(10) . $legend;
}

function table_import_xml($structure, $db_table, $filename) {
//echo 'structure = '; print_r($structure); echo '<br>';
  global $db;
  $data = file_get_contents($_FILES[$filename]['tmp_name'], "r");
  $temp = $structure->Module->Table;
  foreach ($structure->Module->Table as $table) if ($table->Name == $db_table) {
	$tbl_active = $table;
	$tbl_tagname = $table->TagName;
	break;
  }
  if (!$result = xml_to_object($data)) return false;
  // fix some special cases, multi elements with single entries convert to arrays
  if (is_object($result->$tbl_tagname)) $result = array($result);
  foreach ($result->$tbl_tagname as $entry) {
    $sql_array = array();
	foreach ($tbl_active->Field as $field) {
	  $tag = $field->TagName;
	  if (isset($entry->$tag)) $sql_array[$field->Name] = $entry->$tag;
	}
//echo 'sql_array to write to table ' . DB_PREFIX . $db_table . ': '; print_r($sql_array); echo '<br>';
	db_perform(DB_PREFIX . $db_table, $sql_array, 'insert');
	// fetch the id for use with dependent tables
	$id = db_insert_id();
	// now look into dependent tables
    if (is_object($tbl_active->LinkTable)) $tbl_active->LinkTable = array($tbl_active->LinkTable);
	if (isset($tbl_active->LinkTable)) foreach ($tbl_active->LinkTable as $subtable) {
	  $sub_sql_array = array();
	  $sub_sql_array[$subtable->DependentField] = $id;
	  $sub_table_name = $subtable->Name;
      foreach ($temp as $working) if ($subtable->Name == $working->Name) {
	    $subtag = $working->TagName;
	    foreach ($working->Field as $field) {
	      $fieldtag = $field->TagName;
		  if (isset($entry->$subtag->$fieldtag)) $sub_sql_array[$field->Name] = $entry->$subtag->$fieldtag;
	    }
	  }
//echo 'sql_array to write to subtable ' . DB_PREFIX . $sub_table_name . ': '; print_r($sub_sql_array); echo '<br><br>';
	  db_perform(DB_PREFIX . $sub_table_name, $sub_sql_array, 'insert');
	}
  }
}

function table_import_csv($structure, $db_table, $filename) {
  global $db, $messageStack;
  $data = array_map('str_getcsv', file($_FILES[$filename]['tmp_name']));
  // read the header and build array
  if (count(is_array($data) ? $data : []) < 2) {
    $messageStack->add('The number of lines in the file is to small, a csv file must contain a header line and at least on input line!','error');
	return false;
  }
  $header = array_shift($data);
  foreach ($header as $key => $value) $header[$key] = trim($value);
  // build the map structure
  $temp = $structure->Module->Table;
  $map_array = array();
  foreach ($structure->Module->Table as $table) if ($table->Name == $db_table) {
	foreach ($table->Field as $field) {
	  $key = array_search($field->TagName, $header);
	  if ($key !== false) $map_array[$key] = array('cnt' => 0, 'table' => $table->Name, 'field' => $field->Name);
	}
	break;
  }
  // build dependent map tables
  $ref_mapping = array();
  if (is_object($table->LinkTable)) $table->LinkTable = array($table->LinkTable);
  if (isset($table->LinkTable)) foreach ($table->LinkTable as $subtable) {
    foreach ($structure->Module->Table as $working) if ($subtable->Name == $working->Name) {
	  $ref_mapping[$subtable->Name] = array(
		'pri_field' => $subtable->PrimaryField,
		'ref_field' => $subtable->DependentField,
	  );
	  for ($i = 1; $i <= MAX_IMPORT_CSV_ITEMS; $i++) {
		foreach ($working->Field as $field) {
		  $key = array_search($field->TagName . '_' . $i, $header);
		  if ($key !== false) $map_array[$key] = array(
		    'cnt'   => $i,
			'table' => $subtable->Name,
			'field' => $field->Name,
		  );
	    }
	  }
	}
  }
  foreach ($data as $line) {
	$line_array = $map_array;
	$sql_array  = array();
    for ($i = 0; $i < count(is_array($line) ? $line : []); $i++) $line_array[$i]['value'] = $line[$i];
	foreach ($line_array as $value) {
		if (!$value['table']) continue;
		$sql_array[$value['table']][$value['cnt']][$value['field']] = $value['value'];
	}
	foreach ($sql_array as $table => $count) {
	  foreach ($count as $cnt => $table_array) {
	    if ($cnt == 0) { // main record, fetch id afterwards
	      if (!empty($table_array)) {
//echo "inserting main record to table $table data: ".print_r($table_array, true).'<br>';
	      	db_perform(DB_PREFIX . $table, $table_array, 'insert');
	      }
		  $id = db_insert_id();
		} else { // dependent table 
		  $data_present = false;
		  foreach ($table_array as $value) if (gen_not_null($value)) $data_present = true;
		  if ($data_present) {
		  	$table_array[$ref_mapping[$table]['ref_field']] = $id;
//echo "inserting main record to table $table data: ".print_r($table_array, true).'<br>';
		  	db_perform(DB_PREFIX . $table, $table_array, 'insert');
		  }
		}
	  }
	}
  }
}

function table_export_xml($structure, $db_table) {
  global $db;
  $output = '';
  $temp   = $structure->Module->Table;
  foreach ($structure->Module->Table as $table) if ($table->Name == $db_table) {
    $tag_map = array();
	foreach ($table->Field as $field) $tag_map[$field->Name] = $field->TagName;
	$result = $db->Execute("select * from " . DB_PREFIX . $db_table);
    if ($result->RecordCount() > 0) while (!$result->EOF) {
	  $output   .= '<' . $table->TagName . '>' . chr(10);
	  foreach ($result->fields as $key => $value) {
	    $output .= '  <' . $tag_map[$key] . '>' . $value . '</' . $tag_map[$key] . '>' . chr(10);
	  }
      $output   .= '</' . $table->TagName . '>' . chr(10);
	  $result->MoveNext();
	}
  }
  return $output;
}

function table_export_csv($structure, $db_table) {
  global $db;
  $output = '';
  $header = false;
  $temp   = $structure->Module->Table;
  foreach ($structure->Module->Table as $table) if ($table->Name == $db_table) {
    $tag_map = array();
	foreach ($table->Field as $field) $tag_map[$field->Name] = $field->TagName;
	$result = $db->Execute("select * from " . DB_PREFIX . $db_table);
    if ($result->RecordCount() > 0) while (!$result->EOF) {
	  if (!$header) { // output the header
	    $temp    = array();
		foreach ($result->fields as $key => $value) $temp[] = $tag_map[$key];
	    $output .= implode(',', $temp) . chr(10);
	    $header  = true;
	  }
	  $temp = array();
	  foreach ($result->fields as $key => $value) {
	    $temp[] = (strpos($value, ',') !== false) ? ('"'.$value.'"') : $value;
	  }
	  $output .= implode(',', $temp) . chr(10);
	  $result->MoveNext();
	}
  }
  return $output;
}

function csv_explode($str, $delim = ',', $enclose = '"', $preserve = false){
	$results = preg_split("/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", trim($str));
	return preg_replace("/^\"(.*)\"$/", "$1", $results);
} 

/**************************** extra tab/fields functions ***********************************************/
// Syncronizes the fields in the module db with the field parameters 
// (usually only needed for first entry to inventory field builder)
  function xtra_field_sync_list($module = '', $db_table = '') {
	global $db, $messageStack;
	if (!$module || !$db_table) {
	  $messageStack->add('Sync fields called without all necessary parameters!','error');
	  return;
	}
	// First check to see if inventory field table is synced with actual inventory table
	$table_fields = array();
	$temp = $db->Execute("describe " . $db_table);
	while (!$temp->EOF) {
		$table_fields[]=$temp->fields['Field'];
		$temp->MoveNext();
	}
	sort($table_fields);
	$field_list = array(); // stays empty when TABLE_EXTRA_FIELDS has no rows yet for this module (e.g. a fresh install)
	$temp = $db->Execute("select field_name from " . TABLE_EXTRA_FIELDS . " where module_id = '$module' order by field_name");
	while (!$temp->EOF) {
		$field_list[]=$temp->fields['field_name'];
		$temp->MoveNext();
	}
	$needs_sync = false;
	foreach ($table_fields as $key=>$value) {
		if ($value <> ($field_list[$key] ?? null)) {
			$needs_sync = true;
			break;
		}
	}
	if ($needs_sync) {
		if (is_array($field_list)) {
			$add_list = array_diff($table_fields, $field_list);
		} else {
			$add_list = $table_fields;
		}
			$delete_list = '';
		if (is_array($field_list)) $delete_list = array_diff($field_list, $table_fields);
		if (isset($add_list)) {
			foreach ($add_list as $value) { // find the field attributes and copy to field list table
				$myrow = $db->Execute("show fields from $db_table like '$value'");
				$Params = array('default' => $myrow->fields['Default']);
				$type = $myrow->fields['Type'];
				if (strpos($type,'(') === false) {
					$data_type = strtolower($type);
				} else {
					$data_type = strtolower(substr($type,0,strpos($type,'(')));
				}
				switch ($data_type) {
					case 'date':      $Params['type'] = 'date'; break;

					case 'time':      $Params['type'] = 'time'; break;
					case 'datetime':  $Params['type'] = 'date_time'; break;
					case 'timestamp': $Params['type'] = 'time_stamp'; break;
					case 'year':      $Params['type'] = 'date'; break;
	
					case 'bigint':
					case 'int':
					case 'mediumint':
					case 'smallint':
					case 'tinyint':
						$Params['type'] = 'integer';
						if ($data_type=='tinyint')   $Params['default'] = '0';
						if ($data_type=='smallint')  $Params['default'] = '1';
						if ($data_type=='mediumint') $Params['default'] = '2';
						if ($data_type=='int')       $Params['default'] = '3';
						if ($data_type=='bigint')    $Params['default'] = '4';
						break;
					case 'decimal':
					case 'double':
					case 'float':
						$Params['type'] = 'decimal';
						if ($data_type=='float')  $Params['default'] = '0';
						if ($data_type=='double') $Params['default'] = '1';
						break;
					case 'tinyblob':
					case 'tinytext':
					case 'char':
					case 'varchar':
					case 'longblob':
					case 'longtext':
					case 'mediumblob':
					case 'mediumtext':
					case 'blob':
					case 'text':
						$Params['type'] = 'text';
						if ($data_type=='varchar' OR $data_type=='char') { // find the actual db length
							$Length = trim(substr($type, strpos($type,'(')+1, strpos($type,')')-strpos($type,'(')-1));
							$Params['length'] = $Length;
						}
						if ($data_type=='tinytext'   OR $data_type=='tinyblob')   $Params['length'] = '255';
						if ($data_type=='text'       OR $data_type=='blob')       $Params['length'] = '65,535';
						if ($data_type=='mediumtext' OR $data_type=='mediumblob') $Params['length'] = '16,777,215';
						if ($data_type=='longtext'   OR $data_type=='longblob')   $Params['length'] = '4,294,967,295';
						break;
					case 'enum':
					case 'set':
						$Params['type'] = 'drop_down';
						$temp = trim(substr($type, strpos($type,'(')+1, strpos($type,')')-strpos($type,'(')-1));
						$selections = explode(',', $temp);
						$defaults = '';
						foreach($selections as $selection) {
							$selection = preg_replace("/'/", '', $selection);
							if ($myrow->fields['Default'] == $selection) $set = 1; else $set = 0;
							$defaults .= $selection . ':' . $selection .':' . $set . ',';
						}
						$defaults = substr($defaults, 0, -1);
						$Params['default'] = $defaults;
						break;
					default:
				}
				$temp = $db->Execute("insert into " . TABLE_EXTRA_FIELDS . " set 
					module_id = '$module', 
					tab_id = 0, 
					entry_type = '{$Params['type']}', 
					field_name = '$value', 
					description = '$value', 
					params = '" . serialize($Params) . "'");  // tab_id = 0 for System category
			}
		}
		if ($delete_list) {
			foreach ($delete_list as $value) {
				$temp = $db->Execute("delete from " . TABLE_EXTRA_FIELDS . " where module_id='$module' and field_name='$value'");
			}
		}
	}
	return;
  }

  function xtra_field_get_tabs($module = '') {
    global $db;
    $tab_array = array(0 => TEXT_SYSTEM);
	if (!$module) return $tab_array;
    $result = $db->Execute("select id, tab_name from " . TABLE_EXTRA_TABS . " where module_id = '$module' order by tab_name");
    while (!$result->EOF) {
      $tab_array[$result->fields['id']] = $result->fields['tab_name'];
      $result->MoveNext();
    }
    return $tab_array;
  }

  function xtra_field_prep_form($form_array) {
	// set the default values
	$form_array['text_length']     = DEFAULT_TEXT_LENGTH;
	$form_array['text_default']    = '';
	$form_array['link_default']    = '';
	$form_array['integer_range']   = '0';
	$form_array['integer_default'] = '0';
	$form_array['decimal_range']   = '0';
	$form_array['decimal_display'] = DEFAULT_REAL_DISPLAY_FORMAT;
	$form_array['decimal_default'] = '';
	$form_array['radio_default']   = '';
	$form_array['date_range']      = '0';
	$form_array['time_range']      = '0';
	$form_array['date_time_range'] = '0';
	$form_array['check_box_range'] = '0';
	switch ($form_array['entry_type']) {
		case 'text':
		case 'html':
			$form_array['text_length']  = $form_array['length'];
			$form_array['text_default'] = $form_array['default'];
			break;
		case 'hyperlink':
		case 'image_link':
		case 'inventory_link':
			$form_array['link_default'] = $form_array['default'];
			break;
		case 'integer':
			$form_array['integer_range']   = $form_array['select'];
			$form_array['integer_default'] = $form_array['default'];
			break;
		case 'decimal':
			$form_array['decimal_range']   = $form_array['select'];
			$form_array['decimal_display'] = $form_array['display'];
			$form_array['decimal_default'] = $form_array['default'];
			break;
		case 'multi_check_box':
		case 'drop_down':
		case 'radio':
		case 'enum':
			$form_array['radio_default'] = $form_array['default'];
			break;
		case 'date':
			$form_array['date_range'] = $form_array['select'];
			break;
		case 'time':
			$form_array['time_range'] = $form_array['select'];
			break;
		case 'date_time':
			$form_array['date_time_range'] = $form_array['select'];
			break;
		case 'check_box':
			$form_array['check_box_range'] = $form_array['select'];
			break;
		case 'time_stamp':
			break;
		default:
	}
	return $form_array;
  }

  /**
   * Establishes an authenticated session for $user (a TABLE_USERS row -
   * needs admin_id, display_name, admin_email, admin_prefs, account_id,
   * admin_security). Shared by both the password login and the passkey
   * login ceremony in modules/phreedom/pages/main/pre_process.php so the
   * two credential types can never diverge in what a "logged in" session
   * looks like.
   */
  function pb_establish_session($user, $company, $language) {
	global $db, $loaded_modules;
	$db->Execute("UPDATE " . TABLE_USERS . " SET failed_attempts = 0, locked_until = NULL WHERE admin_id = " . (int)$user['admin_id']);
	session_regenerate_id(true); // prevent session fixation
	$_SESSION['admin_id']       = $user['admin_id'];
	$_SESSION['display_name']   = $user['display_name'];
	$_SESSION['admin_email']    = $user['admin_email'];
	$_SESSION['admin_prefs']    = unserialize($user['admin_prefs']);
	$_SESSION['company']        = $company;
	$_SESSION['language']       = $language;
	$_SESSION['account_id']     = $user['account_id'];
	$_SESSION['admin_security'] = gen_parse_permissions($user['admin_security']);
	// set some cookies for the next visit to remember the company, language, and theme
	$cookie_exp = 2592000 + time(); // one month
	$cookie_opts = ['expires' => $cookie_exp, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => ENABLE_SSL_ADMIN === 'true'];
	setcookie('pb_company' , $company,  $cookie_opts);
	setcookie('pb_language', $language, $cookie_opts);
	// load init functions for each module and execute
	require_once(DIR_FS_MODULES . 'phreedom/classes/install.php');
	$phreedom = new phreedom_admin();
	if (MODULE_PHREEDOM_STATUS <> MODULE_PHREEDOM_VERSION) $phreedom->update('phreedom');
	$phreedom->initialize($loaded_modules);
	if (defined('TABLE_CONTACTS')) {
	  $dept = $db->Execute("select dept_rep_id from " . TABLE_CONTACTS . " where id = " . (int)$user['account_id']);
	  $_SESSION['department'] = $dept->fields['dept_rep_id'];
	}
  }

?>