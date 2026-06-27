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
//  Path: /modules/labels/ajax/label_modal.php
//
// Returns the inner HTML of the Print Labels modal body+footer.

validate_ajax_user(SECURITY_ID_LABELS_PRINT);

gen_pull_language('labels');

$preselect_sku = db_prepare_input($_GET['sku'] ?? '');

$templates = array();
$result = $db->Execute("SELECT id, name, printer_type FROM " . TABLE_LABEL_TEMPLATES . " ORDER BY name");
while (!$result->EOF) {
  $templates[] = $result->fields;
  $result->MoveNext();
}

$render_url = html_href_link(FILENAME_DEFAULT, 'module=labels&page=ajax&op=render', 'SSL');
?>
<div class="modal-header">
  <h5 class="modal-title"><?php echo TEXT_PRINT_LABELS; ?></h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<?php if (empty($templates)): ?>
  <p class="text-muted"><?php echo TEXT_NO_TEMPLATES_DEFINED; ?></p>
<?php else: ?>
  <div class="mb-3">
    <label class="form-label"><?php echo TEXT_SELECT_TEMPLATE; ?></label>
    <select id="lbl_template_id" class="form-select form-select-sm">
      <?php foreach ($templates as $t): ?>
      <option value="<?php echo (int)$t['id']; ?>" data-type="<?php echo htmlspecialchars($t['printer_type'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?>
        (<?php echo $t['printer_type'] === 'zpl' ? 'ZPL' : 'HTML'; ?>)
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label"><?php echo TEXT_QTY_PER_ITEM; ?></label>
    <input type="number" id="lbl_qty" class="form-control form-control-sm" value="1" min="1" max="999" style="width:80px">
  </div>
  <div id="lbl_error" class="text-danger small" style="display:none"></div>
<?php endif; ?>
</div>
<div class="modal-footer">
  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo TEXT_CANCEL; ?></button>
<?php if (!empty($templates)): ?>
  <button type="button" class="btn btn-primary btn-sm" onclick="lblPrint(<?php echo htmlspecialchars(json_encode($preselect_sku), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo TEXT_PRINT_LABELS; ?></button>
<?php endif; ?>
</div>
