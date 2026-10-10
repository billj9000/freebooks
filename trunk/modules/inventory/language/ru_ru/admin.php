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
// Module/Method: inventory
// ISO Language: ru_ru
// Version: 3.7
// +-----------------------------------------------------------------+
// Path: //modules/inventory/language/ru_ru/admin.php

define('MODULE_INVENTORY_TITLE','Модуль Склад');
define('MODULE_INVENTORY_DESCRIPTION','Складской модуль содержит все функциональные возможности для хранения и управления товарами и услугами, используемых в вашей компании. Это включает в себя изделия для внутреннего и внешнего использования, а также продуктов, которые вы продаете. <b> ПРИМЕЧАНИЕ: Это основной модуль и не может быть удалены </ B>!');
define('BOX_INV_ADMIN','Управление модулем');
define('INV_TABS_HEADING_TITLE','Пользовательские Табы');
define('INV_FIELDS_HEADING_TITLE','Пользовательские поля');
define('INV_HEADING_FIELD_PROPERTIES','Тип поля и свойства (выберите один)');
if (!defined('TEXT_DEFAULT_GL_ACCOUNTS')) define('TEXT_DEFAULT_GL_ACCOUNTS','Счет главной книги по умолчанию'); // also defined in phreebooks/admin.php, phreedom/admin.php
define('TEXT_INVENTORY_TYPES','Тип единицы');
define('TEXT_SALES_ACCOUNT','Счет главной книги(продажи)');
define('TEXT_INVENTORY_ACCOUNT','Счет главной книги(склад)');
define('TEXT_COGS_ACCOUNT','Cost of Sales Account');
define('TEXT_COST_METHOD','Стоимость');
define('TEXT_STOCK_ITEMS','Склад');
define('TEXT_MS_ITEMS','Главный склад');
define('TEXT_ASSY_ITEMS','Сборка');
define('TEXT_SERIAL_ITEMS','Сериализация');
define('TEXT_NS_ITEMS','Нескладская ед.');
define('TEXT_SRV_ITEMS','Сервис');
define('TEXT_LABOR_ITEMS','Оплата труда');
define('TEXT_ACT_ITEMS','Активность');
define('TEXT_CHARGE_ITEMS','Расходы');
define('TEXT_DESC_ITEMS', 'Описание');
define('MODULE_INVENTORY_NOTES_1','PRIORITY MEDIUM: Set default general ledger accounts for inventory types, after loading GL accounts (Company -> Inventory Module Properties -> Inventory tab)');
define('CD_05_50_DESC','Determines the default sales tax rate to use when adding inventory items.<br /><br />NOTE: This value is applied to inventory Auto-Add but can be changed in the Inventory => Maintain screen. The tax rates are selected from the table tax_rates and must be setup through Setup => Sales tax Rates.');
define('CD_05_52_DESC','Determines the default purchase tax rate to use when adding inventory items.<br /><br />NOTE: This value is applied to inventory Auto-Add but can be changed in the Inventory => Maintain screen. The tax rates are selected from the table tax_rates and must be setup through Setup => Purchase tax Rates.');
define('CD_05_55_DESC','Позволяет автоматическое создание товарно-материальных ценностей в окне заказов. <br /> <br /> Наименований не требуется. Эта функция позволяет автоматическое создание наименований в таблице базы данных исклада .');
define('CD_05_60_DESC','Allows an ajax call to fill in possible choices as data is entered into the SKU field. his feature is helpful when the SKUs are known and expedites filling in order forms. May slow down SKU entries when bar code scanners are used.');
define('CD_05_65_DESC','When enabled, FreeBooks looks for a SKU length in the order form equal to the Bar Code Length value and when the length is reached, attempts to match with an inventory item. This allow fast entry of items when using bar code readers.');
define('CD_05_70_DESC','Sets the number of characters to expect when reading inventory bar code values. FreeBooks only searches when the number of characters has been reached. Typical values are 12 and 13 characters.');
define('CD_05_75_DESC','When enabled, FreeBooks will update the item cost in the inventory table with either the PO price or Purchase/Receive price. Usefule for on the fly PO/Purchases and updating prices from the order screen without having to update the inventory tables first.');
define('INV_INFO_CATEGORY_NAME','Inventory Category name<br />Name should be short (10) with no special characters or spaces.');
define('INV_INFO_INSERT_INTRO','Введите новую категорию и ее свойства');
define('INV_INFO_DELETE_INTRO','точно хотите удалить категорию?\\nКатегории не могут быть удалены, если есть запись поля в категории.');
define('INV_INFO_DELETE_ERROR','Категория существует, выбирите другое имя.');
define('INV_TABS_LOG','Табы товара - ');
// INV_IMAGE_* live in this module's language.php - the item page raises them and
// never loads admin.php. Defining them here too collides when both files load.
define('INV_TOOLS_VALIDATE_SO_PO','Инвентаризация: проверка количества товаров в заказах');
define('INV_TOOLS_VALIDATE_SO_PO_DESC','This operation tests to make sure your inventory quantity on Purchase Order and quantity of Sales Order match with the journal entries. The calculated values from the journal entries override the value in the inventory table.');
define('INV_TOOLS_REPAIR_SO_PO','Test and Repair Inventory Quantity on Order Values');
define('INV_TOOLS_BTN_SO_PO_FIX','Начать проверку и восстановление');
define('INV_TOOLS_PO_ERROR','SKU: %s had a quantity on Purchase Order of %s and should be %s. The inventory table balance was fixed.');
define('INV_TOOLS_SO_ERROR','SKU: %s had a quantity on Sales Order of %s and should be %s. The inventory table balance was fixed.');
define('INV_TOOLS_SO_PO_RESULT','Finished processing Inventory order quantities. The total number of items processed was %s. The number of records with errors was %s.');
define('INV_TOOLS_AUTDIT_LOG_SO_PO','Inv Tools - Repair SO/PO Qty (%s)');
define('INV_TOOLS_VALIDATE_INVENTORY','Validate Inventory Displayed Stock');
define('INV_TOOLS_VALIDATE_INV_DESC','This operation tests to make sure your inventory quantities listed in the inventory database and displayed in the inventory screens are the same as the quantities in the inventory history database as calculated by FreeBooks when inventory movements occur. The only items tested are the ones that are tracked in the cost of goods sold calculation. Repairing inventory balances will correct the quantity in stock and leave the inventory history data alone. ');
define('INV_TOOLS_REPAIR_TEST','Test Inventory Balances with COGS History');
define('INV_TOOLS_REPAIR_FIX','Repair Inventory Balances with COGS History');
define('INV_TOOLS_REPAIR_CONFIRM','Are you sure you want to repair the inventory stock on hand to match the FreeBooks COGS history calculated values?');
define('INV_TOOLS_BTN_TEST','Проверить баланс Склада');
define('INV_TOOLS_BTN_REPAIR','Синхронизировать Кол. на складе');
define('INV_TOOLS_OUT_OF_BALANCE','SKU: %s -> stock indicates %s on hand but COGS history list %s available');
define('INV_TOOLS_IN_BALANCE','Баланс склада в норме.');
define('INV_TOOLS_STOCK_ROUNDING_ERROR','SKU: %s -> Stock indicates %s on hand but is less than your precision. Please repair your inventory balances, the stock on hand will be rounded to %s.');
define('INV_TOOLS_BALANCE_CORRECTED','АРТ: %s -> Количество на руках изменено на %s.');
define('NEXT_SKU_NUM_DESC', 'Следующий артикул');

?>
