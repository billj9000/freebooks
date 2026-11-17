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
//  Path: /modules/shipping/pages/popup_tracking/template_main.php
//
echo html_form('popup_tracking', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = !empty($sID);
// A carrier whose labels cannot be cancelled with it says so in its own confirmation.
$carrier_confirm = 'MODULE_SHIPPING_' . strtoupper((string)($cInfo->carrier ?? '')) . '_DELETE_CONFIRM';
$delete_confirm  = defined($carrier_confirm) ? constant($carrier_confirm) : (!empty($has_carrier_cancel) ? SHIPPING_CANCEL_CONFIRM : SHIPPING_DELETE_CONFIRM);
$toolbar->icon_list['delete']['params'] = 'onclick="if (confirm(\'' . addslashes($delete_confirm) . '\')) submitToDo(\'delete\')"';
if (!empty($label_viewer_url)) {
  $toolbar->icon_list['print']['show']   = true;
  $toolbar->icon_list['print']['params'] = 'onclick="window.open(\'' . $label_viewer_url . '\', \'label_reprint\', \'width=800,height=600\')"';
  $toolbar->icon_list['print']['text']   = defined('SHIPPING_TEXT_PRINT_LABEL') ? SHIPPING_TEXT_PRINT_LABEL : 'Reprint Label';
} else {
  $toolbar->icon_list['print']['show']   = false;
}
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('');
echo $toolbar->build_toolbar();
?>
<div class="d-flex justify-content-center mt-3">
<div class="d-inline-flex flex-column">
<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_SHIPMENT_DETAILS; ?></div>
  <div class="card-body py-2">
    <div style="display:grid;grid-template-columns:max-content 1fr;align-items:center;gap:0.3rem 0.75rem">
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_SHIPMENT_ID . SHIPPING_SET_BY_SYSTEM; ?></label>
      <?php echo html_input_field('shipment_id',  $cInfo->shipment_id,                  'class="form-control form-control-sm" readonly="readonly"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_REFERENCE_ID; ?></label>
      <?php echo html_input_field('ref_id',        $cInfo->ref_id,                       'class="form-control form-control-sm" readonly="readonly"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_CARRIER; ?></label>
      <?php echo html_pull_down_menu('carrier',    $methods,         $cInfo->carrier,    'class="form-select form-select-sm" disabled="disabled"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_SERVICE; ?></label>
      <?php echo html_pull_down_menu('method',     $carrier_methods, $cInfo->method,     'class="form-select form-select-sm" disabled="disabled"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_TRACKING_NUM; ?></label>
<?php $track_url = shipping_tracking_url($cInfo->carrier, $cInfo->tracking_id); ?>
<?php if ($track_url): ?>
      <div class="input-group input-group-sm">
        <?php echo html_input_field('tracking_id', $cInfo->tracking_id, 'class="form-control form-control-sm" readonly="readonly"'); ?>
        <a class="btn btn-primary btn-sm d-inline-flex align-items-center" href="<?php echo htmlspecialchars($track_url); ?>" target="_blank" rel="noopener"><?php echo SHIPPING_TRACK; ?></a>
      </div>
<?php else: ?>
      <?php echo html_input_field('tracking_id',   $cInfo->tracking_id,                  'class="form-control form-control-sm" readonly="readonly"'); ?>
<?php endif; ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_SHIPMENT_DATE; ?></label>
      <?php echo html_input_field('ship_date',     gen_locale_date($cInfo->ship_date),   'class="form-control form-control-sm" readonly="readonly"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_EXPECTED_DATE; ?></label>
      <?php echo html_input_field('deliver_date',  gen_locale_date($cInfo->deliver_date),'class="form-control form-control-sm" readonly="readonly"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_COST; ?></label>
      <?php echo html_input_field('cost',          $cInfo->cost,                         'class="form-control form-control-sm" readonly="readonly"'); ?>
    </div>
  </div>
</div>
<?php if (!empty($order_shipments) && (count($order_shipments) > 1 || array_filter(array_column($order_shipments, 'items')) || !empty($outstanding_lines))): ?>
<div class="card fb-card mt-3">
  <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_ORDER_SHIPMENTS; ?></div>
  <div class="card-body p-0">
    <?php echo html_hidden_field('item_id', ''); ?>
    <table class="table table-sm table-borderless table-hover mb-0">
      <thead class="table-dark">
        <tr>
          <th><?php echo SHIPPING_TEXT_SHIPMENT_ID; ?></th>
          <th><?php echo SHIPPING_TEXT_SHIPMENT_DATE; ?></th>
          <th><?php echo SHIPPING_TEXT_TRACKING_NUM; ?></th>
          <th><?php echo SHIPPING_TEXT_CONTENTS; ?></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($order_shipments as $shipment_id => $s): ?>
        <tr<?php echo $s['log_id'] === (int)$sID || in_array($cInfo->tracking_id, $s['tracking'], true) ? ' class="table-active"' : ''; ?>>
          <td class="text-nowrap"><a href="<?php echo html_href_link(FILENAME_DEFAULT, 'module=shipping&amp;page=popup_tracking&amp;sID=' . $s['log_id'], 'SSL'); ?>"><?php echo (int)$shipment_id; ?></a>
<?php if ($s['other_ref'] !== ''): ?>
            <div class="small text-muted"><?php echo sprintf(SHIPPING_SHIPMENT_OF_ORDER, htmlspecialchars($s['other_ref'])); ?></div>
<?php endif; ?>
          </td>
          <td class="text-nowrap"><?php echo gen_locale_date(substr($s['ship_date'], 0, 10)); ?></td>
          <td><?php
  $links = array();
  foreach ($s['tracking'] as $t) {
    $url = shipping_tracking_url($s['carrier'], $t);
    $links[] = $url ? '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">' . htmlspecialchars($t) . '</a>' : htmlspecialchars($t);
  }
  echo implode(', ', $links); ?></td>
          <td>
<?php   if (!$s['items']) echo '<span class="text-muted">&mdash;</span>'; ?>
<?php   foreach ($s['items'] as $item): ?>
            <div class="text-nowrap">
              <?php echo htmlspecialchars($item['sku']) . ' &times; ' . (float)$item['qty']; ?>
<?php     if ($item['for_ref'] !== ''): ?>
              <span class="small text-muted"><?php echo sprintf(SHIPPING_LINE_FOR_ORDER, htmlspecialchars($item['for_ref'])); ?></span>
<?php     endif; ?>
<?php     if ($item['status'] === 'cancelled'): ?>
              <span class="badge bg-secondary"><?php echo SHIPPING_TEXT_CANCELLED; ?></span>
<?php     endif; ?>
<?php     if ($item['undoable']): ?>
              <button type="button" class="btn btn-secondary btn-sm py-0"
                      onclick="if (confirm('<?php echo addslashes(SHIPPING_RESTORE_CONFIRM); ?>')) { document.getElementById('item_id').value='<?php echo (int)$item['id']; ?>'; submitToDo('restore_line'); }"><?php echo SHIPPING_LINE_RESTORE; ?></button>
<?php     endif; ?>
            </div>
<?php   endforeach; ?>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php if (!empty($outstanding_lines)): ?>
<div class="card fb-card mt-3">
  <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_OUTSTANDING; ?></div>
  <div class="card-body p-0">
    <?php echo html_hidden_field('settle_line_id', '') . html_hidden_field('settle_qty', '') . html_hidden_field('settle_target', ''); ?>
    <table class="table table-sm table-borderless table-hover mb-0">
      <thead class="table-dark">
        <tr>
          <th><?php echo TEXT_ITEM; ?></th>
          <th class="text-end"><?php echo SHIPPING_TEXT_STILL_TO_SEND; ?></th>
          <th><?php echo TEXT_QUANTITY; ?></th>
          <th><?php echo SHIPPING_SETTLE_WHAT; ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($outstanding_lines as $line): $lid = (int)$line['id']; ?>
        <tr>
          <td><?php echo htmlspecialchars($line['sku']); ?>
            <div class="small text-muted"><?php echo htmlspecialchars($line['description']); ?></div></td>
          <td class="text-end"><?php echo (float)$line['remaining']; ?></td>
          <td><input type="number" id="settle_qty_<?php echo $lid; ?>" class="form-control form-control-sm" style="width:5.5rem"
                     min="0" step="any" max="<?php echo (float)$line['remaining']; ?>" value="<?php echo (float)$line['remaining']; ?>"></td>
          <td>
            <select id="settle_target_<?php echo $lid; ?>" class="form-select form-select-sm">
              <option value=""><?php echo SHIPPING_SHORT_CHOOSE; ?></option>
              <optgroup label="<?php echo htmlspecialchars(SHIPPING_SETTLE_SENT_WITH_GROUP); ?>">
<?php   if (!$contact_shipments): ?>
                <option value="" disabled="disabled"><?php echo SHIPPING_SETTLE_NO_SHIPMENTS; ?></option>
<?php   endif; ?>
<?php   foreach ($contact_shipments as $with_id => $w): ?>
                <option value="<?php echo (int)$with_id; ?>"><?php echo htmlspecialchars(sprintf(SHIPPING_SETTLE_SHIPMENT_OPTION, (int)$with_id,
                  gen_locale_date(substr($w['ship_date'], 0, 10)), $w['ref'], $w['tracking'])); ?></option>
<?php   endforeach; ?>
              </optgroup>
              <option value="cancel"><?php echo SHIPPING_TEXT_CANCELLED; ?></option>
            </select>
          </td>
          <td><button type="button" class="btn btn-primary btn-sm" onclick="settleLine(<?php echo $lid; ?>)"><?php echo SHIPPING_SETTLE_RECORD; ?></button></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
</div>
</div>
</form>
