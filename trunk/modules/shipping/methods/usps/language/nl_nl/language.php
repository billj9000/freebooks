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
//  Path: /modules/shipping/methods/usps/language/nl_nl/language.php
//
define('MODULE_SHIPPING_USPS_TEXT_TITLE', 'United States Postal Service');
define('MODULE_SHIPPING_USPS_TITLE_SHORT', 'USPS');
define('MODULE_SHIPPING_USPS_TEXT_DESCRIPTION', 'Deze methode ondersteunt momenteel alleen tariefberekening voor binnenlandse Express-, Priority- en Parcel Post-zendingen.');
define('MODULE_SHIPPING_USPS_TEXT_INTRODUCTION', 'U moet een account bij USPS hebben geregistreerd op http://www.uspsprioritymail.com/et_regcert.html om deze module te gebruiken. USPS verwacht dat u ponden gebruikt als gewichtseenheid voor uw producten.');
define('MODULE_SHIPPING_USPS_TRACKING_URL','http://trkcnfrm1.smi.usps.com/PTSInternetWeb/InterLabelInquiry.do');
define('SHIPPING_USPS_SHIPMENTS_ON','US Postal Service-zendingen op: ');

// Key descriptions
define('MODULE_SHIPPING_USPS_TITLE_DESC', 'Titel voor weergave in de verzendcalculator');
define('MODULE_SHIPPING_USPS_USERID_DESC', 'Voer de USPS USERID in die aan u is toegewezen.');
define('MODULE_SHIPPING_USPS_SERVER_DESC', 'Een account bij USPS is nodig om de productieserver te gebruiken');
define('MODULE_SHIPPING_USPS_MACHINABLE_DESC', 'Worden alle verzonden producten als machinaal verwerkbaar beschouwd volgens C700 Package Services 2.0 Nonmachinable PARCEL POST USPS Rules and Regulations?<br /><strong>Let op: niet-machinaal verwerkbare pakketten leiden meestal tot een hoger Parcel Post-tarief.<br />Pakketten van 35 lbs of meer, of lichter dan 6 ounces (.375), worden overschreven en op False gezet</strong>');
define('MODULE_SHIPPING_USPS_TYPES_DESC', 'Selecteer de binnenlandse USPS-diensten die standaard worden aangeboden.');
//define('MODULE_SHIPPING_USPS_TYPES_INTL_DESC', 'Select the international services to be offered:');
define('MODULE_SHIPPING_USPS_SORT_ORDER_DESC', 'Volgorde van tonen. Bepaalt de volgorde waarin deze methode in alle gegenereerde lijsten verschijnt.');

// Shipping Methods
define('MODULE_SHIPPING_USPS_1DM', 'Express Mail');
define('MODULE_SHIPPING_USPS_1DA', 'Express Flat Rate Env');
define('MODULE_SHIPPING_USPS_1DP', 'Priority Mail');
define('MODULE_SHIPPING_USPS_2DA', 'Priority Flat Rate Env');
define('MODULE_SHIPPING_USPS_2DP', 'Priority Sml Flat Rate Box');
define('MODULE_SHIPPING_USPS_3DA', 'Priority Med Flat Rate Box');
define('MODULE_SHIPPING_USPS_3DS', 'Priority Lrg Flat Rate Box');
define('MODULE_SHIPPING_USPS_GND', 'Parcel Post');
define('MODULE_SHIPPING_USPS_GDR', 'Media Mail');

?>
