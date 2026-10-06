<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
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
//  Path: /modules/shipping/pages/dhl_pickup/template_main.php
//
echo html_form('dhl_pickup', FILENAME_DEFAULT, 'module=shipping&page=dhl_pickup', 'post') . chr(10);
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('pickup_id', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="if(window.opener) window.opener.location.reload(); self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
echo $toolbar->build_toolbar();
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES); };
?>
<h1><?php echo DHL_PICKUP_TITLE; ?></h1>
<div style="max-width:780px">

  <div class="card mb-3">
    <div class="card-header py-2 fb-widget-header"><?php echo DHL_PICKUP_NEW_HEADING; ?></div>
    <div class="card-body">
      <div class="row g-2 mb-2">
        <div class="col-sm-4">
          <label class="form-label mb-0" for="pickup_date"><?php echo DHL_PICKUP_DATE; ?></label>
          <input type="date" class="form-control form-control-sm" id="pickup_date" name="pickup_date"
                 value="<?php echo $h($date); ?>" min="<?php echo $today; ?>" max="<?php echo $last_day; ?>"
                 onchange="dhlPickupDay(this.value)">
        </div>
        <div class="col-sm-4">
          <label class="form-label mb-0" for="ready"><?php echo DHL_PICKUP_READY; ?></label>
          <input type="time" class="form-control form-control-sm" id="ready" name="ready" value="<?php echo $h($form['ready']); ?>">
        </div>
        <div class="col-sm-4">
          <label class="form-label mb-0" for="close"><?php echo DHL_PICKUP_CLOSE; ?></label>
          <input type="time" class="form-control form-control-sm" id="close" name="close" value="<?php echo $h($form['close']); ?>">
        </div>
        <div class="col-sm-8">
          <label class="form-label mb-0" for="location"><?php echo DHL_PICKUP_LOCATION; ?></label>
          <input type="text" class="form-control form-control-sm" id="location" name="location" maxlength="80" value="<?php echo $h($form['location']); ?>">
        </div>
        <div class="col-sm-4">
          <label class="form-label mb-0" for="location_type"><?php echo DHL_PICKUP_LOCATION_TYPE; ?></label>
          <select class="form-select form-select-sm" id="location_type" name="location_type">
            <option value="business"<?php echo $form['location_type'] === 'business' ? ' selected' : ''; ?>><?php echo DHL_PICKUP_BUSINESS; ?></option>
            <option value="residence"<?php echo $form['location_type'] === 'residence' ? ' selected' : ''; ?>><?php echo DHL_PICKUP_RESIDENCE; ?></option>
          </select>
        </div>
        <div class="col-sm-6">
          <label class="form-label mb-0" for="contact"><?php echo DHL_PICKUP_CONTACT; ?></label>
          <input type="text" class="form-control form-control-sm" id="contact" name="contact" maxlength="64" value="<?php echo $h($form['contact']); ?>">
        </div>
        <div class="col-sm-6">
          <label class="form-label mb-0" for="phone"><?php echo DHL_PICKUP_PHONE; ?></label>
          <input type="text" class="form-control form-control-sm" id="phone" name="phone" maxlength="32" value="<?php echo $h($form['phone']); ?>">
        </div>
        <div class="col-12">
          <label class="form-label mb-0" for="instructions"><?php echo DHL_PICKUP_INSTRUCTIONS; ?></label>
          <textarea class="form-control form-control-sm" id="instructions" name="instructions" rows="3"><?php echo $h($form['instructions']); ?></textarea>
        </div>
      </div>

      <h6 class="mt-3"><?php echo DHL_PICKUP_SHIPMENTS; ?></h6>
<?php if (!$shipments): ?>
      <p class="text-muted"><?php echo DHL_PICKUP_NO_SHIPMENTS; ?></p>
<?php else: ?>
      <table class="table table-sm table-borderless table-hover mb-2">
        <thead class="table-dark">
          <tr>
            <th></th>
            <th><?php echo SHIPPING_TEXT_SHIPMENT_ID; ?></th>
            <th><?php echo SHIPPING_TEXT_REFERENCE_ID; ?></th>
            <th><?php echo SHIPPING_TEXT_TRACKING_NUM; ?></th>
            <th><?php echo DHL_PICKUP_PARCELS; ?></th>
          </tr>
        </thead>
        <tbody>
<?php foreach ($shipments as $sid => $s): ?>
          <tr>
            <td><input type="checkbox" class="form-check-input" name="ship[]" value="<?php echo $sid; ?>"<?php echo isset($ticked[$sid]) ? ' checked' : ''; ?>></td>
            <td><?php echo $sid; ?></td>
            <td><?php echo $h($s['ref_id']); ?></td>
            <td><code><?php echo $h($s['tracking']); ?></code></td>
            <td>
