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
//  Path: /modules/work_orders/language/nl_nl/language.php
//

// Headings 
define('WO_POPUP_TASK_WINDOW_TITLE','Werkordertaken');
define('HEADING_WORK_ORDER_MODULE_NEW','Werkorder aanmaken');
define('HEADING_WORK_ORDER_MODULE_EDIT','Werkorder bewerken');
define('HEADING_WORK_ORDER_MODULE_BUILD','Werkorder bouwen en volgen');
// General Defines
define('TEXT_APPROVALS','Goedkeuringen');
define('TEXT_BUILD','Werkorder bouwen');
define('TEXT_CLOSE_DATE','Datum gesloten');
define('TEXT_COMPLETE','Voltooid');
define('TEXT_DATA_ENTRY','Gegevensinvoer');
define('TEXT_DAYS','Dagen');
define('TEXT_DOCUMENTS','Ref. documenten');
define('TEXT_DRAWINGS','Ref. tekeningen');
define('TEXT_ENTRY_VALUE','Invoerwaarde');
define('TEXT_ERP_ENTRY','ERP-invoer');
define('TEXT_HOURS','Uren');
define('TEXT_MFG','Prod.');
define('TEXT_MFG_INIT','Aftekening productie');
define('TEXT_MINUTES','Minuten');
if (!defined('TEXT_NA')) define('TEXT_NA','N.v.t.'); // also defined in phreedom/language.php
define('TEXT_PRIORITY','Prioriteit');
define('TEXT_PROCEDURE','Procedure');
define('TEXT_QA','QA');
define('TEXT_QA_INIT','Aftekening QA');
if (!defined('TEXT_REVISION')) define('TEXT_REVISION','Revisie'); // also defined in doc_ctl/language.php, inventory/language.php
define('TEXT_REVISION_DATE','Rev.datum');
define('TEXT_SPECIAL_NOTES','Notities en opmerkingen werkorder');
define('TEXT_STEP','Stap');
define('TEXT_TASKS','Taken');
define('TEXT_TASK_DESC','Taakomschrijving');
define('TEXT_TASK_NAME','Taaknaam');
define('TEXT_TASK_SEQUENCE','Taakvolgorde');
define('TEXT_TASK_STATUS','Taakstatus');
define('TEXT_TASK_TIME','Taaktijd');
define('TEXT_USE_ALLOCATION','Toewijzing gebruiken');
define('TEXT_WORK_ORDER','Werkorder');
define('TEXT_WORK_ORDERS','Werkorders');
define('TEXT_WORK_ORDERS_TASK','Werkordertaken');
define('TEXT_WO_ID','Werkorder-ID');
define('TEXT_WO_TITLE','Titel werkorder');
define('TEXT_WO_HISTORY','Geschiedenis (laatste %s resultaten)');
// Error and Information Messages
define('WO_MESSAGE_BUILDER_ERROR','Er is een fout opgetreden bij het toevoegen/bijwerken van de werkorder. Controleer de gegevens en probeer het opnieuw.');
define('WO_ERROR_CANNOT_DELETE_BUILDER','Deze werkorder is in het systeem gebruikt. Deze kan niet worden verwijderd.');
define('WO_BUILDER_ERROR_DUP_TITLE','De titel van de werkorder is al in gebruik. Probeer het opnieuw!');
define('WO_MSG_COPY_INTRO','Voer de titel voor de nieuwe werkorder in.');
define('WO_SKU_NOT_FOUND','Het ingevoerde artikelnummer is niet gevonden in de voorraadtabel van de database!');
define('WO_INSUFFICIENT_INVENTORY','Onvoldoende van de volgende onderdelen om het ingevoerde artikelnummer te bouwen. De tekorten zijn:');
define('WO_MESSAGE_SUCCESS_MAIN_UPDATE','De werkorder is succesvol bijgewerkt!');
define('WO_MESSAGE_SUCCESS_MAIN_ADD','De werkorder is succesvol toegevoegd!');
define('WO_NOTHING_TO_PRINT','Deze werkorder is nog niet opgeslagen, dus er is niets om af te drukken.');
define('WO_MESSAGE_SUCCESS_MAIN_DELETE','De werkorder is succesvol verwijderd.');
define('WO_MESSAGE_STEP_UPDATE_SUCCESS','Werkorderstap %s is succesvol bijgewerkt.');
define('WO_MESSAGE_SUCCESS_COMPLETE','Werkorder %s is succesvol voltooid.');
define('WO_MESSAGE_MAIN_ERROR','Er is een fout opgetreden bij het toevoegen/bijwerken van de werkorder. Controleer de gegevens en probeer het opnieuw.');
define('WO_MFG_PASSWORD_BAD','Het productiewachtwoord komt niet overeen met de gebruikersnaam.');
define('WO_QA_PASSWORD_BAD','Het wachtwoord voor kwaliteitsborging komt niet overeen met de gebruikersnaam.');
define('WO_DATA_VALUE_BLANK','Er is een gegevenswaarde vereist, maar deze is leeg gelaten.');
define('WO_DB_UPDATE_ERROR','Er is een fout opgetreden bij het bijwerken van de database.');
define('WO_MESSAGE_SUCCESS_UPDATE','De werkordertaak is succesvol bijgewerkt!');
define('WO_MESSAGE_SUCCESS_ADD','De werkordertaak is succesvol toegevoegd!');
define('WO_MESSAGE_ERROR','Er is een fout opgetreden bij het toevoegen/bijwerken van de werkordertaak. Controleer de gegevens en probeer het opnieuw.');
define('WO_SKU_ID_REQUIRED','Het artikelnummer en de titel van de werkorder zijn verplichte velden.');
define('WO_CANNOT_SAVE','Van deze werkorder bestaat een hogere revisie; wijzigingen in deze werkorder kunnen niet worden opgeslagen.');
define('WO_ROLL_REVISION','Deze werkorder is in het systeem gebruikt; elke wijziging verhoogt de revisie naar het volgende niveau.');
define('WO_ERROR_CANNOT_DELETE','De werkordertaak kan niet worden verwijderd omdat deze in een werkorder wordt gebruikt! Zie werkorder nr. ');
define('WO_DUPLICATE_TASK_ID','Het record is niet gewijzigd; er bestaat al een taak-ID met deze naam in het systeem!');
define('WO_TASK_ID_MISSING','Zowel het veld Taak-ID als het veld Omschrijving is verplicht!');
define('WO_TEXT_PARTS_SHORTAGE','(%s van %s) nodig van artikelnummer %s - %s');
// Audit log defines
define('WO_AUDIT_LOG_MAIN','WO hoofd (%s) - ');
define('WO_AUDIT_LOG_BUILDER','WO bouwer (%s) - ');
define('WO_AUDIT_LOG_TASK','WO taak ($s) - ');
define('WO_AUDIT_LOG_STEP_COMPLETE','WO stap %s - voltooid');
define('WO_AUDIT_LOG_WO_COMPLETE','WO %s - voltooid');
// Javascrpt defines
define('WORK_ORDER_MSG_DELETE_WO','Weet u zeker dat u deze invoer wilt verwijderen?');

?>