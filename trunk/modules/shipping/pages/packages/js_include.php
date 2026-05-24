<?php
//  Path: /modules/shipping/pages/packages/js_include.php
$vol_divisor  = defined('SHIPPING_VOL_WEIGHT_DIVISOR') ? (float)SHIPPING_VOL_WEIGHT_DIVISOR : 5000;
$auto_open    = ($pkgID > 0 || (($_REQUEST['action'] ?? '') === 'new')) ? 'true' : 'false';
$dialog_title = addslashes($selected_pkg ? $selected_pkg['name'] : TEXT_NEW);
?>
<script>
var volDivisor     = <?php echo $vol_divisor; ?>;
var autoOpenDialog = <?php echo $auto_open; ?>;
var dialogTitle    = '<?php echo $dialog_title; ?>';

function calcVolWeight() {
  var l = parseFloat($('#int_length').val()) || 0;
  var w = parseFloat($('#int_width').val())  || 0;
  var h = parseFloat($('#int_height').val()) || 0;
  if (l > 0 && w > 0 && h > 0 && volDivisor > 0) {
    $('#vol_weight').text((l * w * h / volDivisor).toFixed(3));
  } else {
    $('#vol_weight').text('—');
  }
}

function init() {
  $('#int_length, #int_width, #int_height').bind('input change', calcVolWeight);

  $('#edit-dialog').dialog({
    modal:    true,
    autoOpen: false,
    width:    420,
    buttons: {
      '<?php echo TEXT_SAVE; ?>':   function() { submitToDo('save'); },
      '<?php echo TEXT_CANCEL; ?>': function() { $(this).dialog('close'); }
    }
  });

  $('#edit-dialog').bind('keydown', function(e) {
    if (e.keyCode === 13) { e.preventDefault(); submitToDo('save'); }
  });

  if (autoOpenDialog) {
    $('#edit-dialog').dialog('option', 'title', dialogTitle).dialog('open');
    calcVolWeight();
  }
}

function submitToDo(action) {
  if (action === 'delete') {
    if (!confirm('<?php echo TEXT_DELETE_ENTRY; ?>')) return;
  }
  if (action === 'new') {
    $('input[name=pkgID]').val('');
  }
  if (action === 'edit') action = 'update';
  else if (action === 'save' && $('input[name=pkgID]').val() !== '') action = 'update';
  $('input[name=action]').val(action);
  document.shippkg.submit();
}

function submitSeq(id, action) {
  $('input[name=pkgID]').val(id);
  $('input[name=action]').val(action);
  document.shippkg.submit();
}
</script>
