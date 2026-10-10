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
//  Path: /modules/forms/language/nl_nl/language.php
//

define('BOX_FORMS_DOCUMENTS',      'Rapporten');
define('FORMS_PAGE_TITLE',         'Rapporten');
define('FORMS_PAGE_INTRO',         'Alle rapporten van dit bedrijf. Formulieren — facturen, orders, overzichten — worden afgedrukt vanuit het document waar ze bij horen, en worden opgemaakt onder Gereedschappen &rarr; Formulieropmaak.');

define('FORMS_COL_TITLE',          'Document');
define('FORMS_COL_GROUP',          'Groep');
define('FORMS_COL_SERVED_BY',      'Geleverd door');
define('FORMS_COL_ACTIONS',        'Uitvoeren');

define('FORMS_SERVED_DATASET',     'gegevensset: %s');
define('FORMS_SERVED_PROVIDER',    'ingebouwd in code: %s');
define('FORMS_SERVED_NOT_YET',     'nog niet geleverd door Forms');
define('FORMS_SERVED_FORM',        'een formulier — afgedrukt vanuit een document');
define('FORMS_BTN_PRINT',          'Afdrukken…');

define('FORMS_LEGEND_OPTIONS',     'Uitvoeropties');
define('FORMS_DATE_RANGE',         'Datumbereik');
define('FORMS_PERIOD',             'Boekingsperiode');
define('FORMS_GL_ACCOUNT',         'Grootboekrekening');
define('FORMS_BTN_PDF',            'PDF');
define('FORMS_BTN_CSV',            'CSV');
define('FORMS_BTN_VIEW',           'Bekijken');
define('FORMS_BTN_MORE',           'Meer manieren om dit uit te voeren');
define('FORMS_BTN_OPTIONS',        'Opties…');
define('FORMS_RUN_DIALOG_TITLE',   'Uitvoeren: %s');
define('FORMS_OPT_AS_SET',         'Zoals het rapport is ingesteld');
define('FORMS_OPT_AS_SET_NAMED',   'Zoals het rapport is ingesteld — %s');

define('FORMS_FILTER_PLACEHOLDER', 'Filteren op naam');
define('FORMS_FILTER_NO_MATCH',    'Geen document komt overeen met wat u hebt getypt.');

define('FORMS_ERROR_NOT_FOUND',    'Geen document met id %s.');
define('FORMS_ERROR_CANNOT_RUN',   'Forms kan dit document niet uitvoeren: %s');
define('FORMS_ERROR_NO_FORMAT',    'Onbekend uitvoerformaat: %s');
define('FORMS_ERROR_DELIVERY',     'Het document is aangemaakt maar kon niet worden verzonden: %s');
define('FORMS_ERROR_GL_REQUIRED',  'Kies een grootboekrekening voordat u de afstemming uitvoert.');
define('FORMS_GL_NONE',            'Dit bedrijf heeft geen kas- of bankrekeningen, dus er is niets af te stemmen. Voeg er een toe onder Grootboek &rarr; Rekeningschema en voer dit daarna opnieuw uit.');
define('FORMS_WARN_CONVERSION',    'Dit rapport is met waarschuwingen omgezet: %s');
define('FORMS_WARN_NOT_COUNTED',   'Het document is geleverd, maar de afdrukteller is niet bijgewerkt: %s');
define('FORMS_NO_DOCUMENTS',       'Er zijn geen documenten gedefinieerd.');
define('FORMS_ROW_IS_FORM',        'een formulier — opgemaakt onder Gereedschappen → Formulieropmaak');

/***** printing a form from the document that owns it (?module=forms&page=print) *****/
define('FORMS_PRINT_TITLE',        'Document afdrukken');
define('FORMS_PRINT_CHOOSE',       'Kies zo nodig een ander.');
define('FORMS_PRINT_WHAT',         'Wat wordt afgedrukt');
define('FORMS_PRINT_RECORDS',      'Geselecteerde records');
define('FORMS_PRINT_DOCUMENT',     'Document');
define('FORMS_PRINT_FOR',          'Voor');
define('FORMS_PRINT_ONE_RECORD',   '%d record');
define('FORMS_PRINT_N_RECORDS',    '%d records');
define('FORMS_PRINT_AND_MORE',     'en nog %d');
define('FORMS_PRINT_DELIVER',      'Leveren');
define('FORMS_PRINT_BTN_VIEW',     'PDF bekijken');
define('FORMS_PRINT_BTN_DOWNLOAD', 'PDF downloaden');
define('FORMS_PRINT_BTN_EMAIL',    'E-mail verzenden');
define('FORMS_PRINT_EMAIL',        'In plaats daarvan e-mailen');
define('FORMS_PRINT_EMAIL_TO',     'Aan adres');
define('FORMS_PRINT_EMAIL_NAME',   'Aan naam');
define('FORMS_PRINT_EMAIL_SUBJECT','Onderwerp');
define('FORMS_PRINT_EMAIL_MESSAGE','Bericht');
define('FORMS_PRINT_EMAIL_BODY',   'Bijgaand vindt u uw %s van %s.');
define('FORMS_PRINT_EMAIL_NO_ADDRESS', 'Voer een adres in om naar te verzenden.');
define('FORMS_PRINT_EMAIL_MANY',   'Deze documenten zijn voor meer dan één klant en zouden als één bijlage worden verzonden, daarom is er geen adres ingevuld.');
define('FORMS_PRINT_NO_FORMS',     'Er zijn geen formulieren gedefinieerd in groep %s.');
define('FORMS_PRINT_NOTHING_ASKED','Er is geen formulier genoemd. Deze pagina wordt geopend vanuit het document dat wordt afgedrukt.');
define('FORMS_PRINT_NO_RECORDS',   'Er is niets geselecteerd om af te drukken.');
define('FORMS_PRINT_OPEN_FROM_DOCUMENT', 'Een formulier drukt één record tegelijk af en wordt daarom geopend vanuit het document waar het bij hoort — open de order, factuur of het overzicht en gebruik de knop Afdrukken daarvan.');

