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
//  Path: /modules/vin_lookup/defaults.php
//

// Fallback values used only if the corresponding pb_configuration row is missing
if (!defined('VIN_LOOKUP_ENDPOINT'))  define('VIN_LOOKUP_ENDPOINT',  'https://uk.api.vehicledataglobal.com/r2/lookup');
if (!defined('VIN_LOOKUP_PACKAGES')) define('VIN_LOOKUP_PACKAGES', 'VehicleDetails');
