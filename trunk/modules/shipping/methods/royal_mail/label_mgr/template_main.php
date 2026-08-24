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
//  Path: /modules/shipping/methods/royal_mail/label_mgr/template_main.php
//
echo html_form('label_mgr', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['params']  = 'onclick="submitToDo(\'label\')"';
$toolbar->icon_list['print']['text']    = SHIPPING_TEXT_SAVE_PRINT_LABEL;
$toolbar->icon_list['print']['icon']    = 'devices/media-floppy.png';
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('09.01.10');
echo $toolbar->build_toolbar();
$country_list = gen_get_countries();
$sel_sm = 'class="form-select form-select-sm"';
?>
<h1><?php echo MODULE_SHIPPING_ROYAL_MAIL_TEXT_TITLE; ?></h1>

<!-- === Sender / Recipient address row === -->
<div class="row g-2 mb-3 align-items-start" style="max-width:780px">

  <!-- Sender -->
  <div class="col">
    <div class="card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_SENDER; ?></div>
      <div class="card-body py-2">
        <div class="row g-1">
          <div class="col-12">
            <label class="form-label"><?php echo GEN_PRIMARY_NAME; ?></label>
            <input type="text" class="form-control form-control-sm" name="sender_primary_name" id="sender_primary_name"
                   value="<?php echo htmlspecialchars($sInfo->sender_primary_name ?? ''); ?>" maxlength="60">
          </div>
          <div class="col-12">
            <label class="form-label"><?php echo GEN_CONTACT; ?></label>
            <input type="text" class="form-control form-control-sm" name="sender_contact" id="sender_contact"
                   value="<?php echo htmlspecialchars($sInfo->sender_contact ?? ''); ?>" maxlength="60">
          </div>
          <div class="col-12">
            <label class="form-label"><?php echo GEN_ADDRESS1; ?></label>
            <input type="text" class="form-control form-control-sm" name="sender_address1" id="sender_address1"
                   value="<?php echo htmlspecialchars($sInfo->sender_address1 ?? ''); ?>" maxlength="50">
          </div>
          <div class="col-12">
            <label class="form-label"><?php echo GEN_ADDRESS2; ?></label>
            <input type="text" class="form-control form-control-sm" name="sender_address2" id="sender_address2"
                   value="<?php echo htmlspecialchars($sInfo->sender_address2 ?? ''); ?>" maxlength="50">
          </div>
          <div class="col-7">
            <label class="form-label"><?php echo GEN_CITY_TOWN; ?></label>
            <input type="text" class="form-control form-control-sm" name="sender_city_town" id="sender_city_town"
                   value="<?php echo htmlspecialchars($sInfo->sender_city_town ?? ''); ?>" maxlength="50">
          </div>
          <div class="col-5">
            <label class="form-label"><?php echo GEN_POSTAL_CODE; ?></label>
            <input type="text" class="form-control form-control-sm" name="sender_postal_code" id="sender_postal_code"
                   value="<?php echo htmlspecialchars($sInfo->sender_postal_code ?? ''); ?>" maxlength="15">
          </div>
          <div class="col-12">
            <label class="form-label"><?php echo GEN_COUNTRY; ?></label>
            <?php echo html_pull_down_menu('sender_country_code', $country_list, $sInfo->sender_country_code ?? '',
              $sel_sm . ' onchange="toggleCustoms()"'); ?>
          </div>
          <div class="col-6">
            <label class="form-label"><?php echo GEN_TELEPHONE1; ?></label>
            <input type="text" class="form-control form-control-sm" name="sender_telephone1" id="sender_telephone1"
                   value="<?php echo htmlspecialchars($sInfo->sender_telephone1 ?? ''); ?>" maxlength="20">
          </div>
          <div class="col-6">
            <label class="form-label"><?php echo GEN_EMAIL; ?></label>
            <input type="text" class="form-control form-control-sm" name="sender_email" id="sender_email"
                   value="<?php echo htmlspecialchars($sInfo->sender_email ?? ''); ?>" maxlength="60">
          </div>
          <div class="col-6">
            <label class="form-label"><?php echo ACT_VAT_NUMBER; ?></label>
            <input type="text" class="form-control form-control-sm" name="sender_vat_number" id="sender_vat_number"
                   value="<?php echo htmlspecialchars($sInfo->sender_vat_number ?? ''); ?>" maxlength="32">
          </div>
          <div class="col-6">
            <label class="form-label"><?php echo ACT_EORI_NUMBER; ?></label>
            <input type="text" class="form-control form-control-sm" name="sender_eori_number" id="sender_eori_number"
                   value="<?php echo htmlspecialchars($sInfo->sender_eori_number ?? ''); ?>" maxlength="32">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Swap button -->
  <div class="col-auto text-center align-self-center">
    <button type="button" class="btn btn-primary btn-sm" onclick="swapShipAddresses()"
            title="<?php echo SHIPPING_TEXT_SWAP_ADDRESSES; ?>">&harr;</button>
  </div>

  <!-- Recipient -->
  <div class="col">
    <div class="card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_RECIPIENT; ?></div>
      <div class="card-body py-2">
        <div class="row g-1">
          <div class="col-12">
            <label class="form-label"><?php echo GEN_PRIMARY_NAME; ?></label>
            <input type="text" class="form-control form-control-sm" name="ship_primary_name" id="ship_primary_name"
                   value="<?php echo htmlspecialchars($sInfo->ship_primary_name ?? ''); ?>" maxlength="60">
          </div>
          <div class="col-12">
            <label class="form-label"><?php echo GEN_CONTACT; ?></label>
            <input type="text" class="form-control form-control-sm" name="ship_contact" id="ship_contact"
                   value="<?php echo htmlspecialchars($sInfo->ship_contact ?? ''); ?>" maxlength="60">
          </div>
          <div class="col-12">
            <label class="form-label"><?php echo GEN_ADDRESS1; ?></label>
            <input type="text" class="form-control form-control-sm" name="ship_address1" id="ship_address1"
                   value="<?php echo htmlspecialchars($sInfo->ship_address1 ?? ''); ?>" maxlength="50">
          </div>
          <div class="col-12">
            <label class="form-label"><?php echo GEN_ADDRESS2; ?></label>
            <input type="text" class="form-control form-control-sm" name="ship_address2" id="ship_address2"
                   value="<?php echo htmlspecialchars($sInfo->ship_address2 ?? ''); ?>" maxlength="50">
          </div>
          <div class="col-7">
            <label class="form-label"><?php echo GEN_CITY_TOWN; ?></label>
            <input type="text" class="form-control form-control-sm" name="ship_city_town" id="ship_city_town"
                   value="<?php echo htmlspecialchars($sInfo->ship_city_town ?? ''); ?>" maxlength="50">
          </div>
          <div class="col-5">
            <label class="form-label"><?php echo GEN_POSTAL_CODE; ?></label>
            <input type="text" class="form-control form-control-sm" name="ship_postal_code" id="ship_postal_code"
                   value="<?php echo htmlspecialchars($sInfo->ship_postal_code ?? ''); ?>" maxlength="15">
          </div>
          <div class="col-12">
            <label class="form-label"><?php echo GEN_COUNTRY; ?></label>
            <?php echo html_pull_down_menu('ship_country_code', $country_list, $sInfo->ship_country_code ?? '',
              $sel_sm . ' onchange="toggleCustoms()"'); ?>
          </div>
          <div class="col-6">
            <label class="form-label"><?php echo GEN_TELEPHONE1; ?></label>
            <input type="text" class="form-control form-control-sm" name="ship_telephone1" id="ship_telephone1"
                   value="<?php echo htmlspecialchars($sInfo->ship_telephone1 ?? ''); ?>" maxlength="20">
          </div>
          <div class="col-6">
            <label class="form-label"><?php echo GEN_EMAIL; ?></label>
            <input type="text" class="form-control form-control-sm" name="ship_email" id="ship_email"
                   value="<?php echo htmlspecialchars($sInfo->ship_email ?? ''); ?>" maxlength="60">
          </div>
          <div class="col-6">
            <label class="form-label"><?php echo ACT_VAT_NUMBER; ?></label>
            <input type="text" class="form-control form-control-sm" name="ship_vat_number" id="ship_vat_number"
                   value="<?php echo htmlspecialchars($sInfo->ship_vat_number ?? ''); ?>" maxlength="32">
          </div>
          <div class="col-6">
            <label class="form-label"><?php echo ACT_EORI_NUMBER; ?></label>
            <input type="text" class="form-control form-control-sm" name="ship_eori_number" id="ship_eori_number"
                   value="<?php echo htmlspecialchars($sInfo->ship_eori_number ?? ''); ?>" maxlength="32">
          </div>
        </div>
      </div>
    </div>
  </div>

</div><!-- end address row -->

<!-- === Summary + Shipment Details row === -->
<div class="row g-2 mb-3">
  <div class="col-md-5">
    <div class="card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo SHIPPNIG_SUMMARY; ?></div>
      <div class="card-body py-2">
        <div class="row g-2 align-items-center mb-1">
          <div class="col-auto"><label class="form-label mb-0"><?php echo SHIPPING_TOTAL_WEIGHT; ?></label></div>
          <div class="col-auto">
            <input type="text" class="form-control form-control-sm text-end" style="width:8em"
                   name="total_weight" id="total_weight"
                   value="<?php echo htmlspecialchars($sInfo->total_weight ?? ''); ?>" readonly>
          </div>
        </div>
        <div class="row g-2 align-items-center">
          <div class="col-auto"><label class="form-label mb-0"><?php echo SHIPPING_TOTAL_VALUE; ?></label></div>
          <div class="col-auto">
            <?php $home_currency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'GBP'; ?>
            <div class="input-group input-group-sm">
              <input type="text" class="form-control form-control-sm text-end" style="width:8em"
                     name="total_value" id="total_value"
                     value="<?php echo htmlspecialchars($sInfo->total_value ?? ''); ?>" readonly>
              <span class="input-group-text" style="font-size:11px;border-radius:0;padding:2px 5px">
                <?php echo htmlspecialchars($home_currency); ?>
              </span>
            </div>
          </div>
          <?php
          $order_currency = $sInfo->currencies_code ?? '';
          if ($order_currency && $order_currency !== $home_currency):
            $eur_value = number_format(round((float)($sInfo->total_value ?? 0) * (float)($sInfo->currencies_value ?: 1), 2), 2);
          ?>
          <div class="col-auto">
            <span class="badge" style="background-color:#2191c0;font-size:11px;border-radius:0">
              <?php echo $eur_value . ' ' . htmlspecialchars($order_currency); ?>
            </span>
          </div>
          <?php endif; ?>
        </div>
        <input type="hidden" name="currencies_code"  value="<?php echo htmlspecialchars($sInfo->currencies_code  ?? ''); ?>">
        <input type="hidden" name="currencies_value" value="<?php echo htmlspecialchars($sInfo->currencies_value ?? '1'); ?>">
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_SHIPMENT_DETAILS; ?></div>
      <div class="card-body py-2">
        <div class="row g-2 align-items-center mb-1">
          <label class="col-auto col-form-label col-form-label-sm"><?php echo SHIPPING_TEXT_SHIPMENT_DATE; ?></label>
          <div class="col-auto">
            <input type="date" class="form-control form-control-sm" name="ship_date"
                   value="<?php echo htmlspecialchars($sInfo->ship_date ?? date('Y-m-d')); ?>">
          </div>
        </div>
        <div class="row g-2 align-items-center mb-1">
          <label class="col-auto col-form-label col-form-label-sm"><?php echo SHIPPING_SERVICE_TYPE; ?></label>
          <div class="col-auto">
            <?php echo html_pull_down_menu('ship_method', gen_build_pull_down($shipping_methods), $sInfo->ship_method,
              $sel_sm . ' onchange="filterFormatOptions()"'); ?>
          </div>
        </div>
        <div class="row g-2 align-items-center mb-1">
          <label class="col-auto col-form-label col-form-label-sm"><?php echo TEXT_INVOICE; ?></label>
          <div class="col-auto">
            <input type="text" class="form-control form-control-sm" name="purchase_invoice_id"
                   value="<?php echo htmlspecialchars($sInfo->purchase_invoice_id ?? ''); ?>">
          </div>
        </div>
        <div class="row g-2 align-items-center mb-1">
          <label class="col-auto col-form-label col-form-label-sm"><?php echo TEXT_PO_NUMBER; ?></label>
          <div class="col-auto">
            <input type="text" class="form-control form-control-sm" name="purch_order_id"
                   value="<?php echo htmlspecialchars($sInfo->purch_order_id ?? ''); ?>">
          </div>
        </div>
        <div class="row g-2 align-items-center">
          <div class="col-auto">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="include_returns_label"
                     id="include_returns_label" value="1">
              <label class="form-check-label" for="include_returns_label">
                <?php echo RM_TEXT_RETURNS_LABEL; ?>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- === Customs section (hidden for domestic) === -->
<div id="customs_section" class="mb-3">
  <div class="card">
    <div class="card-header py-1 fb-widget-header"><?php echo RM_CUSTOMS_SECTION; ?></div>
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <label class="col-auto col-form-label col-form-label-sm"><?php echo RM_TEXT_DECLARATION_CATEGORY; ?></label>
        <div class="col-auto">
          <?php
          $categories = array(
            array('id' => 'saleOfGoods',      'text' => RM_DECL_SALE_OF_GOODS),
            array('id' => 'gift',             'text' => RM_DECL_GIFT),
            array('id' => 'documents',        'text' => RM_DECL_DOCUMENTS),
            array('id' => 'commercialSample', 'text' => RM_DECL_COMMERCIAL_SAMPLE),
            array('id' => 'returnedGoods',    'text' => RM_DECL_RETURNED_GOODS),
            array('id' => 'other',            'text' => RM_DECL_OTHER),
          );
          echo html_pull_down_menu('customs_declaration_category', $categories,
            $sInfo->customs_declaration_category ?? 'saleOfGoods', $sel_sm);
          ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- === Packages table === -->
<div class="card mb-3">
  <div class="card-header py-1 fb-widget-header d-flex align-items-center gap-2 flex-wrap">
    <span><?php echo SHIPPING_PACKAGE_DETAILS; ?></span>
    <div class="ms-auto d-flex gap-2 align-items-center">
      <span class="form-label mb-0"><?php echo TEXT_WEIGHT; ?>:</span>
      <?php echo html_pull_down_menu('pkg_weight_unit', gen_build_pull_down($shipping_defaults['weight_unit']),
        $sInfo->pkg_weight_unit ?? SHIPPING_DEFAULT_WEIGHT_UNIT, $sel_sm . ' style="min-width:5rem"'); ?>
    </div>
  </div>
  <div class="card-body py-2 px-2">
    <table class="table table-sm table-bordered mb-2" style="width:auto">
      <thead class="table-light">
        <tr>
          <th style="width:2.5em"></th>
          <th><?php echo TEXT_QUANTITY; ?></th>
          <th><?php echo TEXT_WEIGHT; ?></th>
          <th><?php echo RM_TEXT_PACKAGE_FORMAT; ?></th>
        </tr>
      </thead>
      <tbody id="item_table">
        <?php if (!empty($sInfo->package)):
          $rowCnt = 1;
          foreach ($sInfo->package as $package): ?>
        <tr>
          <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 lh-1"
                    onclick="if(confirm('<?php echo addslashes(SHIPPING_DELETE_CONFIRM); ?>')) removeRow(<?php echo $rowCnt; ?>)">&times;</button>
          </td>
          <td><input type="text" class="form-control form-control-sm text-end" style="width:4.5em"
                     name="qty_<?php echo $rowCnt; ?>" id="qty_<?php echo $rowCnt; ?>" value="1"></td>
          <td><input type="text" class="form-control form-control-sm text-end" style="width:6em"
                     name="wt_<?php echo $rowCnt; ?>"  id="wt_<?php echo $rowCnt; ?>"
                     value="<?php echo htmlspecialchars($package['weight']); ?>"
                     onchange="updateWeight()"></td>
          <td><?php
            $allowedFmt = royal_mail::allowedFormats($sInfo->ship_method ?? '');
            $fmtOptions = array_values(array_filter(array(
              array('id' => 'letter',       'text' => RM_PKG_LETTER),
              array('id' => 'largeLetter',  'text' => RM_PKG_LARGE_LETTER),
              array('id' => 'smallParcel',  'text' => RM_PKG_SMALL_PARCEL),
              array('id' => 'mediumParcel', 'text' => RM_PKG_MEDIUM_PARCEL),
              array('id' => 'largeParcel',  'text' => RM_PKG_LARGE_PARCEL),
              array('id' => 'parcel',       'text' => RM_PKG_PARCEL),
              array('id' => 'documents',    'text' => RM_PKG_DOCUMENTS),
            ), function($opt) use ($allowedFmt) { return in_array($opt['id'], $allowedFmt); }));
            $curFmt = in_array($package['format'] ?? '', $allowedFmt) ? $package['format'] : ($allowedFmt[0] ?? 'parcel');
            echo html_pull_down_menu('fmt_' . $rowCnt, $fmtOptions, $curFmt,
              'id="fmt_' . $rowCnt . '" ' . $sel_sm . ' style="min-width:9em"');
          ?></td>
        </tr>
          <?php $rowCnt++; endforeach;
        endif; ?>
      </tbody>
    </table>
    <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()"><?php echo TEXT_ADD; ?></button>
  </div>
</div>

<?php if (!empty($sInfo->line_items)): ?>
<!-- === Line items / Customs declaration (hidden for domestic) === -->
<div id="line_items_section" class="card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo RM_LINE_ITEMS_HEADER; ?></div>
  <div class="card-body py-2 px-2">
    <input type="hidden" name="num_line_items" value="<?php echo count($sInfo->line_items); ?>">
    <table class="table table-sm table-bordered mb-2">
      <thead class="table-light">
        <tr>
          <th><?php echo TEXT_SKU; ?></th>
          <th><?php echo TEXT_DESCRIPTION; ?></th>
          <th><?php echo RM_TEXT_ORIGIN_COUNTRY; ?></th>
          <th><?php echo RM_TEXT_HS_CODE; ?></th>
          <th class="text-end"><?php echo TEXT_QUANTITY; ?></th>
          <th><?php echo RM_TEXT_UNIT_WEIGHT; ?> (<?php echo htmlspecialchars($sInfo->pkg_weight_unit ?? SHIPPING_DEFAULT_WEIGHT_UNIT); ?>)</th>
          <th><?php echo RM_TEXT_UNIT_VALUE; ?> (<?php echo htmlspecialchars($home_currency); ?>)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sInfo->line_items as $li => $item): $n = $li + 1; ?>
        <tr>
          <td class="align-middle">
            <?php echo htmlspecialchars($item['sku']); ?>
            <input type="hidden" name="li_sku_<?php echo $n; ?>" value="<?php echo htmlspecialchars($item['sku']); ?>">
            <input type="hidden" name="li_qty_<?php echo $n; ?>" id="li_qty_<?php echo $n; ?>" value="<?php echo (float)$item['qty']; ?>">
          </td>
          <td><input type="text" class="form-control form-control-sm"
                     name="li_desc_<?php echo $n; ?>" value="<?php echo htmlspecialchars($item['description']); ?>"
                     maxlength="50" style="min-width:12em"></td>
          <td><input type="text" class="form-control form-control-sm text-center text-uppercase"
                     style="width:4em" name="li_origin_<?php echo $n; ?>"
                     value="<?php echo htmlspecialchars($item['origin']); ?>" maxlength="2" placeholder="XX"></td>
          <td><input type="text" class="form-control form-control-sm"
                     name="li_hs_<?php echo $n; ?>" value="<?php echo htmlspecialchars($item['hs_code']); ?>"
                     maxlength="10" style="width:8em"></td>
          <td class="text-end align-middle"><?php echo number_format((float)$item['qty'], 0); ?></td>
          <td><input type="text" class="form-control form-control-sm text-end"
                     style="width:8em" name="li_wt_<?php echo $n; ?>" id="li_wt_<?php echo $n; ?>"
                     value="<?php echo number_format((float)$item['weight'], 3); ?>"
                     maxlength="10" onchange="updateWeight()"></td>
          <td>
            <input type="text" class="form-control form-control-sm text-end"
                   style="width:8em" name="li_val_<?php echo $n; ?>" id="li_val_<?php echo $n; ?>"
                   value="<?php echo number_format((float)$item['value'], 2); ?>"
                   maxlength="10" onchange="updateWeight()">
            <?php if ($order_currency && $order_currency !== $home_currency):
              $li_conv = number_format(round((float)$item['value'] * (float)($sInfo->currencies_value ?: 1), 2), 2);
            ?>
            <span class="badge" style="background-color:#2191c0;font-size:10px;border-radius:0">
              <?php echo $li_conv . ' ' . htmlspecialchars($order_currency); ?>
            </span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="text-end">
      <button type="button" class="btn btn-secondary btn-sm"
              onclick="submitToDo('save_items')"><?php echo RM_TEXT_SAVE_TO_INV; ?></button>
    </div>
  </div>
</div>
<?php endif; ?>
</form>
