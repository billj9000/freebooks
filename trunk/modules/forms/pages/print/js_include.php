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

// &autoprint=1 — show the document without a click.
//
// Square Terminal sends it: the browser is already on the payment screen and
// the till operator wants the receipt, not a dialog. PhreeForm answered it by
// guessing which form the group meant (it preferred one whose label said
// "Invoice") and fetching the PDF into a full-screen iframe. Neither is needed
// here — pre_process.php has already resolved the single form in the group, so
// this only has to press the button.
//
// The View button's presence IS the readiness test, since template_main.php
// draws it only once a form is resolved and records selected. When the group
// holds several forms the chooser is showing instead, and there is nothing to
// guess between: printing the wrong document unasked is worse than waiting for
// a click. Into _self rather than a new tab, so the popup that opened for this
// becomes the viewer instead of spawning a second window.
document.addEventListener('DOMContentLoaded', function() {
  if (new URLSearchParams(window.location.search).get('autoprint') !== '1') return;
  if (!document.getElementById('forms_print_view')) return;
  const form = document.forms['forms_print'];
  if (!form) return;
  form.action.value = 'view';
  form.target = '_self';
  form.submit();
});
</script>
