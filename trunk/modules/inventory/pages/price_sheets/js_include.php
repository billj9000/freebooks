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
//  Path: /modules/inventory/pages/price_sheets/js_include.php
//
?>
<script type="text/javascript">
var num_price_levels = <?php echo MAX_NUM_PRICE_LEVELS; ?>;
<?php echo js_calendar_init($cal_ps); ?>

function init() {
<?php
  if ($_REQUEST['action'] != 'new' && $_REQUEST['action'] != 'edit') {
    echo "  document.getElementById('search_text').focus();";
    echo "  document.getElementById('search_text').select();";
  }
?>
}

function check_form() {
  var error         = 0;
  var error_message = "<?php echo JS_ERROR; ?>";
<?php if ($_REQUEST['action'] == 'new') { ?>
  var sheetName = document.getElementById('sheet_name').value;
  if (!sheetName) {
    error = 1;
    error_message += '<?php echo JS_ERROR_NO_SHEET_NAME; ?>';
  }
<?php } ?>
  if (error == 1) {
    alert(error_message);
    return false;
  }
  return true;
}

function priceMgr(rowID, itemID) {
  var rowCost  = cleanCurrency(document.getElementById('cost_' + rowID).value);
  var rowPrice = cleanCurrency(document.getElementById('sell_' + rowID).value);
  window.open('index.php?module=inventory&page=popup_prices&iID=' + itemID + '&cost=' + rowCost + '&price=' + rowPrice, 'price_mgr', 'width=800,height=400,resizable=1,scrollbars=1,top=150,left=200');
}

// Posted (submitPost) with the page's csrf_token; the page refuses a plain link.
function deleteItem(id) {
  submitPost('index.php?module=inventory&page=price_sheets&action=delete', {psID: id});
}

function initEditForm() {
  updatePrice();
}

function updatePrice() {
  for (var rowID = 1; rowID <= num_price_levels; rowID++) {
    var src = document.getElementById('src_' + rowID).selectedIndex;
    if (rowID == 1) src++;
    switch (src) {
      case 0:
        document.getElementById('adj_'     + rowID).value    = 0;
        document.getElementById('rnd_'     + rowID).value    = 0;
        document.getElementById('price_'   + rowID).value    = 0;
        document.getElementById('adj_'     + rowID).disabled = true;
        document.getElementById('rnd_'     + rowID).disabled = true;
        document.getElementById('adj_val_' + rowID).disabled = true;
        document.getElementById('rnd_val_' + rowID).disabled = true;
        break;
      case 1:
        document.getElementById('adj_'     + rowID).value    = 0;
        document.getElementById('rnd_'     + rowID).value    = 0;
        document.getElementById('adj_'     + rowID).disabled = true;
        document.getElementById('rnd_'     + rowID).disabled = true;
        document.getElementById('adj_val_' + rowID).disabled = true;
        document.getElementById('rnd_val_' + rowID).disabled = true;
        document.getElementById('price_'   + rowID).disabled = false;
        break;
      case 2:
      case 3:
      case 4:
      default:
        document.getElementById('adj_'     + rowID).disabled = false;
        document.getElementById('rnd_'     + rowID).disabled = false;
        document.getElementById('adj_val_' + rowID).disabled = false;
        document.getElementById('rnd_val_' + rowID).disabled = false;
        document.getElementById('price_'   + rowID).disabled = true;
    }

    document.getElementById('adj_val_' + rowID).disabled = false;
    switch (document.getElementById('adj_' + rowID).selectedIndex) {
      case 0:
        document.getElementById('adj_val_' + rowID).disabled = true;
        break;
      case 1:
      case 2:
      case 3:
      case 4:
    }

    switch (document.getElementById('rnd_' + rowID).selectedIndex) {
      case 0:
      case 1:
        document.getElementById('rnd_val_' + rowID).disabled = true;
        break;
      case 2:
      case 3:
        document.getElementById('rnd_val_' + rowID).disabled = false;
    }
  }
}
</script>