// ── Dataset authoring (design §3.2, §3.3) ────────────────────────────────────
define('BOX_FORMS_DATASETS',        'Rapportgegevenssets');
define('FORMS_DS_TITLE',            'Rapportgegevenssets');
define('FORMS_DS_INTRO',            'Een gegevensset is de verzameling tabellen waaruit een rapportauteur velden mag halen. Het definiëren ervan is een apart recht, los van het schrijven of uitvoeren van rapporten, omdat het bepaalt welke gegevens bereikbaar worden.');
define('FORMS_DS_SHIPPED_NOTE',     'Gegevenssets die met FreeBooks worden meegeleverd, zijn in code gedefinieerd en kunnen hier niet worden bewerkt.');
define('FORMS_DS_COL_NAME',         'Naam');
define('FORMS_DS_COL_LABEL',        'Label');
define('FORMS_DS_COL_TABLES',       'Tabellen');
define('FORMS_DS_COL_FIELDS',       'Velden');
define('FORMS_DS_COL_TIER',         'Herkomst');
define('FORMS_DS_TIER_SHIPPED',     'Meegeleverd');
define('FORMS_DS_TIER_CUSTOM',      'Aangepast');
define('FORMS_DS_NEW',              'Nieuwe gegevensset');
define('FORMS_DS_EDIT',             'Gegevensset bewerken');
define('FORMS_DS_NONE',             'Er zijn geen aangepaste gegevenssets gedefinieerd.');

define('FORMS_DS_LEGEND_ABOUT',     'Gegevensset');
define('FORMS_DS_LEGEND_JOINS',     'Tabellen');
define('FORMS_DS_LEGEND_FIELDS',    'Velden');
define('FORMS_DS_NAME',             'Naam');
define('FORMS_DS_NAME_HELP',        'Letters, cijfers en underscores. Wordt gebruikt in opgeslagen rapportdefinities en kan later dus niet worden gewijzigd.');
define('FORMS_DS_LABEL',            'Label');
define('FORMS_DS_DESCRIPTION',      'Omschrijving');
define('FORMS_DS_BASE_TABLE',       'Begintabel');
define('FORMS_DS_ADD_JOIN',         'Een tabel toevoegen');
define('FORMS_DS_ADD_FIELD',        'Een veld toevoegen');
define('FORMS_DS_REMOVE',           'Verwijderen');
define('FORMS_DS_JOIN_TYPE',        'Rijen opnemen');
define('FORMS_DS_JOIN_INNER',       'Alleen waar beide overeenkomen');
define('FORMS_DS_JOIN_LEFT',        'Alle, al dan niet overeenkomend');
define('FORMS_DS_JOIN_TABLE',       'Tabel');
define('FORMS_DS_JOIN_MATCH',       'Gekoppeld op');
define('FORMS_DS_FIELD_KEY',        'Veldnaam');
define('FORMS_DS_FIELD_LABEL',      'Weergegeven als');
define('FORMS_DS_FIELD_SOURCE',     'Kolom');
define('FORMS_DS_FIELD_TYPE',       'Type');
define('FORMS_DS_NO_SQL_NOTE',      'Tabellen en kolommen worden gekozen uit deze database. Er is bewust geen plek om SQL te typen.');

define('FORMS_DS_SAVED',            "Gegevensset '%s' opgeslagen.");
define('FORMS_DS_DELETED',          "Gegevensset '%s' verwijderd.");
define('FORMS_DS_ERROR',            'Gegevensset niet opgeslagen: %s');
define('FORMS_DS_NOT_FOUND',        "Er is geen aangepaste gegevensset met de naam '%s'.");
define('FORMS_DS_IN_USE',           "Gegevensset '%s' kan niet worden verwijderd: %d rapport(en) gebruiken deze nog.");
define('FORMS_DS_CONFIRM_DELETE',   'Deze gegevensset verwijderen? Rapporten die deze gebruiken, werken dan niet meer.');
define('FORMS_DS_PROBLEM',          '%s');

// ── the report editor (design §13.7 step 2) ──────────────────────────────────
define('FORMS_DESIGN_TITLE',        'Rapporteditor');
define('FORMS_DESIGN_TITLE_FOR',    'Rapporteditor — %s');
define('FORMS_DESIGN_PICK_ONE',     'Er is geen rapport gekozen om te bewerken. Rapporten worden bewerkt met de knop Bewerken ernaast in de lijst Rapporten.');
define('FORMS_DESIGN_BACK_TO_LIST', 'Naar de lijst Rapporten');
define('FORMS_DESIGN_EDIT',         'Bewerken');
define('FORMS_DESIGN_IS_FORM',      'Dit is een formulier, geen rapport. Formulieren worden op een pagina opgemaakt in plaats van in kolommen, en worden bewerkt in de formulierontwerper.');
define('FORMS_DESIGN_DENIED',       'U hebt geen toestemming om rapporten te maken.');
define('FORMS_DESIGN_CONVERT_WARNING', 'Dit rapport is nog in het oude formaat opgeslagen. Als u het hier opslaat, wordt het herschreven in het Forms-formaat en kan de oude rapportengine het niet meer openen. Een kopie van het origineel wordt ernaast bewaard.');
define('FORMS_DESIGN_SAVED',        "Rapport '%s' opgeslagen.");
define('FORMS_DESIGN_ERROR',        'Rapport niet opgeslagen: %s');

