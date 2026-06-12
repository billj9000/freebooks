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
//  Path: /modules/phreebooks/custom/pages/bills/extra_js.php
//

// start the extra javascript
?>
<script type="text/javascript">
<!--
// pass any php variables generated during pre-process that are used in the javascript functions.
// Include translations here as well.

// ****** BOF - ajax pair for filling in amazon payments ****************
function amazonFillRequest() {
  var fn = prompt('Enter the Filename to fill receipt window with.', '');
  if (!fn) return;
  fetchXml('index.php?module=pps&page=ajax&op=amazon_receipt&fn=' + fn, amazonFillResponse);
}

function amazonFillResponse(xml) {
  var msg = '';
  if (!xml) return;
  Array.from(xml.getElementsByTagName("Order")).forEach(function(order) {
    var orderID = xmlText(order, "Number");
    var found = false;
    for (var r=1; r<document.getElementById('item_table').rows.length; r++) {
      if (document.getElementById('desc_'+r).value == orderID) {
        document.getElementById('dscnt_'+r).value = parseFloat(xmlText(order, "Discount"));
        updateRowTotal(r);
        found = true;
        break;
      }
    }
    if (!found) msg += 'could not find order # '+orderID+'\n';
  });
  alert(msg+'Finished Processing! Any listed orders could not be found.');
}
// ****** EOF - ajax pair for filling in amazon payments ****************

// -->
</script>