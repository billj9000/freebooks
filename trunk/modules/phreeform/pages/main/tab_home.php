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
//  Path: /modules/phreeform/pages/main/tab_home.php
//

$fieldset_content = '';
$fieldset_content .= '<div class="row">' . chr(10);
$fieldset_content .= '<div class="col-md-6">' . chr(10);
$fieldset_content .= '<div class="fb-card mb-2">' . chr(10);
$fieldset_content .= '<div class="card-header py-1 fb-widget-header">' . TEXT_MY_REPORTS . '</div>' . chr(10);
$fieldset_content .= '<div class="card-body p-2">' . load_my_reports() . '</div>' . chr(10);
$fieldset_content .= '</div></div>' . chr(10);
$fieldset_content .= '<div class="col-md-6">' . chr(10);
$fieldset_content .= '<div class="fb-card mb-2">' . chr(10);
$fieldset_content .= '<div class="card-header py-1 fb-widget-header">' . TEXT_RECENTLY_ADDED . '</div>' . chr(10);
$fieldset_content .= '<div class="card-body p-2">' . load_recently_added() . '</div>' . chr(10);
$fieldset_content .= '</div></div>' . chr(10);
$fieldset_content .= '</div>' . chr(10);
?>