define('FORMS_DESIGN_ABOUT',        'Rapport');
define('FORMS_DESIGN_NAME',         'Naam');
define('FORMS_DESIGN_DESCRIPTION',  'Omschrijving');
define('FORMS_DESIGN_FROM_DATASET', 'Velden komen uit de gegevensset %s (%s). Alleen velden die daarin zijn gedeclareerd, kunnen worden gebruikt.');
define('FORMS_DESIGN_FROM_PROVIDER','Dit rapport wordt opgebouwd door de berekening %s, dus de kolommen liggen vast en er kunnen geen nieuwe worden toegevoegd.');

define('FORMS_DESIGN_COLUMNS',      'Kolommen');
define('FORMS_DESIGN_COL_FIELD',    'Veld');
define('FORMS_DESIGN_COL_HEADING',  'Kop');
define('FORMS_DESIGN_COL_WIDTH',    'Breedte');
define('FORMS_DESIGN_COL_ALIGN',    'Uitlijning');
define('FORMS_DESIGN_COL_FORMAT',   'Opmaak');
define('FORMS_DESIGN_COL_TOTAL',    'Totaal');
define('FORMS_DESIGN_ADD_COLUMN',   'Een kolom toevoegen');
define('FORMS_DESIGN_COL_BREAK',    'Scheiding');
define('FORMS_DESIGN_COLUMNS_NOTE', 'Breedte is in millimeters; laat leeg om het rapport te laten bepalen. Scheiding: aangevinkt begint het volgende veld een nieuwe kolom verderop op de pagina; niet aangevinkt wordt het onder dit veld in dezelfde kolom afgedrukt.');

define('FORMS_DESIGN_ALIGN_DEFAULT','Automatisch');
define('FORMS_DESIGN_ALIGN_LEFT',   'Links');
define('FORMS_DESIGN_ALIGN_CENTER', 'Midden');
define('FORMS_DESIGN_ALIGN_RIGHT',  'Rechts');

define('FORMS_DESIGN_GROUPING',     'Groeperen en totalen');
define('FORMS_DESIGN_GROUP_BY',     'Groeperen op');
define('FORMS_DESIGN_GROUP_NONE',   '(geen)');
define('FORMS_DESIGN_GROUP_FORMAT', 'Opmaak groepslabel');
define('FORMS_DESIGN_TOTALS_ONLY',  'Alleen groepstotalen tonen');
define('FORMS_DESIGN_GROUP_BREAK',  'Na elke groep een nieuwe pagina beginnen');

define('FORMS_DESIGN_SORTING',      'Sorteren');
define('FORMS_DESIGN_ADD_SORT',     'Een sortering toevoegen');
define('FORMS_DESIGN_ASCENDING',    'Oplopend');
define('FORMS_DESIGN_DESCENDING',   'Aflopend');

define('FORMS_DESIGN_FILTERS',      'Filters');
define('FORMS_DESIGN_FILTER_IS',    'Vergelijking');
define('FORMS_DESIGN_FILTER_VALUE', 'Waarde');
define('FORMS_DESIGN_FILTER_AND',   'en');
define('FORMS_DESIGN_FILTER_EXPRESSION', 'Vergeleken met andere kolommen. Dit filter blijft zoals het is; het kan hier nog niet worden bewerkt.');
define('FORMS_DESIGN_ADD_FILTER',   'Een filter toevoegen');
define('FORMS_DESIGN_FILTERS_NOTE', 'Scheid bij "is een van" de waarden met komma\'s.');

define('FORMS_DESIGN_PAGE',         'Pagina');
define('FORMS_DESIGN_PAPER',        'Papier');
define('FORMS_DESIGN_ORIENTATION',  'Afdrukstand');
define('FORMS_DESIGN_PORTRAIT',     'Staand');
define('FORMS_DESIGN_LANDSCAPE',    'Liggend');
define('FORMS_DESIGN_MARGINS',      'Marges');
define('FORMS_DESIGN_TOP',          'Boven');
define('FORMS_DESIGN_BOTTOM',       'Onder');
define('FORMS_DESIGN_LEFT',         'Links');
define('FORMS_DESIGN_RIGHT',        'Rechts');
define('FORMS_DESIGN_MM',           'Millimeters.');
define('FORMS_DESIGN_DATE_RANGE',   'Gedekte datums');
define('FORMS_DESIGN_DATE_FIELD',   'Datumbereik geldt voor');
define('FORMS_DESIGN_TRUNCATE',     'Tekst afkappen op kolombreedte');

define('FORMS_DESIGN_HEADINGS',     'Koppen en opmaak');
define('FORMS_DESIGN_TITLE1',       'Eerste titelregel');
define('FORMS_DESIGN_TITLE2',       'Tweede titelregel');
define('FORMS_DESIGN_TITLE_TOKENS', 'In de titelregels zijn %reportname% (rapportnaam) en %date% (datum) toegestaan.');
define('FORMS_DESIGN_BAND',         'Deel van de pagina');
define('FORMS_DESIGN_SHOWN',        'Getoond');
define('FORMS_DESIGN_STYLED',       'Opgemaakt');
define('FORMS_DESIGN_FONT',         'Lettertype');
define('FORMS_DESIGN_SIZE',         'Grootte');
define('FORMS_DESIGN_COLOUR',       'Kleur');
define('FORMS_DESIGN_BAND_HEADING', 'Bedrijfsnaam');
define('FORMS_DESIGN_BAND_TITLE1',  'Eerste titel');
define('FORMS_DESIGN_BAND_TITLE2',  'Tweede titel');
define('FORMS_DESIGN_BAND_FILTER',  'Filteromschrijving');
define('FORMS_DESIGN_BAND_DATA',    'Kolommen en rijen');
define('FORMS_DESIGN_BAND_TOTALS',  'Totaalrijen');

