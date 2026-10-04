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
//  Path: /modules/forms/pages/admin/template_main.php
//
echo html_form('admin', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=admin', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
if ($security_level > 2) $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
else                     $toolbar->icon_list['save']['show']   = false;
// 11.03, the settings topic.
$toolbar->add_help('11.03');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div class="fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_ADMIN_CONFIG_INFO; ?></div>
  <div class="card-body p-2">
    <table class="table table-borderless table-sm mb-0">
      <tbody>
        <tr>
          <td style="white-space:nowrap"><?php echo FORMS_DEFAULT_PAPER_TEXT; ?></td>
          <?php // form-select is width:100% of its cell, so the cell needs a width
                // of its own — 1% would collapse it to a stub with the arrow
                // only. ?>
          <td style="width:14rem"><?php echo html_pull_down_menu('forms_default_paper', $forms_paper_choices, $forms_paper, 'class="form-select form-select-sm"'); ?></td>
          <td class="text-muted small"><?php echo FORMS_DEFAULT_PAPER_DESC; ?></td>
        </tr>
        <tr>
          <td style="white-space:nowrap"><?php echo FORMS_DEFAULT_ROWSPACE_TEXT; ?></td>
          <td style="width:14rem"><?php echo html_input_field('forms_default_rowspace', $forms_rowspace, 'class="form-control form-control-sm" size="4" maxlength="3"'); ?></td>
          <td class="text-muted small"><?php echo FORMS_DEFAULT_ROWSPACE_DESC; ?></td>
        </tr>
        <tr>
          <td style="white-space:nowrap"><?php echo FORMS_DEFAULT_COLUMN_WIDTH_TEXT; ?></td>
          <td style="width:14rem"><?php echo html_input_field('forms_default_column_width', $forms_column_width, 'class="form-control form-control-sm" size="4" maxlength="3"'); ?></td>
          <td class="text-muted small"><?php echo FORMS_DEFAULT_COLUMN_WIDTH_DESC; ?></td>
        </tr>
      </tbody>
    </table>
    <div class="alert alert-warning py-1 px-2 mb-0 small"><?php echo FORMS_ADMIN_PRINT_CAUTION; ?></div>
  </div>
</div>

<?php // The two tools (§13.42), on a card rather than a second tab: this page has one
      // screen's worth of content, and a tab strip over two cards is furniture.
      //
      // Shown only to somebody who could actually use them: both branches call
      // validate_security($security_level, 3), so at a lower level the button
      // is a refusal waiting to happen. Same test the Save icon makes above.
      if ($security_level > 2) { ?>
<div class="fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_ADMIN_TOOLS_INFO; ?></div>
  <div class="card-body p-2">

    <div class="fb-fieldset mb-3">
      <div class="fb-fieldset-legend"><?php echo FORMS_TOOLS_RESTAMP_TITLE; ?></div>
      <p class="small mb-2"><?php echo sprintf(FORMS_TOOLS_RESTAMP_DESC, FORMS_DEFAULT_PAPER); ?></p>
      <p class="text-center mb-0"><?php echo FORMS_TOOLS_RESTAMP_SUBMIT; ?>
        <button type="button" class="btn btn-primary btn-sm"
                onclick="if (confirm('<?php echo FORMS_TOOLS_RESTAMP_CONFIRM; ?>')) submitToDo('restamp')"><?php echo TEXT_SUBMIT; ?></button></p>
    </div>

    <div class="fb-fieldset mb-0">
      <div class="fb-fieldset-legend"><?php echo FORMS_TOOLS_REBUILD_TITLE; ?></div>
      <p class="small mb-2"><?php echo FORMS_TOOLS_REBUILD_DESC; ?></p>
      <p class="text-center mb-0"><?php echo FORMS_TOOLS_REBUILD_SUBMIT; ?>
        <button type="button" class="btn btn-primary btn-sm"
                onclick="if (confirm('<?php echo FORMS_TOOLS_REBUILD_CONFIRM; ?>')) submitToDo('rebuild')"><?php echo TEXT_SUBMIT; ?></button></p>
    </div>

  </div>
</div>
<?php } ?>
</form>
