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
//  Path: /modules/doc_ctl/language/nl_nl/language.php
//
// General
define('TEXT_BOOKMARK_DOC','Document als bladwijzer opslaan');
define('TEXT_BOOKMARKED','Bladwijzer');
define('TEXT_CANCEL_CHECKOUT','Uitcheckmarkering annuleren');
define('TEXT_CHECKED_OUT','Uitgecheckt');
define('TEXT_CHECKOUT_DOC','Document uitchecken');
define('TEXT_CREATE_DATE','Aanmaakdatum');
if (!defined('TEXT_DETAILS')) define('TEXT_DETAILS','Details');
define('TEXT_DOCUMENT','Document: ');
define('TEXT_DOCUMENT_TITLE','Inhoud van: ');
define('TEXT_DOCUMENTS','Documentenlijst');
define('TEXT_DOWNLOAD_DOCUMENT','Document downloaden');
if (!defined('TEXT_FILENAME')) define('TEXT_FILENAME','Bestandsnaam');
define('TEXT_IMG_NOT_AVAILABLE','Afbeelding niet beschikbaar');
define('TEXT_LAST_UPDATE','Laatst bijgewerkt');
define('TEXT_LAST_VIEW','Laatst bekeken');
define('TEXT_LOCK_DOC','Document vergrendelen');
define('TEXT_LOCKED','Vergrendeld');
define('TEXT_MY_BOOKMARKS','Mijn bladwijzers');
define('TEXT_MY_CHECKED_OUT','Uitgecheckte bestanden');
define('TEXT_NEW_FOLDER','Nieuwe map');
define('TEXT_NEW_DOCUMENT','Nieuw document');
define('TEXT_SELECT_FOLDER','Selecteer eerst een map.');
define('TEXT_NO_BOOKMARKS','Er zijn geen bladwijzers ingesteld.');
define('TEXT_NO_CHECKED_OUT','Er zijn geen uitgecheckte bestanden gevonden.');
if (!defined('TEXT_NO_DOCUMENTS')) define('TEXT_NO_DOCUMENTS','Er zijn geen documenten gevonden.');
define('TEXT_OWNER','Eigenaar');
define('TEXT_PATH','Pad');
define('TEXT_RECENTLY_ADDED','Recent toegevoegde documenten');
define('TEXT_REMOVE_BOOKMARK','Bladwijzer verwijderen');
if (!defined('TEXT_REVISION')) define('TEXT_REVISION','Revisie'); // also defined in inventory/language.php, work_orders/language.php
define('TEXT_THUMBNAIL','Miniatuur');
define('TEXT_UNLOCK_DOC','Document ontgrendelen');
define('TEXT_UPLOAD_FILE','Revisie uploaden');
// Specific to this module
define('DOC_CTL_EMPTY_FOLDER','De map bevat geen documenten.');
define('DOC_CTL_FILE_WRITE_ERROR','Er is een fout opgetreden bij het schrijven van het bestand. Controleer de rechten van de map (%s) en probeer het opnieuw.');
define('DOC_CTL_ITEMS', 'Mappen en documenten');
define('DOC_CTL_DELETE_DOCUMENT','Weet u zeker dat u dit document wilt verwijderen?');
define('DOC_CTL_DELETE_DIRECTORY','Weet u zeker dat u deze map wilt verwijderen?');
// Javascript defines
define('DOC_CTL_JS_DEL_BOOKMARK','Weet u zeker dat u deze bladwijzer wilt verwijderen?');
define('DOC_CTL_JS_DIR_DELETED_ERROR','De map is niet leeg en kan niet worden verwijderd!');
 
?>