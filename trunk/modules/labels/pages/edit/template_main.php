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
//  Path: /modules/labels/pages/edit/template_main.php

$is_new = ($cInfo['id'] == 0);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=labels&page=main', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
if ($security_level > 1) {
  $toolbar->icon_list['save']['params'] = 'onclick="document.getElementById(\'label_form\').submit()"';
}
$toolbar->add_help('07.04.07');
echo $toolbar->build_toolbar();
?>
<h1><?php echo $is_new ? TEXT_ADD_TEMPLATE : TEXT_EDIT_TEMPLATE; ?></h1>

<div class="pkg-wrap">

<form id="label_form" method="post" action="<?php echo html_href_link(FILENAME_DEFAULT, 'module=labels&page=edit', 'SSL'); ?>">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?php echo (int)$cInfo['id']; ?>">

  <div class="row">
    <div class="col-lg-7">

      <!-- Template settings -->
      <div class="card fb-card mb-3">
        <div class="card-header py-1 fb-widget-header"><?php echo $is_new ? TEXT_ADD_TEMPLATE : TEXT_EDIT_TEMPLATE; ?></div>
        <div class="card-body">
          <div class="row mb-2">
            <label class="col-sm-3 col-form-label"><?php echo TEXT_LABEL_NAME; ?></label>
            <div class="col-sm-9">
              <input type="text" name="name" class="form-control form-control-sm" maxlength="100"
                     value="<?php echo htmlspecialchars($cInfo['name'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>
          <div class="row mb-2">
            <label class="col-sm-3 col-form-label"><?php echo TEXT_WIDTH_MM; ?></label>
            <div class="col-sm-3">
              <input type="number" name="width_mm" class="form-control form-control-sm" step="0.5" min="1"
                     value="<?php echo htmlspecialchars((string)$cInfo['width_mm'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <label class="col-sm-3 col-form-label"><?php echo TEXT_HEIGHT_MM; ?></label>
            <div class="col-sm-3">
              <input type="number" name="height_mm" class="form-control form-control-sm" step="0.5" min="1"
                     value="<?php echo htmlspecialchars((string)$cInfo['height_mm'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>
          <div class="row mb-2">
            <label class="col-sm-3 col-form-label"><?php echo TEXT_PRINTER_TYPE; ?></label>
            <div class="col-sm-5">
              <select name="printer_type" id="printer_type" class="form-select form-select-sm" onchange="toggleZpl(this.value)">
                <option value="html"<?php echo $cInfo['printer_type'] === 'html' ? ' selected' : ''; ?>><?php echo TEXT_PRINTER_TYPE_HTML; ?></option>
                <option value="zpl"<?php echo $cInfo['printer_type'] === 'zpl'  ? ' selected' : ''; ?>><?php echo TEXT_PRINTER_TYPE_ZPL;  ?></option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- HTML template -->
      <div class="card fb-card mb-3">
        <div class="card-header py-1 fb-widget-header"><?php echo TEXT_HTML_TEMPLATE; ?></div>
        <div class="card-body">
          <textarea name="html_template" class="form-control form-control-sm font-monospace" rows="10"
                    placeholder="<div style=&quot;padding:4px&quot;><strong>{{ sku }}</strong><br>{{ description }}<br>{{ barcode_svg | raw }}<br>{{ qrcode_svg | raw }}</div>"><?php echo htmlspecialchars($cInfo['html_template'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>

      <!-- ZPL template (shown only when printer_type=zpl) -->
      <div class="card fb-card mb-3" id="zpl_card"<?php echo $cInfo['printer_type'] !== 'zpl' ? ' style="display:none"' : ''; ?>>
        <div class="card-header py-1 fb-widget-header"><?php echo TEXT_ZPL_TEMPLATE; ?></div>
        <div class="card-body">
          <textarea name="zpl_template" class="form-control form-control-sm font-monospace" rows="10"
                    placeholder="^XA&#10;^FO20,20^A0N,28,28^FD{{ sku }}^FS&#10;^FO20,55^A0N,20,20^FD{{ description }}^FS&#10;^FO20,80^BCN,50,Y,N,N^FD{{ sku }}^FS&#10;^FO20,150^BQN,2,4^FDLA,{{ sku }}^FS&#10;^XZ"><?php echo htmlspecialchars($cInfo['zpl_template'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
          <div class="form-text"><?php echo TEXT_PRINTER_TYPE_ZPL; ?> — use plain text variables (no <code>| raw</code> needed).</div>
        </div>
      </div>

      <!-- Preview area -->
      <div class="card fb-card mb-3">
        <div class="card-header py-1 fb-widget-header"><?php echo TEXT_PREVIEW; ?></div>
        <div class="card-body">
          <button type="button" class="btn btn-primary btn-sm mb-2" onclick="previewLabel()"><?php echo TEXT_PREVIEW; ?></button>
          <div id="label-preview" style="border:1px dashed #aaa; display:inline-block; min-width:50px; min-height:20px;"></div>
        </div>
      </div>

    </div>
    <div class="col-lg-5">

      <!-- Variable reference -->
      <div class="fb-fieldset mb-3">
        <div class="fb-fieldset-legend"><?php echo TEXT_VARIABLE_REFERENCE; ?></div>
        <table class="table table-sm table-borderless mb-0" style="font-size:0.85em">
          <tbody>
            <tr><td><code>{{ sku }}</code></td><td><?php echo TEXT_VAR_SKU; ?></td></tr>
            <tr><td><code>{{ description }}</code></td><td><?php echo TEXT_VAR_DESCRIPTION; ?></td></tr>
            <tr><td><code>{{ short_description }}</code></td><td><?php echo TEXT_VAR_SHORT_DESC; ?></td></tr>
            <tr><td><code>{{ price }}</code></td><td><?php echo TEXT_VAR_PRICE; ?></td></tr>
            <tr><td><code>{{ cost }}</code></td><td><?php echo TEXT_VAR_COST; ?></td></tr>
            <tr><td><code>{{ upc }}</code></td><td><?php echo TEXT_VAR_UPC; ?></td></tr>
            <tr><td><code>{{ weight }}</code></td><td><?php echo TEXT_VAR_WEIGHT; ?></td></tr>
            <tr><td><code>{{ barcode_svg | raw }}</code></td><td><?php echo TEXT_VAR_BARCODE_SVG; ?></td></tr>
            <tr><td><code>{{ qrcode_svg | raw }}</code></td><td><?php echo TEXT_VAR_QRCODE_SVG; ?></td></tr>
            <tr><td><code>{{ pack_qty }}</code></td><td><?php echo TEXT_VAR_QTY_PER_ITEM; ?></td></tr>
          </tbody>
        </table>
      </div>

    </div>
  </div>

</form>

</div><!-- /pkg-wrap -->

<script>
function toggleZpl(val) {
  document.getElementById('zpl_card').style.display = (val === 'zpl') ? '' : 'none';
}

function previewLabel() {
  var form = document.getElementById('label_form');
  var meta = document.querySelector('meta[name="csrf-token"]');
  var data = new FormData();
  data.append('csrf_token',    meta ? meta.getAttribute('content') : '');
  data.append('html_template', form.querySelector('[name=html_template]').value);
  data.append('width_mm',      form.querySelector('[name=width_mm]').value);
  data.append('height_mm',     form.querySelector('[name=height_mm]').value);

  fetch('<?php echo html_href_link(FILENAME_DEFAULT, 'module=labels&page=ajax&op=label_preview', 'SSL'); ?>', {
    method: 'POST',
    body: data
  })
  .then(function(r){ return r.text(); })
  .then(function(html){ document.getElementById('label-preview').innerHTML = html; })
  .catch(function(e){ console.error('Preview error:', e); });
}
</script>
