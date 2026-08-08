<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or  |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// +-----------------------------------------------------------------+
//  Path: /modules/labels/pages/print/template_main.php

$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['print']['show']  = false;
$toolbar->add_help('07.04.07');
echo $toolbar->build_toolbar();
?>
<h1><?php echo TEXT_PRINT_LABELS; ?></h1>

<div class="pkg-wrap">

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_PRINT_LABELS; ?></div>
  <div class="card-body">

    <div class="row mb-3">
      <label class="col-sm-3 col-form-label"><?php echo TEXT_SKU_SEARCH; ?></label>
      <div class="col-sm-9">
        <input type="text" id="lbl_sku_search" name="q" class="form-control form-control-sm" autocomplete="off"
               placeholder="<?php echo TEXT_SKU_SEARCH_PLACEHOLDER; ?>"
               hx-get="<?php echo html_href_link(FILENAME_DEFAULT, 'module=labels&page=ajax&op=sku_search', 'SSL'); ?>"
               hx-trigger="keyup changed delay:300ms, keyup[key=='Enter']"
               hx-include="this"
               hx-target="#lbl_sku_results"
               hx-swap="innerHTML">
        <div id="lbl_sku_results" class="list-group mt-1" style="max-height:220px;overflow-y:auto;"></div>
        <div id="lbl_sku_selected" class="mt-2" style="display:none">
          <span class="badge bg-primary" style="font-size:0.9em">
            <span id="lbl_sku_selected_text"></span>
            <a href="#" onclick="lblClearSku();return false;" class="text-white ms-2" style="text-decoration:none">&times;</a>
          </span>
        </div>
      </div>
    </div>

    <div class="row mb-3">
      <label class="col-sm-3 col-form-label"><?php echo TEXT_SELECT_TEMPLATE; ?></label>
      <div class="col-sm-9">
      <?php if (empty($templates)): ?>
        <p class="text-muted mb-0"><?php echo TEXT_NO_TEMPLATES_DEFINED; ?></p>
      <?php else: ?>
        <select id="lbl_template_id" class="form-select form-select-sm" style="max-width:320px">
          <?php foreach ($templates as $t): ?>
          <option value="<?php echo (int)$t['id']; ?>" data-type="<?php echo htmlspecialchars($t['printer_type'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo ((int)$t['id'] === $last_template_id) ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?>
            (<?php echo $t['printer_type'] === 'zpl' ? 'ZPL' : 'HTML'; ?>)
          </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($templates)): ?>
    <div class="row mb-3">
      <label class="col-sm-3 col-form-label"><?php echo TEXT_QTY_PER_ITEM; ?></label>
      <div class="col-sm-9">
        <input type="number" id="lbl_qty" class="form-control form-control-sm" value="1" min="1" max="999" style="width:80px">
      </div>
    </div>

    <div class="row mb-3">
      <label class="col-sm-3 col-form-label"><?php echo TEXT_NUM_LABELS; ?></label>
      <div class="col-sm-9">
        <input type="number" id="lbl_count" class="form-control form-control-sm" value="1" min="1" max="999" style="width:80px">
      </div>
    </div>

    <div id="lbl_error" class="text-danger small mb-2" style="display:none"></div>

    <button type="button" class="btn btn-primary btn-sm" id="lbl_print_btn" onclick="lblPrint()" disabled><?php echo TEXT_PRINT_LABELS; ?></button>
    <?php endif; ?>

  </div>
</div>

</div><!-- /pkg-wrap -->

<script src="<?php echo DIR_WS_ADMIN; ?>modules/phreedom/includes/qz-tray/qz-tray.js"></script>
<script>
var lblRenderUrl = <?php echo json_encode(html_href_link(FILENAME_DEFAULT, 'module=labels&page=ajax&op=render', 'SSL')); ?>;
var lblSelectedSku = '';
var lblEnterArmed = false;

// Pressing Enter selects the SKU immediately if the results list already shows exactly one
// match; otherwise it forces the search to run right away (bypassing the debounce, in case the
// user typed and hit Enter before the debounced request fired) and selects once that settles,
// but only if it still narrows to exactly one match.
document.getElementById('lbl_sku_search').addEventListener('keydown', function(e) {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  var results = document.querySelectorAll('#lbl_sku_results button');
  if (results.length === 1) {
    results[0].click();
  } else {
    lblEnterArmed = true;
  }
});

document.getElementById('lbl_sku_results').addEventListener('htmx:afterSwap', function() {
  if (!lblEnterArmed) return;
  lblEnterArmed = false;
  var results = document.querySelectorAll('#lbl_sku_results button');
  if (results.length === 1) {
    results[0].click();
  }
});

function lblSelectSku(sku, description) {
  lblSelectedSku = sku;
  document.getElementById('lbl_sku_search').value = '';
  document.getElementById('lbl_sku_results').innerHTML = '';
  document.getElementById('lbl_sku_selected_text').textContent = sku + ' — ' + description;
  document.getElementById('lbl_sku_selected').style.display = '';
  var btn = document.getElementById('lbl_print_btn');
  if (btn) btn.disabled = false;
}

function lblClearSku() {
  lblSelectedSku = '';
  document.getElementById('lbl_sku_selected').style.display = 'none';
  var btn = document.getElementById('lbl_print_btn');
  if (btn) btn.disabled = true;
}

function lblResetQtyFields() {
  document.getElementById('lbl_qty').value = 1;
  document.getElementById('lbl_count').value = 1;
}

function lblPrint() {
  if (!lblSelectedSku) return;
  var templateSelect = document.getElementById('lbl_template_id');
  var templateId = templateSelect ? templateSelect.value : '';
  var qty        = parseInt(document.getElementById('lbl_qty').value, 10) || 1;
  var count      = parseInt(document.getElementById('lbl_count').value, 10) || 1;
  var errDiv     = document.getElementById('lbl_error');
  errDiv.style.display = 'none';

  var skus = [{sku: lblSelectedSku, qty: qty, count: count}];

  // Open the window NOW while still inside the click handler (user gesture).
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
