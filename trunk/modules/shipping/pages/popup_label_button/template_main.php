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
//  Path: /modules/shipping/pages/popup_label_button/template_main.php
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
  <div class="text-center">
    <button type="button" class="btn btn-primary btn-sm"
            onclick="parent.opener.location.reload(); parent.close()">
      <?php echo TEXT_CLOSE; ?>
    </button>
  </div>
</body>
</html>
