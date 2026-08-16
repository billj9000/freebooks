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
//  Path: /modules/forms/ajax/canvas_image.php
//
/**
 * An image file, into the folder a form draws from.
 *
 * The canvas can point an image element at a file, and until now that file had
 * to already be there — put in place over the file system, which the person
 * laying out an invoice does not have. This is the way in.
 *
 * Answers in JSON, unlike the two ajax ops beside it: those stream a PDF into a
 * viewer, this one is called by script that has to act on the ANSWER — the name
 * the file was actually stored under, and its pixel size, neither of which the
 * browser can know because both are decided here (see ImageStore).
 *
 * ## What guards it
 *
 *   - `index.php` refuses any POST without a matching csrf_token, so this is
 *     reached only from a page of ours;
 *   - `validate_ajax_user()` proves a session, and nothing more — which is why
 *     the level check below exists. Writing a file is at least as much authority
 *     as saving a form, so it takes the same level the save takes, not the level
 *     that merely lets someone LOOK at the canvas;
 *   - everything about the file itself is ImageStore's business, in one place,
 *     because that is also the class that decides what the list contains.
 */

/**************   Check user security   *****************************/
validate_ajax_user();

/**************  include page specific files    *********************/
gen_pull_language('phreeform');
gen_pull_language('forms');

// phreeform's defaults.php is what defines PF_DIR_MY_REPORTS, and it reads the
// PF_FONT_* constants from the language file pulled above — hence that order.
// Only the one class is required: nothing here builds a document.
require_once(DIR_FS_MODULES . 'phreeform/defaults.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/image_store.php');

use FreeBooks\Forms\App\{ImageStore, ImageStoreException};

/** One shape of answer, whatever happened, so the caller has one thing to read. */
function forms_image_answer($status, array $body) {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($body);
  die;
}

// Note what validate_ajax_user() returns with no argument: 1, always, for any
// signed-in user. It proves a session and nothing else, so the level has to be
// read from the session directly — asking IT for the level would refuse
// everybody, which at least fails safe, but only by accident.
$security_level = (int)($_SESSION['admin_security'][SECURITY_ID_FORMS_AUTHOR] ?? 0);
if ($security_level < 3) {
  forms_image_answer(403, array('error' => FORMS_DESIGN_DENIED));
}

$store = new ImageStore(PF_DIR_MY_REPORTS . 'images');

// A POST that arrives with no file at all is the browser's upload limit being
// hit, or a form submitted empty. Either way there is nothing to store.
if (empty($_FILES['image'])) {
  forms_image_answer(400, array('error' => FORMS_UPLOAD_NOTHING));
}

try {
  $stored = $store->store((array)$_FILES['image']);
} catch (ImageStoreException $e) {
  // The class's own sentence: it is the only code that knows which check failed.
  forms_image_answer(400, array('error' => $e->getMessage()));
} catch (\Throwable $e) {
  forms_image_answer(500, array('error' => FORMS_UPLOAD_FAILED));
}

// The refreshed list travels with it, so the canvas can rebuild its menu from
// what the SERVER has rather than from what it believes it just added.
forms_image_answer(200, array(
  'name'   => $stored['name'],
  'width'  => $stored['width'],
  'height' => $stored['height'],
  'images' => $store->names(),
));
