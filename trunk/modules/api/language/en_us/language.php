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
//  Path: /modules/api/language/en_us/language.php
//

// Settings page
define('API_PAGE_TITLE',           'REST API Keys');
define('API_LBL_LABEL',            'Label');
define('API_LBL_PREFIX',           'Key Prefix');
define('API_LBL_COMPANY',          'Company');
define('API_LBL_SCOPES',           'Scopes');
define('API_LBL_ENABLED',          'Enabled');
define('API_LBL_CREATED',          'Created');
define('API_LBL_LAST_USED',        'Last Used');
define('API_LBL_ACTIONS',          'Actions');
define('API_LBL_NEVER',            'Never');
define('API_BTN_GENERATE',         'Generate New Key');
define('API_BTN_REVOKE',           'Revoke');
define('API_BTN_ENABLE',           'Enable');
define('API_BTN_DELETE',           'Delete');
define('API_CONFIRM_DELETE',       'Delete this key? Any client using it will immediately lose access.');
define('API_KEY_GENERATED_TITLE',  'New API Key — copy now');
define('API_KEY_GENERATED_NOTE',   'This key will not be shown again. Copy it to a secure location before closing this dialog.');
define('API_KEY_LABEL_PROMPT',     'Label (optional, e.g. "Phone System")');
define('API_BTN_COPY',             'Copy');
define('API_BTN_CLOSE',            'Close');
define('API_NO_KEYS',              'No API keys have been generated yet.');
define('API_CENTRAL_DB_MISSING',   'The API central database is not available. Please install the REST API module first, or check database permissions.');
define('API_LBL_GENERATE_HEADING', 'New Key Label');
define('API_SCOPES_DEFAULT',       'contacts:read,invoices:read');