define('FORMS_DESIGN_PREVIEW',      'Voorbeeld');
define('FORMS_DESIGN_PREVIEW_NOTE', 'Het voorbeeld toont wat er op deze pagina staat, inclusief wijzigingen die u nog niet hebt opgeslagen.');
define('FORMS_DESIGN_REFRESH',      'Voorbeeld vernieuwen');
define('FORMS_DESIGN_PREVIEW_EMPTY','Dit rapport leverde geen rijen op, dus er is niets te tonen. Controleer de filters en de gedekte datums.');
define('FORMS_DESIGN_PREVIEW_DATES', 'Voorbeeld over');
define('FORMS_DESIGN_PREVIEW_AS_SET','Zoals het rapport is ingesteld');
define('FORMS_DESIGN_PREVIEW_SAMPLE','VOORBEELDGEGEVENS — dit rapport leverde geen rijen op, dus de onderstaande cijfers zijn verzonnen om de opmaak te tonen.');

// The filter description drawn under a report's titles.
define('FORMS_FILTER_PREFIX',       'Rapportfilters: ');
define('FORMS_FILTER_GROUPED_BY',   'Gegroepeerd op: %s');
define('FORMS_FILTER_SORTED_BY',    'Gesorteerd op: %s');
define('FORMS_FILTER_DATE_RANGE',   'Datumbereik: %s tot %s');
define('FORMS_FILTER_PERIOD',       'Boekingsperiode: %s');

// Emailing a document — the run options card on the document list, and the
// extra recipients on the form print page.
define('FORMS_BTN_EMAIL',      'E-mailen…');
define('FORMS_EMAIL_LEGEND',   'Een rapport e-mailen');
define('FORMS_EMAIL_NOTE',     'Het rapport wordt als PDF bijgevoegd en bevat precies wat de opties hierboven vragen. Laat het onderwerp leeg om het onder de eigen naam van het rapport te verzenden.');
define('FORMS_EMAIL_FROM',     'Van');
define('FORMS_EMAIL_CC',       'CC');
define('FORMS_EMAIL_BCC',      'BCC');

// ── the form canvas (design §13.7 step 3) ────────────────────────────────────
define('BOX_FORMS_CANVAS',        'Formulieropmaak');
define('FORMS_CANVAS_TITLE',      'Formulieropmaak');
define('FORMS_CANVAS_TITLE_FOR',  'Formulieropmaak — %s');
define('FORMS_CANVAS_PICK_ONE',  'Alle formulieren van dit bedrijf. Kies er een om te zien waar de elementen op de afgedrukte pagina staan en om ze te verplaatsen. Een bonprinterformulier wordt in plaats daarvan in de boneditor geopend.');
define('FORMS_CANVAS_FOLDER',     'Map');
define('FORMS_CANVAS_CHOOSE',     'Formulieren die u kunt bekijken');
define('FORMS_CANVAS_OPEN',       'Opmaak');
define('FORMS_CANVAS_IS_REPORT',  'Dit is een rapport, geen formulier. Rapporten bestaan uit kolommen in plaats van gepositioneerde vakken, en worden gewijzigd in de rapporteditor.');
// ── new, copy, rename and delete (§13.29) ────────────────────────────────────
define('FORMS_DOC_NEW',        'Nieuw');
define('FORMS_DOC_COPY',       'Kopiëren');
define('FORMS_DOC_RENAME',     'Naam wijzigen');
define('FORMS_DOC_DELETE',     'Verwijderen');
define('FORMS_DOC_NAME',       'Naam');
define('FORMS_DOC_FOLDER',     'Map');
define('FORMS_DOC_DATASET',    'Gebaseerd op');
define('FORMS_DOC_CREATED',    '%s is aangemaakt. Het is leeg — voeg hieronder inhoud toe.');
define('FORMS_DOC_COPIED',     '%s is gekopieerd. Het origineel is ongewijzigd.');
define('FORMS_DOC_RENAMED',    'Naam gewijzigd in %s.');
define('FORMS_DOC_DELETED',    'Het document is verwijderd.');
define('FORMS_DOC_WAS_LAST',   'Dat was het enige document in de map, dus een knop Afdrukken die daarnaar verwijst, heeft nu niets meer om te openen.');
define('FORMS_DOC_NEW_REPORT', 'Nieuw rapport');
define('FORMS_DOC_NEW_FORM',   'Nieuw formulier');
define('FORMS_DOC_COPY_OF',    'Kopie van %s');
define('FORMS_DOC_CONFIRM_DELETE', 'Dit document verwijderen? Dit kan hier niet ongedaan worden gemaakt.');
define('FORMS_DOC_CONFIRM_LAST',   'Dit is het enige document in de map. Als u het verwijdert, heeft een knop Afdrukken die daarnaar verwijst niets meer om te openen. Toch verwijderen?');
define('FORMS_DOC_NEW_HELP',   'Een nieuw document begint leeg. Wilt u uitgaan van een document dat bijna goed is, kopieer dat dan.');

