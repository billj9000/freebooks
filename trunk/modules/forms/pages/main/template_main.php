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

<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_PAGE_TITLE; ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th><?php echo FORMS_COL_TITLE; ?></th>
            <th><?php echo FORMS_COL_GROUP; ?></th>
            <th><?php echo FORMS_COL_SERVED_BY; ?></th>
            <th class="text-end"><?php echo FORMS_COL_ACTIONS; ?></th>
          </tr>
        </thead>
        <tbody>
<?php if (!$forms_documents) { ?>
          <tr><td colspan="4" class="text-muted p-2"><?php echo FORMS_NO_DOCUMENTS; ?></td></tr>
<?php } else {
  $row = 0;
  foreach ($forms_documents as $document) {
    $plan     = $document['plan'];
    $runnable = $plan->runnable();
    // The run link is assembled in JavaScript from the selects above, so the
    // options apply to whichever document is run without a form per row.
    $base = html_href_link(FILENAME_DEFAULT,
        'module=forms&page=main&action=run&docID=' . $document['id'], 'SSL');
?>
          <tr class="<?php echo ($row++ % 2) ? 'odd' : 'even'; ?>">
            <td><?php echo htmlspecialchars($document['title']); ?></td>
            <td class="text-muted"><?php echo htmlspecialchars($document['group']); ?></td>
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
<?php   } elseif ($plan->isForm()) {
          // A form needs records, which this list cannot know — so it links to
          // the print page rather than offering PDF and CSV. Before this, a
          // query-driven form had no special_class, planned as an ordinary
          // dataset, and the buttons here rendered an invoice as a table.
?>
              <a class="btn btn-primary btn-sm"
                 href="<?php echo htmlspecialchars(html_href_link(FILENAME_DEFAULT,
                     'module=forms&amp;page=print&amp;rID=' . $document['id'], 'SSL'), ENT_QUOTES); ?>">
                <?php echo FORMS_BTN_PRINT; ?>
              </a>
<?php   } else { ?>
              <span class="text-muted">&mdash;</span>
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
