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
//  Path: /modules/forms/pages/main/template_main.php
//
use FreeBooks\Forms\App\RunPlan;

// Only the five the toolbar defines are addressed by key. 'new' is not one of
// them — inventing an entry gives it no 'order', and build_toolbar() sorts with
// array_multisort(), which is fatal when the key arrays differ in length. An
// icon that is not a default has to come from add_icon().
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \''
    . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
echo $toolbar->build_toolbar();
?>
<h1><?php echo FORMS_PAGE_TITLE; ?></h1>
<p class="text-muted"><?php echo FORMS_PAGE_INTRO; ?></p>

<?php // One form around the whole page: PDF and CSV navigate away with a query
      // string, but emailing has to POST the message with the request, and the
      // run options belong to both. html_form() rather than a hand-written tag,
      // because index.php refuses any POST without its csrf_token. ?>
<?php echo html_form('forms_run_form', FILENAME_DEFAULT, 'module=forms&page=main&action=email'); ?>
<input type="hidden" name="docID"  id="forms_doc_id"  value="">
<input type="hidden" name="format" id="forms_format"  value="pdf">

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_LEGEND_OPTIONS; ?></div>
  <div class="card-body py-2">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1" for="forms_dates"><?php echo FORMS_DATE_RANGE; ?></label>
        <?php echo html_pull_down_menu('forms_dates', $forms_date_choices, 'a',
                                       'class="form-select form-select-sm"'); ?>
      </div>
      <div class="col-md-4">
        <label class="form-label mb-1" for="forms_period"><?php echo FORMS_PERIOD; ?></label>
        <?php echo html_pull_down_menu('forms_period', $forms_periods, $forms_current_period,
                                       'class="form-select form-select-sm"'); ?>
      </div>
      <div class="col-md-4">
        <label class="form-label mb-1" for="forms_gl"><?php echo FORMS_GL_ACCOUNT; ?></label>
        <?php echo html_pull_down_menu('forms_gl', $forms_gl_accounts, '',
                                       'class="form-select form-select-sm"'); ?>
      </div>
    </div>
  </div>
</div>

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header d-flex justify-content-between align-items-center">
    <span><?php echo FORMS_EMAIL_LEGEND; ?></span>
    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse"
            data-bs-target="#forms_email_details" aria-expanded="<?php echo $forms_email['to_email'] !== '' ? 'true' : 'false'; ?>"
            aria-controls="forms_email_details"><?php echo FORMS_EMAIL_SHOW; ?></button>
  </div>
  <?php // Open already when a send was refused, so the user can see what to fix
        // rather than having to go looking for their own typed values. ?>
  <div class="collapse<?php echo $forms_email['to_email'] !== '' ? ' show' : ''; ?>" id="forms_email_details">
    <div class="card-body py-2">
      <p class="text-muted small mb-2"><?php echo FORMS_EMAIL_NOTE; ?></p>
      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label mb-1" for="from_email"><?php echo FORMS_EMAIL_FROM; ?></label>
          <input type="email" class="form-control form-control-sm" id="from_email" name="from_email"
                 value="<?php echo htmlspecialchars($forms_email['from_email']); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1" for="to_email"><?php echo FORMS_PRINT_EMAIL_TO; ?></label>
          <input type="email" class="form-control form-control-sm" id="to_email" name="to_email"
                 value="<?php echo htmlspecialchars($forms_email['to_email']); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1" for="to_name"><?php echo FORMS_PRINT_EMAIL_NAME; ?></label>
          <input type="text" class="form-control form-control-sm" id="to_name" name="to_name"
                 value="<?php echo htmlspecialchars($forms_email['to_name']); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1" for="cc_email"><?php echo FORMS_EMAIL_CC; ?></label>
          <input type="email" class="form-control form-control-sm" id="cc_email" name="cc_email"
                 value="<?php echo htmlspecialchars($forms_email['cc_email']); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1" for="bcc_email"><?php echo FORMS_EMAIL_BCC; ?></label>
          <input type="email" class="form-control form-control-sm" id="bcc_email" name="bcc_email"
                 value="<?php echo htmlspecialchars($forms_email['bcc_email']); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1" for="subject"><?php echo FORMS_PRINT_EMAIL_SUBJECT; ?></label>
          <input type="text" class="form-control form-control-sm" id="subject" name="subject"
                 value="<?php echo htmlspecialchars($forms_email['subject']); ?>">
        </div>
        <div class="col-12">
          <label class="form-label mb-1" for="message_body"><?php echo FORMS_PRINT_EMAIL_MESSAGE; ?></label>
          <textarea class="form-control form-control-sm" id="message_body" name="message_body"
                    rows="3"><?php echo htmlspecialchars($forms_email['body']); ?></textarea>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_PAGE_TITLE; ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <?php // No Group column: the group is the heading each block sits
                // under now, and printing the raw code beside every row was
                // what made an ordered list look unordered. ?>
          <tr>
            <th><?php echo FORMS_COL_TITLE; ?></th>
            <th><?php echo FORMS_COL_SERVED_BY; ?></th>
            <th class="text-end"><?php echo FORMS_COL_ACTIONS; ?></th>
          </tr>
        </thead>
        <tbody>
