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
//  Path: /modules/phreebooks/pages/popup_cvv/template_main.php
//
?>
<h1><?php echo HEADING_CVV; ?></h1>
<div class="d-flex justify-content-center">
  <div class="d-inline-flex flex-column gap-2" style="max-width:500px">

    <div class="card fb-card">
      <div class="card-header py-1 fb-widget-header">Visa / Mastercard / Discover</div>
      <div class="card-body py-2">
        <?php echo sprintf(TEXT_CVV_HELP1, '<div class="mt-2">' . html_image(DIR_WS_IMAGES . 'cvv2visa.gif') . '</div>'); ?>
      </div>
    </div>

    <div class="card fb-card">
      <div class="card-header py-1 fb-widget-header">American Express</div>
      <div class="card-body py-2">
        <?php echo sprintf(TEXT_CVV_HELP2, '<div class="mt-2">' . html_image(DIR_WS_IMAGES . 'cvv2amex.gif') . '</div>'); ?>
      </div>
    </div>

    <div class="text-center">
      <button type="button" class="btn btn-primary btn-sm" onclick="window.close()">Close</button>
    </div>

  </div>
</div>
