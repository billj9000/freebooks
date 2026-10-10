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
//  Path: /modules/rma/language/nl_nl/language.php
//

// Headings 
define('BOX_RMA_MAINTAIN','Retourautorisaties');
define('MENU_HEADING_NEW_RMA','Nieuwe RMA aanmaken');

// General Defines
define('TEXT_RMAS','RMA\'s');
define('TEXT_RMA_ID','RMA-nr.');
define('TEXT_ASSIGNED_BY_SYSTEM','(Toegekend door systeem)');
define('TEXT_CREATION_DATE','Aangemaakt');
define('TEXT_PURCHASE_INVOICE_ID','Verkoop/factuur nr.');
define('TEXT_INVOICE_DATE','Factuurdatum');
define('TEXT_ORIG_PO_SO','Oorspronkelijk VO/IO nr.');
define('TEXT_CALLER_NAME','Naam beller');
define('TEXT_CUSTOMER_ID','Klant-ID');
define('TEXT_TELEPHONE','Telefoon');
define('TEXT_DATE_MANUFACTURE','Productiedatum fabrikant');
define('TEXT_DATE_WARRANTY','Einddatum garantie');
if (!defined('TEXT_DETAILS')) define('TEXT_DETAILS','Details'); // also defined in cp_action/language.php, doc_ctl/language.php, phreedom/language.php
define('TEXT_DISPOSITION','Afhandeling');
define('TEXT_REASON_FOR_RETURN','Reden van retour');
define('TEXT_ENTERED_BY','Ingevoerd door');
define('TEXT_RECEIVE_DATE','Datum ontvangen');
define('TEXT_RECEIVED_BY','Ontvangen door');
define('TEXT_RECEIVE_CARRIER','Vervoerder zending');
define('TEXT_RECEIVE_TRACKING_NUM','Trackingnummer zending');
define('TEXT_RECEIVING','Ontvangst');
// Messages
define('RMA_DISPOSITION_DESC','<b>Zet de status op gesloten en sluit af wanneer voltooid!</b>');
define('RMA_MESSAGE_ERROR','Er is een fout opgetreden bij het aanmaken/bijwerken van de RMA.');
define('RMA_MESSAGE_SUCCESS_ADD','RMA succesvol aangemaakt, nr. ');
define('RMA_MESSAGE_SUCCESS_UPDATE','RMA succesvol bijgewerkt, nr. ');
define('RMA_MESSAGE_DELETE','De RMA is succesvol verwijderd.');
define('RMA_ERROR_CANNOT_DELETE','Er is een fout opgetreden bij het verwijderen van de RMA.');
// Javascrpt defines
define('RMA_MSG_DELETE_RMA','Weet u zeker dat u deze RMA wilt verwijderen?');
define('RMA_ROW_DELETE_ALERT','Weet u zeker dat u deze artikelregel wilt verwijderen?');
// audit log messages
define('RMA_LOG_USER_ADD','RMA aangemaakt - RMA nr. ');
define('RMA_LOG_USER_UPDATE','RMA bijgewerkt - RMA nr. ');
//  codes for status and RMA reason
define('RMA_STATUS_0','Kies status ...');
define('RMA_STATUS_1','RMA aangemaakt/wacht op onderdelen');
define('RMA_STATUS_2','Onderdelen ontvangen');
define('RMA_STATUS_3','Ingangscontrole');
define('RMA_STATUS_4','In afhandeling');
define('RMA_STATUS_5','In test/beoordeling');
define('RMA_STATUS_6','Wacht op creditering');
define('RMA_STATUS_7','Gesloten - vervangen');
define('RMA_STATUS_8','Gesloten - geen garantie');
define('RMA_STATUS_9','Schadeclaim');
define('RMA_STATUS_10','Gesloten - geretourneerd');
define('RMA_STATUS_90','Gesloten - niet ontvangen');
define('RMA_STATUS_99','Gesloten');

define('RMA_REASON_0','Kies reden voor RMA ...');
define('RMA_REASON_1','Niet nodig');
define('RMA_REASON_2','Verkeerd onderdeel besteld');
define('RMA_REASON_3','Paste niet');
define('RMA_REASON_4','Defect/omruilen');
define('RMA_REASON_5','Beschadigd tijdens verzending');
define('RMA_REASON_6','Geweigerd door klant');
define('RMA_REASON_7','Dubbele zending');
define('RMA_REASON_80','Verkeerde connector');
define('RMA_REASON_99','Overig (specificeer in notities)');

define('RMA_ACTION_0','Kies actie ...');
define('RMA_ACTION_1','Terug op voorraad');
define('RMA_ACTION_2','Terug naar klant');
define('RMA_ACTION_3','Testen en vervangen');
define('RMA_ACTION_4','Vervangen onder garantie');
define('RMA_ACTION_5','Afvoeren');
define('RMA_ACTION_6','Testen en crediteren');
define('RMA_ACTION_99','Overig (specificeer in notities)');

?>