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
//  Path: /modules/shipping/methods/yrc/language/nl_nl/language.php
//

define('MODULE_SHIPPING_YRC_TEXT_TITLE', 'YRC Freight');
define('MODULE_SHIPPING_YRC_TITLE_SHORT', 'YRC');
define('MODULE_SHIPPING_YRC_TEXT_DESCRIPTION', 'YRC Freight Lines');
define('MODULE_SHIPPING_YRC_TEXT_INTRODUCTION', '<h3>YRC Freight (alleen offertes)</h3>
Deze methode geeft offertes met korting op basis van uw YRC-contractaccount. De inloggegevens (gebruikersnaam, wachtwoord en BusId) moeten worden aangevraagd via uw lokale YRC-vertegenwoordiger.');
// key descriptions
define('MODULE_SHIPPING_YRC_TITLE_DESC','Titel voor weergave bij verzending met YRC.');
define('MODULE_SHIPPING_YRC_USER_ID_DESC','Voer de YRC-gebruikersnaam in die u van YRC hebt ontvangen.');
define('MODULE_SHIPPING_YRC_PASSWORD_DESC','Voer het YRC-wachtwoord in dat u van YRC hebt ontvangen.');
define('MODULE_SHIPPING_YRC_BUSID_DESC','Voer het YRC-BusID in dat u van YRC hebt ontvangen.');
define('MODULE_SHIPPING_YRC_SORT_ORDER_DESC','Volgorde van tonen. De laagste wordt als eerste getoond.');
// Delivery Methods
define('MODULE_SHIPPING_YRC_GDF','Standard LTL Ground Freight');
define('MODULE_SHIPPING_YRC_2DF','Guaranteed Standard by Noon');
define('MODULE_SHIPPING_YRC_3DF','Guaranteed Standard by 5PM');
// General defines
define('SHIPPING_YRC_SHIPMENTS_ON','YRC-zendingen op ');
define('SHIPPING_YRC_CURL_ERROR','Er is een fout opgetreden bij de communicatie met de YRC-server:');
define('SHIPPING_YRC_RATE_ERROR','Fout in YRC-tariefantwoord: ');
define('SHIPPING_YRC_RATE_CITY_MATCH','Plaats komt niet overeen met postcode.');

?>