// ── the receipt editor (§13.27) ──────────────────────────────────────────────
define('FORMS_RECEIPT_TITLE',      'Bonnen');
define('FORMS_RECEIPT_TITLE_FOR',  'Bon: %s');
define('FORMS_RECEIPT_BACK_TO_LIST', 'Terug naar de formulierenlijst');
define('FORMS_RECEIPT_NO_LAYOUT',  'Dit is gemarkeerd als bonprinterformulier, maar er kon geen opmaak in tekencellen uit worden gelezen.');
define('FORMS_RECEIPT_ABOUT',      'Deze bon');
define('FORMS_RECEIPT_NAME',       'Naam');
define('FORMS_RECEIPT_PAPER',      'Papierbreedte');
define('FORMS_RECEIPT_PAPER_HELP', 'Hoeveel tekens er in de breedte op het papier passen. Dit krijgt een nieuw veld mee, en dit meet de liniaal boven het voorbeeld — er wordt niets mee afgekapt.');
define('FORMS_RECEIPT_LINES',      'Regels');
define('FORMS_RECEIPT_LINES_HELP', 'Velden worden in deze volgorde afgedrukt. Meerdere velden kunnen één regel delen: elk wordt OVER de tot dan toe opgebouwde regel geschreven, zo staan een label links en een bedrag rechts naast elkaar. Vink Einde regel aan bij het laatste veld van elke regel.');
define('FORMS_RECEIPT_COL_LINE',   'Regel');
define('FORMS_RECEIPT_COL_PRINTS', 'Drukt af');
define('FORMS_RECEIPT_COL_FORMAT', 'Opmaak');
define('FORMS_RECEIPT_COL_WIDTH',  'Cel');
define('FORMS_RECEIPT_COL_ALIGN',  'Positie');
define('FORMS_RECEIPT_COL_BREAK',  'Einde regel');
define('FORMS_RECEIPT_COL_ACTION', 'Verplaatsen');
define('FORMS_RECEIPT_ALIGN_L',    'Links');
define('FORMS_RECEIPT_ALIGN_C',    'Gecentreerd');
define('FORMS_RECEIPT_ALIGN_R',    'Rechts');
define('FORMS_RECEIPT_ADD_TEXT',   'Een tekstregel toevoegen');
define('FORMS_RECEIPT_ADD_VALUE',  'of een waarde:');
define('FORMS_RECEIPT_ADD_VALUE_BTN', 'Toevoegen');
define('FORMS_RECEIPT_ADD_HELP',   'Tekst is tekst die u zelf typt. Een waarde is iets wat deze bon kan leveren — de lijst bevat alle mogelijke waarden. Een nieuw veld komt aan het einde en sluit zijn regel af; met de pijlen verplaatst u het naar de juiste plek.');
define('FORMS_RECEIPT_NEW_TEXT',   'Nieuwe tekst');
define('FORMS_RECEIPT_KIND_TEXT',  'Tekst');
define('FORMS_RECEIPT_KIND_VALUE', 'Waarde');
define('FORMS_RECEIPT_KIND_BLOCK', 'Blok');
define('FORMS_RECEIPT_KIND_TABLE', 'Artikelen');
define('FORMS_RECEIPT_KIND_OTHER', 'Niet afgedrukt');
define('FORMS_RECEIPT_PREVIEW',    'Wat wordt afgedrukt');
define('FORMS_RECEIPT_PREVIEW_OF', 'Een echte verkoop (boeking %d), afgedrukt via de bonengine zelf.');
define('FORMS_RECEIPT_NOT_A_RECEIPT', 'Dit is geen bonprinterformulier.');
define('FORMS_RECEIPT_PREVIEW_STALE', 'Toont wat is opgeslagen. Wijzig iets om te zien wat er zou worden afgedrukt.');
define('FORMS_RECEIPT_NO_SAMPLE',  'Er is geen kassaboeking in dit bedrijf om af te drukken, dus er is geen voorbeeld. De regels kunnen nog steeds worden bewerkt.');
define('FORMS_CANVAS_IS_RECEIPT', 'Bon');
define('FORMS_CANVAS_NAME',       'Naam');
define('FORMS_CANVAS_OPEN_RECEIPT', 'Regels');
define('FORMS_CANVAS_READ_ONLY',  'Dit toont waar elk element op de afgedrukte pagina staat.');
define('FORMS_CANVAS_SAMPLED',    'Er is geen record in dit bedrijf dat dit formulier zou afdrukken, dus de onderstaande cijfers zijn verzonnen. De opmaak is echt; alleen de gegevens niet.');
define('FORMS_CANVAS_PAGE',       'De afgedrukte pagina');
define('FORMS_CANVAS_SHOW_BOXES', 'De elementen tonen');
define('FORMS_CANVAS_ELEMENTS',   'Elementen');
define('FORMS_CANVAS_ELEMENT_COUNT', 'op dit formulier. Wijs er een aan om het op de pagina te vinden.');
define('FORMS_CANVAS_LOADING',    'Pagina wordt weergegeven…');
define('FORMS_CANVAS_FAILED',     'De pagina kon niet worden weergegeven.');
define('FORMS_CANVAS_GROWS',      'De tekst loopt door onder dit vak.');
define('FORMS_CANVAS_DASHED',     'Een vak met een gestippelde onderrand bevat tekst die eronder doorloopt — een adresblok, of een waarde die lang genoeg is om af te breken. Het vak geeft aan waar de tekst begint en hoe hoog één regel is, niet waar die eindigt.');
define('FORMS_CANVAS_UNMEASURED', 'De grootte van dit vak kon niet worden bepaald, dus deze is slechts indicatief.');

