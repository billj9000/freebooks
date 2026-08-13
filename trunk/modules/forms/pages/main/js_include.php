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
//  Path: /modules/forms/pages/main/js_include.php
//
?>
<script>
// Running a document is a plain navigation, not an htmx swap: the response is a
// PDF or a CSV with its own Content-Disposition, so it must reach the browser
// as a download rather than be fetched into the page. htmx drives the parts of
// this module that DO swap markup.
function formsRun(base, format, needsGlAccount) {
  const dates  = document.getElementById('forms_dates');
  const period = document.getElementById('forms_period');
  const gl     = document.getElementById('forms_gl');

  if (needsGlAccount && (!gl || !gl.value)) {
    alert(<?php echo json_encode(FORMS_ERROR_GL_REQUIRED); ?>);
    if (gl) gl.focus();
    return;
  }

  const url = new URL(base, window.location.href);
  url.searchParams.set('format', format);
  if (dates  && dates.value)  url.searchParams.set('dates',  dates.value);
  if (period && period.value) url.searchParams.set('period', period.value);
  if (gl     && gl.value)     url.searchParams.set('gl_account', gl.value);
  window.location.href = url.toString();
}
</script>
