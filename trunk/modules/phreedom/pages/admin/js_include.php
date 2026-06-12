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
//  Path: /modules/phreedom/pages/admin/js_include.php
//
?>
<script type="text/javascript">
// pass any php variables generated during pre-process that are used in the javascript functions.
// Include translations here as well.
<?php echo js_calendar_init($cal_clean); ?>

function init() {
  new simpleDatatables.DataTable('#currency_table', dataTables_i18n);
  updateEmailTransport();
}

function updateEmailTransport() {
  var transport = document.getElementById('email_transport');
  if (!transport) return;
  var val        = transport.value;
  var showSmtp   = (val === 'smtp' || val === 'smtpauth' || val === 'oauth2');
  var showAuth   = (val === 'smtpauth');
  var showOAuth2 = (val === 'oauth2');
  var smtpRows   = ['smtp_header','smtp_row_server','smtp_row_port','smtp_row_encryption','smtp_row_self_signed','smtp_row_test'];
  var authRows   = ['smtp_row_mailbox','smtp_row_password'];
  var oauth2Rows = ['oauth2_header','oauth2_row_provider','oauth2_row_client_id','oauth2_row_client_secret',
                    'oauth2_row_tenant','oauth2_row_email','oauth2_row_refresh_token','oauth2_row_authorize'];
  for (var i = 0; i < smtpRows.length; i++) {
    var row = document.getElementById(smtpRows[i]);
    if (row) row.style.display = showSmtp ? '' : 'none';
  }
  for (var i = 0; i < authRows.length; i++) {
    var row = document.getElementById(authRows[i]);
    if (row) row.style.display = showAuth ? '' : 'none';
  }
  for (var i = 0; i < oauth2Rows.length; i++) {
    var row = document.getElementById(oauth2Rows[i]);
    if (row) row.style.display = showOAuth2 ? '' : 'none';
  }
  // For oauth2, show mailbox row (labelled as "Email address") but hide password
  if (showOAuth2) {
    var mbox = document.getElementById('smtp_row_mailbox');
    if (mbox) mbox.style.display = 'none'; // oauth2_row_email replaces it
  }
}

function startOAuth2() {
  var provider    = document.getElementById('email_oauth2_provider').value;
  var clientId    = document.getElementById('email_oauth2_client_id') ? document.getElementById('email_oauth2_client_id').value : '';
  var tenant      = document.getElementById('email_oauth2_tenant')    ? document.getElementById('email_oauth2_tenant').value    : 'common';
  var result      = document.getElementById('oauth2_auth_result');

  if (!clientId) {
    result.style.display    = 'block';
    result.style.background = '#f2dede';
    result.style.color      = '#a94442';
    result.style.border     = '1px solid #ebccd1';
    result.textContent      = 'Enter a Client ID before authorising.';
    return;
  }

  // Build redirect_uri pointing to oauth2_callback.php at the webroot
  var redirectUri = window.location.protocol + '//' + window.location.host +
                    window.location.pathname.replace(/\/[^\/]*$/, '/') + 'oauth2_callback.php';

  var authUrl;
  if (provider === 'google') {
    authUrl = 'https://accounts.google.com/o/oauth2/v2/auth' +
      '?client_id='     + encodeURIComponent(clientId) +
      '&redirect_uri='  + encodeURIComponent(redirectUri) +
      '&response_type=code' +
      '&scope='         + encodeURIComponent('https://mail.google.com/') +
      '&access_type=offline' +
      '&prompt=consent';
  } else {
    authUrl = 'https://login.microsoftonline.com/' + encodeURIComponent(tenant) + '/oauth2/v2.0/authorize' +
      '?client_id='     + encodeURIComponent(clientId) +
      '&redirect_uri='  + encodeURIComponent(redirectUri) +
      '&response_type=code' +
      '&scope='         + encodeURIComponent('https://outlook.office365.com/SMTP.Send offline_access') +
      '&prompt=consent';
  }

  result.style.display    = 'block';
  result.style.background = '#d9edf7';
  result.style.color      = '#31708f';
  result.style.border     = '1px solid #bce8f1';
  result.textContent      = 'Waiting for authorisation…';

  window._oauth2Popup = window.open(authUrl, 'oauth2_consent', 'width=520,height=620,resizable=1,scrollbars=1');
}

