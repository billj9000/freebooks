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
//  Path: /api.php
//
// FreeBooks REST API entry point.
//
// Usage: GET /api.php?v=1&resource={resource}&[fields=...]&[filters=...]&[order_by=...]&[limit_start=N]&[limit_page_length=N]
// Auth:  Authorization: Bearer fbk_live_<key>
//
// See trunk/modules/api/README.md for full documentation.
//

define('PAGE_EXECUTION_START_TIME', microtime(true));
header('Content-Type: application/json; charset=utf-8');

// ── Resolve trunk root ────────────────────────────────────────────────────
$trunk = __DIR__;

if (!file_exists($trunk . '/includes/configure.php')) {
  http_response_code(503);
  echo json_encode(['ok' => false, 'error' => 'Application not configured.']);
  exit;
}
require($trunk . '/includes/configure.php');
if (!defined('DIR_FS_ADMIN')) define('DIR_FS_ADMIN', $trunk . '/');

// ── Path constants (mirrors cron/zencart_sync.php and application_top.php) ───
if (!defined('PATH_TO_MY_FILES')) define('PATH_TO_MY_FILES', 'my_files/');
define('DIR_WS_MODULES',   'modules/');
define('DIR_WS_MY_FILES',  PATH_TO_MY_FILES);
define('DIR_FS_INCLUDES',  DIR_FS_ADMIN . 'includes/');
define('DIR_FS_MODULES',   DIR_FS_ADMIN . 'modules/');
define('DIR_FS_MY_FILES',  DIR_FS_ADMIN . PATH_TO_MY_FILES);
define('DIR_FS_THEMES',    DIR_FS_ADMIN . 'themes/');
define('FILENAME_DEFAULT', 'index');
define('COG_ITEM_TYPES',   'si,sr,ms,mi,ma,sa');

if (file_exists(DIR_FS_ADMIN . 'vendor/autoload.php')) {
  require_once(DIR_FS_ADMIN . 'vendor/autoload.php');
}

// ── Minimal session-like context ──────────────────────────────────────────
// No real PHP session — auth is via Bearer token.  We initialise $_SESSION
// as a plain array so any downstream function that reads from it works.
$_SESSION = ['language' => 'en_us', 'admin_id' => 0];
$_REQUEST = array_merge($_GET, $_POST);

// ── Shared infrastructure ─────────────────────────────────────────────────
// Load language file first so that phreedom/defaults.php constant references resolve
$lang_path = DIR_FS_MODULES . 'phreedom/language/en_us/language.php';
if (file_exists($lang_path)) require_once($lang_path);
require_once(DIR_FS_MODULES . 'phreedom/defaults.php');
require_once(DIR_FS_INCLUDES . 'common_functions.php');
require_once(DIR_FS_INCLUDES . 'common_classes.php');
$messageStack = new messageStack;

// ── Dispatch ──────────────────────────────────────────────────────────────
require_once(DIR_FS_MODULES . 'api/classes/api_request.php');
$api = new api_request();
$api->dispatch();