<?php   if ($s['data']):
          $kg = 0;
          foreach ($s['data']['packages'] as $pkg) $kg += (float)$pkg['weight'];
          echo count($s['data']['packages']) . ' / ' . round($kg, 2) . ' kg';
        else: ?>
              <div class="small text-muted mb-1"><?php echo DHL_PICKUP_ENTER_FIGURES; ?></div>
              <div class="d-flex gap-1 align-items-center flex-wrap">
                <input type="number" class="form-control form-control-sm" style="width:4.5em" name="n_<?php echo $sid; ?>" min="1" max="99"
                       value="<?php echo $h($_POST['n_' . $sid] ?? '1'); ?>" title="<?php echo DHL_PICKUP_PARCELS; ?>">
                <input type="number" class="form-control form-control-sm" style="width:6em" name="wt_<?php echo $sid; ?>" min="0" step="0.001"
                       value="<?php echo $h($_POST['wt_' . $sid] ?? ''); ?>" placeholder="kg" title="<?php echo DHL_PICKUP_WEIGHT_KG; ?>">
<?php     foreach (array('len', 'wid', 'hgt') as $dim): ?>
                <input type="number" class="form-control form-control-sm" style="width:4.5em" name="<?php echo $dim . '_' . $sid; ?>" min="0" step="1"
                       value="<?php echo $h($_POST[$dim . '_' . $sid] ?? ''); ?>" placeholder="cm" title="<?php echo DHL_PICKUP_SIZE_CM; ?>">
<?php     endforeach; ?>
                <label class="form-check-label small ms-1">
                  <input type="checkbox" class="form-check-input" name="cust_<?php echo $sid; ?>" value="1"<?php echo ($_POST ? !empty($_POST['cust_' . $sid]) : true) ? ' checked' : ''; ?>>
                  <?php echo DHL_TEXT_IS_CUSTOMS; ?>
                </label>
              </div>
<?php   endif; ?>
<?php   if ($s['booking'] !== ''): ?>
              <div class="small text-muted"><?php echo $h(sprintf(DHL_PICKUP_IN_BOOKING, $s['booking'])); ?></div>
<?php   endif; ?>
<?php   if ($s['elsewhere'] !== ''): ?>
              <div class="small text-muted"><?php echo $h(sprintf(DHL_PICKUP_ELSEWHERE, $s['elsewhere'])); ?></div>
<?php   endif; ?>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
      <button type="button" class="btn btn-primary btn-sm" onclick="dhlPickupSubmit('book', '')"><?php echo DHL_PICKUP_BOOK_BTN; ?></button>
<?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header py-2 fb-widget-header"><?php echo DHL_PICKUP_BOOKED_HEADING; ?> &mdash; <?php echo gen_locale_date($date); ?></div>
    <div class="card-body p-0">
<?php if (!$pickups): ?>
      <p class="text-muted p-3 mb-0"><?php echo DHL_PICKUP_NONE; ?></p>
<?php else: ?>
      <table class="table table-sm table-borderless table-hover mb-0">
        <thead class="table-dark">
          <tr>
            <th><?php echo DHL_PICKUP_CONFIRMATION; ?></th>
            <th><?php echo DHL_PICKUP_READY . ' &ndash; ' . DHL_PICKUP_CLOSE; ?></th>
            <th><?php echo SHIPPING_TEXT_SHIPMENT_ID; ?></th>
            <th><?php echo TEXT_STATUS; ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
<?php foreach ($pickups as $p): ?>
          <tr>
            <td><code><?php echo $h($p['confirmation']); ?></code></td>
            <td><?php echo $h($p['ready_time'] . ($p['close_time'] !== '' ? ' – ' . $p['close_time'] : '')); ?></td>
            <td><?php echo $h(str_replace(',', ', ', $p['shipment_ids'])); ?></td>
            <td><?php echo $p['status'] === 'booked' ? DHL_PICKUP_STATUS_BOOKED : DHL_PICKUP_STATUS_CANCELLED; ?></td>
            <td class="text-end">
<?php   if ($p['status'] === 'booked' && $date >= $today): ?>
              <div class="d-flex gap-1 justify-content-end">
                <select class="form-select form-select-sm w-auto" name="reason_<?php echo (int)$p['id']; ?>" title="<?php echo DHL_PICKUP_CANCEL_REASON; ?>">
<?php     foreach ($cancel_reasons as $key => $text): ?>
                  <option value="<?php echo $key; ?>"><?php echo $h($text); ?></option>
<?php     endforeach; ?>
                </select>
                <button type="button" class="btn btn-secondary btn-sm"
                        onclick="if(confirm('<?php echo addslashes(DHL_PICKUP_CANCEL_CONFIRM); ?>')) dhlPickupSubmit('cancel', '<?php echo (int)$p['id']; ?>')"><?php echo DHL_PICKUP_CANCEL_BTN; ?></button>
              </div>
<?php   endif; ?>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
<?php endif; ?>
    </div>
  </div>

</div>
</form>
