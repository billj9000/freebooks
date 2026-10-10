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
//  Path: /modules/zencart/language/nl_nl/admin.php

// Module information
define('MODULE_ZENCART_TITLE','ZenCart-module');
define('MODULE_ZENCART_DESCRIPTION','De ZenCart-module koppelt PhreeBooks aan een ZenCart-webwinkel. Functies zijn onder meer producten uploaden, orders downloaden en productdatabases synchroniseren.');
define('ZENCART_ADMIN_URL','URL van de ZenCart-winkel (zonder slash aan het eind, bijv. https://shop.example.com)');
define('ZENCART_ADMIN_API_KEY','API-sleutel (moet overeenkomen met FREEBOOKS_API_KEY in de ZenCart-configuratietabel)');
define('MODULE_ZENCART_CONFIG_INFO','Stel de configuratiewaarden in voor uw ZenCart-webwinkel.');
define('ZENCART_LBL_ENABLED','De ZenCart-koppeling inschakelen. Bij Nee is al het ZenCart-verkeer (cron voor orderimport, product-/voorraadsynchronisatie, verzendmeldingen) voor dit bedrijf uitgeschakeld.');
define('ZENCART_TAX_CLASS','Voer het tekstveld ZenCart-belastingklasse in (moet exact overeenkomen met de invoer in ZenCart als er btw wordt berekend)');
define('ZENCART_USE_PRICES','Wilt u prijslijsten gebruiken?');
define('ZENCART_TEXT_PRICE_SHEET','Te gebruiken ZenCart-prijslijst');
define('ZENCART_SHIP_ID','Numerieke ZenCart-statuscode voor verzonden orders');
define('ZENCART_PARTIAL_ID','Numerieke ZenCart-statuscode voor gedeeltelijk verzonden orders');
define('ZENCART_LBL_STORE_TIMEZONE','Tijdzone waarin de ZenCart-winkel zijn orders vastlegt. Gedownloade orders worden gedateerd in de eigen tijdzone van het bedrijf, omgerekend vanuit deze tijdzone. Laat deze gelijk aan die van het bedrijf, tenzij de winkel elders draait.');
define('ZENCART_TEXT_SAME_AS_COMPANY','Gelijk aan het bedrijf');
define('ZENCART_LBL_NOTIFY_SHIPMENTS','Verzendmeldingen naar ZenCart sturen in de dagelijkse cron (mailt klanten wanneer hun order is verzonden, met de statuscodes hierboven)');
define('ZENCART_LBL_NOTIFY_MESSAGE','Bericht verzendmelding — gebruik {date}, {carrier}, {service}, {tracking} in willekeurige volgorde');
define('ZENCART_LBL_UPDATED_BY','Naam die wordt getoond in de kolom "Updated By" van de ZenCart-orderstatus');
define('ZENCART_CONFIG_SAVED','ZenCart-configuratiewaarden bijgewerkt/opgeslagen.');
define('ZENCART_CATALOG_ADD','Upload naar ZenCart-catalogus toestaan');
// Stored in xtra_fields.description (varchar(64)) — keep these at or under 64 chars.
define('ZENCART_CATALOG_CATEGORY_ID',"ZenCart - categorie-ID. Moet overeenkomen met de categorie in Zen Cart.");
define('ZENCART_CATALOG_MANUFACTURER',"ZenCart - fabrikant. Moet overeenkomen met de fabrikant in Zen Cart.");
if (!defined('ZENCART_CATALOG_URL')) define('ZENCART_CATALOG_URL','ZenCart - product-URL.'); // also defined in zencart/language.php
if (!defined('ZENCART_CATALOG_MODEL')) define('ZENCART_CATALOG_MODEL','ZenCart - productmodel.'); // also defined in zencart/language.php
define('ZENCART_CATALOG_DESCRIPTION','ZenCart - lange HTML-productomschrijving.');
define('ZENCART_LBL_CATCH_ALL_ACCT','Verzamelklant-ID (contact) voor orders waarbij het e-mailadres niet kan worden gekoppeld of geen nieuwe klant kan worden aangemaakt (0 = dergelijke orders overslaan)');
define('ZENCART_LBL_DEFAULT_TAX','Reserve-btw-tarief-ID wanneer het btw-% conform het ZenCart-artikel niet in de tabel Btw-tarieven kan worden gevonden (0 = zonder btw)');
define('ZENCART_LBL_CREATE_INVOICE','Ook een verkoopfactuur (dagboek 12) aanmaken voor elke geïmporteerde order');
define('ZENCART_LBL_DEFAULT_REP','Standaardverkoper voor geïmporteerde orders (0 = geen)');
define('ZENCART_LBL_LAST_ORDER','ID van de laatst geïmporteerde ZenCart-order — wijzig dit om orders vanaf een bepaald punt opnieuw te importeren');
define('ZENCART_LBL_PAYMENT_MAP','Koppeling betaalmodules — kommagescheiden items in de vorm zcmodule=fbmodule:glacct (bijv. paypalr=paypal:1020,stripe=freecharger:1030). fbmodule en glacct zijn elk optioneel.');
define('ZENCART_LBL_SHIPPING_MAP','Koppeling verzendmethoden — met een verticale streep gescheiden items in de vorm zc_module:keyword=fb_module:fb_service. keyword wordt hoofdletterongevoelig gezocht als deel van de ZenCart-verzendomschrijving (mag dubbele punten bevatten); de langste overeenkomst wint. Laat keyword leeg voor een vangnetitem voor die module. Voorbeeld: royalmail:RM Tracked 24=royal_mail:RM24|royalmail:=royal_mail:RM48|dhl:DHL Express: Domestic=dhl_express:DHL_D');
define('ZENCART_LBL_NON_SHIP_SKUS','Niet-verzendbare artikelnummers — kommagescheiden lijst van ZenCart-artikelcodes die kostenposten zijn (IOSS, invoerrechten, enz.) en die moeten worden uitgesloten van de douane-/verzendregels en de aangegeven goederenwaarde. Deze artikelen worden voor de boekhouding nog steeds in de order geïmporteerd.');
define('ZENCART_LBL_VAT_INVOICE','Een btw-factuur mailen voor elke geïmporteerde order wanneer bij het klantcontact een btw-nummer is vastgelegd (Contacten &gt; veld BTW-nummer)');
define('ZENCART_LBL_VAT_INVOICE_FORM','PhreeForm-factuurformulier dat als btw-factuur wordt gemaild');
define('ZENCART_LBL_VAT_INVOICE_SUBJECT','Onderwerp e-mail btw-factuur — gebruik {invoice}, {order}, {name}, {company}, {date} in willekeurige volgorde');
define('ZENCART_LBL_VAT_INVOICE_MESSAGE','Bericht e-mail btw-factuur — gebruik {invoice}, {order}, {name}, {company}, {date} in willekeurige volgorde');
define('ZENCART_LBL_VAT_INVOICE_BCC','BCC-adres voor e-mails met btw-factuur (optioneel, leeg laten voor geen)');
// Validation warnings for config maps
define('ZENCART_WARN_PAYMENT_MAP_FMT',      'Item #%d van de betaalkoppeling ("%s"): scheidingsteken "=" ontbreekt — verwacht formaat zcmodule=fbmodule:glacct.');
define('ZENCART_WARN_PAYMENT_MAP_EMPTY',    'Item #%d van de betaalkoppeling: de naam van de ZenCart-module is leeg.');
define('ZENCART_WARN_SHIPPING_MAP_FMT',     'Item #%d van de verzendkoppeling ("%s"): scheidingsteken "=" ontbreekt — verwacht formaat zc_module:keyword=fb_module:fb_service.');
define('ZENCART_WARN_SHIPPING_MAP_EMPTY',   'Item #%d van de verzendkoppeling: de naam van de ZenCart-module is leeg.');
define('ZENCART_WARN_SHIPPING_MAP_MODULE',  'Item #%d van de verzendkoppeling: FreeBooks-verzendmodule "%s" niet gevonden onder modules/shipping/methods/.');
// audit log messages
define('ZENCART_LOG_TABS','Zencart voorraad tabblad toevoegen');
define('ZENCART_LOG_FIELDS','Zencart voorraad veld toevoegen');

?>