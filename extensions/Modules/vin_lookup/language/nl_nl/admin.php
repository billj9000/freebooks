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
//  Path: /modules/vin_lookup/language/nl_nl/admin.php
//

// Required by the phreedom Modules tab
define('MODULE_VIN_LOOKUP_TITLE',       'VIN opzoeken');
define('MODULE_VIN_LOOKUP_DESCRIPTION', 'Zoekt de volledige gegevens van een voertuig op, inclusief het VIN, aan de hand van een Brits kenteken via Vehicle Data Global. Resultaten worden lokaal in de cache bewaard, zodat herhaalde zoekopdrachten geen extra kosten bij de aanbieder veroorzaken.');

// Settings page
define('VIN_LBL_API_KEY',         'API-sleutel');
define('VIN_LBL_PACKAGES',        'Datapakket(ten)');
define('VIN_LBL_ENDPOINT',        'API-endpoint');
define('VIN_TEXT_TEST_CONNECTION', 'Verbinding testen');
define('VIN_BTN_TEST',            'Testen');
define('VIN_TEXT_SETTINGS_SAVED', 'Instellingen voor VIN opzoeken opgeslagen.');
define('VIN_TEST_SUCCESS',        'Gelukt (HTTP %d) — de aanbieder gaf een geldig antwoord voor dit kenteken.');
define('VIN_TEST_FAILURE',        'Mislukt (HTTP %d): %s');
