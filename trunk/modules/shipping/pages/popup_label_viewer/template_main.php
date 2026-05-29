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
<div class="fb-viewer-toolbar">
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

<!-- Email panel -->
<div id="email_panel" style="display:none" class="fb-viewer-panel">
  <div id="email_success" style="display:none" class="alert alert-success mb-2"><?php echo SHIPPING_LABEL_EMAIL_SENT; ?></div>
  <div id="email_error"   style="display:none" class="alert alert-danger  mb-2"><?php echo SHIPPING_LABEL_EMAIL_ERROR; ?></div>
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
    <button type="button" class="btn btn-primary btn-sm" onclick="sendEmail()">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor"
           viewBox="0 0 16 16" style="margin-right:3px;vertical-align:-1px">
        <path d="M15.854.146a.5.5 0 0 1 .11.54l-5.819 14.547a.75.75 0 0 1-1.329.124l-3.178-4.995L.643 7.184a.75.75 0 0 1 .124-1.33L15.314.037a.5.5 0 0 1 .54.11ZM6.636 10.07l2.761 4.338L14.13 2.576 6.636 10.07Zm6.787-8.201L1.591 6.602l4.339 2.76 7.494-7.493Z"/>
      </svg>
      <?php echo TEXT_SEND; ?>
    </button>
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
        document.getElementById('email_success').style.display = '';
        document.getElementById('email_error').style.display   = 'none';
        setTimeout(function() {
          document.getElementById('email_panel').style.display   = 'none';
          document.getElementById('email_success').style.display = 'none';
        }, 2000);
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
