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
//  Path: /modules/inventory/pages/main/template_main.php

echo html_form('inventory', FILENAME_DEFAULT, gen_get_all_get_params(['action', 'list', 'inventory_type'])) . chr(10);
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);

$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['print']['show']    = false;
if ($security_level > 1) $toolbar->add_icon('new', 'onclick="submitToDo(\'new\')"', $order = 10);
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.04.01');
echo $toolbar->build_toolbar($add_search = true);
?>
<h1><?php echo BOX_INV_MAINTAIN; ?></h1>

<div class="pkg-wrap">

<table id="filter_table" class="table table-sm pkg-table mb-2" style="width:auto">
  <thead>
    <tr>
      <th></th>
      <th><?php echo FILTER_TABEL_HEAD_FIELD; ?></th>
      <th><?php echo FILTER_TABEL_HEAD_COPAIRISON; ?></th>
      <th><?php echo FILTER_TABEL_HEAD_VALUE; ?></th>
    </tr>
  </thead>
  <tbody id="filter_table_body">
    <?php
    if ($_SESSION['filter_field']) {
      foreach ($_SESSION['filter_field'] as $key => $value) {
        echo '<script>TableStartValues("' . $_SESSION['filter_field'][$key] . '","' . $_SESSION['filter_criteria'][$key] . '","' . $_SESSION['filter_value'][$key] . '");</script>' . chr(10);
      }
    } else {
      echo '<script>TableStartValues("a.sku","0","");</script>' . chr(10);
    }
    ?>
  </tbody>
  <tfoot class="tfoot-plain">
    <tr>
      <td><?php echo html_icon('actions/list-add.png', TEXT_ADD, 'medium', 'onclick="addFilterRow()"'); ?></td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td class="text-end">
        <?php echo html_icon('actions/system-search.png', TEXT_SEARCH, 'medium', 'onclick="submitToDo(\'filter\')"'); ?>
        <?php if ($_SESSION['filter_field']) echo html_icon('actions/view-refresh.png', TEXT_RESET, 'small', 'onclick="location.href = \'index.php?' . gen_get_all_get_params(['search_text', 'search_period', 'search_date', 'list', 'action']) . '&action=reset\';" style="cursor:pointer;"'); ?>
      </td>
    </tr>
  </tfoot>
</table>

<div class="d-flex justify-content-between align-items-center mb-1" style="min-height:20px">
  <small class="text-muted"><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_ITEMS); ?></small>
  <span><?php echo $query_split->display_links(); ?></span>
</div>

