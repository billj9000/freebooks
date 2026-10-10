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
// +-----------------------------------------------------------------+
// Phreedom Language Translation File
// Generated: 2014-11-23 10:04:29
// Module/Method: payment-moneyorder
// ISO Language: ru_ru
// Version: 1
// +-----------------------------------------------------------------+
// Path: //modules/payment/methods/moneyorder/language/ru_ru/language.php

define('MODULE_PAYMENT_MONEYORDER_TEXT_TITLE','Чек/Денежный перевод');
define('MODULE_PAYMENT_MONEYORDER_TEXT_DESCRIPTION','Пожалуйста, отправте перевод или чек:<br />\' . MODULE_PAYMENT_MONEYORDER_PAYTO . \'<br /><br />Получатель:<br />\' . nl2br(COMPANY_NAME) . \'<br /><br />\' . \'Ваш заказ будет отправлен после получения оплаты.');
define('MODULE_PAYMENT_MONEYORDER_TEXT_INTRODUCTION', 'Выпишите чек или денежный перевод на имя:<br />' . (defined('MODULE_PAYMENT_MONEYORDER_PAYTO') ? MODULE_PAYMENT_MONEYORDER_PAYTO : '') . '<br />Отправьте платёж по адресу:<br />' . (defined('COMPANY_NAME') ? nl2br(COMPANY_NAME) : ''));
define('MODULE_PAYMENT_MONEYORDER_TEXT_REF_NUM','Референс №');
define('MODULE_PAYMENT_MONEYORDER_PAYTO_DESC','Платеж для:');
define('MODULE_PAYMENT_MONEYORDER_SORT_ORDER_DESC','Порядок отображения. Меньшее внизу.');

?>
