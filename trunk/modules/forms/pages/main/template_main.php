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
// New, the FreeBooks way of making a thing.
if ($forms_can_author) $toolbar->add_icon('new', 'data-fb-doc="new"', 2);
$toolbar->add_help('11.01');
echo $toolbar->build_toolbar();
?>
<h1><?php echo FORMS_PAGE_TITLE; ?></h1>
<p class="text-muted"><?php echo FORMS_PAGE_INTRO; ?></p>

<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header d-flex justify-content-between align-items-center">
    <span><?php echo FORMS_PAGE_TITLE; ?></span>
    <?php // Outside the run form deliberately: an <input> in a form takes Enter
          // as "submit", and the only submit this page has is "send the email".
          // No name attribute either — it is a view control, not an answer. ?>
    <input type="search" id="forms_filter" class="form-control form-control-sm" style="max-width:16rem"
           placeholder="<?php echo htmlspecialchars(FORMS_FILTER_PLACEHOLDER); ?>"
           aria-label="<?php echo htmlspecialchars(FORMS_FILTER_PLACEHOLDER); ?>" autocomplete="off">
  </div>
  <div class="card-body p-0">
    <?php // No .table-responsive wrapper. Two columns never need to scroll
          // sideways, and the wrapper's overflow-x makes a clipping context that
          // cuts off the row dropdowns — a menu that opens and is invisible. ?>
    <table class="table table-borderless table-hover table-sm mb-0" id="forms_documents">
      <thead class="table-dark">
        <?php // No Group column: the group is the heading each block sits under,
              // by name; a raw code beside every row makes an ordered list look
              // unordered. No "Served by" column either — the dataset or provider a
              // definition resolved to is diagnostic, so it sits under the title
              // for the people who can act on it. ?>
        <tr>
          <th><?php echo FORMS_COL_TITLE; ?></th>
          <th class="text-end"><?php echo FORMS_COL_ACTIONS; ?></th>
        </tr>
      </thead>
      <tbody>
