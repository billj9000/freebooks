<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2014 PhreeSoft                       |
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
// Path: /modules/phreepos/language/nl_nl/language.php

define('BOX_PHREEPOS_RETURN','Kassa - Teruggave');
define('PHREEPOS_PAYMENT_TITLE','Betaal Wijze');
define('BNK_19_AMOUNT_PAID','Bedrag Ontv');
define('TEXT_ADD_UPDATE','Toevoegen/Updaten');
define('TEXT_SUBTOTAL','Subtotaal');
define('TEXT_AMOUNT_PAID','Bedrag betaald');
define('TEXT_BALANCE_DUE','Huidig Openstaand Saldo');
define('TEXT_RETURN','Verwerk Teruggave');
define('TEXT_DISCOUNT_PERCENT','Kortings Perc.');
define('TEXT_DISCOUNT_AMOUNT','Kortings Bedr.');
define('TEXT_REFUND','Restitutie');
define('TEXT_REFUND_METHOD','Restitutie Methode');
define('TEXT_ENTRIES','Vermeldingen');
define('PHREEPOS_ITEM_NOTES','Cursor moet in het artikelnummer vak staan, wil de barcode scanner werken.<br><b>ESC</b> = Schoon Scherm.<br><b>Alt+R</b> = Schakelen tussen ruilen en gewone verkopen.<br><b>F7</b> = Toon voorraad pop-up.<br><b>F8</b> = Toon klant pop-up.<br><b>F9</b> = Toon betaalwijze pop-up.');
define('PHREEPOS_PAYMENT_NOTES','<b>ESC</b> = Pop-up sluiten.<br><b>F11</b>  = Bewaren.<br><b>F12</b>  = Printen<br><b>pijl omhoog & omlaag<b> = ander betaaltype');
define('POS_MSG_DELETE_CONFIRM','Weet u zeker dat u deze boeking wil verwijderen?');
define('GENERAL_JOURNAL_19_ERROR_6','Een kassa verkoop kan niet verwijderd worden als deze gesloten is!');
define('TEXT_TILL','Kassa');
define('TEXT_OPEN_DRAWER','open kassala');
define('TEXT_PRINT_PREVIOUS','Print Vorige Bon');
define('TEXT_SELECT_CUSTOMER','Selecteer Klant');
define('TEXT_AMOUNT_ORIGINAL_CURRENCY','Oorspr Bedrag');
if (!defined('TEXT_SALES')) define('TEXT_SALES','Verkopen');
define('PHREEPOS_HANDELING_CASH_DIFFERENCE','Geboekt Kasverschil');
define('POS_HEADING_CLOSING','Dag Afsluiting');

define('NEW_BALANCE','Nieuw Kassa Saldo');
define('PAYMENTS_SHOULD_BE','te Ontvangen Betalingen:');
define('PAYMENTS_RECEIVED','Ontvangen Betalingen:');
define('TILL_BALANCE','Start Saldo Kassa:');
define('TILL_END_BALANCE','Kasverschil');
define('TEXT_SHOW_COUNT_HELP','Toon tel hulp');
define('EXCEED_MAX_DISCOUNT','U heeft het maximum kortingspercentage van %s overschreden');
define('POS_ERROR_CONTACT_REQUIRED','Deze verkoop heeft een klant nodig voordat deze kan worden opgeslagen.');
define('POS_ERROR_POSTING','De verkoop kon niet in het grootboek worden geboekt. Er is niets opgeslagen.');
define('POS_ERROR_OTHER_TRANS_INCOMPLETE','Kies een transactiesoort en een kassa, en vul een bedrag groter dan nul in. Er is niets opgeslagen.');
define('EXCEED_MAX_DISCOUNT_SKU','U heeft het maximum kortingspercentage van %s overschreden voor artiekel %s');
define('OTHER_OPTIONS','Overige Opties');
define('POS_PRINT_OTHER','Print Andere Bon');

define('TEXT_CASH_IN','Geld In'); 
define('TEXT_CASH_OUT','Geld Uit');
define('TEXT_EXPENSES','Uitgave');
define('TEXT_TYPE_OF_TRANSACTION','Type transactie');
define('ERROR_NO_PAYMENT_METHODES','Kan kassa niet openen omdat er geen betaal methodes zijn. ga naar Bedrijf > module administratie > betaal module en stel methodes in om zichtbaar te worden in de kassa en voeg groot boekrekeningen toe.');
// Used on the POS screen and its sales, which every POS user opens (issue #111).
define('TEXT_ROUNDING_OF','Afronden');
define('TEXT_TAX','standaard btw');
define('TEXT_OTHER_TRANS','Overige Transacties');
?>
