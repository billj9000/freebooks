<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft      (www.PhreeSoft.com)       |
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
<!--
// pass any php variables generated during pre-process that are used in the javascript functions.
// Include translations here as well.
<?php echo js_calendar_init($cal_clean); ?>

function init() {
	$(function() {
		$('#admintabs').tabs();
		$('#currency_table').dataTable( dataTables_i18n );
		updateEmailTransport();
	});
}

function updateEmailTransport() {
  var transport = document.getElementById('email_transport');
  if (!transport) return;
  var val       = transport.value;
  var showSmtp  = (val === 'smtp' || val === 'smtpauth');
  var showAuth  = (val === 'smtpauth');
  var smtpRows  = ['smtp_header','smtp_row_server','smtp_row_port','smtp_row_encryption','smtp_row_self_signed','smtp_row_test'];
  var authRows  = ['smtp_row_mailbox','smtp_row_password'];
  for (var i = 0; i < smtpRows.length; i++) {
    var row = document.getElementById(smtpRows[i]);
    if (row) row.style.display = showSmtp ? '' : 'none';
  }
  for (var i = 0; i < authRows.length; i++) {
    var row = document.getElementById(authRows[i]);
    if (row) row.style.display = showAuth ? '' : 'none';
  }
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

  $.ajax({
    url:  'index.php?module=phreedom&page=ajax&op=test_smtp',
    type: 'POST',
    data: data,
    dataType: 'json',
    success: function(resp) {
      result.style.display = 'block';
      result.style.background = resp.ok ? '#dff0d8' : '#f2dede';
      result.style.color      = resp.ok ? '#3c763d' : '#a94442';
      result.style.border     = '1px solid ' + (resp.ok ? '#d6e9c6' : '#ebccd1');
      var html = resp.message;
      if (resp.log && resp.log.length) {
        html += '<details style="margin-top:6px"><summary style="cursor:pointer;font-size:0.85em">Connection log</summary><pre style="margin:4px 0 0;font-size:0.8em;white-space:pre-wrap">' + resp.log.join('\n') + '</pre></details>';
      }
      result.innerHTML = html;
    },
    error: function(xhr) {
      result.style.display    = 'block';
      result.style.background = '#f2dede';
      result.style.color      = '#a94442';
      result.style.border     = '1px solid #ebccd1';
      result.textContent = 'Request failed (' + xhr.status + '). Check server logs.';
    },
    complete: function() {
      clearInterval(timer);
      btn.disabled    = false;
      btn.textContent = 'Test SMTP Connection';
    }
  });
}

function subjectDelete(subject, id) {
  document.getElementById('subject').value = subject;
  submitSeq(id, 'delete');
}

// -->
</script>