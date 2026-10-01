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
//  Path: /modules/phreedom/pages/backup/template_restore.php
//
$upload_url = gen_get_all_get_params(array('action')) . '&action=upload_restore';
echo html_form('restore', FILENAME_DEFAULT, $upload_url, 'post', 'enctype="multipart/form-data"') . chr(10);

$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('01');
echo $toolbar->build_toolbar();

echo '<h1>' . BOX_HEADING_RESTORE . '</h1>';
?>

<div id="restore_progress" style="display:none">
  <div class="fb-fieldset mb-3">
    <div class="fb-fieldset-legend"><?php echo GEN_RESTORE_PROGRESS; ?></div>
    <p id="restore_filename" class="fw-bold mb-2"></p>
    <div style="width:100%;background:#ddd;border:1px solid #999;height:18px;margin:6px 0">
      <div id="restore_pct_bar" style="height:18px;background:#000080;width:0%;transition:width 0.3s"></div>
    </div>
    <table class="pkg-table table table-borderless table-sm mb-2" style="width:auto">
      <thead class="table-dark">
        <tr>
          <th><?php echo TEXT_QUERIES; ?></th>
          <th><?php echo TEXT_PERCENT; ?></th>
          <th><?php echo TEXT_LINES; ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td id="restore_queries" class="text-center">0</td>
          <td id="restore_pct" class="text-center">0%</td>
          <td id="restore_lines" class="text-center">0</td>
        </tr>
      </tbody>
    </table>
    <p id="restore_status"></p>
  </div>
</div>

<div class="fb-fieldset mb-3">
  <div class="fb-fieldset-legend"><?php echo BOX_HEADING_RESTORE; ?></div>
<?php
$backups_dir = DIR_FS_MY_FILES . 'backups/';
$files = is_dir($backups_dir) ? scandir($backups_dir) : array();
usort($files, function($a, $b) use ($backups_dir) {
  if ($a === '.' || $a === '..') return 1;
  if ($b === '.' || $b === '..') return -1;
  return filemtime($backups_dir . $b) - filemtime($backups_dir . $a);
});
$has_files = false;
foreach ($files as $f) {
  if ($f === '.' || $f === '..') continue;
  if (preg_match('/\.(sql|gz|bz2|zip|csv)$/i', $f)) { $has_files = true; break; }
}
if (!$has_files) {
  echo '<p>' . BIGDUMP_NO_FILES . '</p>';
} else {
?>
  <table class="pkg-table table table-hover table-sm mb-0">
    <thead class="table-dark">
      <tr>
        <th><?php echo TEXT_FILENAME; ?></th>
        <th><?php echo TEXT_SIZE; ?></th>
        <th><?php echo TEXT_DATE; ?></th>
        <th><?php echo TEXT_TYPE; ?></th>
        <th colspan="2">&nbsp;</th>
      </tr>
    </thead>
    <tbody>
<?php
foreach ($files as $f) {
  if ($f === '.' || $f === '..') continue;
  if (!preg_match('/\.(sql|gz|bz2|zip|csv)$/i', $f)) continue;
  $filepath  = $backups_dir . $f;
  $fsize     = filesize($filepath);
  $fsize_str = $fsize > 1048576 ? round($fsize/1048576, 1) . ' MB' : round($fsize/1024, 1) . ' KB';
  $fdate     = date('Y-m-d H:i', filemtime($filepath));
  $is_full   = (bool)preg_match('/^bu-/i', $f);

  if (preg_match('/\.sql$/i', $f))     $type = 'SQL';
  elseif (preg_match('/\.gz$/i', $f))  $type = 'GZip';
  elseif (preg_match('/\.bz2$/i', $f)) $type = 'BZip2';
  elseif (preg_match('/\.zip$/i', $f)) $type = 'ZIP';
  else                                  $type = 'Other';

  $fn_js  = htmlspecialchars(json_encode($f), ENT_QUOTES);
  // Deleting is a POST (submitPost), so index.php checks its csrf_token.
  $del_url = html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'fn')) . '&action=delete_restore_file', 'SSL');

  echo "<tr>\n";
  echo "  <td>" . htmlspecialchars($f) . "</td>\n";
  echo "  <td>$fsize_str</td>\n";
  echo "  <td>$fdate</td>\n";
  echo "  <td>$type</td>\n";
  if ($is_full) {
    echo "  <td><button type=\"button\" class=\"btn btn-primary btn-sm\" onclick=\"if(confirm(" . htmlspecialchars(json_encode(GEN_RESTORE_CONFIRM_FULL), ENT_QUOTES) . ")) startFullRestore($fn_js)\">" . GEN_RESTORE_FULL_BUTTON . "</button></td>\n";
  } else {
    echo "  <td><button type=\"button\" class=\"btn btn-primary btn-sm\" onclick=\"startRestore($fn_js, 0, 0, 1)\">" . GEN_RESTORE_DB_BUTTON . "</button></td>\n";
  }
  echo "  <td><a href=\"#\" onclick=\"if (confirm(" . htmlspecialchars(json_encode(TEXT_DELETE . '?'), ENT_QUOTES) . ")) submitPost("
     . htmlspecialchars(json_encode(str_replace('&amp;', '&', $del_url)), ENT_QUOTES) . ", {fn: $fn_js}); return false;\">" . BIGDUMP_DEL_FILE . "</a></td>\n";
  echo "</tr>\n";
}
?>
    </tbody>
  </table>
<?php } ?>
</div>

<div class="fb-fieldset mb-3">
  <div class="fb-fieldset-legend"><?php echo GEN_RESTORE_UPLOAD_LABEL; ?></div>
<?php
$tempfile = @tempnam($backups_dir, 'chk');
if ($tempfile) {
  @unlink($tempfile);
  $max_bytes = ini_get('upload_max_filesize');
  preg_match('/([0-9]+)([KMG]?)/i', $max_bytes, $m);
  $mult = array('K'=>1024,'M'=>1048576,'G'=>1073741824);
  $max_int = (int)$m[1] * ($mult[strtoupper($m[2] ?? '')] ?? 1);
?>
  <p><?php printf(BIGDUMP_UPLOAD_C, $max_bytes, round($max_int/1048576, 1)); ?><?php echo BIGDUMP_UPLOAD_D . $backups_dir; ?></p>
  <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $max_int; ?>" />
  <p><?php echo GEN_RESTORE_UPLOAD_LABEL; ?>:
    <input type="file" name="dumpfile" accept=".sql,.gz,.bz2,.zip,.csv" size="60" />
    &nbsp;<button type="button" class="btn btn-primary btn-sm" onclick="document.restore.submit()">Upload</button>
  </p>
<?php } else { ?>
  <p><?php printf(BIGDUMP_UPLOAD_A, $backups_dir); ?><?php echo BIGDUMP_UPLOAD_B . $backups_dir; ?></p>
<?php } ?>
</div>
</form>