// ── editing on the canvas (design §13.7 step 4) ──────────────────────────────
define('FORMS_CANVAS_INTRO',      'Sleep een element om het te verplaatsen, of sleep een van de randen of hoeken om het formaat te wijzigen. Alles kan ook rechts exact worden ingetypt. De pagina wordt tijdens het werken opnieuw getekend; zet Automatisch opnieuw tekenen uit als het traag aanvoelt, en gebruik dan Pagina opnieuw tekenen.');
define('FORMS_CANVAS_GROUPING',   'Om meerdere elementen samen te verplaatsen, klikt u met Shift ingedrukt op elk element, of begint u op een lege plek en sleept u een kader dat ze volledig omsluit. Hun onderlinge posities blijven behouden.');
define('FORMS_CANVAS_SNAP',       'Uitlijnen op');
define('FORMS_CANVAS_SNAP_OFF',   'Uit');
define('FORMS_CANVAS_SELECTED',   'Geselecteerd element');
define('FORMS_CANVAS_NOTHING',    'Klik op een element op de pagina, of op een rij in de lijst hieronder.');
define('FORMS_CANVAS_MANY',       '%d elementen zijn geselecteerd. Slepen, Verwijderen en de volgordepijlen gelden voor allemaal; de vakken hieronder beschrijven het element dat hierboven is genoemd.');
define('FORMS_CANVAS_X',          'Horizontaal (mm)');
define('FORMS_CANVAS_Y',          'Verticaal (mm)');
define('FORMS_CANVAS_W',          'Breedte (mm)');
define('FORMS_CANVAS_H',          'Hoogte (mm)');
define('FORMS_CANVAS_LINE_HEIGHT','Regelhoogte (mm)');
define('FORMS_CANVAS_TEXT',       'Tekst');
define('FORMS_CANVAS_TEXT_NOTE',  'Druk op Enter voor een nieuwe regel.');
// Which record the underlay is drawn from (design 13.53). "Showing" rather than
// "Record", because the control answers a question about the picture on screen
// and not about the form being edited - the form is drawn for every record, and
// this is the one it happens to be drawn for right now.
define('FORMS_CANVAS_RECORD',      'Weergave van');
define('FORMS_CANVAS_RECORD_OR',   'of zoek');
define('FORMS_CANVAS_RECORD_FIND', 'referentie');
define('FORMS_CANVAS_RECORD_SHOW', 'Tonen');
define('FORMS_CANVAS_RECORD_NONE', 'Niets kwam overeen met %s.');
define('FORMS_CANVAS_RECORD_OTHER','%s kon niet door dit formulier worden afgedrukt, daarom wordt een ander record getoond.');
define('FORMS_CANVAS_LABEL',      'Naam');
define('FORMS_CANVAS_LABEL_NOTE', 'Hoe dit element in de lijst heet. Laat leeg om het te beschrijven naar wat het is.');
define('FORMS_CANVAS_BIND',       'Toont');
define('FORMS_CANVAS_BIND_NOTE',  'De velden die dit formulier kan leveren, met aan het einde uw bedrijfsgegevens (COMPANY_...). Een bedrijfsgegeven is op elk record hetzelfde en wordt gewijzigd in de Bedrijfsinstellingen.');
define('FORMS_CANVAS_BORDER',     'Randbreedte');
define('FORMS_CANVAS_BORDER_SHOW','Tekenen');
define('FORMS_CANVAS_VISIBLE',    'Verschijnt op');
define('FORMS_CANVAS_VIS_ALL',    'Elke pagina');
define('FORMS_CANVAS_VIS_FIRST',  'Alleen de eerste pagina');
define('FORMS_CANVAS_VIS_LAST',   'Alleen de laatste pagina');
define('FORMS_CANVAS_VIS_CONTINUED', 'De laatste pagina, met "Vervolg" ervoor');
// When an element prints at all, as a test against the record (design 13.51).
// The operator labels read as the middle of a sentence - "Currency is one of" -
// because the control above them names the field and the one after holds the
// value, so the three read left to right as what they do.
define('FORMS_CANVAS_WHEN',        'Drukt af wanneer');
define('FORMS_CANVAS_WHEN_ALWAYS', 'Altijd');
define('FORMS_CANVAS_WHEN_NOTE',   'Laat dit op Altijd staan en het element wordt op elk record afgedrukt. Om voor verschillende records verschillende tekst af te drukken, plaatst u meerdere elementen op dezelfde plek, elk met een andere test.');
define('FORMS_CANVAS_WHEN_LIST_NOTE', 'Scheid meerdere waarden met komma\'s.');
define('FORMS_CANVAS_WHEN_EQUALS',     'is');
define('FORMS_CANVAS_WHEN_NOTEQUALS',  'is niet');
define('FORMS_CANVAS_WHEN_IN',         'is een van');
define('FORMS_CANVAS_WHEN_NOTIN',      'is geen van');
define('FORMS_CANVAS_WHEN_ISEMPTY',    'is leeg');
define('FORMS_CANVAS_WHEN_ISNOTEMPTY', 'is niet leeg');
define('FORMS_CANVAS_WHEN_GREATER',    'is meer dan');
define('FORMS_CANVAS_WHEN_LESS',       'is minder dan');
define('FORMS_CANVAS_WHEN_MARK',    'Drukt alleen af op sommige records');
define('FORMS_CANVAS_WHEN_GHOSTED', 'Vervaagde elementen worden voor het hier getoonde record niet afgedrukt. Kies een ander record om ze te zien.');
define('FORMS_CANVAS_FORWARD',    'Naar voren');
define('FORMS_CANVAS_BACKWARD',   'Naar achteren');
define('FORMS_CANVAS_CONFIRM_DELETE', 'Dit element van het formulier verwijderen?');
define('FORMS_CANVAS_CONFIRM_DELETE_MANY', 'Deze %d elementen van het formulier verwijderen?');
define('FORMS_CANVAS_DELETE_HINT', 'Verwijder de geselecteerde elementen. De Delete-toets doet hetzelfde.');
define('FORMS_CANVAS_ADD',        'Een element toevoegen');
define('FORMS_CANVAS_ADD_TEXT',   'Tekst');
define('FORMS_CANVAS_ADD_FIELD',  'Waarde');
define('FORMS_CANVAS_ADD_LINE',   'Lijn');
define('FORMS_CANVAS_ADD_RECT',   'Kader');
define('FORMS_CANVAS_ADD_IMAGE',  'Afbeelding');
define('FORMS_CANVAS_ADD_BLOCK',   'Adresblok');
define('FORMS_CANVAS_ADD_TABLE',   'Orderregels');
define('FORMS_CANVAS_ADD_PAGENUM', 'Paginanummer');
define('FORMS_CANVAS_ADD_BARCODE', 'Streepjescode');
define('FORMS_CANVAS_ADD_NOTE',   'Een nieuw element verschijnt linksboven op de pagina. Sleep het naar de gewenste plek.');
define('FORMS_CANVAS_NEW',        'Nieuw element');
define('FORMS_CANVAS_IMAGE',      'Afbeeldingsbestand');
define('FORMS_CANVAS_IMAGE_NOTE', 'De afbeeldingen van dit bedrijf. Voeg hieronder een andere toe.');
define('FORMS_CANVAS_UPLOAD',     'Een afbeelding toevoegen');
define('FORMS_CANVAS_UPLOAD_GO',  'Uploaden');
define('FORMS_CANVAS_UPLOAD_NOTE','PNG, JPEG of GIF, tot %d MB. Deze wordt alleen aan dit bedrijf toegevoegd, en het geselecteerde element gaat deze gebruiken.');
define('FORMS_CANVAS_UPLOADING',  'Bezig met uploaden…');
define('FORMS_CANVAS_UPLOADED',   '%s is toegevoegd.');
define('FORMS_UPLOAD_NOTHING',    'Er is geen afbeelding gekozen.');
define('FORMS_UPLOAD_FAILED',     'De afbeelding kon niet worden opgeslagen.');
define('FORMS_CANVAS_RERENDER',   'Pagina opnieuw tekenen');
define('FORMS_CANVAS_FONT_DEFAULT', 'De standaard van het rapport');
define('FORMS_CANVAS_FONT_OTHER',   '%s (al in gebruik)');
define('FORMS_CANVAS_BORDER_COLOUR', 'Randkleur');
define('FORMS_CANVAS_BORDER_NOTE', 'In punten. 0 is een haarlijn, niet "geen rand".');
// A line and a box ARE the outline, so the width they carry is their own
// thickness rather than a border around something else. Same control, named
// for what it does on the element in hand (design §13.49).
define('FORMS_CANVAS_THICKNESS',      'Dikte');
define('FORMS_CANVAS_THICKNESS_NOTE', 'In punten. 0 is een haarlijn, niet "geen lijn".');
define('FORMS_CANVAS_TABLE_ON_TOP', 'De tabel met orderregels wordt altijd over de andere elementen getekend, ongeacht de plaats in deze volgorde.');

