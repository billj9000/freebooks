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
//  Path: /modules/inventory/pages/main/template_detail.php

$can_print_labels = defined('MODULE_LABELS_STATUS') && (
  ($_SESSION['admin_id'] ?? 0) == 1
  || (array_key_exists(SECURITY_ID_LABELS_PRINT, $_SESSION['admin_security'] ?? [])
      && $_SESSION['admin_security'][SECURITY_ID_LABELS_PRINT] > 0)
);

echo html_form('inventory', FILENAME_DEFAULT, gen_get_all_get_params(['action', 'cID', 'sku', 'add', 'inventory_type']), 'post', 'enctype="multipart/form-data"');
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('rowSeq',    $cInfo->id) . chr(10);
echo html_hidden_field('id',        $cInfo->id) . chr(10);
if (isset($cInfo->ms_attr_0)) echo html_hidden_field('ms_attr_0', $cInfo->ms_attr_0) . chr(10);
if (isset($cInfo->ms_attr_1)) echo html_hidden_field('ms_attr_1', $cInfo->ms_attr_1) . chr(10);

if ($_REQUEST['action'] == 'properties') {
  echo html_hidden_field('search_text', '') . chr(10);
  $toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
  $toolbar->icon_list['open']['show']     = false;
  $toolbar->icon_list['delete']['show']   = false;
  $toolbar->icon_list['save']['show']     = false;
  $toolbar->icon_list['print']['show']    = false;
} else {
  $toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(['action', 'page']), 'SSL') . '\'"';
  $toolbar->icon_list['open']['show']     = false;
  if ($security_level > 3 && $cInfo->inventory_type != 'mi' && $cInfo->inventory_type != 'ia'
      && ($cInfo->last_journal_date != '0000-00-00 00:00:00' || $cInfo->last_journal_date != '')) {
    $toolbar->icon_list['delete']['show']   = true;
    $toolbar->icon_list['delete']['params'] = "onclick='if (confirm(\"" . INV_MSG_DELETE_INV_ITEM . "\")) deleteItem({$cInfo->id},\"{$cInfo->inventory_type}\")'";
  } else {
    $toolbar->icon_list['delete']['show'] = false;
  }
  if ($security_level > 2 || $first_entry) {
    $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
    $toolbar->add_icon('post_next', 'onclick="submitToDo(\'post_next\')"', 11);
    $toolbar->icon_list['post_next']['icon'] = 'actions/go-next.png';
    $toolbar->icon_list['post_next']['text'] = TEXT_SAVE_AND_COPY_TO_NEW;
  } else {
    $toolbar->icon_list['save']['show'] = false;
  }
  $toolbar->icon_list['print']['show'] = false;
  $toolbar->add_help($cInfo->help_path);
}
if (!empty($extra_toolbar_buttons_detail)) {
  foreach ($extra_toolbar_buttons_detail as $key => $value) $toolbar->icon_list[$key] = $value;
}
if ($can_print_labels && !empty($cInfo->sku)) {
  $toolbar->add_icon('print_label',
    'onclick="lblOpenModal(\'' . htmlspecialchars(addslashes($cInfo->sku), ENT_QUOTES, 'UTF-8') . '\')"',
    $order = 70);
  $toolbar->icon_list['print_label']['icon'] = 'devices/printer.png';
  $toolbar->icon_list['print_label']['text'] = TEXT_PRINT_LABEL;
}
echo $toolbar->build_toolbar();
$fields->set_fields_to_display($cInfo->inventory_type);

// merge in any extension-injected tabs
if (isset($extra_inventory_tabs) && is_array($extra_inventory_tabs)) {
  foreach ($extra_inventory_tabs as $tab) {
    $cInfo->tab_list[$tab['id']] = ['file' => $tab['filename'], 'tag' => $tab['id'], 'order' => $tab['order'], 'text' => $tab['title']];
  }
}

// sort tabs by order
$temp = [];
foreach ($cInfo->tab_list as $key => $value) $temp[$key] = $value['order'];
array_multisort($temp, SORT_ASC, $cInfo->tab_list);

// Convert $fields->extra_tab_li (legacy tab format) to Bootstrap nav buttons
$extra_nav_items = '';
if (!empty($fields->extra_tab_li)) {
  preg_match_all("/<li><a href='#(tab_\d+)'>(.*?)<\/a><\/li>/i", $fields->extra_tab_li, $matches, PREG_SET_ORDER);
  foreach ($matches as $m) {
    $extra_nav_items .= '<li class="nav-item" role="presentation">'
      . '<button class="nav-link" id="' . $m[1] . '-tab" data-bs-toggle="tab" data-bs-target="#' . $m[1] . '" type="button" role="tab" aria-controls="' . $m[1] . '" aria-selected="false">'
      . $m[2] . '</button></li>' . chr(10);
  }
}

