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
//  Path: /modules/forms/pages/canvas/template_main.php
//
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
// Read-only, by design: this step proves the alignment before anything can be
// moved (§13.7 step 3). A Save button here would promise an edit that is not
// built yet.
$toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \''
    . html_href_link(FILENAME_DEFAULT, 'module=forms&page=main', 'SSL') . '\'"';
$toolbar->add_help('11.07');
echo $toolbar->build_toolbar();

if ($forms_canvas_definition === null) { ?>
<h1><?php echo FORMS_CANVAS_TITLE; ?></h1>
<p class="text-muted"><?php echo FORMS_CANVAS_PICK_ONE; ?></p>

<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_CANVAS_CHOOSE; ?></div>
  <div class="card-body p-0">
    <table class="table table-borderless table-hover table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th><?php echo FORMS_COL_TITLE; ?></th>
          <th><?php echo FORMS_COL_GROUP; ?></th>
          <th class="text-end">&nbsp;</th>
        </tr>
      </thead>
      <tbody>
<?php if (!$forms_canvas_choices) { ?>
        <tr><td colspan="3" class="text-muted p-2"><?php echo FORMS_NO_DOCUMENTS; ?></td></tr>
<?php } else { $row = 0; foreach ($forms_canvas_choices as $choice) { ?>
        <tr class="<?php echo ($row++ % 2) ? 'odd' : 'even'; ?>">
          <td><?php echo htmlspecialchars($choice['title']); ?></td>
          <td class="text-muted"><?php echo htmlspecialchars($choice['group']); ?></td>
          <td class="text-end">
            <a class="btn btn-primary btn-sm" href="<?php echo html_href_link(FILENAME_DEFAULT,
              'module=forms&amp;page=canvas&amp;rID=' . $choice['id'], 'SSL'); ?>">
              <?php echo FORMS_CANVAS_OPEN; ?>
            </a>
          </td>
        </tr>
<?php } } ?>
      </tbody>
    </table>
  </div>
</div>
<?php
  return;
}
?>
<h1><?php echo htmlspecialchars($forms_canvas_definition->title); ?></h1>

<div class="alert alert-info py-2 px-3 small mb-2">
  <?php echo FORMS_CANVAS_READ_ONLY; ?>
  <br><?php echo FORMS_CANVAS_DASHED; ?>
</div>
<div class="alert alert-warning py-2 px-3 small mb-2 d-none" id="forms_canvas_sampled">
  <?php echo FORMS_CANVAS_SAMPLED; ?>
</div>

<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header d-flex justify-content-between align-items-center">
    <span><?php echo FORMS_CANVAS_PAGE; ?></span>
    <span class="d-inline-block">
      <button type="button" class="btn btn-primary btn-sm" id="forms_canvas_out">&minus;</button>
      <span class="mx-1 small" id="forms_canvas_zoom">100%</span>
      <button type="button" class="btn btn-primary btn-sm" id="forms_canvas_in">+</button>
      <button type="button" class="btn btn-primary btn-sm ms-2" id="forms_canvas_prev">&larr;</button>
      <span class="mx-1 small" id="forms_canvas_pages">&nbsp;</span>
      <button type="button" class="btn btn-primary btn-sm" id="forms_canvas_next">&rarr;</button>
      <?php // The outlines are what makes this a canvas rather than a viewer,
            // so being able to take them away is how an author checks that a
            // box is over its text rather than merely near it. ?>
      <label class="ms-3 small">
        <input type="checkbox" class="form-check-input align-middle" id="forms_canvas_show" checked>
        <?php echo FORMS_CANVAS_SHOW_BOXES; ?>
      </label>
    </span>
  </div>
  <div class="card-body p-2">
    <div class="row g-2">
      <div class="col-lg-9">
        <div style="overflow:auto;max-height:78vh;background:#e9edf2;padding:0.5rem">
          <?php // The stack: the rasterized page, then a transparent layer of
                // absolutely positioned boxes. Same origin, same scale, so a
                // box at 140 mm lands on the text drawn at 140 mm. ?>
          <div id="forms_canvas_stack" style="position:relative;margin:0 auto;width:max-content;
                      box-shadow:0 1px 6px rgba(0,0,0,.3);background:#fff">
            <canvas id="forms_canvas_page" style="display:block"></canvas>
            <div id="forms_canvas_boxes" style="position:absolute;inset:0"></div>
          </div>
          <p class="text-muted small mt-2 mb-0" id="forms_canvas_status"><?php echo FORMS_CANVAS_LOADING; ?></p>
        </div>
      </div>
      <div class="col-lg-3">
        <div class="fb-fieldset">
          <div class="fb-fieldset-legend"><?php echo FORMS_CANVAS_ELEMENTS; ?></div>
          <p class="text-muted small mb-2" id="forms_canvas_count">&nbsp;</p>
          <div style="max-height:66vh;overflow:auto">
            <table class="table table-borderless table-hover table-sm mb-0">
              <tbody id="forms_canvas_list"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
