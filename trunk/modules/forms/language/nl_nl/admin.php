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
//  Path: /modules/forms/language/nl_nl/admin.php
//
// Read by Company -> Module Administration, which builds its list by scanning
// modules/*/config.php and reading MODULE_<NAME>_TITLE and _DESCRIPTION for
// each. Both are required: a module without them is a fatal on that page, not a
// missing row.

define('MODULE_FORMS_TITLE',       'Module Forms');
define('MODULE_FORMS_DESCRIPTION', 'Rapport- en formulierengine. Voert de documentcatalogus uit met getypeerde waarden, zodat dezelfde definitie een leesbare PDF afdrukt en een machineleesbare CSV exporteert. Het definiëren van gegevenssets is een apart recht, los van het schrijven of uitvoeren van rapporten.');

// The module's own settings page, reached from Company -> Module Administration.
define('FORMS_ADMIN_TITLE',        'Instellingen module Forms');
define('FORMS_ADMIN_CONFIG_INFO',  'Instellingen');

// The renderer defaults, set on the module's own settings page.
define('FORMS_DEFAULT_PAPER_TEXT',        'Papierformaat');
define('FORMS_DEFAULT_PAPER_DESC',        'Het papier waarop een document wordt gezet als de eigen opmaak niets aangeeft. Een document dat zijn eigen formaat noemt, behoudt dat.');
define('FORMS_DEFAULT_ROWSPACE_TEXT',     'Regelafstand');
define('FORMS_DEFAULT_ROWSPACE_DESC',     'Extra ruimte tussen de rijen van een rapporttabel, in punten. 2 is de standaardwaarde.');
define('FORMS_DEFAULT_COLUMN_WIDTH_TEXT', 'Standaard kolombreedte');
define('FORMS_DEFAULT_COLUMN_WIDTH_DESC', 'Breedte in millimeters voor een rapportkolom waarvan de definitie geen breedte opgeeft. 25 is de standaardwaarde.');
define('FORMS_ADMIN_PRINT_CAUTION',       'Voor afdrukken is het recht <b>Forms - Run and print documents</b> nodig. Wie dat recht niet via zijn rol heeft, kan geen document openen met een knop Afdrukken; controleer daarom de rollen onder Bedrijf &rarr; Gebruikersbeveiliging.');

/***** the tools card: re-stamp the paper, rebuild the list *****/
define('FORMS_ADMIN_TOOLS_INFO',    'Gereedschappen');

define('FORMS_TOOLS_RESTAMP_TITLE',   'Papierformaat opnieuw toekennen');
define('FORMS_TOOLS_RESTAMP_DESC',    'Zet elk opgeslagen rapport en formulier om naar het huidige standaard papierformaat (%s). Elementen worden niet verplaatst, dus een formulier dat voor een bredere pagina is getekend, komt op een smallere pagina dichter bij de rechterrand te staan. Een bestand waarvan de pagina-instelling niet kan worden gevonden, blijft ongewijzigd en wordt geteld.');
define('FORMS_TOOLS_RESTAMP_SUBMIT',  'Alle rapporten en formulieren opnieuw toekennen');
define('FORMS_TOOLS_RESTAMP_CONFIRM', 'Hiermee wordt het paginaformaat van elk opgeslagen rapport en formulier herschreven. Doorgaan?');
define('FORMS_RESTAMP_DONE',          '%d rapport(en) en formulier(en) omgezet naar %s.');
define('FORMS_RESTAMP_SKIPPED',       '%d bestand(en) zijn ongewijzigd gelaten omdat de pagina-instelling niet kon worden gevonden.');

define('FORMS_TOOLS_REBUILD_TITLE',   'Documentenlijst controleren / opnieuw opbouwen');
define('FORMS_TOOLS_REBUILD_DESC',    'Bouwt de documentenlijst opnieuw op uit de definitiebestanden op schijf. De mappenstructuur wordt opnieuw geladen, elk document waarvan de map is verdwenen wordt opnieuw ingedeeld, en lijstitems zonder bestand worden verwijderd. Gebruik dit wanneer de lijst na een herstel niet klopt.');
define('FORMS_TOOLS_REBUILD_SUBMIT',  'Controleren / opnieuw opbouwen starten');
define('FORMS_TOOLS_REBUILD_CONFIRM', 'Hiermee wordt de hele documentenlijst opnieuw opgebouwd uit de bestanden op schijf. Doorgaan?');
define('FORMS_TOOLS_REBUILD_SUCCESS', 'De documentenlijst is opnieuw opgebouwd: %s document(en), waarvan er %s hun map kwijt waren en onder Diversen zijn ingedeeld.');
define('FORMS_TOOLS_REBUILD_SKIPPED', '%s bestand(en) konden niet als document worden gelezen en zijn op hun plaats gelaten. Ze staan niet in de opnieuw opgebouwde lijst.');