// Called by oauth2_callback.php via window.opener
function oauth2Complete(refreshToken) {
  var result = document.getElementById('oauth2_auth_result');
  document.getElementById('email_oauth2_refresh_token').value = refreshToken;
  result.style.display    = 'block';
  result.style.background = '#dff0d8';
  result.style.color      = '#3c763d';
  result.style.border     = '1px solid #d6e9c6';
  result.textContent      = 'Authorised successfully. Save the form to store the token.';
  if (window._oauth2Popup && !window._oauth2Popup.closed) window._oauth2Popup.close();
}

function oauth2Failed(message) {
  var result = document.getElementById('oauth2_auth_result');
  result.style.display    = 'block';
  result.style.background = '#f2dede';
  result.style.color      = '#a94442';
  result.style.border     = '1px solid #ebccd1';
  result.textContent      = 'Authorisation failed: ' + message;
  if (window._oauth2Popup && !window._oauth2Popup.closed) window._oauth2Popup.close();
}

function check_form() {
  return true;
}

// Insert other page specific functions here.
function loadPopUp(action, id) {
  switch(action) {
    case 'countries_new':    action = 'new';    subject = 'countries'; break;
    case 'currency_new':     action = 'new';    subject = 'currency';  break;
    case 'zones_new':        action = 'new';    subject = 'zones';     break;
    case 'countries_edit':   action = 'edit';   subject = 'countries'; break;
    case 'currency_edit':    action = 'edit';   subject = 'currency';  break;
    case 'zones_edit':       action = 'edit';   subject = 'zones';     break;
//    case 'countries_delete': action = 'delete'; subject = 'countries'; break;
//    case 'currency_delete':  action = 'delete'; subject = 'currency';  break;
//    case 'zones_delete':     action = 'delete'; subject = 'zones';     break;
  }
  window.open("index.php?module=phreedom&page=popup_setup&topic="+module+"&subject="+subject+"&action="+action+"&sID="+id,"popup_setup","width=500,height=550,resizable=1,scrollbars=1,top=150,left=200");
}

function testSmtpConnection() {
  var btn    = document.getElementById('smtp_test_btn');
  var result = document.getElementById('smtp_test_result');
  btn.disabled = true;
  btn.textContent = 'Testing…';
  result.style.display    = 'block';
  result.style.background = '#d9edf7';
  result.style.color      = '#31708f';
  result.style.border     = '1px solid #bce8f1';

  var elapsed = 0;
  var timer = setInterval(function() {
    elapsed++;
    result.innerHTML = '&#9696; Connecting to server&hellip; (' + elapsed + 's)';
  }, 1000);
  result.innerHTML = '&#9696; Connecting to server&hellip; (0s)';

  var data = {
    transport:   document.getElementById('email_transport').value,
    server:      document.getElementById('email_smtpauth_mail_server').value,
    port:        document.getElementById('email_smtpauth_mail_server_port').value,
    encryption:  document.getElementById('email_smtpauth_tls').value,
    mailbox:     document.getElementById('email_smtpauth_mailbox') ? document.getElementById('email_smtpauth_mailbox').value : '',
    password:    document.getElementById('email_smtpauth_password') ? document.getElementById('email_smtpauth_password').value : '',
    self_signed: document.getElementById('email_smtp_allow_self_signed').value
  };

  var params = new URLSearchParams(data);
  params.append('csrf_token', (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '');
  fetch('index.php?module=phreedom&page=ajax&op=test_smtp', { method: 'POST', body: params })
    .then(function(r) { clearInterval(timer); btn.disabled = false; btn.textContent = 'Test SMTP Connection'; return r.json(); })
    .then(function(resp) {
      result.style.display    = 'block';
      result.style.background = resp.ok ? '#dff0d8' : '#f2dede';
      result.style.color      = resp.ok ? '#3c763d' : '#a94442';
      result.style.border     = '1px solid ' + (resp.ok ? '#d6e9c6' : '#ebccd1');
      var html = resp.message;
      if (resp.log && resp.log.length) {
        html += '<details style="margin-top:6px"><summary style="cursor:pointer;font-size:0.85em">Connection log</summary><pre style="margin:4px 0 0;font-size:0.8em;white-space:pre-wrap">' + resp.log.join('\n') + '</pre></details>';
      }
      result.innerHTML = html;
    })
    .catch(function(err) {
      clearInterval(timer);
      btn.disabled    = false;
      btn.textContent = 'Test SMTP Connection';
      result.style.display    = 'block';
      result.style.background = '#f2dede';
      result.style.color      = '#a94442';
      result.style.border     = '1px solid #ebccd1';
      result.textContent = 'Request failed. Check server logs.';
    });
}

function subjectDelete(subject, id) {
  document.getElementById('subject').value = subject;
  submitSeq(id, 'delete');
}

</script>
