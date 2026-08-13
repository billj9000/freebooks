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
//  Path: /modules/forms/classes/deliver/load.php
//
/**
 * Forms — loads the delivery layer.
 *
 * FreeBooks has no PSR-4 autoloader for application code, so the layer is
 * loaded explicitly:
 *
 *     require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
 *     require_once(DIR_FS_MODULES . 'forms/classes/deliver/load.php');
 *
 * The document seam must be loaded first — the deliverers take a RenderedOutput.
 * The render layer is NOT required: delivery works on bytes and never asks how
 * they were drawn, which is the whole point of it being a separate layer.
 */

require_once(__DIR__ . '/delivery.php');