<?php if (!$forms_documents) { ?>
        <tr><td colspan="2" class="text-muted p-2"><?php echo FORMS_NO_DOCUMENTS; ?></td></tr>
<?php } else {
  $row = 0;
  $folder = null;
  $folderIndex = 0;
  foreach ($forms_documents as $document) {
    // A heading when the folder changes. Only folders that HOLD something get
    // one, which is the whole reason it is driven by the documents rather than
    // by the folder list: five folders in a stock install are empty.
    if ($document['folder'] !== $folder) {
      $folder = $document['folder'];
      $row    = 0;
      $folderIndex++;
?>
        <tr class="fb-forms-folder" data-folder="<?php echo $folderIndex; ?>">
          <td colspan="2">
            <?php // A button, not a styled cell: it is operated, so it has to be
                  // reachable from the keyboard and say so to a screen reader. ?>
            <?php // No utility classes on it: .text-muted and friends carry
                  // !important, so a colour of our own could not win. The look
                  // is fb-folder-toggle's, in freebooks.css. ?>
            <button type="button" class="btn btn-link fb-folder-toggle" aria-expanded="true">
              <span class="fb-folder-caret">&#9662;</span>
              <?php echo htmlspecialchars($folder); ?>
            </button>
          </td>
        </tr>
<?php
    }
    $plan     = $document['plan'];
    $runnable = $plan->runnable();
    // The run link is assembled in JavaScript from the dialog's controls, so the
    // answers apply to whichever document is run without a form per row.
    $base = html_href_link(FILENAME_DEFAULT,
        'module=forms&page=main&action=run&docID=' . $document['id'], 'SSL');
?>
        <?php // The folder number, not an id: every row of a folder carries the
              // same one, and an id may not repeat. It is what the heading above
              // collapses and what the filter hides. ?>
        <tr class="fb-forms-row <?php echo ($row++ % 2) ? 'odd' : 'even'; ?>"
            data-folder="<?php echo $folderIndex; ?>"
            <?php // Lowercased in the browser rather than here: PHP's strtolower
                  // is byte-wise and mbstring is not guaranteed, while the typed
                  // term is folded by JavaScript — fold the two differently and
                  // an accented title stops matching itself. ?>
            data-search="<?php echo htmlspecialchars(
                $document['title'] . ' ' . $document['folder'], ENT_QUOTES); ?>"
            data-doc-id="<?php echo (int)$document['id']; ?>"
            data-doc-title="<?php echo htmlspecialchars($document['title'], ENT_QUOTES); ?>"
            data-url="<?php echo htmlspecialchars($base, ENT_QUOTES); ?>"
            <?php // Every answer the dialog might ask this document for, and the
                  // answers the definition already holds. A document that needs
                  // nothing never opens the dialog at all. ?>
            data-needs-dates="<?php echo $plan->needsDates     ? 1 : 0; ?>"
            data-needs-period="<?php echo $plan->needsPeriod    ? 1 : 0; ?>"
            data-needs-gl="<?php echo $plan->needsGlAccount ? 1 : 0; ?>"
            data-must-ask="<?php echo $plan->mustAsk()      ? 1 : 0; ?>"
            data-period="<?php echo (int)$document['period']; ?>"
            data-dates-as-set="<?php echo htmlspecialchars($document['dates_as_set'], ENT_QUOTES); ?>">
          <td>
            <?php if (!$runnable) { ?>
              <span class="text-warning-emphasis" title="<?php
                echo htmlspecialchars(implode(' ', $plan->notes), ENT_QUOTES); ?>">&#9888;</span>
            <?php } ?>
            <?php echo htmlspecialchars($document['title']); ?>
            <?php // The dataset or provider that will serve it. Shown to the
                  // people who write reports, because it is the answer to "why
                  // can this report not see that field"; noise to everyone else.
                  // A document that CANNOT run says so to everybody, since that
                  // is what the reader is looking for when a document they
                  // expect is missing from the list. ?>
            <?php if ($forms_can_author || !$runnable) { ?>
              <div class="small <?php echo $runnable ? 'text-muted' : 'text-warning-emphasis'; ?>">
                <?php echo htmlspecialchars($document['served']); ?>
              </div>
            <?php } ?>
          </td>
          <td class="text-end text-nowrap">
<?php   if ($runnable) { ?>
            <div class="btn-group btn-group-sm">
              <?php // PDF runs straight away, on the answers the definition
                    // already carries — the common case costs no clicks and the
                    // report comes out as its author drew it. The caret is for
                    // the run that is not the usual one. ?>
              <button type="button" class="btn btn-primary fb-run" data-format="pdf">
                <?php echo FORMS_BTN_PDF; ?>
              </button>
              <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split"
                      data-bs-toggle="dropdown" aria-expanded="false">
                <span class="visually-hidden"><?php echo FORMS_BTN_MORE; ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><button type="button" class="dropdown-item fb-run" data-format="csv">
                  <?php echo FORMS_BTN_CSV; ?></button></li>
                <li><button type="button" class="dropdown-item fb-options" data-email="1">
                  <?php echo FORMS_BTN_EMAIL; ?></button></li>
<?php     if ($plan->hasOptions()) { ?>
                <li><hr class="dropdown-divider"></li>
                <li><button type="button" class="dropdown-item fb-options">
                  <?php echo FORMS_BTN_OPTIONS; ?></button></li>
<?php     } ?>
              </ul>
            </div>
<?php   } elseif ($plan->isForm()) {
          // This page lists doc_ext='rpt', so a form should not reach here —
          // but isForm() is decided by the DEFINITION, not by the catalogue
          // row, and the two can disagree: a query-driven form with no
          // special_class plans as an ordinary dataset and would render an
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
        // Only for reports: a form is laid out on the form canvas.
        if ($forms_can_author && !$plan->isForm()) { ?>
            <a class="btn btn-primary btn-sm ms-1"
               href="<?php echo html_href_link(FILENAME_DEFAULT,
                   'module=forms&amp;page=design&amp;rID=' . $document['id'], 'SSL'); ?>">
              <?php echo FORMS_DESIGN_EDIT; ?>
            </a>
            <?php // Data attributes, not an inline handler: nothing has to be
                  // global, and a title is escaped once rather than for HTML and
                  // for JavaScript at the same time. ?>
            <button type="button" class="btn btn-primary btn-sm"
                    data-fb-doc="copy"
                    data-id="<?php echo (int)$document['id']; ?>"
                    data-title="<?php echo htmlspecialchars($document['title'], ENT_QUOTES); ?>">
              <?php echo FORMS_DOC_COPY; ?></button>
<?php     if (($_SESSION['admin_security'][SECURITY_ID_FORMS_AUTHOR] ?? 0) > 3) { ?>
            <button type="button" class="btn btn-primary btn-sm"
                    data-fb-doc="delete"
                    data-id="<?php echo (int)$document['id']; ?>"
                    data-last="<?php echo !empty($document['last']) ? '1' : '0'; ?>">
              <?php echo FORMS_DOC_DELETE; ?></button>
<?php     } ?>
<?php   } ?>
          </td>
        </tr>
<?php }
} ?>
        <tr id="forms_no_match" class="d-none">
          <td colspan="2" class="text-muted p-2"><?php echo FORMS_FILTER_NO_MATCH; ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>


