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
//  Path: /modules/shipping/pages/popup_label_viewer/template_main.php
//
$post_url = html_href_link(FILENAME_DEFAULT,
  'module=shipping&page=popup_label_viewer' .
  '&method='  . urlencode($method) .
  '&date='    . urlencode($date)   .
  '&labels='  . urlencode($label_list), 'SSL');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
  <title><?php echo TITLE . ' - ' . COMPANY_NAME; ?></title>
  <link rel="stylesheet" href="ext/bootstrap5/bootstrap.min.css">
  <link rel="stylesheet" href="ext/bootstrap5/freebooks.css">
  <link rel="stylesheet" href="jodit/jodit.min.css">
  <style>
    body { margin: 0; }
    .label-iframe { width: 100%; border: none; min-height: 80vh; display: block; }
  </style>
</head>
<body>
<script>if (window.opener) window.opener.location.reload();</script>


<!-- Toolbar -->
<div class="d-flex align-items-center gap-2 p-2 border-bottom bg-light flex-wrap">
  <button type="button" class="btn btn-secondary btn-sm"
          onclick="if(window.opener)window.opener.location.reload(); window.close()">
    <?php echo TEXT_CLOSE; ?>
  </button>
  <?php
  $download_count = 0;
  foreach ($content_list as $item) {
    if ($item['type'] === 'pdf' || $item['type'] === 'gif') $download_count++;
  }
  $idx = 0;
  foreach ($content_list as $item):
    if ($item['type'] !== 'pdf' && $item['type'] !== 'gif') continue;
    $idx++;
    $suffix = $download_count > 1 ? ' #' . $idx : '';
  ?>
  <a href="<?php echo htmlspecialchars($item['url']); ?>" download
     class="btn btn-primary btn-sm"><?php echo SHIPPING_LABEL_DOWNLOAD . $suffix; ?></a>
  <?php endforeach; ?>
  <button type="button" class="btn btn-outline-primary btn-sm"
          onclick="toggleEmailPanel()"><?php echo SHIPPING_LABEL_EMAIL; ?></button>
</div>

<div id="email_success" style="display:none" class="alert alert-success alert-dismissible fade show mb-0 rounded-0 py-2 px-3" role="alert">
  <?php echo SHIPPING_LABEL_EMAIL_SENT; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

<!-- Email panel -->
<div id="email_panel" style="display:none" class="border-bottom p-3 bg-white">
  <div id="email_error" style="display:none" class="alert alert-danger py-2 mb-2"><?php echo SHIPPING_LABEL_EMAIL_ERROR; ?></div>
  <form id="email_form">
    <div class="row g-2 mb-2">
      <div class="col-md-3">
        <label class="form-label"><?php echo SHIPPING_LABEL_TO_NAME; ?></label>
        <input type="text" class="form-control form-control-sm" name="to_name"
               value="<?php echo htmlspecialchars($email_to_name); ?>" maxlength="60">
      </div>
      <div class="col-md-3">
        <label class="form-label"><?php echo SHIPPING_LABEL_TO_EMAIL; ?></label>
        <input type="email" class="form-control form-control-sm" name="to_email"
               value="<?php echo htmlspecialchars($email_to_addr); ?>" maxlength="100">
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-md-3">
        <label class="form-label"><?php echo SHIPPING_LABEL_FROM_NAME; ?></label>
        <input type="text" class="form-control form-control-sm" name="from_name"
               value="<?php echo htmlspecialchars($email_from_name); ?>" maxlength="60">
      </div>
      <div class="col-md-3">
        <label class="form-label"><?php echo SHIPPING_LABEL_FROM_EMAIL; ?></label>
        <input type="email" class="form-control form-control-sm" name="from_email"
               value="<?php echo htmlspecialchars($email_from_addr); ?>" maxlength="100">
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-md-3">
        <label class="form-label"><?php echo SHIPPING_LABEL_CC_EMAIL; ?></label>
        <input type="email" class="form-control form-control-sm" name="cc_email"
               value="" maxlength="100" placeholder="<?php echo SHIPPING_LABEL_CC_EMAIL; ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label"><?php echo SHIPPING_LABEL_BCC_EMAIL; ?></label>
        <input type="email" class="form-control form-control-sm" name="bcc_email"
               value="" maxlength="100" placeholder="<?php echo SHIPPING_LABEL_BCC_EMAIL; ?>">
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-12">
        <label class="form-label"><?php echo TEXT_MESSAGE_SUBJECT; ?></label>
        <input type="text" class="form-control form-control-sm" name="message_subject"
               value="<?php echo htmlspecialchars(SHIPPING_LABEL_EMAIL_SUBJECT); ?>" maxlength="150">
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-12">
        <label class="form-label"><?php echo TEXT_MESSAGE_BODY; ?></label>
        <textarea name="message_body" id="message_body"
                  rows="3"><?php echo htmlspecialchars(SHIPPING_LABEL_EMAIL_BODY); ?></textarea>
      </div>
    </div>
    <button type="button" class="btn btn-primary btn-sm"
            onclick="sendEmail()"><?php echo TEXT_SEND; ?></button>
  </form>
</div>

<!-- Label display -->
<?php foreach ($content_list as $item): ?>
<iframe src="<?php echo htmlspecialchars($item['url']); ?>" class="label-iframe"></iframe>
<?php endforeach; ?>

<script src="ext/bootstrap5/bootstrap.bundle.min.js"></script>
<script src="jodit/jodit.min.js"></script>
<script>
var _postUrl      = '<?php echo addslashes($post_url); ?>';
var _joditViewer  = null;

function initMsgEditor() {
  if (!_joditViewer) {
    _joditViewer = Jodit.make(document.getElementById('message_body'));
  }
}

function toggleEmailPanel() {
  var p = document.getElementById('email_panel');
  p.style.display = p.style.display === 'none' ? 'block' : 'none';
  if (p.style.display === 'block') {
    document.getElementById('email_error').style.display = 'none';
    setTimeout(initMsgEditor, 0);
  }
}

function sendEmail() {
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var form = document.getElementById('email_form');
  var body = _joditViewer ? _joditViewer.value : document.getElementById('message_body').value;
  var data = new FormData(form);
  data.set('action',       'email');
  data.set('csrf_token',   csrf);
  data.set('message_body', body);
  fetch(_postUrl, { method: 'POST', body: data })
    .then(function(r) { return r.json(); })
    .then(function(json) {
      if (json.status === 'ok') {
        document.getElementById('email_panel').style.display   = 'none';
        document.getElementById('email_success').style.display = '';
      } else {
        document.getElementById('email_error').style.display = '';
      }
    })
    .catch(function() {
      document.getElementById('email_error').style.display = '';
    });
}
</script>
</body>
</html>
