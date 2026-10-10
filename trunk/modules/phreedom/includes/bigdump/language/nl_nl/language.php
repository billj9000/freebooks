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
//  Path: /modules/phreedom/includes/bigdump/language/nl_nl/language.php
//

if (!defined('TEXT_FILENAME')) define('TEXT_FILENAME','Bestandsnaam');
if (!defined('TEXT_MISC'))     define('TEXT_MISC','Overig');
define('TEXT_QUERY','Query: ');
define('TEXT_MYSQL','MYSQL: ');
define('TEXT_PROCESSING_FILE','Bestand verwerken: ');
define('TEXT_STARTING_LINE','Beginnen bij regel: ');
define('TEXT_TO_GO','Te gaan');
define('TEXT_DONE','Gereed');
define('TEXT_SESSION','Sessie');
define('TEXT_LINES','Regels');
define('TEXT_QUERIES','Query\'s');
define('TEXT_BYTES','Bytes');
define('TEXT_KB','KB');
define('TEXT_MB','MB');
define('TEXT_PERCENT','%');
define('TEXT_PERCENT_BAR','% balk');
define('TEXT_STOP','STOP');
define('TEXT_PRESS','Druk op ');

define('BIGDUMP_INTRO','Dit script is een aangepaste versie van het BigDump-script, ontwikkeld door <a href="mailto:alexey@ozerov.de">Alexey Ozerov</a> - <a href="http://www.ozerov.de/bigdump.php" target="_blank">BigDump-homepage</a>');
define('BIGDUMP_FILE_EXISTS','Bestand %s bestaat al! Verwijder het en upload het opnieuw!');
define('BIGDUMP_UPLOAD_TYPES','U kunt alleen .sql-, .gz-, .bz2-, .zip- of .csv-bestanden uploaden.');
define('BIGDUMP_ERROR_MOVE','Fout bij het verplaatsen van geüpload bestand %s naar %s');
define('BIGDUMP_ERROR_PERM','Controleer de mapmachtigingen voor %s (moet 777 zijn)!');
define('BIGDUMP_FILE_SAVED','Geüpload bestand opgeslagen als %s');
define('BIGDUMP_ERROR_UPLOAD','Fout bij het uploaden van bestand ');
define('BIGDUMP_REMOVED',' is succesvol verwijderd');
define('BIGDUMP_FAIL_REMOVE','Kan niet verwijderen: ');
define('BIGDUMP_START_IMP','Import starten');
define('BIGDUMP_START_LOC',"naar %s op %s");
define('BIGDUMP_DEL_FILE','Bestand verwijderen');
define('BIGDUMP_NO_FILES','Geen geüploade bestanden gevonden in de werkmap');
define('BIGDUMP_ERROR_DIR','Fout bij het weergeven van map %s');
define('BIGDUMP_FROM_LOC','van %s naar %s op %s');
define('BIGDUMP_UPLOAD_A','Uploadformulier uitgeschakeld. De machtigingen voor de werkmap <i>%s</i> <b>moeten op 777 staan</b> om ');
define('BIGDUMP_UPLOAD_B',"hier bestanden te kunnen uploaden. U kunt uw dumpbestanden ook via FTP uploaden naar de map: ");
define('BIGDUMP_UPLOAD_C','U kunt nu uw dumpbestand van maximaal %s bytes (%s MB) ');
define('BIGDUMP_UPLOAD_D',"rechtstreeks vanuit uw browser naar de server uploaden. U kunt dumpbestanden van elke grootte ook via FTP uploaden naar de map: ");
define('BIGDUMP_OPEN_FAIL','Kan %s niet openen voor import');
define('BIGDUMP_BAD_NAME','Controleer of de naam van uw dumpbestand alleen alfanumerieke tekens bevat en hernoem het zo nodig, bijvoorbeeld: %s.<br />Of geef in bigdump.php bij \%s de volledige bestandsnaam op. <br />Of u moet eerst %s naar de server uploaden.');
define('BIGDUMP_NO_SEEK','Kan niet zoeken in %s');
define('BIGDUMP_IMPORT_MSG_1','ONVERWACHT: niet-numerieke waarden voor start en offset');
define('BIGDUMP_IMPORT_MSG_2','Fout bij het verwijderen van records uit %s.');
define('BIGDUMP_IMPORT_MSG_3','ONVERWACHT: kan de bestandspositie niet voorbij het einde van het bestand zetten');
define('BIGDUMP_IMPORT_MSG_4','ONVERWACHT: kan de bestandspositie niet zetten op offset: ');
define('BIGDUMP_IMPORT_MSG_5','Gestopt bij regel %s.');
define('BIGDUMP_IMPORT_MSG_6','Op deze plaats komt de huidige query uit een csv-bestand, maar %s is niet ingesteld.');
define('BIGDUMP_IMPORT_MSG_7','U moet aangeven waar u uw gegevens naartoe wilt sturen.');
define('BIGDUMP_IMPORT_MSG_8','Op deze plaats bevat de huidige query meer dan %s dumpregels. Dat kan gebeuren als uw dumpbestand is gemaakt met een tool die niet aan het einde van elke query een puntkomma gevolgd door een regeleinde plaatst, of als uw dump extended inserts bevat. Lees de BigDump-FAQ voor meer informatie.');
define('BIGDUMP_IMPORT_MSG_9','Fout op regel %s: ');
define('BIGDUMP_IMPORT_MSG_10','ONVERWACHT: kan de offset van de bestandspositie niet lezen');
define('BIGDUMP_IMPORT_MSG_11','Nu <b>wacht ik %s milliseconden</b> voordat de volgende sessie start...');
define('BIGDUMP_IMPORT_MSG_12','Ga verder vanaf regel %s (schakel JavaScript in om dit automatisch te doen)');
define('BIGDUMP_IMPORT_MSG_13'," om de import af te breken <b>OF WACHT!</b>");
define('BIGDUMP_IMPORT_MSG_14','Gestopt bij fout');
define('BIGDUMP_IMPORT_MSG_15','Opnieuw beginnen');
define('BIGDUMP_IMPORT_MSG_16',' (verwijder de oude tabellen met DROP voordat u opnieuw begint)');
define('BIGDUMP_IMPORT_MSG_17','DATABASE-IMPORT GESLAAGD - KLIK HIER OM UIT EN WEER IN TE LOGGEN EN HET HERSTEL TE VOLTOOIEN');

?>