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
//  Path: /modules/vin_lookup/language/nl_nl/language.php
//

define('VIN_PAGE_TITLE', 'VIN opzoeken');

// Labels
define('VIN_LBL_REGISTRATION', 'Kenteken');
define('VIN_LBL_VIN',          'VIN');
define('VIN_LBL_MAKE',         'Merk');
define('VIN_LBL_MODEL',        'Model');
define('VIN_LBL_CACHED',       'In cache');
define('VIN_LBL_HITS',         'Treffers');
define('VIN_LBL_ACTIONS',      'Acties');

// Buttons
define('VIN_BTN_LOOKUP',  'Opzoeken');
define('VIN_BTN_COPY',    'Kopiëren');
define('VIN_BTN_REFRESH', 'Vernieuwen bij aanbieder');
define('VIN_BTN_VIEW',    'Bekijken');
define('VIN_BTN_DELETE',  'Verwijderen');

// Page text
define('VIN_TEXT_RECENT_LOOKUPS',  'Recente zoekopdrachten');
define('VIN_TEXT_NO_LOOKUPS',      'Er zijn nog geen kentekens opgezocht.');
define('VIN_TEXT_PROMPT',          'Voer een kenteken in en klik op Opzoeken.');
define('VIN_TEXT_FETCHED',         'Opgehaald bij de aanbieder.');
define('VIN_TEXT_CACHED',          'In cache %s &middot; %d treffer(s)');
define('VIN_TEXT_VIN_RESTRICTED',  'VIN niet beschikbaar voor dit voertuig.');
define('VIN_TEXT_VEHICLE_DETAILS', 'Voertuiggegevens');
define('VIN_TEXT_RAW_JSON',        'Onbewerkt antwoord van de aanbieder tonen');
define('VIN_TEXT_DELETED',         'Opgeslagen zoekresultaat verwijderd.');
define('VIN_CONFIRM_DELETE',       'Dit opgeslagen zoekresultaat verwijderen? Het wordt de volgende keer dat het wordt opgezocht opnieuw opgehaald bij de aanbieder.');

// Errors
define('VIN_ERROR_NO_KEY',       'VIN opzoeken is niet geconfigureerd — voer een API-sleutel in onder Bedrijf &gt; Configuratie &gt; VIN opzoeken.');
define('VIN_ERROR_NO_VRM',       'Voer een kenteken in.');
define('VIN_ERROR_NO_RESPONSE',  'Geen antwoord ontvangen van de aanbieder.');
define('VIN_ERROR_BAD_RESPONSE', 'De aanbieder gaf een onleesbaar antwoord.');
define('VIN_ERROR_HTTP_STATUS',  'De aanbieder gaf HTTP-status %d.');
define('VIN_ERROR_NOT_FOUND',    'Er is geen voertuig gevonden voor dit kenteken.');
