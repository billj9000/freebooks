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
//  Path: /modules/quickbooks_sync/pages/admin/template_main.php
//
require_once(DIR_FS_WORKING . 'functions/quickbooks_sync.php');

echo html_form('admin', FILENAME_DEFAULT, gen_get_all_get_params(['action'])) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=admin', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
if ($can_save) $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
else           $toolbar->icon_list['save']['show']   = false;
$toolbar->add_help('10.QUICKBOOKS.01');
echo $toolbar->build_toolbar();

$rows_url = html_href_link(FILENAME_DEFAULT, 'module=quickbooks_sync&amp;page=ajax&amp;op=rows', 'SSL');
$disabled = $can_save ? '' : ' disabled';
$text     = fn($name, $value, $max = 100, $list = '') => '<input type="text" class="form-control form-control-sm" id="' . $name . '" name="' . $name . '" maxlength="' . $max . '" value="' . htmlspecialchars((string)$value) . '"' . ($list ? ' list="' . $list . '"' : '') . $disabled . '>';
$datalist = function (string $id, array $options): string {
  $html = '<datalist id="' . $id . '">' . chr(10);
  foreach ($options as $option) $html .= '  <option value="' . htmlspecialchars($option['value']) . '" label="' . htmlspecialchars($option['label']) . '"></option>' . chr(10);
  return $html . '</datalist>' . chr(10);
};
$hint     = fn($phrase) => '<div class="form-text">' . htmlspecialchars($phrase) . '</div>';
?>
<h1><?php echo PAGE_TITLE; ?></h1>

