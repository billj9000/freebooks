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
//  Path: /modules/shipping/pages/popup_label_image/template_main.php
//
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo TITLE . ' - ' . COMPANY_NAME; ?></title>
  <link rel="stylesheet" href="ext/bootstrap5/bootstrap.min.css">
  <link rel="stylesheet" href="ext/bootstrap5/freebooks.css">
</head>
<body class="p-3">
  <div class="d-flex justify-content-center">
    <?php if ($image): ?>
      <div class="alert alert-warning"><?php echo $image; ?></div>
    <?php else: ?>
      <?php echo html_form('download', FILENAME_DEFAULT, gen_get_all_get_params(array('action')) . '&action=download'); ?>
      <div class="d-flex flex-column align-items-center gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><?php echo SHIPPING_TEXT_DOWNLOAD; ?></button>
        <div class="text-muted small"><?php echo SHIPPING_THERMAL_INST; ?></div>
      </div>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