<?php if ($forms_can_author) { ?>
<?php // The New icon on the toolbar is the FreeBooks convention for making a
      // thing, so that is where this is reached from rather than a panel under
      // the list. The three answers are collected BEFORE the editor opens,
      // because all three are properties of the DOCUMENT rather than of its
      // layout — an editor that opened first would have to ask afterwards, or
      // guess. ?>
<div class="modal fade" id="forms_new_modal" tabindex="-1"
     aria-labelledby="forms_new_title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <?php echo html_form('forms_new_form', FILENAME_DEFAULT,
          'module=forms&page=main&action=new'); ?>
      <div class="modal-header py-2">
        <h5 class="modal-title" id="forms_new_title"><?php echo FORMS_DOC_NEW_REPORT; ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"
                aria-label="<?php echo TEXT_CANCEL; ?>"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small"><?php echo FORMS_DOC_NEW_HELP; ?></p>
        <div class="mb-2">
          <label class="form-label mb-0 small" for="forms_new_name"><?php echo FORMS_DOC_NAME; ?></label>
          <input type="text" name="doc_title" id="forms_new_name" maxlength="64"
                 class="form-control form-control-sm" required autocomplete="off">
        </div>
        <div class="mb-2">
          <label class="form-label mb-0 small" for="forms_new_folder"><?php echo FORMS_DOC_FOLDER; ?></label>
          <select name="doc_group" id="forms_new_folder" class="form-select form-select-sm">
<?php foreach ($forms_groups as $forms_new_code => $forms_new_name) { ?>
            <option value="<?php echo htmlspecialchars($forms_new_code); ?>"><?php
              echo htmlspecialchars($forms_new_name); ?></option>
<?php } ?>
          </select>
        </div>
        <div class="mb-1">
          <label class="form-label mb-0 small" for="forms_new_dataset"><?php echo FORMS_DOC_DATASET; ?></label>
          <select name="dataset" id="forms_new_dataset" class="form-select form-select-sm">
<?php foreach ($forms_datasets as $forms_new_code => $forms_new_name) { ?>
            <option value="<?php echo htmlspecialchars($forms_new_code); ?>"><?php
              echo htmlspecialchars($forms_new_name); ?></option>
<?php } ?>
          </select>
        </div>
      </div>
      <div class="modal-footer py-1">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
          <?php echo TEXT_CANCEL; ?></button>
        <button type="submit" class="btn btn-primary btn-sm"><?php echo FORMS_DOC_NEW; ?></button>
      </div>
      </form>
    </div>
  </div>
</div>

<?php // Copy and Delete POST rather than link: a delete in a URL is one the
      // browser offers to repeat, and a repeated delete is a second document. ?>
<?php echo html_form('forms_doc_action', FILENAME_DEFAULT, 'module=forms&page=main'); ?>
  <input type="hidden" name="action"    id="forms_doc_action_kind"  value="">
  <input type="hidden" name="rID"       id="forms_doc_action_id"    value="">
  <input type="hidden" name="doc_title" id="forms_doc_action_title" value="">