<table class="table table-sm pkg-table">
  <thead>
    <tr><?php echo $list_header; ?></tr>
  </thead>
  <tbody>
  <?php
  $odd = true;
  while (!$query_result->EOF):
    $not_show_types = ['ns', 'lb', 'sv', 'sf', 'ci', 'ai', 'ds'];
    if ($query_result->fields['inventory_type'] == 'pb') {
      $vr = $db->Execute(
        "SELECT MIN(FLOOR(i.quantity_on_hand / a.qty)) AS v
         FROM " . TABLE_INVENTORY_ASSY_LIST . " a
         INNER JOIN " . TABLE_INVENTORY . " i ON a.sku = i.sku
         WHERE a.ref_id = '" . $query_result->fields['id'] . "' AND a.qty > 0"
      );
      $qty_in_stock = (!$vr->EOF && $vr->fields['v'] !== null) ? max(0, (int)$vr->fields['v']) : 0;
    } elseif (in_array($query_result->fields['inventory_type'], $not_show_types)) {
      $qty_in_stock = '';
    } else {
      $qty_in_stock = $query_result->fields['quantity_on_hand'];
    }
    $row_class = ($odd ? 'odd' : 'even') . ($query_result->fields['inactive'] ? ' table-warning' : '');
  ?>
    <tr class="<?php echo $row_class; ?>" style="cursor:pointer">
      <td onclick="submitSeq(<?php echo $query_result->fields['id'] . ', \'edit\''; ?>)"><?php echo htmlspecialchars($query_result->fields['sku']); ?></td>
      <td class="text-center" onclick="submitSeq(<?php echo $query_result->fields['id'] . ', \'edit\''; ?>)"><?php echo ($query_result->fields['inactive'] == '1' ? TEXT_YES : ''); ?></td>
      <td onclick="submitSeq(<?php echo $query_result->fields['id'] . ', \'edit\''; ?>)"><?php echo htmlspecialchars($query_result->fields['description_short']); ?></td>
      <td class="text-center" onclick="submitSeq(<?php echo $query_result->fields['id'] . ', \'edit\''; ?>)"><?php echo $qty_in_stock; ?></td>
      <td class="text-center" onclick="submitSeq(<?php echo $query_result->fields['id'] . ', \'edit\''; ?>)"><?php echo $query_result->fields['quantity_on_sales_order']; ?></td>
      <td class="text-center" onclick="submitSeq(<?php echo $query_result->fields['id'] . ', \'edit\''; ?>)"><?php echo $query_result->fields['quantity_on_allocation']; ?></td>
      <td class="text-center" onclick="submitSeq(<?php echo $query_result->fields['id'] . ', \'edit\''; ?>)"><?php echo $query_result->fields['quantity_on_order']; ?></td>
      <td class="text-center" onclick="event.stopPropagation()">
        <?php
        if (function_exists('add_extra_action_bar_buttons')) echo add_extra_action_bar_buttons($query_result->fields);
        if (defined('MODULE_LABELS_STATUS') && $security_level > 0)
          echo html_icon('devices/printer.png', TEXT_PRINT_LABELS, 'small',
            "onclick=\"lblOpenModal('" . htmlspecialchars(addslashes($query_result->fields['sku']), ENT_QUOTES, 'UTF-8') . "')\"") . chr(10);
        if ($security_level > 1) echo html_icon('actions/edit-find-replace.png', TEXT_EDIT, 'small',
          'onclick="window.open(\'' . html_href_link(FILENAME_DEFAULT, 'module=inventory&amp;page=main&amp;cID=' . $query_result->fields['id'] . '&amp;action=edit&amp;inventory_type=' . $query_result->fields['inventory_type'], 'SSL') . "','_blank')\"") . chr(10);
        if ($security_level > 3 && $query_result->fields['inventory_type'] != 'mi' && $query_result->fields['inventory_type'] != 'ia')
          echo html_icon('apps/accessories-text-editor.png', TEXT_RENAME, 'small', "onclick='renameItem({$query_result->fields['id']},\"{$query_result->fields['inventory_type']}\")'") . chr(10);
        if ($security_level > 3 && $query_result->fields['inventory_type'] != 'mi' && $query_result->fields['inventory_type'] != 'ia' && ($query_result->fields['last_journal_date'] != '0000-00-00 00:00:00' || $query_result->fields['last_journal_date'] != ''))
          echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', "onclick='if (confirm(\"" . INV_MSG_DELETE_INV_ITEM . "\")) deleteItem({$query_result->fields['id']},\"{$query_result->fields['inventory_type']}\")'") . chr(10);
        if ($security_level > 1 && $query_result->fields['inventory_type'] != 'mi' && $query_result->fields['inventory_type'] != 'ia')
          echo html_icon('actions/edit-copy.png', TEXT_COPY, 'small', "onclick='copyItem({$query_result->fields['id']},\"{$query_result->fields['inventory_type']}\")'") . chr(10);
        if ($security_level > 2)
          echo html_icon('mimetypes/x-office-spreadsheet.png', BOX_SALES_PRICE_SHEETS, 'small', "onclick='priceMgr({$query_result->fields['id']}, \"\",{$query_result->fields['full_price']}, \"c\")'") . chr(10);
        ?>
      </td>
    </tr>
  <?php
    $query_result->MoveNext();
    $odd = !$odd;
  endwhile;
  ?>
  </tbody>
</table>

<div class="d-flex justify-content-between align-items-center">
  <small class="text-muted"><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_ITEMS); ?></small>
  <span><?php echo $query_split->display_links(); ?></span>
</div>

</div><!-- /pkg-wrap -->
</form>

