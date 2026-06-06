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
//  Path: /modules/phreeform/pages/main/tab_report.php
//

$docbar = new toolbar;
$docbar->icon_list['cancel']['show'] = false;
$docbar->icon_list['open']['params'] = 'onclick="ReportGenPopup(' . $id . ', \'' . $doc_details->fields['doc_ext'] . '\')"';
$docbar->icon_list['save']['show']   = false;
$docbar->icon_list['print']['show']  = false;
if ($security_level > 2) $docbar->add_icon('edit',   'onclick="ReportPopup(\'design\', ' . $id . ')"', $order = 11);
if ($security_level > 2) $docbar->add_icon('rename', 'onclick="ReportPopup(\'rename\', ' . $id . ')"', $order = 12);
if ($security_level > 1) $docbar->add_icon('copy',   'onclick="ReportPopup(\'copy\', '   . $id . ')"', $order = 13);
if ($security_level > 1) $docbar->add_icon('export', 'onclick="ReportPopup(\'export\', ' . $id . ')"', $order = 15);
if ($security_level > 3) {
  $docbar->icon_list['delete']['params'] = 'onclick="if (confirm(\'' . PHREEFORM_DELETE_DOCUMENT . '\')) docAction(\'delete\')"';
} else {
  $docbar->icon_list['delete']['show']   = false;
}

$fieldset_content  = NULL;
$fieldset_content .= $docbar->build_toolbar() . chr(10);
$fieldset_content .= '<h1>' . TEXT_REPORT . ': ' . $doc_details->fields['doc_title'] . '</h1>';
$fieldset_content .= '<div class="fb-card mb-2">' . chr(10);
$fieldset_content .= '<div class="card-header py-1 fb-widget-header">' . TEXT_PROPERTIES . '</div>' . chr(10);
$fieldset_content .= '<div class="card-body p-2">' . chr(10);
$fieldset_content .= '<table class="table table-borderless table-sm mb-0" style="width:auto"><tbody>' . chr(10);
$fieldset_content .= '<tr><td>' . TEXT_TYPE        . '</td><td>' . $phreeformTypes[$doc_details->fields['doc_ext']]          . '</td></tr>' . chr(10);
$fieldset_content .= '<tr><td>' . TEXT_GROUP       . '</td><td>' . $groups['reports'][$doc_details->fields['doc_group']]     . '</td></tr>' . chr(10);
$fieldset_content .= '<tr><td>' . TEXT_CREATE_DATE . '</td><td>' . gen_locale_date($doc_details->fields['create_date'])      . '</td></tr>' . chr(10);
$fieldset_content .= '<tr><td>' . TEXT_LAST_UPDATE . '</td><td>' . gen_locale_date($doc_details->fields['last_update'])      . '</td></tr>' . chr(10);
$fieldset_content .= '</tbody></table>' . chr(10);
$fieldset_content .= '</div></div>' . chr(10);
?>
