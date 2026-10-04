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
//  Path: /modules/forms/pages/print/js_include.php
//
// template_index.php die()s outright if this file is missing, so it exists even
// when a page has little to say.
?>
<script>
// A form submit rather than an htmx swap: the response to 'view' or 'download'
// is a PDF with its own Content-Disposition, which has to reach the browser as
// a document and not be fetched into the page. 'email' posts the address fields
// alongside, which is the other reason this is a form and not a link.
function formsPrint(action) {
  const form = document.forms['forms_print'];
  if (!form) return;
  form.action.value = action;
  if (action === 'view') form.target = '_blank';   // keep the popup on screen
  else                   form.target = '_self';
  form.submit();
}

// Picking a different form out of the group.
//
// Answered by the page rather than by a fragment of it, because more changes
// than the name: the records the form selects come from its own plan, whether
// it can run at all comes from the definition, and the email subject and
// message are the definition's words. Re-rendering costs one small request and
// is the same code that drew the page on arrival, so the two cannot disagree.
//
// target is set back to _self explicitly: a View earlier in this page's life
// left it on _blank, and reselecting would then open the popup's own reload in
// a tab.
function formsPick() {
  const form = document.forms['forms_print'];
  if (!form) return;
  form.action.value = 'reselect';
  form.target = '_self';
  form.submit();
}

// &autoprint=1 — show the document without a click.
//
// Square Terminal sends it: the browser is already on the payment screen and
// the till operator wants the receipt, not a dialog. pre_process.php has
// already resolved the single form in the group, so this only has to press the
// button.
//
// The View button's presence IS the readiness test, since template_main.php
// draws it only once a form is resolved and records selected. Into _self rather
// than a new tab, so the popup that opened for this becomes the viewer instead
// of spawning a second window.
//
// A group holding several forms is refused outright. The page arrives with the
// first of them chosen so that an ATTENDED print is one click - but nobody is
// watching this one, and the person who would have seen which form was selected
// is not there to see it. Printing whichever form sorts first, unasked, is worse
// than printing nothing; alphabetical order is not a decision anyone made.
// Counting the options is how that is known here, because by this point the page
// does not say how large the group was.
document.addEventListener('DOMContentLoaded', function() {
  if (new URLSearchParams(window.location.search).get('autoprint') !== '1') return;
  if (document.querySelectorAll('input[type="radio"][name="rID"]').length > 1) return;
  if (!document.getElementById('forms_print_view')) return;
  const form = document.forms['forms_print'];
  if (!form) return;
  form.action.value = 'view';
  form.target = '_self';
  form.submit();
});
</script>
