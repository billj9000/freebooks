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
//  Path: /modules/phreehelp/pages/main/template_left.php
//
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo TITLE_TOP_FRAME; ?></title>
  <link rel="stylesheet" href="<?php echo DIR_WS_ADMIN; ?>ext/bootstrap5/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo DIR_WS_ADMIN; ?>ext/bootstrap5/freebooks.css">
  <link rel="shortcut icon" type="image/ico" href="favicon.ico" />
  <script>
    var icon_path = '<?php echo DIR_WS_ICONS; ?>';
  </script>
  <script src="<?php echo DIR_WS_ADMIN; ?>ext/bootstrap5/bootstrap.bundle.min.js" defer></script>
  <?php require_once(DIR_FS_WORKING . 'pages/' . $page . '/js_include.php'); ?>
</head>

<body>
<ul class="nav nav-tabs fb-tabs" id="helptabs">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab_contents"><?php echo HEADING_CONTENTS; ?></a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_index"><?php echo HEADING_INDEX; ?></a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_search"><?php echo TEXT_SEARCH; ?></a></li>
</ul>
<div class="tab-content fb-tab-content">
  <div class="tab-pane show active" id="tab_contents">
    <?php echo retrieve_toc(); ?>
  </div>
  <div class="tab-pane" id="tab_index">
    <?php echo retrieve_index(); ?>
  </div>
  <div class="tab-pane" id="tab_search">
    <p class="mb-1"><?php echo TEXT_KEYWORD; ?></p>
    <?php echo html_form('search_form', FILENAME_DEFAULT, 'module=phreehelp&amp;page=main&amp;fID=left'); ?>
      <?php echo html_input_field('search_text', $search_text, 'class="form-control form-control-sm mb-1"'); ?>
      <?php echo html_icon('actions/system-search.png', TEXT_SEARCH, 'small', 'style="cursor:pointer;" onclick="document.search_form.submit()"') . "\n"; ?>
    </form>
    <?php if ($search_text) { ?>
      <p class="mt-2 mb-1"><?php echo TEXT_SEARCH_RESULTS; ?></p>
      <?php echo search_results($search_text); ?>
    <?php } ?>
  </div>
</div>
</body>
</html>
