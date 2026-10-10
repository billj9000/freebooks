<?php
// +-----------------------------------------------------------------+
// Phreedom Language Translation File
// Generated: 2011-11-27 05:02:30
// Module/Method: install
// ISO Language: nl_nl
// Version: 3.3
// +-----------------------------------------------------------------+
// Path: /Iinstall/language/nl_nl/language.php
// HTML_PARAMS and CHARSET intentionally not defined here — they're also
// defined (identically) by modules/phreedom/language/nl_nl/language.php,
// which install/pages/main/pre_process.php loads immediately after this file.
// The LANGUAGE define below is left commented out rather than deleted, for
// the same reason, but install/functions/install.php reads this file as raw
// text to build the welcome page's language selector, so its exact text
// (not a paraphrase) must stay present. Do not remove or reword this line.
// define('LANGUAGE','Nederlands (NL)');
define('LANGUAGE_TEXT','Beschikbare Talen: ');
define('TITLE_WELCOME','Welkom - Phreedom Klein Bedrijf oplossingen');
define('TEXT_AGREE','Akkoord');
define('TEXT_DISAGREE','Niet Akkoord');
define('INTRO_WELCOME', '<h2>Welkom bij PhreeBooks Accounting</h2>
<p>Dit script helpt bij de installatie van de applicatie en controleert of uw systeem aan de minimale vereisten voldoet. U heeft de volgende informatie nodig om door te gaan:</p>
<ul>
  <li>Een bestaande mysql database met toegangsgegevens</li>
  <li>Schrijfrechten voor de webserver (777) op de directories: /includes en /my_files</li>
  <li>Een gebruikersnaam, e-mailadres en wachtwoord voor de beheerder</li>
  <li>SSL Server padinformatie (aanbevolen; dit kan later indien nodig worden gewijzigd)</li>
  <li>De beginmaand en het beginjaar van het boekjaar voor het opslaan van journaalposten</li>
</ul>
<p>Bevestig dat u de licentievoorwaarden accepteert en klik op Doorgaan om verder te gaan.</p>');
define('DESC_AGREE','Ik heb de licentie door gelezen en ga akkoord met de voorwaarden');
define('DESC_DISAGREE','Ik heb de Licentie door gelezen en ga niet akkoord met de voorwaarden.');
define('TITLE_INSPECT','Controleer - Phreedom Klein Bedrijf Oplossingen');
define('MSG_INSPECT_ERRORS', 'De volgende installatiefouten zijn gevonden.
<ul>
  <li>Fouten (in rood) moeten worden opgelost voordat het installatiescript verder kan gaan.</li>
  <li>Waarschuwingen (in geel) verhinderen de installatie niet, maar kunnen ervoor zorgen dat modules niet goed werken.</li>
</ul>');
define('INSTALL_ERROR_PHP_VERSION','Uw php versie moet 8.2 of hoger zijn, en lager dan 9.0');
define('INSTALL_ERROR_SESSION_SUPPORT','Uw php configuratie is geïnstalleerd  zonder session support. Dit is vereist om met Phreedom te werken.');
define('INSTALL_ERROR_PDO_MYSQL', 'Uw php configuratie heeft de pdo_mysql extensie niet geïnstalleerd. Deze extensie is nodig om verbinding te maken met de database.');
define('INSTALL_ERROR_OPENSSL','Uw server moet openssl geinstalleerd hebben.');
define('INSTALL_ERROR_CURL','Uw servers php applicatie is geïnstalleerd  zonder cURL support. cURL support is voor beveiligde communicatie tussen applications van derden.');
define('INSTALL_ERROR_MBSTRING', 'De php applicatie van uw server is geïnstalleerd zonder mbstring ondersteuning. Sommige modules werken mogelijk niet correct zonder deze functie.');
define('INSTALL_ERROR_GD', 'De php applicatie van uw server is geïnstalleerd zonder GD ondersteuning. Barcodes, labels en sommige PDF rapporten zijn zonder deze functie niet beschikbaar.');
define('INSTALL_ERROR_ZIP', 'De php applicatie van uw server is geïnstalleerd zonder Zip ondersteuning. Database backup en herstel zijn zonder deze functie niet beschikbaar.');
define('INSTALL_ERROR_BZ2', 'De php applicatie van uw server is geïnstalleerd zonder BZip2 ondersteuning. Hulpmiddelen voor het importeren/herstellen van grote databases zijn zonder deze functie niet beschikbaar.');
define('INSTALL_ERROR_UPLOADS','Uw servers php applicatie is geïnstalleerd  zonder file upload support. Upload support is vereist om bestanden te kunnen importeren in Phreedom.');
define('INSTALL_ERROR_UPLOAD_DIR','Ik kan geen tijdelijke upload directory vinden op deze server.');
define('INSTALL_ERROR_XML','Uw servers php applicatie is geïnstalleerd  zonder XML support. Sommige modules kunnen niet werken zonder deze optie.');
define('INSTALL_ERROR_FTP','Uw server php applicatie is geïnstalleerd zonder FTP support. Sommige modules kunnen niet werken zonder deze optie.');
define('INSTALL_ERROR_INCLUDES_DIR','De directory /includes is niet beschrijfbaar. We hebben toegang nodig om hier uw configuratie bestand op te slaan.');
define('INSTALL_ERROR_MY_FILES_DIR','De directory /my_files is niet beschrijfbaar. We hebben toegang nodig om hier uw bedrijfsbestanden te kunnen opslaan.');
define('TEXT_RECHECK','Her-Controleer');
// TEXT_INSTALL intentionally not defined here — see note near the top of this
// file; modules/phreedom/language/nl_nl/language.php also defines it.
define('TITLE_INSTALL','Installeer - Phreedom Klein Bedrijf Oplossingen');
define('MSG_INSTALL_INTRO','Vul alstublieft wat informatie over uw bedrijf, administrator, web server en database.');
define('TEXT_COMPANY_INFO','Bedrijf Informatie');
define('TEXT_ADMIN_INFO','Administrator Informatie');
define('TEXT_SRVR_INFO','Server Informatie');
define('TEXT_DB_INFO','Database Informatie');
define('TEXT_FISCAL_INFO','Fiscale Informatie');
define('TEXT_COMPANY_NAME','Voer een korte naam in voor uw bedrijf. Dit wordt getoond in een keuze menu tijdens het inloggen.');
define('TEXT_INSTALL_DEMO_DATA','Wil u voor iedere module demo data installeren? Aantekening: Als u Ja selecteert, worden de tabellen geleegd voordat de demodata wordt opgeslagen.');
define('TEXT_CHART_INFO', 'Select the chart of accounts this company keeps its books on. The chart decides both the accounts the company starts with and which of them the receivables and payables settings point at, so it is worth picking the one that matches the country and trading form. It can be replaced later, from Setup, only while the general journal is still empty.');
define('MSG_CHART_NO_DEMO', 'The demonstration data uses the US Retail account numbers, so it cannot be loaded against the %s. The company has been installed without it.');
define('TEXT_USER_NAME','Voer de gebruikersnaam in van de adminstrator');
define('TEXT_USER_PASSWORD','Voer het wachwoord in voor de administrator');
define('TEXT_UER_PW_CONFIRM','Voer nog maals het wachtwoord in.');
define('TEXT_USER_EMAIL','Voer het email adres van de administrator in');
define('TEXT_HTTP_SRVR','Voer de server http URL in naar de root directory (normaal is de standaard ingestelde waarde goed)');
define('TEXT_USE_SSL','Gebruik SSL om verbinding te maken met uw bedrijf (aantekening: een valide SSL certificaat moet geïnstalleerd zijn op de server). Dit kan later veranderd worden als SSL nu nog niet nodig is.');
define('TEXT_HTTPS_SRVR','Voer de server https URL toe aan de root directory voor beveiligde transacties (gebruikelijk is de standaard ingestelde waarde goed)');
define('TEXT_DB_HOST','Voer de database host naam in');
define('TEXT_DB_NAME','Voer de database naam in (De database moet bestaan op de server)');
define('TEXT_DB_PREFIX','Als de database gedeeld wordt met een andere applicatie, gebruik dan een voorvoegsel voor de installatie (gebruik alleen alfabetische karakters en underscore):');
define('TEXT_DB_USER','Voer de database gebruikersnaam in');
define('TEXT_DB_PASSWORD','Voer de database wachtwoord in');
define('TEXT_FY_MONTH_INFO','Selecteer een start maand om in te stellen als uw eerste boekhoud periode. PhreeBooks zal standaard beginnen aan op de eerste dag van de maand.');
define('TEXT_FY_YEAR_INFO','Selecteer een start jaar om als uw eerste Boek jaar in te stellen. Er kunnen dan geen boekingen gemaakt worden eerder dan de geselecteerde jaar en maand.');
define('TEXT_PRINTING_INFO','Printing Information');
define('TEXT_PAPER_SIZE_INFO', 'Select the paper size for reports and forms. The reports and forms installed here are drawn for US Letter; choosing another size re-targets every one of them as it is installed. This can be changed later, and existing reports re-stamped, under Reports and Forms settings.');
define('TEXT_TIMEZONE_INFO', 'Kies de tijdzone waarin het bedrijf werkt. Documentdatums, geplande taken en vastgelegde tijden gebruiken deze, en hij wordt de standaard voor later toegevoegde bedrijven. Te wijzigen onder Bedrijfsinstellingen.');
define('ERROR_TEXT_ADMIN_COMPANY_ISEMPTY','De bedrijfsnaam is leeg');
define('ERROR_TEXT_ADMIN_USERNAME_ISEMPTY','Admin gebruikersnaam is leeg');
define('ERROR_TEXT_ADMIN_EMAIL_ISEMPTY','Admin email leeg');
define('ERROR_TEXT_LOGIN_PASS_ISEMPTY','Admin wachtwoord is leeg');
define('ERROR_TEXT_LOGIN_PASS_NOTEQUAL','Wachtwoorden komen niet overeen');
define('ERROR_TEXT_DB_PREFIX_NODOTS','Database Tabel-voorvoegsel mag geen van deze tekens bevatten: / of \\ of . ');
define('ERROR_TEXT_DB_HOST_ISEMPTY','DB Host is leeg');
define('ERROR_TEXT_DB_NAME_ISEMPTY','DB naam is leeg');
define('ERROR_TEXT_DB_USERNAME_ISEMPTY','DB Gebruikersnaam is leeg');
define('ERROR_TEXT_DB_PASSWORD_ISEMPTY','De database wachtwoord kan niet leeg zijn.');
define('MSG_ERROR_MODULE_INSTALL','Er hebben zich fouten voorgedaan bij het installeren van module: %s. Zie de bovenstaande melding voor meer details.');
define('MSG_ERROR_CANNOT_CONNECT_DB','Ik kon geen verbinding maken met de database, controleer alstublieft uw instellingen. de ontvangen fout: ');
define('MSG_ERROR_INNODB_NOT_ENABLED', 'MYSQL InnoDB engine is niet geïnstalleerd!. PhreeBooks heeft de transactiemogelijkheden van de InnoDB engine van MySQL nodig om correct te werken.');
define('MSG_ERROR_CONFIGURE_EXISTS','Het bestand includes/configure.php bestaat al, dit kan duiden op dat Phreedom al geïnstalleerd is. Dit bestand moet verwijderd worden om de installatie succes vol te kunnen afronden!');
define('TITLE_FINISH','Afronden - Phreedom Klein Bedrijf Oplossingen');
define('TEXT_GO_TO_COMPANY','Ga Naar Uw Bedrijf');
define('INTRO_FINISHED', '<h2>Gefeliciteerd!</h2>
<h3>U heeft PhreeBooks&trade; Accounting succesvol op uw systeem geïnstalleerd!</h3>
<h2>Volgende Stappen</h2>
<p>Er is een takenlijst aangemaakt met de belangrijkste acties die nodig zijn om de geïnstalleerde modules te gebruiken. Deze lijst verschijnt op het startpagina-dashboard van de beheerder. Aanvullende moduleconfiguratie, voorkeuren en instellingen kunnen worden aangepast via het menu Bedrijf -&gt; Module Administratie.</p>
<p>Om veiligheidsredenen moet u het configuratiebestand alleen-lezen maken. Dit bestand bevindt zich in <strong>/includes/configure.php</strong>. Als u voor de installatie volledige toegang tot de map includes heeft gegeven, kunt u deze terugzetten naar de vorige instellingen. Ook moet de map <strong>/install</strong> worden verwijderd of hernoemd om herinstallatie van de applicatie te voorkomen.</p>
<h2>Documentatie en Ondersteuning</h2>
<p>PhreeBooks bevat een ingebouwd contextgevoelig helpsysteem. Zoals bij de meeste open source applicaties is dit altijd in ontwikkeling, maar het biedt algemene begeleiding en ondersteuning.</p>
<p>Er is ook online documentatie beschikbaar op de PhreeBooks website (<a target="_blank" href="http://www.phreebooks.com">www.PhreeBooks.com</a>). Daar vindt u de meest actuele moduledocumentatie, veelgestelde vragen en ondersteuning voor aanpassingen en ontwikkelaars.</p>
<p>Tot slot biedt het gebruikersforum op de PhreeBooks website ondersteuning door de gemeenschap, waar u vragen, fouten en suggesties kunt plaatsen. Komt u er niet uit, stel dan gerust een vraag! Wij hebben een behulpzame, vriendelijke en deskundige gemeenschap die u graag verwelkomt.</p>');

?>
