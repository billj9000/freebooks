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
// PDF or a CSV with its own Content-Disposition, so it must reach the browser as
// a download rather than be fetched into the page.
//
// One delegated listener rather than an onclick per button. The URL lives in a
// data attribute, where HTML escaping is enough; putting it in an inline handler
// means it has to survive PHP quoting AND HTML attribute quoting at once, and
// the first double quote silently ends the attribute — a button that highlights
// and does nothing.
document.addEventListener('click', function (event) {
  const button = event.target.closest('.fb-run');
  if (!button) return;

  const dates  = document.getElementById('forms_dates');
  const period = document.getElementById('forms_period');
  const gl     = document.getElementById('forms_gl');

  if (button.dataset.needsGl === '1' && (!gl || !gl.value)) {
    alert(<?php echo json_encode(FORMS_ERROR_GL_REQUIRED); ?>);
    if (gl) gl.focus();
    return;
  }

  const url = new URL(button.dataset.url, window.location.href);
  url.searchParams.set('format', button.dataset.format);
  if (dates  && dates.value)  url.searchParams.set('dates',  dates.value);
  if (period && period.value) url.searchParams.set('period', period.value);
  if (gl     && gl.value)     url.searchParams.set('gl_account', gl.value);
  window.location.href = url.toString();
});

// Emailing is a POST, not a navigation: the message travels with the request.
// The run options are read from the same three selects, so a report emailed and
// the same report downloaded cover the same period — reading them here rather
// than duplicating them inside the email card is what keeps that true.
document.addEventListener('click', function (event) {
  const button = event.target.closest('.fb-email');
  if (!button) return;

  const form = document.getElementById('forms_run_form');
  const to   = document.getElementById('to_email');
  const gl   = document.getElementById('forms_gl');
  if (!form) return;

  if (button.dataset.needsGl === '1' && (!gl || !gl.value)) {
    alert(<?php echo json_encode(FORMS_ERROR_GL_REQUIRED); ?>);
    if (gl) gl.focus();
    return;
  }
  // Asked for before the report is built, and the panel opened so the field is
  // actually reachable — a validation message pointing at a collapsed box is a
  // dead end.
  if (!to || to.value.trim() === '') {
    const details = document.getElementById('forms_email_details');
    if (details) details.classList.add('show');
    alert(<?php echo json_encode(FORMS_PRINT_EMAIL_NO_ADDRESS); ?>);
    if (to) to.focus();
    return;
  }

  document.getElementById('forms_doc_id').value = button.dataset.docId;
  document.getElementById('forms_format').value = 'pdf';
  ['forms_dates', 'forms_period', 'forms_gl'].forEach(function (id) {
    const source = document.getElementById(id);
    if (!source || !source.value) return;
    // The selects are OUTSIDE nothing — they are in the same form — but they
    // are named for the page, not for the request, so the request names are
    // added as hidden fields rather than renaming the controls.
    const name = id === 'forms_gl' ? 'gl_account' : id.replace('forms_', '');
    let hidden = form.querySelector('input[type=hidden][name="' + name + '"]');
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = name;
      form.appendChild(hidden);
    }
    hidden.value = source.value;
  });
  form.submit();
});
</script>
