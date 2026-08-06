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
//  Path: /modules/phreedom/pages/profile/js_include.php
//
?>
<script>
function init() {}
function check_form() { return true; }

function updateColors() {
  var theme = document.getElementById('theme').value;
  fetchXml('index.php?module=phreedom&page=ajax&op=phreedom&action=pull_colors&theme=' + encodeURIComponent(theme), function(xml) {
    xml = parseXml(xml);
    if (!xml) return;
    var menuSel   = document.getElementById('menu');
    var colorsSel = document.getElementById('colors');
    while (menuSel.options.length)   menuSel.remove(0);
    while (colorsSel.options.length) colorsSel.remove(0);
    Array.from(xml.getElementsByTagName('menu')).forEach(function(el) {
      menuSel.options.add(new Option(xmlText(el, 'text'), xmlText(el, 'id')));
    });
    Array.from(xml.getElementsByTagName('color')).forEach(function(el) {
      colorsSel.options.add(new Option(xmlText(el, 'text'), xmlText(el, 'id')));
    });
  });
}

// Renames one of my passkeys in place (used by the onclick built in
// modules/phreedom/includes/webauthn/row.php).
function pkRename(id, currentLabel) {
  var newLabel = prompt('<?php echo addslashes(TEXT_PASSKEY_RENAME_PROMPT); ?>', currentLabel);
  if (newLabel === null || newLabel === '') return;
  var params = new URLSearchParams();
  var meta = document.querySelector('meta[name="csrf-token"]');
  params.append('csrf_token', meta ? meta.getAttribute('content') : '');
  params.append('id', id);
  params.append('label', newLabel);
  fetch('index.php?module=phreedom&page=ajax&op=passkey_rename', { method: 'POST', body: params, credentials: 'same-origin' })
    .then(function (r) { return r.text(); })
    .then(function (html) {
      var row = document.getElementById('pk-row-' + id);
      if (row && html) row.outerHTML = html;
    });
}
</script>

<?php if (wa_enabled()): ?>
<script>
var PK_STRINGS = {
  unsupported: '<?php echo addslashes(TEXT_PASSKEY_MSG_UNSUPPORTED); ?>',
  waiting:     '<?php echo addslashes(TEXT_PASSKEY_MSG_WAITING); ?>',
  success:     '<?php echo addslashes(TEXT_PASSKEY_MSG_SUCCESS); ?>',
  failed:      '<?php echo addslashes(TEXT_PASSKEY_MSG_FAILED); ?>',
  cancelled:   '<?php echo addslashes(TEXT_PASSKEY_MSG_CANCELLED); ?>'
};
</script>
<script src="modules/phreedom/includes/webauthn/passkey.js" defer></script>
<script>
addLoadEvent(function () {
  var btn = document.getElementById('pk-add-btn');
  var msg = document.getElementById('pk-msg');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var label = prompt('<?php echo addslashes(TEXT_PASSKEY_NAME_PROMPT); ?>', '');
    if (label === null) return;
    FbPasskey.passkeyRegister(label, function (text, isError) {
      if (!msg) return;
      msg.textContent = text;
      msg.style.color = isError ? '#c0392b' : '#555';
    }, function (ok) {
      if (ok) location.reload();
    });
  });
});
</script>
<?php endif; ?>