</form>
<?php } ?>

<?php // The run dialog. One for the page, filled in from the row that opened it,
      // so the answers are asked for beside the name of the document they apply
      // to rather than in a bar that has scrolled out of sight.
      //
      // Inside the form, and the form is around nothing else: PDF and CSV are
      // navigations that JavaScript assembles, but emailing has to POST the
      // message, and html_form() is what supplies the csrf_token index.php
      // refuses a POST without. Bootstrap leaves a modal where it is in the DOM,
      // so the controls below really are submitted with it. ?>
<?php echo html_form('forms_run_form', FILENAME_DEFAULT, 'module=forms&page=main&action=email'); ?>
<input type="hidden" name="docID"  id="forms_doc_id" value="">
<input type="hidden" name="format" id="forms_format" value="pdf">

<div class="modal fade" id="forms_run_dialog" tabindex="-1" aria-hidden="true"
     aria-labelledby="forms_run_dialog_title">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-1 fb-widget-header">
        <h5 class="modal-title" id="forms_run_dialog_title"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"
                aria-label="<?php echo htmlspecialchars(TEXT_CLOSE); ?>"></button>
      </div>
      <div class="modal-body">

        <div class="fb-fieldset mb-3" id="forms_options_wrap">
          <div class="fb-fieldset-legend"><?php echo FORMS_LEGEND_OPTIONS; ?></div>
          <div class="row g-2">
            <div class="col-md-4" id="forms_dates_wrap">
              <label class="form-label mb-1" for="dates"><?php echo FORMS_DATE_RANGE; ?></label>
              <?php echo html_pull_down_menu('dates', $forms_date_choices, '',
                                             'class="form-select form-select-sm"'); ?>
            </div>
            <div class="col-md-4" id="forms_period_wrap">
              <label class="form-label mb-1" for="period"><?php echo FORMS_PERIOD; ?></label>
              <?php echo html_pull_down_menu('period', $forms_periods, $forms_current_period,
                                             'class="form-select form-select-sm"'); ?>
            </div>
            <div class="col-md-4" id="forms_gl_wrap">
              <label class="form-label mb-1" for="gl_account"><?php echo FORMS_GL_ACCOUNT; ?></label>
              <?php // Its own error text, on the control itself. The page used to
                    // raise an alert() here, which says what is wrong somewhere
                    // other than where it is wrong. ?>
              <?php echo html_pull_down_menu('gl_account', $forms_gl_accounts, '',
                                             'class="form-select form-select-sm"'); ?>
              <div class="invalid-feedback"><?php echo FORMS_ERROR_GL_REQUIRED; ?></div>
              <?php // A company with no cash or bank accounts has nothing to
                    // reconcile, and the control above would be an empty list
                    // that must be answered — a dead end. The dialog says so
                    // instead, and refuses to offer a run that cannot work.
                    // Drawn always and shown by the dialog, because whether it
                    // applies depends on the document being run. ?>
              <div class="small text-warning-emphasis mt-1 d-none" id="forms_gl_none">
                <?php echo FORMS_GL_NONE; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="fb-fieldset" id="forms_email_wrap">
          <div class="fb-fieldset-legend"><?php echo FORMS_EMAIL_LEGEND; ?></div>
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
              <div class="invalid-feedback"><?php echo FORMS_PRINT_EMAIL_NO_ADDRESS; ?></div>
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
      <div class="modal-footer py-1">
        <button type="button" class="btn btn-primary btn-sm fb-dialog-run" data-format="pdf">
          <?php echo FORMS_BTN_PDF; ?></button>
        <button type="button" class="btn btn-primary btn-sm fb-dialog-run" data-format="csv">
          <?php echo FORMS_BTN_CSV; ?></button>
        <button type="button" class="btn btn-primary btn-sm" id="forms_dialog_email">
          <?php echo FORMS_PRINT_BTN_EMAIL; ?></button>
      </div>
    </div>
  </div>
</div>
</form>
