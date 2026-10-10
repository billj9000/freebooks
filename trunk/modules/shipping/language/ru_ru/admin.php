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
// Module/Method: shipping
// ISO Language: ru_ru
// Version: 3.7
// +-----------------------------------------------------------------+
// Path: //modules/shipping/language/ru_ru/admin.php

define('MODULE_SHIPPING_TITLE','Модуль доставки');
define('MODULE_SHIPPING_DESCRIPTION','The shipping module is a wrapper for user configurable shipping methods. Some methods are included with the core package and others are available for download from the PhreeSoft website.');
define('CD_10_01_DESC','Sets the default unit of measure for all packages. Valid values are:<br />LBS - Pounds<br />KGS - Kilograms');
define('CD_10_02_DESC','Valid values are<br />USD - US Dollars<br />EUR - Euros');
define('CD_10_03_DESC','Package unit of measure. Valid values are:<br />IN - Inches<br />CM - Centimeters');
define('CD_10_04_DESC','0 - Sets the default residential ship box to unchecked (commercial address)<br />1 - Sets the default residential ship box to checked (residential address)');
define('CD_10_05_DESC','Specify the default package type to use for shipping.');
define('CD_10_06_DESC','Specify the default type of pickup service for your package service.');
define('CD_10_07_DESC','Enter the default package length to use for a standard shipment.');
define('CD_10_08_DESC','Делитель объёмного веса (напр. 5000 для см/кг, 139 для дюйм/фунт).');
define('CD_10_10_DESC','Show the additional handling charge checkbox');
define('CD_10_14_DESC','Shows the insurance selection option.');
define('CD_10_20_DESC','Show the checkbox to allow heavy shipments to be broken down for small package service.');
define('CD_10_26_DESC','Show the delivery confirmation checkbox');
define('CD_10_32_DESC','Show the handling charge checkbox.');
define('CD_10_38_DESC','Enable the COD checkbox and options');
define('CD_10_44_DESC','Show the checkbox for Saturday pickup');
define('CD_10_48_DESC','Show Saturday delivery checkbox');
define('CD_10_52_DESC','Show Hazardous Material checkbox');
define('CD_10_56_DESC','Show dry ice checkbox');
define('CD_10_60_DESC','Show return services checkbox');
define('CD_10_64_DESC', 'EORI-номер отправителя для международных таможенных деклараций (напр. GB123456789000)');
define('CD_10_65_DESC', 'VAT-номер отправителя для международных отправлений (используется, если VAT не указан у контакта филиала)');
define('CD_10_66_DESC', 'IOSS-номер отправителя для импорта в ЕС до €150 (формат: IM + 10 цифр)');
define('SHIPPING_TEXT_INTL_COMPLIANCE', 'Международные требования');
define('NEXT_SHIPMENT_NUM_DESC', 'Следующий номер отправления');
define('TEXT_SHIPPING_PREFS', 'Настройки адресной книги доставки');
define('CONTACT_SHIP_FIELD_REQ', 'Требовать ли ввод поля: %s для нового адреса доставки');
define('PB_PF_SHIP_METHOD', 'Способ доставки');
define('SHIPPING_METHOD','Select Method:');
define('SHIPPING_MONTH','Select Month:');
define('SHIPPING_YEAR','Select Year:');
define('SHIPPING_TOOLS_TITLE','Shipping Label File Maintenance');
define('SHIPPING_TOOLS_CLEAN_LOG_DESC','This operation creates a downloaded backup of your shipping label files. This will help keep the server storage size down and reduce company backup file sizes. Backing up these files is recommended before cleaning out old files to preserve FreeBooks transaction history. <br />INFORMATION: Cleaning out the shipping labels will leave the current records in the database shipping manager and logs.');

?>