<?php if (defined('MODULE_LABELS_STATUS')): gen_pull_language('labels'); ?>
<!-- Print Labels Modal -->
<div class="modal fade" id="lblModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content" id="lblModalContent">
      <div class="modal-body"><div class="text-center py-3">Loading...</div></div>
    </div>
  </div>
</div>
<script src="<?php echo DIR_WS_ADMIN; ?>modules/phreedom/includes/qz-tray/qz-tray.js"></script>
<script>
var lblModalUrl  = <?php echo json_encode(html_href_link(FILENAME_DEFAULT, 'module=labels&page=ajax&op=label_modal', 'SSL')); ?>;
var lblRenderUrl = <?php echo json_encode(html_href_link(FILENAME_DEFAULT, 'module=labels&page=ajax&op=render', 'SSL')); ?>;
var lblNoItems   = <?php echo json_encode(TEXT_LABEL_NO_ITEMS); ?>;

function lblOpenModal(sku) {
  var content = document.getElementById('lblModalContent');
  content.innerHTML = '<div class="modal-body"><div class="text-center py-3">Loading...</div></div>';
  var modal = new bootstrap.Modal(document.getElementById('lblModal'));
  modal.show();
  fetch(lblModalUrl + '&sku=' + encodeURIComponent(sku))
    .then(function(r){ return r.text(); })
    .then(function(html){ content.innerHTML = html; })
    .catch(function(e){ content.innerHTML = '<div class="modal-body text-danger">Error loading modal</div>'; });
}

function lblPrint(preselectSku) {
  var templateId = document.getElementById('lbl_template_id').value;
  var qty        = parseInt(document.getElementById('lbl_qty').value, 10) || 1;
  var errDiv     = document.getElementById('lbl_error');
  errDiv.style.display = 'none';

  var skus = preselectSku ? [{sku: preselectSku, qty: qty}] : [];
  if (!skus.length) {
    document.querySelectorAll('input[name="selected_sku"]:checked').forEach(function(cb) {
      skus.push({sku: cb.value, qty: qty});
    });
  }
  if (!skus.length) { errDiv.textContent = lblNoItems; errDiv.style.display = ''; return; }

  // Open the window NOW while still inside the click handler (user gesture).
  // Browsers block window.open() called from async callbacks.
  var printWin = window.open('', '_blank');
  if (!printWin) { errDiv.textContent = 'Pop-up blocked - please allow pop-ups for this site.'; errDiv.style.display = ''; return; }
  printWin.document.write('<html><body><p>Preparing labels...</p></body></html>');

  var meta = document.querySelector('meta[name="csrf-token"]');
  var formData = new FormData();
  formData.append('csrf_token',  meta ? meta.getAttribute('content') : '');
  formData.append('template_id', templateId);
  formData.append('skus', JSON.stringify(skus));

  fetch(lblRenderUrl, {method: 'POST', body: formData})
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (data.error) {
        printWin.close();
        errDiv.textContent = data.error; errDiv.style.display = ''; return;
      }
      if (data.type === 'html') {
        printWin.document.open(); printWin.document.write(data.html); printWin.document.close();
      } else if (data.type === 'zpl') {
        printWin.close();
        lblPrintZpl(data.labels, errDiv);
      }
    })
    .catch(function(e){ printWin.close(); errDiv.textContent = 'Error: ' + e; errDiv.style.display = ''; });
}

function lblPrintZpl(labels, errDiv) {
  if (typeof qz === 'undefined') {
    errDiv.textContent = 'QZ Tray is not available. Please install QZ Tray for ZPL printing.';
    errDiv.style.display = '';
    return;
  }
  var lines = labels.map(function(zpl){ return {type:'raw', format:'plain', data:zpl}; });
  qz.security.setCertificatePromise(function(resolve){ resolve(''); });
  qz.security.setSignatureAlgorithm('SHA512');
  qz.security.setSignaturePromise(function(toSign){ return function(resolve){ resolve(''); }; });
  qz.websocket.connect()
    .then(function(){ return qz.printers.getDefault(); })
    .then(function(printer){ return qz.print(qz.configs.create(printer), lines); })
    .catch(function(e){ errDiv.textContent = 'Print error: ' + e; errDiv.style.display = ''; });
}
</script>
<?php endif; ?>
