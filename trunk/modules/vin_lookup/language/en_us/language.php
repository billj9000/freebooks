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
//  Path: /modules/vin_lookup/language/en_us/language.php
//

define('VIN_PAGE_TITLE', 'VIN Lookup');

// Labels
define('VIN_LBL_REGISTRATION', 'Registration Number');
define('VIN_LBL_VIN',          'VIN');
define('VIN_LBL_MAKE',         'Make');
define('VIN_LBL_MODEL',        'Model');
define('VIN_LBL_CACHED',       'Cached');
define('VIN_LBL_HITS',         'Hits');
define('VIN_LBL_ACTIONS',      'Actions');

// Buttons
define('VIN_BTN_LOOKUP',  'Look Up');
define('VIN_BTN_COPY',    'Copy');
define('VIN_BTN_REFRESH', 'Refresh from Provider');
define('VIN_BTN_VIEW',    'View');
define('VIN_BTN_DELETE',  'Delete');

// Page text
define('VIN_TEXT_RECENT_LOOKUPS',  'Recent Lookups');
define('VIN_TEXT_NO_LOOKUPS',      'No registrations have been looked up yet.');
define('VIN_TEXT_PROMPT',          'Enter a registration number and click Look Up.');
define('VIN_TEXT_FETCHED',         'Fetched from provider.');
define('VIN_TEXT_CACHED',          'Cached %s &middot; %d hit(s)');
define('VIN_TEXT_VIN_RESTRICTED',  'VIN not available for this vehicle.');
define('VIN_TEXT_VEHICLE_DETAILS', 'Vehicle Details');
define('VIN_TEXT_RAW_JSON',        'Show raw provider response');
define('VIN_TEXT_DELETED',         'Cached lookup deleted.');
define('VIN_CONFIRM_DELETE',       'Delete this cached lookup? It will be re-fetched from the provider next time it is looked up.');

// Errors
define('VIN_ERROR_NO_KEY',       'VIN Lookup is not configured — enter an API key under Company &gt; Configuration &gt; VIN Lookup.');
define('VIN_ERROR_NO_VRM',       'Enter a registration number.');
define('VIN_ERROR_NO_RESPONSE',  'No response received from the provider.');
define('VIN_ERROR_BAD_RESPONSE', 'The provider returned an unreadable response.');
define('VIN_ERROR_HTTP_STATUS',  'The provider returned HTTP status %d.');
define('VIN_ERROR_NOT_FOUND',    'No vehicle was found for this registration.');
