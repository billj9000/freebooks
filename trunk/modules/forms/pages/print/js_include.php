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
</script>
