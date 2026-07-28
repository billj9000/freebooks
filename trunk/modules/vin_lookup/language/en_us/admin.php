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
//  Path: /modules/vin_lookup/language/en_us/admin.php
//

// Required by the phreedom Modules tab
define('MODULE_VIN_LOOKUP_TITLE',       'VIN Lookup');
define('MODULE_VIN_LOOKUP_DESCRIPTION', 'Looks up a vehicle\'s full details, including its VIN, from a UK registration number via Vehicle Data Global. Results are cached locally so repeat lookups do not incur additional provider cost.');

// Settings page
define('VIN_LBL_API_KEY',         'API key');
define('VIN_LBL_PACKAGES',        'Data package(s)');
define('VIN_LBL_ENDPOINT',        'API endpoint');
define('VIN_TEXT_TEST_CONNECTION', 'Test Connection');
define('VIN_BTN_TEST',            'Test');
define('VIN_TEXT_SETTINGS_SAVED', 'VIN Lookup settings saved.');
define('VIN_TEST_SUCCESS',        'Success (HTTP %d) — the provider returned a valid response for this registration.');
define('VIN_TEST_FAILURE',        'Failed (HTTP %d): %s');
