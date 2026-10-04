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
//  Path: /modules/forms/pages/datasets/pre_process.php
//
/**
 * Defining datasets — the join builder (design §3.2, §3.3).
 *
 * The dangerous privilege of the three, with its own security id.
 *
 * The rule this page exists to keep: **an author picks identifiers; they never
 * supply SQL syntax.** Every table and column offered comes from
 * SchemaInspector, and everything submitted is checked against it again before
 * storage — so a name that was not offered, whether typed, replayed or forged,
 * does not survive. There is deliberately no free-form condition box anywhere
 * on this page: such a box is the whole of §1.1.
 *
 * The worst a careless author can still do is expose a table they should not
 * have — a data-exposure problem, auditable and reversible. They cannot inject
 * SQL, stack a query, UNION, or reach the sensitive tables, because they never
 * supply syntax and Dataset refuses those tables at construction whatever this
 * page does.
 */

$security_level = validate_user(SECURITY_ID_FORMS_DATASETS);

require(DIR_FS_WORKING . 'defaults.php');
// FORMS_DIR_DEFINITIONS comes from the require above, and it is the only thing
// forms_datasets_in_use() needs beyond the catalogue: it counts through the
// loader, which reads a definition in whichever format it is stored in.
require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/catalog/load.php');

use FreeBooks\Forms\Catalog\{Catalog, CatalogException, CustomDatasetStore, DatasetTier,
    JoinType, SchemaInspector, ShippedDatasets};

$schema = new SchemaInspector($db);
$store  = new CustomDatasetStore($db, $schema, TABLE_FORMS_DATASETS);

$forms_ds_id     = isset($_REQUEST['dsID']) ? (string)$_REQUEST['dsID'] : '';
$forms_ds_action = isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '';

/***************   Act on the action request   *************************/
switch ($forms_ds_action) {
  case 'save':
    // Only a POST carries the CSRF token.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      die('This action must be submitted from its page.');
    }
    validate_security($security_level, empty($_POST['existing']) ? 2 : 3);
    try {
      // The submitted arrays are indexed by row, and rows can be removed in the
      // browser, so they arrive sparse. Re-indexing keeps the stored order the
      // author sees; validation is the store's job, not this page's.
      $joins  = array_values((array)($_POST['join']  ?? array()));
      $fields = array_values((array)($_POST['field'] ?? array()));
      foreach ($joins as $index => $join) {
        $joins[$index]['on'] = array_values((array)($join['on'] ?? array()));
      }

      $saved = $store->save(array(
        'id'          => $_POST['id']          ?? '',
        'label'       => $_POST['label']       ?? '',
        'description' => $_POST['description'] ?? '',
        'baseTable'   => $_POST['baseTable']   ?? '',
        'joins'       => $joins,
        'fields'      => $fields,
      ), empty($_POST['existing']));

      $messageStack->add(sprintf(FORMS_DS_SAVED, $saved), 'success');
      gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=forms&page=datasets', 'SSL'));
    } catch (CatalogException $e) {
      // Shown, and the form redisplayed with what was typed: a rejected join is
      // usually one field wrong out of a dozen.
      $messageStack->add(sprintf(FORMS_DS_ERROR, $e->getMessage()), 'error');
      $forms_ds_action = 'edit';
      $forms_ds_id     = (string)($_POST['id'] ?? '');
    } catch (\Throwable $e) {
      $messageStack->add(sprintf(FORMS_DS_ERROR, $e->getMessage()), 'error');
      $forms_ds_action = 'edit';
      $forms_ds_id     = (string)($_POST['id'] ?? '');
    }
    break;

  case 'delete':
    validate_security($security_level, 4);
    // Only as a POST, which index.php has checked for its csrf_token: a GET
    // could be a link someone was sent.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=forms&page=datasets', 'SSL'));
    // A dataset a report still names is not deleted. Removing it would leave
    // the report resolving to nothing, which reads as a broken report rather
    // than as a removed dataset.
    $inUse = forms_datasets_in_use($forms_ds_id);
    if ($inUse > 0) {
      $messageStack->add(sprintf(FORMS_DS_IN_USE, $forms_ds_id, $inUse), 'error');
    } else {
      $store->delete($forms_ds_id);
      $messageStack->add(sprintf(FORMS_DS_DELETED, $forms_ds_id), 'success');
    }
    gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=forms&page=datasets', 'SSL'));
    break;
}

