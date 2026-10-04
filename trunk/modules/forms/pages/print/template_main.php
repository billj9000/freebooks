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
// 11.09, the topic about printing a document you have open - which is what
// this window IS.
$toolbar->add_help('11.09');
echo $toolbar->build_toolbar();

echo html_form('forms_print', FILENAME_DEFAULT, gen_get_all_get_params(array('action')));
echo html_hidden_field('action', '') . chr(10);
?>

<h1 class="mb-2 px-2"><?php
  echo $forms_report
     ? htmlspecialchars(TEXT_FORM . ': ' . (string)($forms_report->title ?? ''))
     : htmlspecialchars(FORMS_PRINT_TITLE);
?></h1>

<?php
// The group's forms and the delivery options are ONE screen: the list lives
// where the document's name goes, so choosing changes a value on the screen
// the person is already on.
$forms_several = count($forms_choices) > 1;
if ($forms_several || ($forms_report && $forms_plan)) { ?>
<div class="fb-card card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_PRINT_WHAT; ?></div>
  <div class="card-body">
    <table class="table table-borderless table-sm mb-0">
      <tbody>
        <?php // What a PERSON is about to print. Not the provider class or the filter
              // expression - both true and both meaningless to whoever pressed Print.
              // Which code serves a document belongs on the Reports list, where an
              // administrator is looking at the catalogue. ?>
        <tr>
          <td style="width:35%"><?php echo FORMS_PRINT_DOCUMENT; ?></td>
          <td><?php
            if (!$forms_several) {
              echo htmlspecialchars((string)$forms_report->title);
            } else {
              // The first is checked by pre_process.php. It is not a default
              // anybody chose - it is whatever sorts first by title - so the
              // list stays on screen with the choice showing in it, and the
              // line below says the selection is there to be changed.
              foreach ($forms_choices as $choice) {
                $picked = ((int)$choice['id'] === (int)$forms_rID);
                printf('<div class="form-check">%s<label class="form-check-label" for="rID_%d">%s</label></div>' . chr(10),
                  html_radio_field('rID', (int)$choice['id'], $picked, '',
                                   'class="form-check-input" onclick="formsPick()"'),
                  (int)$choice['id'], htmlspecialchars((string)$choice['text']));
              }
              printf('<div class="small text-muted">%s</div>' . chr(10),
                     htmlspecialchars(FORMS_PRINT_CHOOSE));
            }
          ?></td>
        </tr>
        <?php if ($forms_report && $forms_plan) { ?>
        <tr>
          <td><?php echo FORMS_PRINT_FOR; ?></td>
          <td><?php
            if (!$forms_selection || $forms_selection->isEmpty()) {
              echo '<span class="text-danger">' . FORMS_PRINT_NO_RECORDS . '</span>';
            } elseif ($forms_selection->labels !== []) {
              $shown = array_map('htmlspecialchars', $forms_selection->labels);
              $more  = count($forms_selection->ids) - count($shown);
              echo implode(', ', $shown);
              if ($more > 0) printf(' %s', htmlspecialchars(sprintf(FORMS_PRINT_AND_MORE, $more)));
            } else {
              // No column on this record table that anybody would recognise —
              // a count is thin, but it is true, and it beats an id.
              $n = count($forms_selection->ids);
              printf('%s', htmlspecialchars(sprintf($n === 1 ? FORMS_PRINT_ONE_RECORD
                                                            : FORMS_PRINT_N_RECORDS, $n)));
            }
          ?></td>
        </tr>
        <?php } ?>
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
            <td style="width:25%"><?php echo FORMS_EMAIL_FROM; ?></td>
            <td><?php echo html_input_field('from_email', $forms_from_email, 'class="form-control form-control-sm"'); ?></td>
          </tr>
          <tr>
            <td><?php echo FORMS_PRINT_EMAIL_TO; ?></td>
            <td><?php
              echo html_input_field('to_email', $forms_to_email, 'class="form-control form-control-sm"');
              // Why it is empty. One attachment carries every selected record,
              // so an address filled in from one of them would send the rest to
              // somebody with no right to them.
              if (!empty($forms_email_many)) {
                printf(chr(10) . '<div class="small text-danger">%s</div>',
                       htmlspecialchars(FORMS_PRINT_EMAIL_MANY));
              }
            ?></td>
          </tr>
          <tr>
            <td><?php echo FORMS_PRINT_EMAIL_NAME; ?></td>
            <td><?php echo html_input_field('to_name', $forms_to_name, 'class="form-control form-control-sm"'); ?></td>
          </tr>
          <tr>
            <td><?php echo FORMS_EMAIL_CC; ?></td>
            <td><?php echo html_input_field('cc_email', $forms_cc_email, 'class="form-control form-control-sm"'); ?></td>
          </tr>
          <tr>
            <td><?php echo FORMS_EMAIL_BCC; ?></td>
            <td><?php echo html_input_field('bcc_email', $forms_bcc_email, 'class="form-control form-control-sm"'); ?></td>
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
