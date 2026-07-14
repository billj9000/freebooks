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
//  Path: /modules/api/ajax/generate_key.php
//
// Generates a new API key for the current company.
// Returns an HTML fragment (htmx target: #generated-key-display).
//
$security_level = validate_ajax_user(SECURITY_ID_CONFIGURATION);
if ($security_level < 3) {
  echo '<div class="alert alert-danger mb-0">' . htmlspecialchars(ERROR_NO_PERMISSION) . '</div>';
  die;
}

gen_pull_language('api');
require_once(DIR_FS_MODULES . 'api/defaults.php');

$company    = db_input($_SESSION['company']);
$label      = db_input(trim($_POST['new_key_label'] ?? ''));

// Generate the plaintext key: prefix + 40 random hex chars
$plaintext  = API_KEY_PREFIX . bin2hex(random_bytes(20));
$key_hash   = hash('sha256', $plaintext);
$key_prefix = db_input(substr($plaintext, 0, strlen(API_KEY_PREFIX) + 6));

$scopes  = db_input(API_SCOPES_DEFAULT);
$created = date('Y-m-d H:i:s');

$db->Execute(
  "INSERT INTO " . TABLE_API_KEYS . " (key_hash, key_prefix, company, label, scopes, enabled, created)
   VALUES ('$key_hash', '$key_prefix', '$company', '$label', '$scopes', '1', '$created')"
);

// Return HTML fragment showing the key — displayed in modal, not persisted anywhere
?>
<div class="alert alert-success mb-2">
  <strong><?php echo htmlspecialchars(API_KEY_GENERATED_TITLE); ?></strong><br>
  <small><?php echo htmlspecialchars(API_KEY_GENERATED_NOTE); ?></small>
</div>
<div class="input-group input-group-sm">
  <code id="generated-key-value" class="form-control font-monospace text-break"><?php echo htmlspecialchars($plaintext); ?></code>
  <button type="button" class="btn btn-outline-secondary" onclick="copyApiKey(this)">
    <?php echo htmlspecialchars(API_BTN_COPY); ?>
  </button>
</div>