<?php echo $datalist('qs-qb-items', $qb_lists['items']) . $datalist('qs-qb-accounts', $qb_lists['accounts']) . $datalist('qs-qb-tax', $qb_lists['tax_codes']); ?>

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo QUICKBOOKS_SYNC_HEAD_CONNECTION; ?></div>
  <div class="card-body">
    <p class="mb-2">
      <?php if ($connected): ?>
        <?php echo htmlspecialchars(sprintf(QUICKBOOKS_SYNC_CONNECTED,
          $link['company_name'] !== '' ? $link['company_name'] : $link['realm_id'],
          $link['environment'] === 'production' ? QUICKBOOKS_SYNC_OPT_PRODUCTION : QUICKBOOKS_SYNC_OPT_SANDBOX,
          gen_locale_date(substr((string)$link['connected_at'], 0, 10)))); ?>
        <?php if ($link['refresh_expires']): ?>
        <br><span class="text-muted"><?php echo htmlspecialchars(sprintf(QUICKBOOKS_SYNC_LAPSES, gen_locale_date(date('Y-m-d', $link['refresh_expires'])))); ?></span>
        <?php endif; ?>
      <?php else: ?>
        <?php echo htmlspecialchars(QUICKBOOKS_SYNC_NOT_CONNECTED); ?>
      <?php endif; ?>
    </p>
    <?php if ($lists_error !== ''): ?>
    <div class="alert alert-warning py-2"><?php echo htmlspecialchars($lists_error); ?></div>
    <?php endif; ?>
    <div class="row g-2 mb-2">
      <div class="col-md-3">
        <label class="form-label" for="environment"><?php echo QUICKBOOKS_SYNC_LBL_ENVIRONMENT; ?></label>
        <?php echo html_pull_down_menu('environment', [['id' => 'sandbox', 'text' => QUICKBOOKS_SYNC_OPT_SANDBOX], ['id' => 'production', 'text' => QUICKBOOKS_SYNC_OPT_PRODUCTION]],
                $link['environment'], 'class="form-select form-select-sm"' . $disabled); ?>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="client_id"><?php echo QUICKBOOKS_SYNC_LBL_CLIENT_ID; ?></label>
        <?php echo $text('client_id', $link['client_id'], 64); ?>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="client_secret"><?php echo QUICKBOOKS_SYNC_LBL_CLIENT_SECRET; ?></label>
        <input type="password" class="form-control form-control-sm" id="client_secret" name="client_secret" autocomplete="new-password"
          placeholder="<?php echo $link['client_secret'] !== '' ? htmlspecialchars(QUICKBOOKS_SYNC_SECRET_KEPT) : ''; ?>"<?php echo $disabled; ?>>
        <?php if ($link['client_secret'] !== ''): ?>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="clear_secret" name="clear_secret" value="1"<?php echo $disabled; ?>>
          <label class="form-check-label" for="clear_secret"><?php echo QUICKBOOKS_SYNC_LBL_CLEAR_SECRET; ?></label>
        </div>
        <?php endif; ?>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="qs_redirect"><?php echo QUICKBOOKS_SYNC_LBL_REDIRECT; ?></label>
        <input type="text" class="form-control form-control-sm" id="qs_redirect" readonly value="<?php echo htmlspecialchars(quickbooks_oauth::redirect_uri()); ?>">
        <?php echo $hint(QUICKBOOKS_SYNC_HELP_REDIRECT); ?>
      </div>
    </div>
    <?php echo $hint(QUICKBOOKS_SYNC_HELP_CLIENT); ?>
    <?php if ($can_save): ?>
    <div class="mt-2">
      <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('connect')"><?php echo $connected ? QUICKBOOKS_SYNC_BTN_RECONNECT : QUICKBOOKS_SYNC_BTN_CONNECT; ?></button>
      <?php if ($connected || $link['refresh_token'] !== ''): ?>
      <button type="button" class="btn btn-danger btn-sm" onclick="if (confirm('<?php echo addslashes(QUICKBOOKS_SYNC_CONFIRM_DISCONNECT); ?>')) submitToDo('disconnect')"><?php echo QUICKBOOKS_SYNC_BTN_DISCONNECT; ?></button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card fb-card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo QUICKBOOKS_SYNC_HEAD_GENERAL; ?></div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label" for="home_country"><?php echo QUICKBOOKS_SYNC_LBL_HOME_COUNTRY; ?></label>
          <?php echo html_pull_down_menu('home_country', $countries, $options['home_country'], 'class="form-select form-select-sm"' . $disabled); ?>
          <?php echo $hint(QUICKBOOKS_SYNC_HELP_HOME_COUNTRY); ?>
        </div>
        <div class="mb-2">
          <label class="form-label" for="country_source"><?php echo QUICKBOOKS_SYNC_LBL_COUNTRY_SOURCE; ?></label>
          <?php echo html_pull_down_menu('country_source', [['id' => 'shipping', 'text' => QUICKBOOKS_SYNC_OPT_SHIPPING], ['id' => 'billing', 'text' => QUICKBOOKS_SYNC_OPT_BILLING]],
                  $options['country_source'], 'class="form-select form-select-sm"' . $disabled); ?>
          <?php echo $hint(QUICKBOOKS_SYNC_HELP_COUNTRY_SOURCE); ?>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-sm-6">
            <label class="form-label" for="due_days"><?php echo QUICKBOOKS_SYNC_LBL_DUE_DAYS; ?></label>
            <input type="number" class="form-control form-control-sm" id="due_days" name="due_days" min="0" max="365" step="1"
              value="<?php echo (int)$options['due_days']; ?>"<?php echo $disabled; ?>>
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="rounding_tolerance"><?php echo QUICKBOOKS_SYNC_LBL_TOLERANCE; ?></label>
            <input type="number" class="form-control form-control-sm" id="rounding_tolerance" name="rounding_tolerance" min="0" max="100" step="0.01"
              value="<?php echo htmlspecialchars((string)$options['rounding_tolerance']); ?>"<?php echo $disabled; ?>>
          </div>
        </div>
        <?php echo $hint(QUICKBOOKS_SYNC_HELP_TOLERANCE); ?>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="use_payment_ref" name="use_payment_ref" value="1"
            <?php echo $options['use_payment_reference'] ? 'checked' : ''; ?><?php echo $disabled; ?>>
          <label class="form-check-label" for="use_payment_ref"><?php echo QUICKBOOKS_SYNC_LBL_PAYMENT_REF; ?></label>
          <?php echo $hint(QUICKBOOKS_SYNC_HELP_PAYMENT_REF); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card fb-card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo QUICKBOOKS_SYNC_HEAD_QUICKBOOKS; ?></div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label" for="default_item"><?php echo QUICKBOOKS_SYNC_LBL_DEFAULT_ITEM; ?></label>
          <?php echo $text('default_item', $mapping['default_item'], 100, 'qs-qb-items'); ?>
          <?php echo $hint(QUICKBOOKS_SYNC_HELP_DEFAULT_ITEM); ?>
        </div>
        <div class="mb-2">
          <label class="form-label" for="rounding_item"><?php echo QUICKBOOKS_SYNC_LBL_ROUNDING_ITEM; ?></label>
          <?php echo $text('rounding_item', $mapping['rounding_item'], 100, 'qs-qb-items'); ?>
        </div>
        <div class="mb-2">
          <label class="form-label" for="discount_account"><?php echo QUICKBOOKS_SYNC_LBL_DISCOUNT_ACCOUNT; ?></label>
          <?php echo $text('discount_account', $mapping['discount_account'], 100, 'qs-qb-accounts'); ?>
          <?php echo $hint(QUICKBOOKS_SYNC_HELP_DISCOUNT_ACCOUNT); ?>
        </div>
        <div class="mb-2">
          <label class="form-label" for="no_vat_code"><?php echo QUICKBOOKS_SYNC_LBL_NO_VAT_CODE; ?></label>
          <?php echo $text('no_vat_code', $mapping['no_vat_code'], 100, 'qs-qb-tax'); ?>
          <?php echo $hint(QUICKBOOKS_SYNC_HELP_NO_VAT_CODE); ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo QUICKBOOKS_SYNC_HEAD_ITEMS; ?></div>
  <div class="card-body p-2">
    <div class="d-sm-flex justify-content-between align-items-start mb-2">
      <div class="form-text mt-0"><?php echo htmlspecialchars(QUICKBOOKS_SYNC_HELP_ITEMS); ?></div>
      <div class="form-check ms-sm-3 text-nowrap">
        <input class="form-check-input" type="checkbox" id="qs_show_all" name="all" value="1"
          hx-get="<?php echo $rows_url; ?>&amp;kind=items" hx-trigger="change" hx-target="#qs-items" hx-swap="innerHTML">
        <label class="form-check-label" for="qs_show_all"><?php echo QUICKBOOKS_SYNC_LBL_SHOW_ALL; ?></label>
      </div>
    </div>
    <table class="table table-borderless table-hover table-sm mb-0">
      <thead class="table-dark">
        <tr><th><?php echo QUICKBOOKS_SYNC_COL_ACCOUNT; ?></th><th><?php echo QUICKBOOKS_SYNC_COL_DOMESTIC; ?></th><th><?php echo QUICKBOOKS_SYNC_COL_FOREIGN; ?></th></tr>
      </thead>
      <tbody id="qs-items"><?php echo quickbooks_sync_item_rows($accounts, $can_save); ?></tbody>
    </table>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card fb-card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo QUICKBOOKS_SYNC_HEAD_TAX; ?></div>
      <div class="card-body p-2">
        <div class="form-text mt-0 mb-2"><?php echo htmlspecialchars(QUICKBOOKS_SYNC_HELP_TAX); ?></div>
        <table class="table table-borderless table-hover table-sm mb-0">
          <thead class="table-dark">
            <tr><th><?php echo QUICKBOOKS_SYNC_COL_TAX_RATE; ?></th><th><?php echo QUICKBOOKS_SYNC_COL_QB_TAX; ?></th></tr>
          </thead>
          <tbody>
          <?php if (!$tax_rates): ?>
            <tr><td colspan="2" class="text-muted"><?php echo htmlspecialchars(QUICKBOOKS_SYNC_NO_TAX_RATES); ?></td></tr>
          <?php endif; ?>
          <?php foreach ($tax_rates as $rate): ?>
            <tr>
              <td><?php echo $rate['rate'] === null
                    ? htmlspecialchars($rate['id'] . ' ' . QUICKBOOKS_SYNC_NOT_A_RATE)
                    : htmlspecialchars($rate['description']) . ' (' . htmlspecialchars(rtrim(rtrim(number_format($rate['rate'], 4, '.', ''), '0'), '.')) . '%)'; ?></td>
              <td><input type="text" class="form-control form-control-sm" maxlength="255" list="qs-qb-tax" name="tax[<?php echo htmlspecialchars($rate['id']); ?>]"
                value="<?php echo htmlspecialchars($rate['qb_value']); ?>"<?php echo $disabled; ?>></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card fb-card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo QUICKBOOKS_SYNC_HEAD_CUSTOMERS; ?></div>
      <div class="card-body p-2">
        <div class="form-text mt-0 mb-2"><?php echo htmlspecialchars(QUICKBOOKS_SYNC_HELP_CUSTOMERS); ?></div>
        <table class="table table-borderless table-hover table-sm mb-2">
          <thead class="table-dark">
            <tr><th><?php echo QUICKBOOKS_SYNC_COL_CUSTOMER; ?></th><th><?php echo QUICKBOOKS_SYNC_COL_QB_CUSTOMER; ?></th></tr>
          </thead>
          <tbody id="qs-customers">
          <?php foreach ($customers as $customer) echo quickbooks_sync_customer_row($customer['fb_key'], $customer['qb_value'], $can_save); ?>
          <?php if ($can_save) echo quickbooks_sync_customer_row('', '', true); ?>
          </tbody>
        </table>
        <?php if ($can_save): ?>
        <button type="button" class="btn btn-primary btn-sm"
          hx-get="<?php echo $rows_url; ?>&amp;kind=customer" hx-target="#qs-customers" hx-swap="beforeend"><?php echo QUICKBOOKS_SYNC_BTN_ADD_CUSTOMER; ?></button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</form>
