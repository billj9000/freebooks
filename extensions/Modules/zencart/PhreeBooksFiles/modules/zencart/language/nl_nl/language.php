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
//  Path: /modules/zencart/language/nl_nl/language.php
//

// Headings 
define('BOX_ZENCART_ADMIN','Zencart-configuratie');
// General Defines
define('ZENCART_CONFIRM_MESSAGE','Uw order is verzonden op {date} via {carrier} {service}, trackingnummer: {tracking}');
define('ZENCART_BULK_UPLOAD_SUCCESS','%s artikel(en) succesvol geüpload naar de ZenCart-webwinkel.');
define('ZENCART_TEXT_ERROR','Fout nr. ');
define('ZENCART_IVENTORY_UPLOAD','ZenCart-upload');
define('ZENCART_SAVE_UPLOAD','Opslaan en uploaden naar ZenCart');
define('ZENCART_SAVE_UPLOAD_SUCCESS','Artikel opgeslagen en succesvol geüpload naar de ZenCart-webwinkel.');
define('ZENCART_BULK_UPLOAD_TITLE','Bulkupload');
define('ZENCART_BULK_UPLOAD_INFO','Alle producten die zijn geselecteerd voor weergave op de ZenCart-webwinkel in bulk uploaden. Afbeeldingen worden alleen meegenomen als het selectievakje is aangevinkt.');
define('ZENCART_BULK_UPLOAD_TEXT','Producten in bulk uploaden naar webwinkel');
define('ZENCART_INCLUDE_IMAGES','Afbeeldingen meenemen');
define('ZENCART_BULK_UPLOAD_BTN','Bulkupload');
define('ZENCART_PRODUCT_SYNC_TITLE','Producten synchroniseren');
define('ZENCART_PRODUCT_SYNC_INFO','Huidige voorraadaantallen en verkoopprijzen vanuit FreeBooks naar ZenCart sturen voor alle producten die voor de catalogus zijn gemarkeerd. Gebruik dit om handmatig een synchronisatie van aantallen af te dwingen buiten de geplande cron-run.');
define('ZENCART_SYNC_QTY_SUCCESS','Aantal/prijs van %s product(en) succesvol gesynchroniseerd naar ZenCart.');
define('ZENCART_SYNC_QTY_CHANGED','Aantal/prijs van %s gewijzigde product(en) van %s naar ZenCart verzonden.');
define('ZENCART_SYNC_QTY_PARTIAL','%s product(en) gesynchroniseerd; %s artikelnummer(s) niet gevonden in ZenCart:');
define('ZENCART_PRODUCT_SYNC_TEXT','Producten synchroniseren met webwinkel');
define('ZENCART_DELETE_ZENCART','Ook producten in ZenCart verwijderen die daar niet gemarkeerd zijn.');
define('ZENCART_PRODUCT_SYNC_BTN','Synchroniseren');
define('ZENCART_SHIP_CONFIRM_TITLE','Verzendingen bevestigen');
define('ZENCART_SHIP_CONFIRM_INFO','Bevestigt alle verzendingen op de geselecteerde datum vanuit Verzendbeheer en zet de status in ZenCart. Voltooide en gedeeltelijk verzonden orders worden bijgewerkt. Er worden geen e-mailmeldingen naar de klant gestuurd.');
define('ZENCART_SHIP_CONFIRM_TEXT','Verzendbevestigingen versturen');
define('ZENCART_TEXT_CONFIRM_ON','Voor orders verzonden op');
define('ZENCART_SHIP_CONFIRM_BTN','Verzendingen bevestigen');
// Error Messages
define('ZENCART_ERROR_NO_ITEMS','Er zijn geen voorraadartikelen geselecteerd om te uploaden naar de ZenCart-catalogus. Er wordt gezocht naar het selectievakje met de naam catalog om te uploaden artikelen te herkennen.');
define('ZENCART_ERROR_CONFRIM_NO_DATA','Er zijn voor deze datum geen records gevonden om met ZenCart te bevestigen.');
define('ZENCART_ERROR_NO_PRICE_SHEET','Kon geen standaard prijsniveau vinden voor prijslijst: ');
define('ZENCART_INVALID_ACTION','Ongeldige actie aangevraagd in de ZenCart-koppelingsklasse. Wordt afgebroken!');
define('ZENCART_INVALID_SKU','Fout in voorraadartikel-id; het record is niet gevonden in de database');
// Javascrpt Defines
// Audit Log Messages
define('ZENCART_UPLOAD_PRODUCT','ZenCart-productupload');
define('ZENCART_BULK_UPLOAD','ZenCart-bulkupload');
define('ZENCART_PRODUCT_SYNC','ZenCart-productsynchronisatie');
define('ZENCART_SHIP_CONFIRM','ZenCart-verzendbevestiging');
define('ZENCART_INVENTORY_CATALOG_IS_EMPTY','Het veld webwinkelmenu / catalogus is leeg. Dit veld is verplicht wanneer u een product wilt uploaden.');
define('ZENCART_DISABLED_NOTICE','De ZenCart-koppeling is uitgeschakeld. Schakel deze in onder Bedrijf → Configuratie → ZenCart voordat u uploadt of synchroniseert.');
// VAT invoice email — fallback default body if ZENCART_VAT_INVOICE_MESSAGE is ever cleared
define('ZENCART_VAT_INVOICE_MESSAGE_DEFAULT','In de bijlage vindt u de btw-factuur {invoice} voor uw order, {name}. Hartelijk dank voor uw bestelling.');

if (!defined('ZENCART_CATALOG_URL')) define('ZENCART_CATALOG_URL','ZenCart - product-URL.'); // also defined in zencart/admin.php
if (!defined('ZENCART_CATALOG_MODEL')) define('ZENCART_CATALOG_MODEL','ZenCart - productmodel.'); // also defined in zencart/admin.php
define('ZENCART_LBL_LIST_ICON_ENABLED','Het ZenCart-uploadpictogram tonen op elke regel van de voorraadlijst. De knop Opslaan en uploaden op de bewerkpagina van een artikel wordt apart geregeld en wordt niet door deze instelling beïnvloed.');

?>