/***************   Prepare the page   *************************/
$forms_ds_editing = in_array($forms_ds_action, array('new', 'edit'), true);

if ($forms_ds_editing) {
  // On a rejected save, redisplay what was submitted rather than what is
  // stored; otherwise the author loses every other row they had filled in.
  if (($_POST['id'] ?? '') !== '') {
    $forms_ds_record = array(
      'id'          => (string)$_POST['id'],
      'label'       => (string)($_POST['label'] ?? ''),
      'description' => (string)($_POST['description'] ?? ''),
      'baseTable'   => (string)($_POST['baseTable'] ?? ''),
      'joins'       => array_values((array)($_POST['join']  ?? array())),
      'fields'      => array_values((array)($_POST['field'] ?? array())),
      'existing'    => !empty($_POST['existing']),
    );
  } elseif ($forms_ds_action === 'edit' && $forms_ds_id !== '') {
    $loaded = $store->load($forms_ds_id);
    if (!$loaded) {
      $messageStack->add(sprintf(FORMS_DS_NOT_FOUND, $forms_ds_id), 'error');
      $forms_ds_editing = false;
    } else {
      $forms_ds_record             = $loaded;
      $forms_ds_record['existing'] = true;
    }
  } else {
    $forms_ds_record = array('id' => '', 'label' => '', 'description' => '',
                             'baseTable' => '', 'joins' => array(), 'fields' => array(),
                             'existing' => false);
  }
}

// Required of every page by includes/template_index.php, which renders it as the
// browser <title>. Set after the editing state settles, because loading a
// missing dataset turns an edit back into the list.
define('PAGE_TITLE', $forms_ds_editing
    ? (!empty($forms_ds_record['existing']) ? FORMS_DS_EDIT : FORMS_DS_NEW)
    : FORMS_DS_TITLE);

// Offered choices. Every one of these comes from the live schema, with the
// sensitive tables already filtered out (§3.3).
$forms_ds_tables = array();
foreach ($schema->tables() as $table) {
  $forms_ds_tables[] = array('id' => $table, 'text' => $table);
}
$forms_ds_join_types = array(
  array('id' => JoinType::Inner->value, 'text' => FORMS_DS_JOIN_INNER),
  array('id' => JoinType::Left->value,  'text' => FORMS_DS_JOIN_LEFT),
);
$forms_ds_value_types = array();
foreach (\FreeBooks\Forms\Document\ValueType::cases() as $case) {
  $forms_ds_value_types[] = array('id' => $case->name, 'text' => $case->name);
}

// The list. Shipped datasets are shown alongside the custom ones and marked as
// uneditable — an author needs to see what already exists before defining
// another, and the tier is exactly the distinction worth showing.
$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$forms_ds_shipped = $catalog->all();
$forms_ds_custom  = $store->all();
foreach ($store->problems() as $problem) {
  $messageStack->add(sprintf(FORMS_DS_PROBLEM, $problem), 'caution');
}

/**
 * How many stored report definitions name this dataset.
 */
function forms_datasets_in_use($datasetId) {
  global $db;
  static $counts = null;
  if ($counts === null) {
    $counts = array();
    require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

    $catalog = new \FreeBooks\Forms\Catalog\Catalog();
    \FreeBooks\Forms\Catalog\ShippedDatasets::registerAll($catalog);
    $schema = new \FreeBooks\Forms\Catalog\SchemaInspector($db);
    (new \FreeBooks\Forms\Catalog\CustomDatasetStore($db, $schema, TABLE_FORMS_DATASETS))
        ->registerAll($catalog);
    // The loader reads a definition in whichever format it is stored in, and a
    // native definition NAMES its dataset, so nothing has to be inferred from a
    // table list.
    $loader = new \FreeBooks\Forms\Definition\DefinitionLoader($catalog);

    $result = $db->Execute("select id from " . TABLE_PHREEFORM . " where doc_type <> '0'");
    while (!$result->EOF) {
      $id = $result->fields['id'];
      $result->MoveNext();
      try {
        $definition = $loader->load($id);
      } catch (\Throwable $e) {
        continue;   // counting what is in use must not fail on one bad file
      }
      if ($definition->datasetId !== null) {
        $counts[$definition->datasetId] = ($counts[$definition->datasetId] ?? 0) + 1;
      }
    }
  }
  return $counts[$datasetId] ?? 0;
}

$use_bootstrap_nav = true;
