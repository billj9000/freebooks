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
//  Path: /oauth_return.php
//
// A redirect address with no query string, for OAuth providers that refuse one. A module starts
// its authorisation with a state of the form "<module>.<random>" and registers this file's
// address as its redirect URI. This file sends the browser on to that module's callback page with
// every parameter the provider returned. It checks nothing about the user or the state: the
// callback page signs in, compares the whole state with the one kept in the session, and uses
// it once.

$state  = (string)($_GET['state'] ?? '');
$module = strstr($state, '.', true);
if ($module === false || !preg_match('/^[a-z][a-z0-9_]*$/', $module)
    || !is_file(__DIR__ . '/modules/' . $module . '/pages/callback/pre_process.php')) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  exit('This address is used only on the way back from an authorisation, and this request did not come from one.');
}
$params = array_filter($_GET, 'is_string');
unset($params['module'], $params['page']);
header('Location: index.php?' . http_build_query(['module' => $module, 'page' => 'callback'] + $params));
exit;