// Add Bootstrap tab-pane class to extra_tab_html divs (legacy tab format uses plain <div id='tab_X'>)
$extra_tab_panes = '';
if (!empty($fields->extra_tab_html)) {
  $extra_tab_panes = preg_replace("/<div id='(tab_\d+)'>/", "<div id='$1' class='tab-pane fade'>", $fields->extra_tab_html);
}
?>
<h1 id="heading_title"><?php echo MENU_HEADING_INVENTORY . ' - ' . TEXT_SKU . '# ' . $cInfo->sku . ' (' . $cInfo->description_short . ')'; ?></h1>

<div class="pkg-wrap">

<!-- Bootstrap nav tabs -->
<ul class="nav nav-tabs fb-tabs" id="invTabs" role="tablist">
<?php
$first_tab = true;
foreach ($cInfo->tab_list as $value):
  $tab_id     = 'tab_' . $value['tag'];
  $active_cls = $first_tab ? ' active' : '';
  $aria_sel   = $first_tab ? 'true' : 'false';
?>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo $active_cls; ?>" id="<?php echo $tab_id; ?>-tab"
            data-bs-toggle="tab" data-bs-target="#<?php echo $tab_id; ?>"
            type="button" role="tab"
            aria-controls="<?php echo $tab_id; ?>" aria-selected="<?php echo $aria_sel; ?>">
      <?php echo $value['text']; ?>
    </button>
  </li>
<?php
  $first_tab = false;
endforeach;
echo $extra_nav_items;
?>
</ul>

<div class="tab-content fb-tab-content" id="invTabContent">
<?php
$first_tab = true;
foreach ($cInfo->tab_list as $value):
  $tab_id     = 'tab_' . $value['tag'];
  $pane_class = 'tab-pane fade' . ($first_tab ? ' show active' : '');
  $tab_file   = file_exists(DIR_FS_WORKING . 'custom/pages/main/' . $value['file'] . '.php')
                  ? DIR_FS_WORKING . 'custom/pages/main/' . $value['file'] . '.php'
                  : DIR_FS_WORKING . 'pages/main/' . $value['file'] . '.php';
?>
  <div class="<?php echo $pane_class; ?>" id="<?php echo $tab_id; ?>" role="tabpanel" aria-labelledby="<?php echo $tab_id; ?>-tab">
    <?php include($tab_file); ?>
  </div>
<?php
  $first_tab = false;
endforeach;
echo $extra_tab_panes;

if (isset($extra_inventory_tabs) && is_array($extra_inventory_tabs)) {
  foreach ($extra_inventory_tabs as $tabs) {
    $file_path = DIR_FS_WORKING . 'custom/pages/main/' . $tabs['tab_filename'] . '.php';
    if (file_exists($file_path)) require($file_path);
  }
}
?>
</div><!-- /tab-content -->

</div><!-- /pkg-wrap -->
</form>

<?php if ($can_print_labels && !empty($cInfo->sku)): gen_pull_language('labels'); ?>
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
var lblModalUrl     = <?php echo json_encode(html_href_link(FILENAME_DEFAULT, 'module=labels&page=ajax&op=label_modal', 'SSL')); ?>;
var lblRenderUrl    = <?php echo json_encode(html_href_link(FILENAME_DEFAULT, 'module=labels&page=ajax&op=render', 'SSL')); ?>;
var lblNoItems      = <?php echo json_encode(TEXT_LABEL_NO_ITEMS); ?>;
var lblNoPermission = <?php echo json_encode(TEXT_LABEL_PRINT_NO_PERMISSION); ?>;

function lblOpenModal(sku) {
  var content = document.getElementById('lblModalContent');
  content.innerHTML = '<div class="modal-body"><div class="text-center py-3">Loading...</div></div>';
  var modal = new bootstrap.Modal(document.getElementById('lblModal'));
  modal.show();
  fetch(lblModalUrl + '&sku=' + encodeURIComponent(sku))
    .then(function(r){ return r.text(); })
    .then(function(html){
      if (html.indexOf('<error>') !== -1) {
        content.innerHTML = '<div class="modal-body text-danger">' + lblNoPermission + '</div>';
      } else {
        content.innerHTML = html;
      }
    })
    .catch(function(e){ content.innerHTML = '<div class="modal-body text-danger">Error loading modal</div>'; });
}

function lblResetQtyFields() {
  document.getElementById('lbl_qty').value = 1;
  document.getElementById('lbl_count').value = 1;
}

function lblPrint(preselectSku) {
  var templateId = document.getElementById('lbl_template_id').value;
  var qty        = parseInt(document.getElementById('lbl_qty').value, 10) || 1;
  var count      = parseInt(document.getElementById('lbl_count').value, 10) || 1;
  var errDiv     = document.getElementById('lbl_error');
  errDiv.style.display = 'none';

  var skus = preselectSku ? [{sku: preselectSku, qty: qty, count: count}] : [];
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
        lblResetQtyFields();
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
    .then(function(){ lblResetQtyFields(); })
    .catch(function(e){ errDiv.textContent = 'Print error: ' + e; errDiv.style.display = ''; });
}
</script>
<?php endif; ?>
