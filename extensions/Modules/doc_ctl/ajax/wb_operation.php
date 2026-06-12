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
//  Path: /modules/doc_ctl/ajax/wb_operation.php
//
$security_level = validate_ajax_user(SECURITY_ID_DOC_CONTROL);
require_once(DIR_FS_MODULES . 'doc_ctl/defaults.php');
require_once(DIR_FS_MODULES . 'doc_ctl/functions/doc_ctl.php');

// Set up legacy DB config required by json_tree's internal _database class
$db_config = [
  'servername' => DB_SERVER_HOST,
  'username'   => DB_SERVER_USERNAME,
  'password'   => DB_SERVER_PASSWORD,
  'database'   => DB_DATABASE,
];
if (extension_loaded('mysqli')) {
  require_once(DIR_FS_MODULES . 'doc_ctl/includes/jstree/_lib/class._database_i.php');
} else {
  require_once(DIR_FS_MODULES . 'doc_ctl/includes/jstree/_lib/class._database.php');
}
require_once(DIR_FS_MODULES . 'doc_ctl/includes/jstree/_lib/class.tree.php');

header('Content-type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$sub = $_REQUEST['sub'] ?? '';

switch ($sub) {
  case 'wb_tree':
    echo json_encode(wb_build_subtree(1));
    break;

  case 'wb_create':
    $parent_id = (int)($_POST['parent_id'] ?? 0);
    $title     = trim($_POST['title'] ?? 'New');
    $type      = in_array($_POST['type'] ?? '', ['folder', 'default']) ? $_POST['type'] : 'default';
    $r         = $db->Execute("SELECT COUNT(*) AS cnt FROM " . TABLE_DC_DOCUMENT . " WHERE parent_id = " . $parent_id);
    $pos       = (int)$r->fields['cnt'];
    $jstree    = new json_tree();
    ob_start();
    $resp = $jstree->create_node(['id' => $parent_id, 'position' => $pos, 'title' => $title, 'type' => $type]);
    ob_end_clean();
    echo $resp;
    break;

  case 'wb_rename':
    $id    = (int)($_POST['id']    ?? 0);
    $title = trim($_POST['title'] ?? '');
    if ($id && $title !== '') {
      db_perform(TABLE_DC_DOCUMENT, ['title' => $title], 'update', "id = $id");
      echo '{"status":1}';
    } else {
      echo '{"status":0}';
    }
    break;

  case 'wb_remove':
    $id     = (int)($_POST['id'] ?? 0);
    $jstree = new json_tree();
    ob_start();
    $resp = $jstree->remove_node(['id' => $id]);
    ob_end_clean();
    echo $resp;
    break;

  case 'wb_move':
    $id       = (int)($_POST['id']       ?? 0);
    $ref      = (int)($_POST['ref']      ?? 0);
    $position = (int)($_POST['position'] ?? 0);
    $jstree   = new json_tree();
    ob_start();
    $resp = $jstree->move_node(['id' => $id, 'ref' => $ref, 'position' => $position, 'copy' => 0]);
    ob_end_clean();
    echo $resp;
    break;

  default:
    echo json_encode(['status' => 0, 'error' => 'unknown sub-operation']);
}

function wb_build_subtree($parent_id) {
  global $db;
  $result = $db->Execute(
    "SELECT id, title, type, `left`, `right`, security FROM " . TABLE_DC_DOCUMENT .
    " WHERE parent_id = " . (int)$parent_id . " ORDER BY position ASC"
  );
  $nodes = [];
  while (!$result->EOF) {
    $f   = $result->fields;
    $sec = $f['security'] ?: 'u:0;g:0';
    if (!security_check($sec)) { $result->MoveNext(); continue; }
    $is_folder    = ($f['type'] === 'folder' || $f['type'] === 'drive');
    $has_children = ((int)$f['right'] - (int)$f['left'] > 1);
    $node = [
      'title'  => $f['title'],
      'key'    => (string)$f['id'],
      'type'   => $f['type'],
      'folder' => $is_folder,
    ];
    if ($is_folder) {
      $node['children'] = $has_children ? wb_build_subtree((int)$f['id']) : [];
    }
    $nodes[] = $node;
    $result->MoveNext();
  }
  return $nodes;
}
?>
