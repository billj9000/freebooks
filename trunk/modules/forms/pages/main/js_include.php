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
// EVERYTHING here waits for the document, and that is not caution — it is the
// only thing that works. template_index.php emits js_include.php inside the
// <HEAD>, before <body> and long before the page's own markup. A getElementById
// at the top level of this file therefore returns null every time, and the first
// guard that tests one bails out of the whole script: every button on the page
// highlights and does nothing. The version before this one delegated on
// `document`, which exists in the head, and so never met this.
//
// Bootstrap is loaded with `defer`, and deferred scripts are guaranteed to have
// run by DOMContentLoaded — so `bootstrap` is safe to name in here too.
document.addEventListener('DOMContentLoaded', function () {
'use strict';

// Running a document is a plain navigation, not an htmx swap: the response is a
// PDF or a CSV with its own Content-Disposition, so it must reach the browser as
// a download rather than be fetched into the page.
//
// One delegated listener rather than an onclick per button. The URL lives in a
// data attribute, where HTML escaping is enough; putting it in an inline handler
// means it has to survive PHP quoting AND HTML attribute quoting at once, and
// the first double quote silently ends the attribute — a button that highlights
// and does nothing.

const TEXT = {
  runTitle:   <?php echo json_encode(FORMS_RUN_DIALOG_TITLE); ?>,
  asSet:      <?php echo json_encode(FORMS_OPT_AS_SET); ?>,
  asSetNamed: <?php echo json_encode(FORMS_OPT_AS_SET_NAMED); ?>
};

const dialog  = document.getElementById('forms_run_dialog');
const table   = document.getElementById('forms_documents');
const form    = document.getElementById('forms_run_form');
if (!dialog || !table || !form) return;

const dates   = document.getElementById('dates');
const period  = document.getElementById('period');
const gl      = document.getElementById('gl_account');
const toEmail = document.getElementById('to_email');
const subject = document.getElementById('subject');

// The row the dialog was opened from. Everything the dialog does — which
// document, which answers apply to it — is read from here, so it is the one
// piece of state the page keeps.
let current = null;

/*******************  the run dialog  *********************************/

function show(element, visible) {
  // classList, not style.display: a Bootstrap display utility carries
  // !important and would win over the inline style, leaving a control that
  // cannot be hidden.
  if (element) element.classList.toggle('d-none', !visible);
}

function invalid(field, is) {
  if (!field) return;
  field.classList.toggle('is-invalid', is);
  if (is) field.focus();
}

function clearValidation() {
  invalid(gl, false);
  invalid(toEmail, false);
}

/** Fill the dialog in from a row, and open it. */
function openDialog(row, emailing) {
  current = row;
  clearValidation();

  document.getElementById('forms_run_dialog_title').textContent =
      TEXT.runTitle.replace('%s', row.dataset.docTitle || '');

  const needsDates  = row.dataset.needsDates  === '1';
  const needsPeriod = row.dataset.needsPeriod === '1';
  const needsGl     = row.dataset.needsGl     === '1';

  // Only the questions this document has an answer for. A report with no date
  // field runs identically whatever a date range says, and a control that
  // changes nothing is worse than no control at all.
  show(document.getElementById('forms_dates_wrap'),  needsDates);
  show(document.getElementById('forms_period_wrap'), needsPeriod);
  show(document.getElementById('forms_gl_wrap'),     needsGl);

  // A required answer with no possible value is a dead end: the field is
  // mandatory, the list is empty, and no amount of trying gets past it. That
  // happens on a company with no cash or bank accounts, where there is nothing
  // to reconcile in the first place — so the dialog says that, and refuses the
  // run rather than demanding an answer that does not exist.
  const stuck = needsGl && (!gl || gl.options.length === 0);
  show(document.getElementById('forms_gl_none'), stuck);
  if (gl) gl.disabled = stuck;
  dialog.querySelectorAll('.fb-dialog-run, #forms_dialog_email').forEach(function (button) {
    button.disabled = stuck;
  });
  show(document.getElementById('forms_options_wrap'), needsDates || needsPeriod || needsGl);
  show(document.getElementById('forms_email_wrap'),   emailing);
  show(document.getElementById('forms_dialog_email'), emailing);
  dialog.querySelectorAll('.fb-dialog-run').forEach(function (button) {
    show(button, !emailing);
  });

  if (dates) {
    // Back to "as the report is set" each time the dialog opens, named with the
    // preference the definition actually holds — so the choice on offer is
    // "leave it as drawn" rather than a range this page picked.
    dates.value = '';
    const asSet = row.dataset.datesAsSet || '';
    dates.options[0].text = asSet
        ? TEXT.asSetNamed.replace('%s', asSet)
        : TEXT.asSet;
  }
  // The period the definition stores, or the current one where it stores none.
  // Unlike the date range there is nothing to fall back on: the structural
  // reports cannot do anything with period 0.
  if (period && row.dataset.period) period.value = row.dataset.period;
  if (subject) subject.placeholder = row.dataset.docTitle || '';

  bootstrap.Modal.getOrCreateInstance(dialog).show();
}

/** Navigate to the run URL, carrying whatever answers the dialog holds. */
function run(row, format, fromDialog) {
  const url = new URL(row.dataset.url, window.location.href);
  url.searchParams.set('format', format);
  if (fromDialog) {
    // Only what was asked for. Sending an answer the document does not use
    // would be harmless; sending one it DOES use, that the user never saw, is
    // how a report drawn as "this quarter" came to run over all time.
    if (row.dataset.needsDates  === '1' && dates  && dates.value)  url.searchParams.set('dates', dates.value);
    if (row.dataset.needsPeriod === '1' && period && period.value) url.searchParams.set('period', period.value);
    if (row.dataset.needsGl     === '1' && gl     && gl.value)     url.searchParams.set('gl_account', gl.value);
  }
  window.location.href = url.toString();
}

// PDF straight from the row. It runs on what the definition already holds,
// which is the report as its author drew it — unless something has no stored
// answer at all, and then the dialog opens instead of guessing one.
table.addEventListener('click', function (event) {
  const button = event.target.closest('.fb-run');
  if (!button) return;
  const row = button.closest('tr');
  if (!row) return;
  if (row.dataset.mustAsk === '1') { openDialog(row, false); return; }
  run(row, button.dataset.format, false);
});

table.addEventListener('click', function (event) {
  const button = event.target.closest('.fb-options');
  if (!button) return;
  const row = button.closest('tr');
  if (row) openDialog(row, button.dataset.email === '1');
});

dialog.addEventListener('click', function (event) {
  const button = event.target.closest('.fb-dialog-run');
  if (!button || !current) return;
  if (current.dataset.needsGl === '1' && (!gl || !gl.value)) { invalid(gl, true); return; }
  run(current, button.dataset.format, true);
});

// Emailing is a POST, not a navigation: the message travels with the request.
// The answers come from the same controls the download uses, so a report
// emailed and the same report downloaded cover the same ground.
document.getElementById('forms_dialog_email').addEventListener('click', function () {
  if (!current) return;
  if (current.dataset.needsGl === '1' && (!gl || !gl.value)) { invalid(gl, true); return; }
  if (!toEmail || toEmail.value.trim() === '') { invalid(toEmail, true); return; }
  document.getElementById('forms_doc_id').value = current.dataset.docId;
  document.getElementById('forms_format').value = 'pdf';
  form.submit();
});

[dates, period, gl, toEmail].forEach(function (field) {
  if (field) field.addEventListener('change', function () { invalid(field, false); });
});
if (toEmail) toEmail.addEventListener('input', function () { invalid(toEmail, false); });

// A refused send comes back as a redrawn page with the message still typed into
// it. Reopening the dialog on the document it was for is what makes that true
// from the user's side rather than only in the markup.
<?php if (!empty($forms_email['reopen']) && (int)$forms_email['docID'] > 0) { ?>
const refused = table.querySelector('tr[data-doc-id="<?php echo (int)$forms_email['docID']; ?>"]');
if (refused) openDialog(refused, true);
<?php } ?>

/*******************  filtering and folders  **************************/

const filter    = document.getElementById('forms_filter');
const noMatch   = document.getElementById('forms_no_match');
const collapsed = new Set();

function apply() {
  const term = filter ? filter.value.trim().toLowerCase() : '';
  const populated = new Set();

  table.querySelectorAll('tr.fb-forms-row').forEach(function (row) {
    const matches = term === ''
        || (row.dataset.search || '').toLowerCase().indexOf(term) !== -1;
    if (matches) populated.add(row.dataset.folder);
    // A collapsed folder hides its rows, but a search looks THROUGH it: typing
    // a name and being told nothing matches, because the folder holding it
    // happens to be shut, is the kind of answer that costs an afternoon.
    show(row, matches && (term !== '' || !collapsed.has(row.dataset.folder)));
  });

  table.querySelectorAll('tr.fb-forms-folder').forEach(function (heading) {
    const folder = heading.dataset.folder;
    show(heading, populated.has(folder));
    const caret = heading.querySelector('.fb-folder-caret');
    const open  = term !== '' || !collapsed.has(folder);
    if (caret) caret.innerHTML = open ? '&#9662;' : '&#9656;';
    const toggle = heading.querySelector('.fb-folder-toggle');
    if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  show(noMatch, populated.size === 0);
}

if (filter) {
  filter.addEventListener('input', apply);
  // A search box's clear button fires 'search', not 'input', in Safari.
  filter.addEventListener('search', apply);
}

table.addEventListener('click', function (event) {
  const toggle = event.target.closest('.fb-folder-toggle');
  if (!toggle) return;
  const folder = toggle.closest('tr').dataset.folder;
  if (collapsed.has(folder)) collapsed.delete(folder); else collapsed.add(folder);
  apply();
});

apply();
});

</script>
<?php
// A block of its OWN, outside whatever this page's script is — the
// canvas's is a module, and a function declared in a module is not
// global, so an inline handler could never see one (§13.29).
forms_document_actions_script();
