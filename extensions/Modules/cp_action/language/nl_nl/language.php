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
//  Path: /modules/cp_action/language/nl_nl/language.php
//

// Headings 
define('BOX_CAPA_MAINTAIN','Corrigerende/preventieve actie');
define('MENU_HEADING_NEW_CAPA','Nieuwe corrigerende actie aanmaken');
// General Defines
define('TEXT_ACTION_EFFECTIVE','Was de genomen actie effectief?');
define('TEXT_ACTION_TAKEN','Genomen actie');
define('TEXT_AGREED_TO_BY','Akkoord gegeven door');
define('TEXT_AGREED_TO_DATE','Datum akkoord');
define('TEXT_ASSIGNED_BY_SYSTEM','(Toegekend door systeem)');
define('TEXT_ASSIGNED_DATE','Datum toegewezen');
define('TEXT_ASSIGEND_TO','Toegewezen aan');
define('TEXT_AUDIT','Audit');
define('TEXT_AUDIT_NOTES','Auditnotities');
define('TEXT_CA','CA');
define('TEXT_CAPAS','CAPA\'s');
define('TEXT_CAPA_ID','CAPA-nr.');
define('TEXT_CAPA_NOTES','Omschrijving van het probleem');
define('TEXT_CAPA_TYPE','Soort actie');
define('TEXT_PURCHASE_INVOICE_ID','Verkoop/factuur nr.');
define('TEXT_CREATION_DATE','Aanmaakdatum');
define('TEXT_CLOSED_BY','Gesloten door');
define('TEXT_CLOSED_DATE','Datum gesloten');
define('TEXT_CUSTOMER_TELEPHONE','Telefoon');
define('TEXT_CUSTOMER_EMAIL','Email');
define('TEXT_CUSTOMER_NOTES','Klantnotities');
define('TEXT_CUSTOMER_ID','Klant-ID');
define('TEXT_CUSTOMER_INFO','Klantgegevens');
define('TEXT_CUSTOMER_NAME','Klantnaam');
if (!defined('TEXT_DETAILS')) define('TEXT_DETAILS','Details'); // also defined in doc_ctl/language.php, phreedom/language.php, rma/language.php
define('TEXT_ENTERED_BY','Ingevoerd door');
define('TEXT_IMPLEMENTATION','Implementatie');
define('TEXT_INVESTIGATION','Onderzoek');
define('TEXT_INVESTIGATION_TITLE','Onderzoek');
define('TEXT_NEW_CAPA_NUMBER','Zo nee, nieuw CA/PA-nummer');
define('TEXT_PA','PA');
define('TEXT_REQUESTED_BY','Aangevraagd door');
//************ CAPA defines *************/
define('MODULE_CAPA_GEN_INFO','Beheertools van de CAPA-module. Kies hieronder een actie.');
define('MODULE_CAPA_INSTALL_INFO','Module Retourautorisatie installeren');
define('MODULE_CAPA_REMOVE_INFO','Module Retourautorisatie verwijderen');
define('MODULE_CAPA_REMOVE_CONFIRM','Weet u zeker dat u de CAPA-module wilt verwijderen?');
define('CAPAS_ERROR_DELETE_MSG','De databasebestanden zijn verwijderd. Om de module volledig te verwijderen, verwijdert u alle bestanden in de map /my_files/custom/cp_action en het configuratiebestand /my_files/custom/extra_menus/cp_action.php');
define('CAPA_MSG_DELETE_CAPA','Weet u zeker dat u deze corrigerende/preventieve actie wilt verwijderen?');
// audit log messages
define('CAPA_LOG_USER_ADD','CAPA aangemaakt - CAPA nr. ');
define('CAPA_LOG_USER_UPDATE','CAPA bijgewerkt - CAPA nr. ');
define('CAPA_MESSAGE_SUCCESS_ADD','CAPA succesvol aangemaakt, nr. ');
define('CAPA_MESSAGE_SUCCESS_UPDATE','CAPA succesvol bijgewerkt, nr. ');
define('CAPA_MESSAGE_ERROR','Er is een fout opgetreden bij het aanmaken/bijwerken van de CAPA.');
define('CAPA_MESSAGE_DELETE','De CAPA is succesvol verwijderd.');
define('CAPA_ERROR_CANNOT_DELETE','Er is een fout opgetreden bij het verwijderen van de CAPA.');
//  codes for status and CAPA reason
define('CAPA_STATUS_0','Kies status ...');
define('CAPA_STATUS_1','CA/PA aangemaakt');
define('CAPA_STATUS_2','In behandeling');
define('CAPA_STATUS_3','Aangemaakt en toegewezen');
define('CAPA_STATUS_4','Onderzocht');
define('CAPA_STATUS_5','Geïmplementeerd');
define('CAPA_STATUS_6','Geaudit');
define('CAPA_STATUS_90','Gesloten - niet succesvol');
define('CAPA_STATUS_99','Gesloten - succesvol');

?>