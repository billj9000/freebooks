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
//  Path: /modules/zencart/notices.php
//
// This module's notices for the home page (issue #132): the order import has been failing
// for longer than the alert delay, or an order needs attention. Shown to users with access
// to the ZenCart module, from the state the import records (see zencart_alert_record()).
if (!defined('ZENCART_ENABLED') || ZENCART_ENABLED !== '1') return [];
if (empty($_SESSION['admin_security'][SECURITY_ID_ZENCART_INTERFACE])) return [];
gen_pull_language('zencart');
require_once(DIR_FS_MODULES . 'zencart/functions/zencart.php');

$notices = [];
$since   = zencart_alert_value('ZENCART_ALERT_SINCE');
$delay   = max(0, (int)(defined('ZENCART_ALERT_DELAY') ? ZENCART_ALERT_DELAY : 60)) * 60;
if ($since !== '' && time() - (int)$since >= $delay) {
    $notices[] = ['type' => 'error', 'text' => sprintf(ZENCART_NOTICE_FAILING, date('Y-m-d H:i', (int)$since),
        nl2br(htmlspecialchars(zencart_alert_value('ZENCART_ALERT_PROBLEM'), ENT_QUOTES, 'UTF-8')))];
}
foreach (zencart_alert_orders() as $o) {
    $notices[] = ['type' => 'caution', 'text' => sprintf(ZENCART_NOTICE_ORDER, (int)$o['order'],
        htmlspecialchars((string)$o['reason'], ENT_QUOTES, 'UTF-8'))];
}
return $notices;