// The line-item table's columns, and the address block's lines (§13.30).
define('FORMS_CANVAS_COLUMNS',      'Kolommen');
define('FORMS_CANVAS_COLUMNS_EDIT', 'Kolommen (%d)…');
define('FORMS_CANVAS_COLUMNS_NONE', 'Deze tabel heeft nog geen kolommen en drukt dus niets af. Voeg hieronder een kolom toe.');
define('FORMS_CANVAS_COLUMNS_TITLE','Kolommen van %s');
define('FORMS_CANVAS_COL_ADD',      'Een kolom toevoegen');
define('FORMS_CANVAS_COL_HEADING',  'Kop');
define('FORMS_CANVAS_COL_WIDTH',    'Breedte');
define('FORMS_CANVAS_COL_FORMAT',   'Opmaak');
// ── value formats (§13.31) ──────────────────────────────────────────────────
//
// The labels for Formatter::CHOOSABLE. A hint's stored name is what goes in the
// file; this is what an author reads. Each names the RESULT rather than the
// mechanism, because "Money, in the document's currency" is a choice somebody
// can make and `posted_cur` is a thing they have to already know.
//
// The stored name is kept in the label, in brackets. It is what appears in a
// converted definition, what the manual is indexed by, and what anyone
// comparing two forms will see - hiding it would make the list easier to read
// once and harder to use twice.
define('FORMS_FORMAT_NONE',       'Zoals opgeslagen');
define('FORMS_FORMAT_GROUP_MONEY','Geld');
define('FORMS_FORMAT_GROUP_NUMBER','Getallen');
define('FORMS_FORMAT_GROUP_TEXT', 'Datums en tekst');
define('FORMS_FORMAT_GROUP_CODE', 'Codes omgezet in woorden');
define('FORMS_FORMAT_OTHER',      '%s (al in gebruik)');
define('FORMS_FORMAT_GROUP_OTHER','Al gebruikt door dit document');

define('FORMS_FORMAT_POSTED_CUR', "Geld, in de valuta van het document (posted_cur)");
define('FORMS_FORMAT_NULL_PCUR',  "Geld, in de valuta van het document; leeg indien nul (null_pcur)");
define('FORMS_FORMAT_DEF_CUR',    "Geld, in de valuta van het bedrijf (def_cur)");
define('FORMS_FORMAT_NULL_DCUR',  "Geld, in de valuta van het bedrijf; leeg indien nul (null_dcur)");
define('FORMS_FORMAT_DLR',        'Geld, altijd in dollar (dlr)');
define('FORMS_FORMAT_NULL_DLR',   'Geld, altijd in dollar; leeg indien nul (null-dlr)');
define('FORMS_FORMAT_EURO',       'Geld, altijd in euro (euro)');

define('FORMS_FORMAT_RND_DEC',    'Getal, op het gebruikelijke aantal decimalen (rnd_dec)');
define('FORMS_FORMAT_RND_PRE',    'Getal, op het precieze aantal decimalen (rnd_pre)');
define('FORMS_FORMAT_RND2D',      'Getal, afgerond op twee decimalen (rnd2d)');
define('FORMS_FORMAT_NEG',        'Getal, met omgekeerd teken (neg)');
define('FORMS_FORMAT_N2WRD',      'Getal, uitgeschreven in woorden (n2wrd)');

define('FORMS_FORMAT_DATE',       'Datum, in het formaat van deze installatie (date)');
define('FORMS_FORMAT_UC',         'Tekst, in hoofdletters (uc)');
define('FORMS_FORMAT_LC',         'Tekst, in kleine letters (lc)');
define('FORMS_FORMAT_BLANK',      'Niets afdrukken (blank)');