<?php if (!$forms_documents) { ?>
          <tr><td colspan="3" class="text-muted p-2"><?php echo FORMS_NO_DOCUMENTS; ?></td></tr>
<?php } else {
  $row = 0;
  $folder = null;
  foreach ($forms_documents as $document) {
    // A heading when the folder changes. Only folders that HOLD something get
    // one, which is the whole reason it is driven by the documents rather than
    // by the folder list: five folders in a stock install are empty.
    if ($document['folder'] !== $folder) {
      $folder = $document['folder'];
      $row    = 0;
?>
          <tr class="fb-forms-folder">
            <td colspan="3" class="fw-bold text-muted pt-2"><?php
              echo htmlspecialchars($folder); ?></td>
          </tr>
<?php
    }
    $plan     = $document['plan'];
    $runnable = $plan->runnable();
    // The run link is assembled in JavaScript from the selects above, so the
    // options apply to whichever document is run without a form per row.
    $base = html_href_link(FILENAME_DEFAULT,
        'module=forms&page=main&action=run&docID=' . $document['id'], 'SSL');
?>
          <tr class="<?php echo ($row++ % 2) ? 'odd' : 'even'; ?>">
            <td><?php echo htmlspecialchars($document['title']); ?></td>
            <td class="<?php echo $runnable ? 'text-muted' : 'text-warning-emphasis'; ?>"
                <?php if ($plan->notes) { ?>title="<?php echo htmlspecialchars(implode(' ', $plan->notes)); ?>"<?php } ?>>
              <?php echo htmlspecialchars($document['served']); ?>
            </td>
            <td class="text-end text-nowrap">
<?php   if ($runnable) {
          // Data attributes and one delegated listener, not an inline handler.
          // An onclick carrying a URL has to survive both PHP and HTML quoting:
          // json_encode() emits double quotes, the attribute is delimited by
          // double quotes, and the browser drops the handler at the first one —
          // which is a button that highlights and does nothing. An attribute
          // VALUE escapes cleanly with htmlspecialchars and cannot break out.
          $runAttributes = sprintf('data-url="%s" data-needs-gl="%d"',
              htmlspecialchars($base, ENT_QUOTES), $plan->needsGlAccount ? 1 : 0);
?>
              <button type="button" class="btn btn-primary btn-sm fb-run"
                      <?php echo $runAttributes; ?> data-format="pdf">
                <?php echo FORMS_BTN_PDF; ?>
              </button>
              <button type="button" class="btn btn-primary btn-sm fb-run"
                      <?php echo $runAttributes; ?> data-format="csv">
                <?php echo FORMS_BTN_CSV; ?>
              </button>
              <?php // Emailing posts the form rather than navigating, so it
                    // carries a document id instead of a URL. ?>
              <button type="button" class="btn btn-primary btn-sm fb-email"
                      data-doc-id="<?php echo (int)$document['id']; ?>"
                      data-needs-gl="<?php echo $plan->needsGlAccount ? 1 : 0; ?>">
                <?php echo FORMS_BTN_EMAIL; ?>
              </button>
<?php   } elseif ($plan->isForm()) {
          // This page lists doc_ext='rpt', so a form should not reach here —
          // but isForm() is decided by the DEFINITION, not by the catalogue
          // row, and the two disagreed once already: a query-driven form with
          // no special_class planned as an ordinary dataset and rendered an
          // invoice as a table. So the case is kept, and it says where the
          // document actually lives rather than offering a button that cannot
          // work from a list with no record in it.
?>
              <span class="text-muted small"><?php echo FORMS_ROW_IS_FORM; ?></span>
<?php   } else { ?>
              <span class="text-muted">&mdash;</span>
<?php   }
        // Authoring is a separate permission from running (decision F), so the
        // Edit button appears for the users who hold it and for nobody else.
        // Only for reports: a form is laid out on a page, and the canvas that
        // edits one is a later step.
        if ($forms_can_author && !$plan->isForm()) { ?>
              <a class="btn btn-primary btn-sm"
                 href="<?php echo html_href_link(FILENAME_DEFAULT,
                     'module=forms&amp;page=design&amp;rID=' . $document['id'], 'SSL'); ?>">
                <?php echo FORMS_DESIGN_EDIT; ?>
              </a>
<?php   } ?>
            </td>
          </tr>
<?php }
} ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</form>
