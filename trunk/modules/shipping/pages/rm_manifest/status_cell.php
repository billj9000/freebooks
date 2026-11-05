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
//  Path: /modules/shipping/pages/rm_manifest/status_cell.php
//
/**
 * The status cell of one manifest row. A waiting manifest's cell asks the page again
 * every few seconds and is replaced by the answer, until the PDF is ready or the
 * manifest has failed.
 */
function rm_manifest_status_cell(array $m) {
  $id   = htmlspecialchars($m['id'], ENT_QUOTES);
  $date = htmlspecialchars($m['date'], ENT_QUOTES);
  switch ($m['state']) {
    case 'ready':
      $url = html_href_link(FILENAME_DEFAULT, 'module=shipping&page=popup_label_viewer&method=royal_mail&date='
        . $m['date'] . '&labels=manifest-' . $m['id'], 'SSL');
      return '<td id="rm-manifest-' . $id . '"><button type="button" class="btn btn-primary btn-sm" onclick="window.open(\''
        . htmlspecialchars($url, ENT_QUOTES) . '\',\'rm_manifest_pdf\',\'width=800,height=700,resizable=1,scrollbars=1\')">'
        . RM_MANIFEST_PRINT . '</button></td>';
    case 'failed':
      return '<td id="rm-manifest-' . $id . '"><span class="text-danger">' . RM_MANIFEST_FAILED . '</span>'
        . ($m['error'] !== '' ? ' <small class="text-muted">' . RM_MANIFEST_ERROR_REF . ' <code>'
          . htmlspecialchars($m['error']) . '</code></small>' : '')
        . ' <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="rmManifestRetry(\'' . $id . '\',\'' . $date . '\')">'
        . RM_MANIFEST_RETRY . '</button></td>';
    default:
      $url = html_href_link(FILENAME_DEFAULT, 'module=shipping&page=rm_manifest&action=status&id=' . $m['id'] . '&date=' . $m['date'], 'SSL');
      return '<td id="rm-manifest-' . $id . '" hx-get="' . htmlspecialchars($url, ENT_QUOTES)
        . '" hx-trigger="load delay:5s" hx-swap="outerHTML"><span class="spinner-border spinner-border-sm me-1" role="status"></span>'
        . RM_MANIFEST_PREPARING . '</td>';
  }
}