define('FORMS_FORMAT_TERMS',      'Betalingsvoorwaarden, uitgeschreven (terms)');
define('FORMS_FORMAT_SHIP_NAME',  'Naam verzendmethode (ship_name)');
define('FORMS_FORMAT_J_DESC',     'Journaalnaam, uit het nummer (j_desc)');
define('FORMS_FORMAT_YESBNO',     '"Ja", of leeg (yesBno)');
define('FORMS_FORMAT_NEWUSED',    '"Nieuw" of "Gebruikt" (NewUsed)');
define('FORMS_FORMAT_PRINTED',    '"Duplicaat" bij een herdruk (printed)');
define('FORMS_FORMAT_COA_TYPE',   'Naam rekeningtype (coa_type)');
define('FORMS_FORMAT_CVT_ISO2',   'Landcode, twee letters uit drie (cvt_iso2)');
define('FORMS_FORMAT_CVT_CNTRY',  'Landnaam, uit de code (cvt_cntry)');

define('FORMS_CANVAS_COL_NOTE',     'Breedtes zijn in millimeters en worden over de tabel verdeeld. Bij een lege breedte krijgt de kolom de resterende ruimte.');

define('FORMS_CANVAS_PARTS',        'Regels');
define('FORMS_CANVAS_PARTS_EDIT',   'Regels (%d)…');
define('FORMS_CANVAS_PARTS_NONE',   'Dit blok heeft nog geen regels en drukt dus niets af. Voeg hieronder een regel toe.');
define('FORMS_CANVAS_PARTS_TITLE',  'Regels van %s');
define('FORMS_CANVAS_PART_ADD',     'Een regel toevoegen');
define('FORMS_CANVAS_PART_JOIN',    'Gevolgd door');
define('FORMS_CANVAS_PART_NOTE',    'Elke regel wordt met de volgende verbonden door wat u hier kiest. Het blok is één tekstvak, dus hiermee komt de plaatsnaam op een eigen regel.');
define('FORMS_CANVAS_PART_FIXED',   'Een bedrijfsgegeven, ongewijzigd.');

define('FORMS_CANVAS_SEP_NONE',     'Niets');
define('FORMS_CANVAS_SEP_NL',       'Een nieuwe regel');
define('FORMS_CANVAS_SEP_DEL_NL',   'Een nieuwe regel, tenzij deze leeg is');
define('FORMS_CANVAS_SEP_SP',       'Een spatie');
define('FORMS_CANVAS_SEP_2SP',      'Twee spaties');
define('FORMS_CANVAS_SEP_COMMA',    'Een komma');
define('FORMS_CANVAS_SEP_COM_SP',   'Een komma en een spatie');
define('FORMS_CANVAS_SEP_SEMI_SP',  'Een puntkomma en een spatie');

define('FORMS_CANVAS_PATTERN',      'Tekst');
define('FORMS_CANVAS_PATTERN_NOTE', 'Gebruik {page} voor het paginanummer, {pages} voor het totaal en {of} voor het woord "van".');
define('FORMS_CANVAS_SYMBOLOGY',       'Type streepjescode');
define('FORMS_CANVAS_SYMBOLOGY_NOTE',  'Elk formulier dat uit de oude ontwerper is omgezet, gebruikt Code 128. Wijzig dit alleen als uw scanner een ander type nodig heeft.');
define('FORMS_CANVAS_SYMBOLOGY_OTHER', '%s (al in gebruik)');
define('FORMS_CANVAS_SUB_DONE',     'Gereed');
define('FORMS_CANVAS_HEADING',      'Kopregel');
define('FORMS_CANVAS_HEADING_NOTE', 'De kolomkoppen worden los van de rijen eronder getekend, dus deze rand is het kader rond de koppen en de rand hierboven is het kader rond de tabel. Laat de grootte leeg om te behouden wat het formulier al gebruikt.');
define('FORMS_CANVAS_BOLD',   'Vet');
define('FORMS_CANVAS_ITALIC', 'Cursief');
define('FORMS_CANVAS_LIVE',     'Automatisch opnieuw tekenen');
define('FORMS_CANVAS_DRAWING',  'Opnieuw tekenen…');
define('FORMS_CANVAS_UNDO',      'Ongedaan maken');
define('FORMS_CANVAS_REDO',      'Opnieuw');
define('FORMS_CANVAS_UNDO_HINT', 'De laatste wijziging ongedaan maken (Ctrl+Z). De geschiedenis gaat verloren als u de pagina verlaat.');
define('FORMS_CANVAS_REDO_HINT', 'De zojuist ongedaan gemaakte wijziging opnieuw uitvoeren (Ctrl+Y).');

/***** the faces a document may be drawn in *****/
define('FORMS_FONT_HELVETICA', 'Helvetica');
define('FORMS_FONT_COURIER',   'Courier');
define('FORMS_FONT_TIMES',     'Times Roman');
define('FORMS_FONT_SERIF',     'Serif');

/***** labels the renderers draw *****/
define('FORMS_TABLE_CONTINUED',    'Vervolg');
define('FORMS_GROUP_TOTAL_FOR',    'Groepstotaal voor:');
define('FORMS_REPORT_TOTAL_FOR',   'Rapporttotaal voor:');

/***** seeding a company's documents *****/
define('FORMS_SEED_DUPLICATE',     'Er bestaat al een document met de naam "%s", dus het is niet vervangen.');
define('FORMS_SEED_WRITE_ERROR',   'De documentdefinitie kon niet worden geschreven naar %s.');
define('FORMS_SEED_UNREADABLE',    'De documentdefinitie "%s" kon niet worden gelezen en is daarom niet geïnstalleerd.');

/***** the paper a document may sit on *****/
define('FORMS_PAPER_LEGAL',        'Legal');
define('FORMS_PAPER_LETTER',       'Letter');
define('FORMS_PAPER_TABLOID',      'Tabloid');
