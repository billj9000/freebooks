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
//  Path: /modules/phreehelp/functions/phreehelp.php
//

function synchronize() {
  global $db;
  $config = $db->Execute('TRUNCATE ' . TABLE_PHREEHELP);
  // recursively read file and store in db
  $extensions = explode(',', VALID_EXTENSIONS);
  $file_list = array();
  $modules = scandir(DIR_FS_MODULES);
  foreach ($modules as $module) {
    if ($module <> '.' && $module <> '..') {
      if       (file_exists(DIR_WS_MODULES . $module . '/language/' . $_SESSION['language'] . '/manual')) {
	    $file_list = array_merge($file_list, directory_to_array(DIR_WS_MODULES . $module . '/language/' . $_SESSION['language'] . '/manual', $extensions));
	  } elseif (file_exists(DIR_WS_MODULES . $module . '/language/en_us/manual')) {
	    $file_list = array_merge($file_list, directory_to_array(DIR_WS_MODULES . $module . '/language/en_us/manual', $extensions));
	  }
	}
  }
  $toc = array();
  foreach ($file_list as $file_name) {
	if (defined('DOC_REL_PATH')) $file_name = str_replace(DOC_REL_PATH, DOC_ROOT_URL, $file_name); // convert to url to read script generated filenames
	$tags      = get_meta_tags($file_name);
	$doc_html  = file_get_contents($file_name);
	preg_match('/<title>([^>]*)<\/title>/si', $doc_html, $match);
	$doc_title = (isset($match) && is_array($match) && count($match) > 0) ? strip_tags($match[1]) : TEXT_NO_TITLE;
	$doc_text  = trim(strip_tags($doc_html));
	$doc_text  = str_replace(chr(10), ' ', $doc_text); // process out special characters
	$sql      = "insert into " . TABLE_PHREEHELP . " (doc_url, doc_pos, doc_index, doc_title, doc_text)
	  values ('" . addslashes($file_name) . "', '" . addslashes($tags['doc_pos']) . "', '" . addslashes($tags['doc_index_1']) . "', '" . addslashes($doc_title) . "', '" . addslashes($doc_text) . "')";
	$row      = $db->Execute($sql);
	$id       = db_insert_id();
	$toc[$id] = $tags['doc_pos'];
  }
  foreach ($toc as $id => $value) {
    if (strrpos($value, '.') === false) {
	  $parent = '0';
    } else {
	  $parent = substr($value, 0, strrpos($value, '.'));
	  $key    = array_search($parent, $toc);
	  if ($key !== false) { // if no parent found, default to root
	    $db->Execute("update " . TABLE_PHREEHELP . " set parent_id = " . $key . " where id = " . $id);
	    $db->Execute("update " . TABLE_PHREEHELP . " set doc_type = '0' where id = " . $key); // set parent to type folder
	  }
    }
  }
}

function directory_to_array($directory, $extension = "", $full_path = true) {
  $array_items = array();
  if (!$contents = scandir($directory)) return $array_items;
  foreach ($contents as $file) {
	if ($file <> "." && $file <> "..") {
	  if (is_dir($directory. "/" . $file)) {
		$array_items = array_merge($array_items, directory_to_array($directory. "/" . $file, $extension, $full_path));
	  } else {
		$file_ext = substr(strrchr($file, "."), 1);
		if (!$extension || in_array($file_ext, $extension)) {
		  $array_items[] = $full_path ? ($directory . "/" . $file) : $file;
		}
	  }
	}
  }
  return $array_items;
}

