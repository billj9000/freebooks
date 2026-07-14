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
//  Path: /modules/api/pages/admin/js_include.php
//
?>
<script>
var _apiKeyGenerated = false;

// Called by hx-on::after-request on the Generate button
function afterKeyGenerated(event) {
  if (!event.detail.successful) return;
  _apiKeyGenerated = true;
  // Collapse the label input; keep only Close in the footer
  var form = document.getElementById('generate-key-form');
  if (form) form.style.display = 'none';
  var footer = document.getElementById('generate-key-footer');
  if (footer) {
    footer.innerHTML = '<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo addslashes(API_BTN_CLOSE); ?></button>';
  }
}

// Copy the generated key to clipboard
function copyApiKey(btn) {
  var keyVal = document.getElementById('generated-key-value');
  if (!keyVal) return;
  navigator.clipboard.writeText(keyVal.textContent.trim()).then(function() {
    btn.textContent = '<?php echo addslashes(API_BTN_COPY); ?> ✓';
    setTimeout(function() { btn.textContent = '<?php echo addslashes(API_BTN_COPY); ?>'; }, 2000);
  });
}

document.addEventListener('DOMContentLoaded', function() {
  var modal = document.getElementById('generateKeyModal');
  if (!modal) return;

  modal.addEventListener('hidden.bs.modal', function() {
    if (_apiKeyGenerated) {
      // Reload so the new key appears in the table
      location.reload();
      return;
    }
    // Modal closed without generating — reset for re-use
    _apiKeyGenerated = false;
    var form = document.getElementById('generate-key-form');
    if (form) form.style.display = '';
    var disp = document.getElementById('generated-key-display');
    if (disp) disp.innerHTML = '';
    var lbl = document.getElementById('new-key-label');
    if (lbl) lbl.value = '';
    var footer = document.getElementById('generate-key-footer');
    if (footer) {
      footer.innerHTML =
        '<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo addslashes(API_BTN_CLOSE); ?></button>' +
        '<button type="button" class="btn btn-primary btn-sm"' +
        ' hx-post="<?php echo html_href_link(FILENAME_DEFAULT, "module=api&page=ajax&op=generate_key", "SSL"); ?>"' +
        ' hx-include="[name=\'new_key_label\']"' +
        ' hx-target="#generated-key-display"' +
        ' hx-swap="innerHTML"' +
        ' hx-on::after-request="afterKeyGenerated(event)">' +
        '<?php echo addslashes(API_BTN_GENERATE); ?>' +
        '</button>';
      htmx.process(footer);
    }
  });
});
</script>
