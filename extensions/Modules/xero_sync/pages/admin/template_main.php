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
//  Path: /modules/xero_sync/pages/admin/template_main.php
//
require_once(DIR_FS_WORKING . 'functions/xero_sync.php');

echo html_form('admin', FILENAME_DEFAULT, gen_get_all_get_params(['action'])) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=admin', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
if ($can_save) $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
else           $toolbar->icon_list['save']['show']   = false;
$toolbar->add_help('10.XERO.01');
echo $toolbar->build_toolbar();

$rows_url = html_href_link(FILENAME_DEFAULT, 'module=xero_sync&amp;page=ajax&amp;op=rows', 'SSL');
$disabled = $can_save ? '' : ' disabled';
$text     = fn($name, $value, $max = 255) => '<input type="text" class="form-control form-control-sm" id="' . $name . '" name="' . $name . '" maxlength="' . $max . '" value="' . htmlspecialchars((string)$value) . '"' . $disabled . '>';
$hint     = fn($phrase) => '<div class="form-text">' . htmlspecialchars($phrase) . '</div>';
?>
<h1><?php echo PAGE_TITLE; ?></h1>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card fb-card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo XERO_SYNC_HEAD_GENERAL; ?></div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label" for="home_country"><?php echo XERO_SYNC_LBL_HOME_COUNTRY; ?></label>
          <?php echo html_pull_down_menu('home_country', $countries, $options['home_country'], 'class="form-select form-select-sm"' . $disabled); ?>
          <?php echo $hint(XERO_SYNC_HELP_HOME_COUNTRY); ?>
        </div>
        <div class="mb-2">
          <label class="form-label" for="country_source"><?php echo XERO_SYNC_LBL_COUNTRY_SOURCE; ?></label>
          <?php echo html_pull_down_menu('country_source', [['id' => 'shipping', 'text' => XERO_SYNC_OPT_SHIPPING], ['id' => 'billing', 'text' => XERO_SYNC_OPT_BILLING]],
                  $options['country_source'], 'class="form-select form-select-sm"' . $disabled); ?>
          <?php echo $hint(XERO_SYNC_HELP_COUNTRY_SOURCE); ?>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-sm-6">
            <label class="form-label" for="due_days"><?php echo XERO_SYNC_LBL_DUE_DAYS; ?></label>
            <input type="number" class="form-control form-control-sm" id="due_days" name="due_days" min="0" max="365" step="1"
              value="<?php echo (int)$options['due_days']; ?>"<?php echo $disabled; ?>>
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="rounding_tolerance"><?php echo XERO_SYNC_LBL_TOLERANCE; ?></label>
            <input type="number" class="form-control form-control-sm" id="rounding_tolerance" name="rounding_tolerance" min="0" max="100" step="0.01"
              value="<?php echo htmlspecialchars((string)$options['rounding_tolerance']); ?>"<?php echo $disabled; ?>>
          </div>
        </div>
        <?php echo $hint(XERO_SYNC_HELP_TOLERANCE); ?>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="use_payment_ref" name="use_payment_ref" value="1"
            <?php echo $options['use_payment_reference'] ? 'checked' : ''; ?><?php echo $disabled; ?>>
          <label class="form-check-label" for="use_payment_ref"><?php echo XERO_SYNC_LBL_PAYMENT_REF; ?></label>
          <?php echo $hint(XERO_SYNC_HELP_PAYMENT_REF); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card fb-card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo XERO_SYNC_HEAD_XERO; ?></div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label" for="default_account"><?php echo XERO_SYNC_LBL_DEFAULT_ACCOUNT; ?></label>
          <?php echo $text('default_account', $mapping['default_account'], 32); ?>
          <?php echo $hint(XERO_SYNC_HELP_DEFAULT_ACCOUNT); ?>
        </div>
        <div class="mb-2">
          <label class="form-label" for="rounding_account"><?php echo XERO_SYNC_LBL_ROUNDING_ACCOUNT; ?></label>
          <?php echo $text('rounding_account', $mapping['rounding_account'], 32); ?>
        </div>
        <div class="mb-2">
          <label class="form-label" for="no_vat_type"><?php echo XERO_SYNC_LBL_NO_VAT_TYPE; ?></label>
          <?php echo $text('no_vat_type', $mapping['no_vat_type']); ?>
          <?php echo $hint(XERO_SYNC_HELP_NO_VAT_TYPE); ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo XERO_SYNC_HEAD_ACCOUNTS; ?></div>
  <div class="card-body p-2">
    <div class="d-sm-flex justify-content-between align-items-start mb-2">
      <div class="form-text mt-0"><?php echo htmlspecialchars(XERO_SYNC_HELP_ACCOUNTS); ?></div>
      <div class="form-check ms-sm-3 text-nowrap">
        <input class="form-check-input" type="checkbox" id="xs_show_all" name="all" value="1"
          hx-get="<?php echo $rows_url; ?>&amp;kind=accounts" hx-trigger="change" hx-target="#xs-accounts" hx-swap="innerHTML">
        <label class="form-check-label" for="xs_show_all"><?php echo XERO_SYNC_LBL_SHOW_ALL; ?></label>
      </div>
    </div>
    <table class="table table-borderless table-hover table-sm mb-0">
      <thead class="table-dark">
        <tr><th><?php echo XERO_SYNC_COL_ACCOUNT; ?></th><th><?php echo XERO_SYNC_COL_DOMESTIC; ?></th><th><?php echo XERO_SYNC_COL_FOREIGN; ?></th></tr>
      </thead>
      <tbody id="xs-accounts"><?php echo xero_sync_account_rows($accounts, $can_save); ?></tbody>
    </table>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card fb-card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo XERO_SYNC_HEAD_TAX; ?></div>
      <div class="card-body p-2">
        <div class="form-text mt-0 mb-2"><?php echo htmlspecialchars(XERO_SYNC_HELP_TAX); ?></div>
        <table class="table table-borderless table-hover table-sm mb-0">
          <thead class="table-dark">
            <tr><th><?php echo XERO_SYNC_COL_TAX_RATE; ?></th><th><?php echo XERO_SYNC_COL_XERO_TAX; ?></th></tr>
          </thead>
          <tbody>
          <?php if (!$tax_rates): ?>
            <tr><td colspan="2" class="text-muted"><?php echo htmlspecialchars(XERO_SYNC_NO_TAX_RATES); ?></td></tr>
          <?php endif; ?>
          <?php foreach ($tax_rates as $rate): ?>
            <tr>
              <td><?php echo $rate['rate'] === null
                    ? htmlspecialchars($rate['id'] . ' ' . XERO_SYNC_NOT_A_RATE)
                    : htmlspecialchars($rate['description']) . ' (' . htmlspecialchars(rtrim(rtrim(number_format($rate['rate'], 4, '.', ''), '0'), '.')) . '%)'; ?></td>
              <td><input type="text" class="form-control form-control-sm" maxlength="255" name="tax[<?php echo htmlspecialchars($rate['id']); ?>]"
                value="<?php echo htmlspecialchars($rate['xero_value']); ?>"<?php echo $disabled; ?>></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card fb-card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo XERO_SYNC_HEAD_CONTACTS; ?></div>
      <div class="card-body p-2">
        <div class="form-text mt-0 mb-2"><?php echo htmlspecialchars(XERO_SYNC_HELP_CONTACTS); ?></div>
        <table class="table table-borderless table-hover table-sm mb-2">
          <thead class="table-dark">
            <tr><th><?php echo XERO_SYNC_COL_CUSTOMER; ?></th><th><?php echo XERO_SYNC_COL_XERO_CONTACT; ?></th></tr>
          </thead>
          <tbody id="xs-contacts">
          <?php foreach ($contacts as $contact) echo xero_sync_contact_row($contact['fb_key'], $contact['xero_value'], $can_save); ?>
          <?php if ($can_save) echo xero_sync_contact_row('', '', true); ?>
          </tbody>
        </table>
        <?php if ($can_save): ?>
        <button type="button" class="btn btn-primary btn-sm"
          hx-get="<?php echo $rows_url; ?>&amp;kind=contact" hx-target="#xs-contacts" hx-swap="beforeend"><?php echo XERO_SYNC_BTN_ADD_CONTACT; ?></button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</form>
