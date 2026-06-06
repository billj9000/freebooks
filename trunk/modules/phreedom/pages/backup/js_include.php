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
//  Path: /modules/phreedom/pages/backup/js_include.php
//
?>
<script type="text/javascript">
function init() {}
function check_form() { return true; }

var restoreActive = false;

// Start importing a plain SQL / gz / bz2 / zip (DB-only) file.
function startRestore(fn, foffset, totalqueries, linenumber) {
  if (restoreActive) return;
  restoreActive = true;
  document.getElementById('restore_progress').style.display = '';
  document.getElementById('restore_filename').innerHTML = '<?php echo addslashes(TEXT_PROCESSING_FILE); ?>' + fn;
  document.getElementById('restore_status').innerHTML = '';
  doChunk(fn, foffset, totalqueries, linenumber);
}

// Extract a full backup archive then import the SQL it contains.
function startFullRestore(fn) {
  if (restoreActive) return;
  restoreActive = true;
  document.getElementById('restore_progress').style.display = '';
  document.getElementById('restore_filename').innerHTML = '<?php echo addslashes(GEN_RESTORE_EXTRACTING); ?>';
  document.getElementById('restore_status').innerHTML = '';

  var url = 'index.php?module=<?php echo $module; ?>&page=ajax&op=restore_extract'
          + '&fn=' + encodeURIComponent(fn);
  $.getJSON(url, function(r) {
    if (r.status !== 'ok') {
      restoreFailed('<?php echo addslashes(GEN_RESTORE_ERROR); ?>' + (r.message || ''));
      return;
    }
    document.getElementById('restore_filename').innerHTML =
      '<?php echo addslashes(TEXT_PROCESSING_FILE); ?>' + r.sql_file;
    doChunk(r.sql_file, 0, 0, 1);
  }).fail(function() {
    restoreFailed('<?php echo addslashes(GEN_RESTORE_ERROR); ?>Network error during extraction.');
  });
}

// Send one chunk request and loop until done.
function doChunk(fn, foffset, totalqueries, linenumber) {
  var url = 'index.php?module=<?php echo $module; ?>&page=ajax&op=restore_chunk'
          + '&fn='           + encodeURIComponent(fn)
          + '&foffset='      + foffset
          + '&totalqueries=' + totalqueries
          + '&start='        + linenumber;

  $.getJSON(url, function(r) {
    if (r.status === 'error') {
      restoreFailed('<?php echo addslashes(GEN_RESTORE_ERROR); ?>' + (r.message || ''));
      return;
    }
    // Update stats
    document.getElementById('restore_queries').innerHTML = r.totalqueries;
    document.getElementById('restore_pct').innerHTML     = r.pct + '%';
    document.getElementById('restore_lines').innerHTML   = r.linenumber;
    document.getElementById('restore_pct_bar').style.width = r.pct + '%';

    if (r.status === 'done') {
      restoreComplete();
    } else {
      setTimeout(function() {
        doChunk(fn, r.foffset, r.totalqueries, r.linenumber);
      }, 100);
    }
  }).fail(function() {
    restoreFailed('<?php echo addslashes(GEN_RESTORE_ERROR); ?>Network error during import.');
  });
}

function restoreComplete() {
  document.getElementById('restore_pct_bar').style.width = '100%';
  document.getElementById('restore_pct').innerHTML = '100%';
  var msg = '<?php echo addslashes(GEN_RESTORE_DONE); ?>';
  document.getElementById('restore_status').innerHTML =
    '<strong>' + msg + '</strong>' +
    ' &nbsp;<a href="index.php?module=phreedom&page=main&action=logout"><?php echo addslashes(TEXT_LOGOUT); ?></a>';
  restoreActive = false;
}

function restoreFailed(msg) {
  document.getElementById('restore_status').innerHTML = '<span style="color:red">' + msg + '</span>';
  restoreActive = false;
}
</script>
