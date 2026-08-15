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
//  Path: /modules/forms/pages/print/template_main.php
//
// Non-default toolbar icons go through add_icon(), which sets all five keys.
// Assigning into $toolbar->icon_list directly creates an entry with no 'order',
// and build_toolbar() feeds those to array_multisort(): "Array sizes are
// inconsistent", a fatal before the page draws anything.
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$forms_ready = $forms_report && $forms_plan && $forms_plan->runnable()
            && $forms_selection && !$forms_selection->isEmpty();
if ($forms_ready) {
  $toolbar->icon_list['print']['params'] = 'onclick="formsPrint(\'view\')"';
} else {
  $toolbar->icon_list['print']['show'] = false;
}
$toolbar->add_help('11.02');
echo $toolbar->build_toolbar();

echo html_form('forms_print', FILENAME_DEFAULT, gen_get_all_get_params(array('action')));
echo html_hidden_field('action', '') . chr(10);
?>

<h1 class="mb-2 px-2"><?php
  echo $forms_report
     ? htmlspecialchars(TEXT_FORM . ': ' . (string)($forms_report->title ?? ''))
     : htmlspecialchars(FORMS_PRINT_TITLE);
?></h1>

<?php if (!$forms_report && $forms_choices) { // several forms in this group ?>
<div class="fb-card card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_PRINT_CHOOSE; ?></div>
  <div class="card-body">
    <table class="table table-borderless table-hover mb-0">
      <thead class="table-dark"><tr><th><?php echo FORMS_COL_TITLE; ?></th><th class="text-end"><?php echo FORMS_COL_ACTIONS; ?></th></tr></thead>
      <tbody>
      <?php foreach ($forms_choices as $index => $choice) { ?>
        <tr class="<?php echo $index % 2 ? 'even' : 'odd'; ?>">
          <td><?php echo htmlspecialchars($choice['text']); ?></td>
          <td class="text-end">
            <a class="btn btn-primary btn-sm" href="<?php
              // The '&amp;' is not decoration. gen_get_all_get_params() joins with
              // '&amp;' and returns NO trailing separator, so concatenating
              // 'rID=' directly glues it onto the last value — the link came out
              // as "…&xmin=1372rID=91", rID never arrived, and the page simply
              // re-rendered this same chooser. A button that appears to do
              // nothing is exactly what that looks like from the outside.
              echo html_href_link(FILENAME_DEFAULT,
                gen_get_all_get_params(array('rID', 'action')) . '&amp;rID=' . (int)$choice['id'], 'SSL');
            ?>"><?php echo FORMS_PRINT_CHOOSE_BTN; ?></a>
          </td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
</div>
<?php } ?>

<?php if ($forms_report && $forms_plan) { ?>
<div class="fb-card card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_PRINT_WHAT; ?></div>
  <div class="card-body">
    <table class="table table-borderless table-sm mb-0">
      <tbody>
        <tr>
          <td style="width:35%"><?php echo FORMS_COL_SERVED_BY; ?></td>
          <td><?php
            // Fully qualified: a `use` statement in pre_process.php applies to
            // that FILE only, and template_main.php is a separate one — the
            // short name here was an unresolved-class fatal.
            echo htmlspecialchars($forms_plan->kind === \FreeBooks\Forms\App\FormPlan::DATASET
               ? sprintf(FORMS_SERVED_DATASET, $forms_plan->name)
               : sprintf(FORMS_SERVED_PROVIDER, $forms_plan->name));
          ?></td>
        </tr>
        <tr>
          <td><?php echo FORMS_PRINT_RECORDS; ?></td>
          <td><?php
            if ($forms_selection && !$forms_selection->isEmpty()) {
              printf('%s &mdash; %s',
                htmlspecialchars((string)count($forms_selection->ids)),
                htmlspecialchars($forms_selection->description));
            } else {
              echo '<span class="text-danger">' . FORMS_PRINT_NO_RECORDS . '</span>';
            }
          ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php } ?>

<?php if ($forms_ready) { ?>
<div class="fb-card card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_PRINT_DELIVER; ?></div>
  <div class="card-body">
    <p>
      <button type="button" id="forms_print_view" class="btn btn-primary btn-sm" onclick="formsPrint('view')"><?php echo FORMS_PRINT_BTN_VIEW; ?></button>
      <button type="button" class="btn btn-primary btn-sm" onclick="formsPrint('download')"><?php echo FORMS_PRINT_BTN_DOWNLOAD; ?></button>
    </p>

    <div class="fb-fieldset mb-2">
      <div class="fb-fieldset-legend"><?php echo FORMS_PRINT_EMAIL; ?></div>
      <table class="table table-borderless table-sm mb-0">
        <tbody>
          <tr>
            <td style="width:25%"><?php echo FORMS_PRINT_EMAIL_TO; ?></td>
            <td><?php echo html_input_field('to_email', $forms_to_email, 'class="form-control form-control-sm"'); ?></td>
          </tr>
          <tr>
            <td><?php echo FORMS_PRINT_EMAIL_NAME; ?></td>
            <td><?php echo html_input_field('to_name', $forms_to_name, 'class="form-control form-control-sm"'); ?></td>
          </tr>
          <tr>
            <td><?php echo FORMS_PRINT_EMAIL_SUBJECT; ?></td>
            <td><?php echo html_input_field('subject', $forms_subject, 'class="form-control form-control-sm"'); ?></td>
          </tr>
          <tr>
            <td><?php echo FORMS_PRINT_EMAIL_MESSAGE; ?></td>
            <td><?php echo html_textarea_field('message_body', 60, 4, $forms_body, 'class="form-control form-control-sm"'); ?></td>
          </tr>
          <tr>
            <td></td>
            <td><button type="button" class="btn btn-primary btn-sm" onclick="formsPrint('email')"><?php echo FORMS_PRINT_BTN_EMAIL; ?></button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php } ?>

</form>
