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
//  Path: /modules/quickbooks_sync/pages/main/template_main.php
//
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
$toolbar->add_help('10.QUICKBOOKS.03');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>

<?php if (!$set_up): ?>
<div class="alert alert-warning py-2">
  <?php echo htmlspecialchars(QUICKBOOKS_SYNC_NOT_SET_UP); ?>
  <?php if (($_SESSION['admin_security'][SECURITY_ID_CONFIGURATION] ?? 0) > 0): ?>
  <a href="<?php echo html_href_link(FILENAME_DEFAULT, 'module=quickbooks_sync&amp;page=admin', 'SSL'); ?>"><?php echo QUICKBOOKS_SYNC_SETTINGS_LINK; ?></a>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card fb-card mb-3">
  <div class="card-body p-2">
    <form id="qs-range" method="get" action="<?php echo html_href_link(FILENAME_DEFAULT, '', 'SSL'); ?>" class="row g-2 align-items-end">
      <input type="hidden" name="module" value="quickbooks_sync">
      <input type="hidden" name="page" value="main">
      <div class="col-auto">
        <label class="form-label mb-0" for="date_from"><?php echo QUICKBOOKS_SYNC_LBL_FROM; ?></label>
        <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" required value="<?php echo htmlspecialchars($date_from); ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-0" for="date_to"><?php echo QUICKBOOKS_SYNC_LBL_TO; ?></label>
        <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" required value="<?php echo htmlspecialchars($date_to); ?>">
      </div>
      <div class="col-auto">
        <div class="form-check mb-1">
          <input class="form-check-input" type="checkbox" id="credit_memos" name="credit_memos" value="1"<?php echo $credit_memos ? ' checked' : ''; ?>>
          <label class="form-check-label" for="credit_memos"><?php echo QUICKBOOKS_SYNC_LBL_CREDIT_MEMOS; ?></label>
        </div>
      </div>
      <div class="col-auto">
        <button type="button" class="btn btn-primary btn-sm"
          hx-get="<?php echo html_href_link(FILENAME_DEFAULT, 'module=quickbooks_sync&amp;page=ajax&amp;op=preview', 'SSL'); ?>"
          hx-include="#date_from, #date_to, #credit_memos" hx-target="#qs-preview" hx-swap="innerHTML" hx-indicator="#qs-busy"><?php echo QUICKBOOKS_SYNC_BTN_PREVIEW; ?></button>
        <button type="submit" class="btn btn-primary btn-sm" name="action" value="invoices"><?php echo QUICKBOOKS_SYNC_BTN_INVOICES; ?></button>
        <button type="submit" class="btn btn-primary btn-sm" name="action" value="credit_memos"><?php echo QUICKBOOKS_SYNC_BTN_CREDITS; ?></button>
        <span id="qs-busy" class="htmx-indicator spinner-border spinner-border-sm ms-2" role="status"></span>
      </div>
    </form>
  </div>
</div>

<div id="qs-preview"></div>