function retrieve_toc() {
  global $db;
  $dir_only = false;
  $toc = $db->Execute('select id, parent_id, doc_type, doc_url, doc_title from ' . TABLE_PHREEHELP . '
	order by parent_id, doc_pos');
  $toc_array = array();
  $toc_array[-1][] = array('id' => 0, 'doc_type' => '0', 'doc_title' => TEXT_MANUAL); // home dir
  while (!$toc->EOF) {
	if (!$dir_only || ($dir_only && $toc->fields['doc_type'] == '0')) {
	  $toc_array[$toc->fields['parent_id']][] = array(
		'id'        => $toc->fields['id'],
		'doc_type'  => $toc->fields['doc_type'],
		'doc_url'   => $toc->fields['doc_url'],
		'doc_title' => $toc->fields['doc_title'],
	  );
	}
	$toc->MoveNext();
  }
  $toc_string = build_help_tree('dir_tree', $toc_array, $index = -1, $level = 0);
  return $toc_string;
}

// Renders the Contents tab as a nested <ul> tree (fb-help-tree, styled in
// template_main.php). Folders default open at the top level and collapsed
// below, toggled client-side by Toggle()/Expand()/Collapse() in
// template_main.php.
function build_help_tree($name, $full_array, $index = -1, $level = 0) {
  $entries = (isset($full_array[$index]) && is_array($full_array[$index])) ? $full_array[$index] : array();
  if (empty($entries)) return '';
  $entry_string = '<ul class="fb-help-tree' . ($level == 0 ? ' fb-help-tree-root' : '') . '">' . chr(10);
  foreach ($entries as $entry) {
    $new_ref      = $index . '_' . $entry['id'];
    $has_children = isset($full_array[$entry['id']]);
    $entry_string .= '<li class="fb-help-node">' . chr(10);
    if ($entry['doc_type'] == '0') { // folder
      $entry_string .= '<span class="fb-help-folder" onclick="Toggle(\'dc_' . $new_ref . '\')">';
      $entry_string .= '<a id="imgdc_' . $new_ref . '" href="javascript:void(0);" class="fb-help-toggle" onclick="event.stopPropagation();Toggle(\'dc_' . $new_ref . '\');">' . html_icon('places/folder.png', TEXT_OPEN, 'small') . '</a> ';
      if ($entry['doc_url']) {
        $entry_string .= '<a href="' . $entry['doc_url'] . '" target="mainFrame" class="fb-help-label" onclick="event.stopPropagation();">' . htmlspecialchars($entry['doc_title']) . '</a>';
      } else {
        $entry_string .= '<span class="fb-help-label">' . htmlspecialchars($entry['doc_title']) . '</span>';
      }
      $entry_string .= '</span>' . chr(10);
    } else { // leaf document
      $entry_string .= '<span class="fb-help-leaf">' . html_icon('mimetypes/text-x-generic.png', '', 'small') . ' <a href="' . $entry['doc_url'] . '" target="mainFrame" class="fb-help-label">' . htmlspecialchars($entry['doc_title']) . '</a></span>' . chr(10);
    }
    if ($has_children) {
      $display_none  = ($level == 0) ? '' : 'display:none;';
      $entry_string .= '<div id="dc_' . $new_ref . '" style="' . $display_none . '">' . chr(10);
      $entry_string .= build_help_tree($name, $full_array, $entry['id'], $level + 1) . chr(10);
      $entry_string .= '</div>' . chr(10);
    }
    $entry_string .= '</li>' . chr(10);
  }
  $entry_string .= '</ul>' . chr(10);
  return $entry_string;
}

// Shared by the Index tab (retrieve_index) and Search tab (search_results).
// Same fb-help-tree markup as build_help_tree; groups always render
// collapsed since there's no natural "top level" here. Note: doc_title
// values already contain HTML entities as authored in each page's <title>
// tag, so they are intentionally NOT re-escaped here.
function build_href($array_tree, $ref = '') {
  ksort($array_tree);
  if (empty($array_tree)) return '';
  $is_root_level = (strpos($ref, '_') === false);
  $entry_string  = '<ul class="fb-help-tree' . ($is_root_level ? ' fb-help-tree-root' : '') . '">' . chr(10);
  foreach ($array_tree as $entry) {
    $new_ref      = $ref . '_' . $entry['id'];
    $has_children = isset($entry['children']);
    $entry_string .= '<li class="fb-help-node">' . chr(10);
    if ($has_children) {
      $entry_string .= '<span class="fb-help-folder" onclick="Toggle(\'' . $new_ref . '\')">';
      $entry_string .= '<a id="img' . $new_ref . '" href="javascript:void(0);" class="fb-help-toggle" onclick="event.stopPropagation();Toggle(\'' . $new_ref . '\');">' . html_icon('places/folder.png', TEXT_FOLDER, 'small') . '</a> ';
    } else {
      $entry_string .= '<span class="fb-help-leaf">';
      $entry_string .= html_icon('mimetypes/text-html.png', TEXT_DOCUMENT, 'small') . ' ';
    }
    $title = isset($entry['doc_title']) ? $entry['doc_title'] : TEXT_UNTITLED;
    if (isset($entry['doc_url'])) {
      $entry_string .= '<a href="' . $entry['doc_url'] . '" target="mainFrame" class="fb-help-label"' . ($has_children ? ' onclick="event.stopPropagation();"' : '') . '>' . $title . '</a>';
    } else {
      $entry_string .= '<span class="fb-help-label">' . $title . '</span>';
    }
    $entry_string .= '</span>' . chr(10);
    if ($has_children) {
      $entry_string .= '<div id="' . $new_ref . '" style="display:none;">' . chr(10);
      $entry_string .= build_href($entry['children'], $new_ref) . chr(10);
      $entry_string .= '</div>' . chr(10);
    }
    $entry_string .= '</li>' . chr(10);
  }
  $entry_string .= '</ul>' . chr(10);
  return $entry_string;
}

function retrieve_index() {
  global $db;
  $index = $db->Execute('select id, doc_url, doc_index from ' . TABLE_PHREEHELP . ' order by doc_index');
  $index_array = array();
  while (!$index->EOF) {
	$element = explode('.', $index->fields['doc_index']);
	$index_array[$element[0]]['id']        = $index->fields['id'];
	$index_array[$element[0]]['doc_title'] = $element[0];
	switch (count($element)) { // currently handles two levels deep
	  case '1': $index_array[$element[0]]['doc_url']   = $index->fields['doc_url']; break;
	  case '2':
		$index_array[$element[0]]['children'][$element[1]]['id']        = $index->fields['id'];
		$index_array[$element[0]]['children'][$element[1]]['doc_url']   = $index->fields['doc_url'];
		$index_array[$element[0]]['children'][$element[1]]['doc_title'] = $element[1];
		break;
	  default: // no index specified, ignore
	}
	$index->MoveNext();
  }
  $index_string = build_href($index_array, $ref = 'idx');
  return $index_string;
}

function search_results($search_text) {
  global $db;
  if (!$search_text) return '';
  $search_text = db_input($search_text);
  $sql = "select id, doc_url, doc_title, MATCH (doc_title, doc_text) AGAINST ('" . $search_text . "') as score
    from " . TABLE_PHREEHELP . " where MATCH (doc_title, doc_text) AGAINST ('" . $search_text . "')";
  $results = $db->Execute($sql);
  if ($results->RecordCount() == 0) return TEXT_NO_RESULTS;
  $search_array = array();
  $index = 0;
  while (!$results->EOF) {
	$search_array[$index]['id']        = $results->fields['id'];
	$search_array[$index]['doc_url']   = $results->fields['doc_url'];
	$search_array[$index]['doc_title'] = $results->fields['doc_title'];
	$index++;
	$results->MoveNext();
  }
  $search_string = build_href($search_array, $ref = 'srch');
  return $search_string;
}

?>
