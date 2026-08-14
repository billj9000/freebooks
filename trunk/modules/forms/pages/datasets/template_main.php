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
//  Path: /modules/forms/pages/datasets/template_main.php
//
use FreeBooks\Forms\Catalog\DatasetTier;

$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['print']['show']  = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['save']['show']   = $forms_ds_editing;
$toolbar->icon_list['save']['params'] = 'onclick="document.getElementById(\'forms_ds_form\').submit()"';
// add_icon() rather than assigning into icon_list: 'new' is not one of the five
// the toolbar defines, and an invented entry carries no 'order' — which
// build_toolbar() feeds to array_multisort(), fatally, when the key arrays end
// up different lengths.
if (!$forms_ds_editing) {
  $toolbar->add_icon('new', 'onclick="location.href = \''
      . html_href_link(FILENAME_DEFAULT, 'module=forms&page=datasets&action=new', 'SSL') . '\'"', 2);
}
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \''
    . html_href_link(FILENAME_DEFAULT, $forms_ds_editing ? 'module=forms&page=datasets' : '', 'SSL') . '\'"';
echo $toolbar->build_toolbar();
?>
<h1><?php echo FORMS_DS_TITLE; ?></h1>
<p class="text-muted"><?php echo FORMS_DS_INTRO; ?></p>

<?php if (!$forms_ds_editing) { ?>

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DS_TIER_CUSTOM; ?></div>
  <div class="card-body p-0">
    <table class="table table-borderless table-hover table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th><?php echo FORMS_DS_COL_NAME; ?></th>
          <th><?php echo FORMS_DS_COL_LABEL; ?></th>
          <th><?php echo FORMS_DS_COL_TABLES; ?></th>
          <th class="text-end"><?php echo FORMS_DS_COL_FIELDS; ?></th>
          <th class="text-end">&nbsp;</th>
        </tr>
      </thead>
      <tbody>
<?php if (!$forms_ds_custom) { ?>
        <tr><td colspan="5" class="text-muted p-2"><?php echo FORMS_DS_NONE; ?></td></tr>
<?php } else { $row = 0; foreach ($forms_ds_custom as $dataset) { ?>
        <tr class="<?php echo ($row++ % 2) ? 'odd' : 'even'; ?>">
          <td><code><?php echo htmlspecialchars($dataset->id); ?></code></td>
          <td><?php echo htmlspecialchars($dataset->label); ?></td>
          <td class="text-muted small"><?php echo htmlspecialchars(implode(', ', $dataset->tables())); ?></td>
          <td class="text-end"><?php echo count($dataset->fields); ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-primary btn-sm" href="<?php
              echo html_href_link(FILENAME_DEFAULT,
                'module=forms&page=datasets&action=edit&dsID=' . urlencode($dataset->id), 'SSL'); ?>">
              <?php echo TEXT_EDIT; ?>
            </a>
            <a class="btn btn-primary btn-sm" href="<?php
              echo html_href_link(FILENAME_DEFAULT,
                'module=forms&page=datasets&action=delete&dsID=' . urlencode($dataset->id), 'SSL'); ?>"
               onclick="return confirm(<?php echo htmlspecialchars(json_encode(FORMS_DS_CONFIRM_DELETE)); ?>)">
              <?php echo TEXT_DELETE; ?>
            </a>
          </td>
        </tr>
<?php } } ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DS_TIER_SHIPPED; ?></div>
  <div class="card-body p-0">
    <p class="text-muted small px-2 pt-2 mb-1"><?php echo FORMS_DS_SHIPPED_NOTE; ?></p>
    <table class="table table-borderless table-hover table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th><?php echo FORMS_DS_COL_NAME; ?></th>
          <th><?php echo FORMS_DS_COL_LABEL; ?></th>
          <th><?php echo FORMS_DS_COL_TABLES; ?></th>
          <th class="text-end"><?php echo FORMS_DS_COL_FIELDS; ?></th>
        </tr>
      </thead>
      <tbody>
<?php $row = 0; foreach ($forms_ds_shipped as $dataset) { ?>
        <tr class="<?php echo ($row++ % 2) ? 'odd' : 'even'; ?>">
          <td><code><?php echo htmlspecialchars($dataset->id); ?></code></td>
          <td><?php echo htmlspecialchars($dataset->label); ?></td>
          <td class="text-muted small"><?php echo htmlspecialchars(implode(', ', $dataset->tables())); ?></td>
          <td class="text-end"><?php echo count($dataset->fields); ?></td>
        </tr>
<?php } ?>
      </tbody>
    </table>
  </div>
</div>

<?php } else {
  // ── the editor ────────────────────────────────────────────────────────────
  $record = $forms_ds_record;
?>
<form id="forms_ds_form" method="post" action="<?php
  echo html_href_link(FILENAME_DEFAULT, 'module=forms&page=datasets&action=save', 'SSL'); ?>">
<input type="hidden" name="existing" value="<?php echo $record['existing'] ? '1' : ''; ?>">

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DS_LEGEND_ABOUT; ?></div>
  <div class="card-body py-2">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label mb-1" for="ds_id"><?php echo FORMS_DS_NAME; ?></label>
        <input type="text" class="form-control form-control-sm" id="ds_id" name="id"
               value="<?php echo htmlspecialchars($record['id']); ?>"
               <?php echo $record['existing'] ? 'readonly' : ''; ?> required>
        <div class="form-text"><?php echo FORMS_DS_NAME_HELP; ?></div>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1" for="ds_label"><?php echo FORMS_DS_LABEL; ?></label>
        <input type="text" class="form-control form-control-sm" id="ds_label" name="label"
               value="<?php echo htmlspecialchars($record['label']); ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label mb-1" for="ds_description"><?php echo FORMS_DS_DESCRIPTION; ?></label>
        <input type="text" class="form-control form-control-sm" id="ds_description" name="description"
               value="<?php echo htmlspecialchars($record['description']); ?>">
      </div>
    </div>
  </div>
</div>

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DS_LEGEND_JOINS; ?></div>
  <div class="card-body py-2">
    <p class="text-muted small mb-2"><?php echo FORMS_DS_NO_SQL_NOTE; ?></p>
    <div class="row g-2 mb-3">
      <div class="col-md-4">
        <label class="form-label mb-1" for="baseTable"><?php echo FORMS_DS_BASE_TABLE; ?></label>
        <?php echo html_pull_down_menu('baseTable', $forms_ds_tables, $record['baseTable'],
            'class="form-select form-select-sm" required'); ?>
      </div>
    </div>

    <div id="ds_joins"></div>
    <button type="button" class="btn btn-primary btn-sm" onclick="formsAddJoin()">
      <?php echo FORMS_DS_ADD_JOIN; ?>
    </button>
  </div>
</div>

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DS_LEGEND_FIELDS; ?></div>
  <div class="card-body py-2">
    <div id="ds_fields"></div>
    <button type="button" class="btn btn-primary btn-sm" onclick="formsAddField()">
      <?php echo FORMS_DS_ADD_FIELD; ?>
    </button>
  </div>
</div>
</form>
<?php } ?>
