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
$toolbar->icon_list['save']['show']   = $forms_canvas_definition !== null && $security_level > 2;
$toolbar->icon_list['save']['params'] = 'onclick="formsCanvasSave()"';
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

<?php echo html_form('forms_canvas_form', FILENAME_DEFAULT,
    'module=forms&page=canvas&action=save&rID=' . (int)$forms_canvas_id); ?>
<input type="hidden" name="title" value="<?php echo htmlspecialchars($forms_canvas_definition->title); ?>">
<?php // Every element is submitted as hidden fields written by the canvas: the
      // boxes are dragged, not typed, and the row ORDER is the drawing order. ?>
<div id="forms_canvas_fields"></div>

<div class="alert alert-info py-2 px-3 small mb-2">
  <?php echo FORMS_CANVAS_INTRO; ?>
  <br><?php echo FORMS_CANVAS_DASHED; ?>
</div>
<div class="alert alert-warning py-2 px-3 small mb-2 d-none" id="forms_canvas_sampled">
  <?php echo FORMS_CANVAS_SAMPLED; ?>
</div>
<?php // Anything the redraw refused — a binding this form cannot supply, say —
      // said now rather than when the author saves. ?>
<div class="alert alert-warning py-2 px-3 small mb-2 d-none" id="forms_canvas_warnings"></div>

<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header d-flex justify-content-between align-items-center">
    <span><?php echo FORMS_CANVAS_PAGE; ?></span>
    <span class="d-inline-block">
      <?php // Document-level, so they sit with the page controls rather than in
            // the element inspector. Disabled when there is nothing to go back
            // to, which is also how an author learns the stack is per-session. ?>
      <button type="button" class="btn btn-primary btn-sm" id="forms_canvas_undo"
              title="<?php echo FORMS_CANVAS_UNDO_HINT; ?>" disabled><?php echo FORMS_CANVAS_UNDO; ?></button>
      <button type="button" class="btn btn-primary btn-sm me-2" id="forms_canvas_redo"
              title="<?php echo FORMS_CANVAS_REDO_HINT; ?>" disabled><?php echo FORMS_CANVAS_REDO; ?></button>
      <button type="button" class="btn btn-primary btn-sm" id="forms_canvas_out">&minus;</button>
      <span class="mx-1 small" id="forms_canvas_zoom">100%</span>
      <button type="button" class="btn btn-primary btn-sm" id="forms_canvas_in">+</button>
      <?php // The underlay is the last version DRAWN, so it goes stale the
            // moment anything moves — which is exactly when it is most needed.
            // Redrawing costs a PDF: too much to spend on every pointer move,
            // cheap enough to spend when the author asks for it. ?>
      <label class="ms-3 small">
        <input type="checkbox" class="form-check-input align-middle" id="forms_canvas_live" checked>
        <?php echo FORMS_CANVAS_LIVE; ?>
      </label>
      <?php // Still here when live drawing is off, and still useful when it is
            // on: a redraw on demand costs nothing extra and is the way back
            // if a request was abandoned. ?>
      <button type="button" class="btn btn-primary btn-sm ms-2" id="forms_canvas_render">
        <?php echo FORMS_CANVAS_RERENDER; ?>
      </button>
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
      <label class="ms-3 small">
        <?php echo FORMS_CANVAS_SNAP; ?>
        <?php echo html_pull_down_menu('forms_canvas_snap', array(
            array('id' => '0',   'text' => FORMS_CANVAS_SNAP_OFF),
            array('id' => '0.5', 'text' => '0.5 mm'),
            array('id' => '1',   'text' => '1 mm'),
            array('id' => '5',   'text' => '5 mm'),
          ), '1', 'class="form-select form-select-sm d-inline-block" style="width:auto"'); ?>
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
        <div class="fb-fieldset mb-2">
          <div class="fb-fieldset-legend"><?php echo FORMS_CANVAS_SELECTED; ?></div>
          <div id="forms_canvas_none" class="text-muted small"><?php echo FORMS_CANVAS_NOTHING; ?></div>
          <div id="forms_canvas_inspector" class="d-none">
            <p class="small mb-2"><strong id="forms_canvas_what">&nbsp;</strong>
               <span class="text-muted" id="forms_canvas_kind"></span></p>

            <div class="row g-1 mb-2">
              <div class="col-6">
                <label class="form-label mb-0 small" for="ins_x"><?php echo FORMS_CANVAS_X; ?></label>
                <input type="text" inputmode="decimal" class="form-control form-control-sm" id="ins_x">
              </div>
              <div class="col-6">
                <label class="form-label mb-0 small" for="ins_y"><?php echo FORMS_CANVAS_Y; ?></label>
                <input type="text" inputmode="decimal" class="form-control form-control-sm" id="ins_y">
              </div>
              <div class="col-6">
                <label class="form-label mb-0 small" for="ins_w"><?php echo FORMS_CANVAS_W; ?></label>
                <input type="text" inputmode="decimal" class="form-control form-control-sm" id="ins_w">
              </div>
              <div class="col-6">
                <?php // Labelled for what it IS on a text element: MultiCell
                      // takes a per-line height, so this is not the element's
                      // extent and calling it "height" would mislead. ?>
                <label class="form-label mb-0 small" for="ins_h" id="ins_h_label"><?php
                  echo FORMS_CANVAS_H; ?></label>
                <input type="text" inputmode="decimal" class="form-control form-control-sm" id="ins_h">
              </div>
            </div>

            <div class="mb-2" id="ins_text_row">
              <label class="form-label mb-0 small" for="ins_text"><?php echo FORMS_CANVAS_TEXT; ?></label>
              <input type="text" class="form-control form-control-sm" id="ins_text">
            </div>

            <div class="mb-2 d-none" id="ins_src_row">
              <label class="form-label mb-0 small" for="ins_src"><?php echo FORMS_CANVAS_IMAGE; ?></label>
              <?php echo html_pull_down_menu('ins_src', $forms_canvas_images, '',
                  'class="form-select form-select-sm"'); ?>
              <div class="form-text"><?php echo FORMS_CANVAS_IMAGE_NOTE; ?></div>
            </div>

            <div class="mb-2" id="ins_bind_row">
              <label class="form-label mb-0 small" for="ins_bind"><?php echo FORMS_CANVAS_BIND; ?></label>
              <?php echo html_pull_down_menu('ins_bind', array_merge(
                  array(array('id' => '', 'text' => FORMS_DESIGN_GROUP_NONE)), $forms_canvas_palette),
                  '', 'class="form-select form-select-sm"'); ?>
              <div class="form-text"><?php echo FORMS_CANVAS_BIND_NOTE; ?></div>
            </div>

            <div class="row g-1 mb-2">
              <div class="col-7">
                <label class="form-label mb-0 small" for="ins_font"><?php echo FORMS_DESIGN_FONT; ?></label>
                <?php // A list, not a box. The stored value is a token nobody
                      // would guess — the default face is spelled "freeserif" —
                      // and a name the PDF layer does not have is an error
                      // raised with the document half drawn, not a fallback. ?>
                <?php echo html_pull_down_menu('ins_font', $forms_canvas_fonts, '',
                    'class="form-select form-select-sm"'); ?>
              </div>
              <div class="col-5">
                <label class="form-label mb-0 small" for="ins_size"><?php echo FORMS_DESIGN_SIZE; ?></label>
                <input type="text" inputmode="decimal" class="form-control form-control-sm" id="ins_size">
              </div>
              <div class="col-12" id="ins_weight_row">
                <div class="form-check form-check-inline">
                  <input type="checkbox" class="form-check-input" id="ins_bold">
                  <label class="form-check-label small" for="ins_bold"><strong><?php
                    echo FORMS_CANVAS_BOLD; ?></strong></label>
                </div>
                <div class="form-check form-check-inline">
                  <input type="checkbox" class="form-check-input" id="ins_italic">
                  <label class="form-check-label small" for="ins_italic"><em><?php
                    echo FORMS_CANVAS_ITALIC; ?></em></label>
                </div>
              </div>
              <div class="col-5">
                <label class="form-label mb-0 small" for="ins_colour"><?php echo FORMS_DESIGN_COLOUR; ?></label>
                <input type="color" class="form-control form-control-sm form-control-color" id="ins_colour">
              </div>
              <div class="col-7">
                <label class="form-label mb-0 small" for="ins_align"><?php echo FORMS_DESIGN_COL_ALIGN; ?></label>
                <?php echo html_pull_down_menu('ins_align', $forms_canvas_aligns, '',
                    'class="form-select form-select-sm"'); ?>
              </div>
            </div>

            <div class="row g-1 mb-2">
              <div class="col-6">
                <label class="form-label mb-0 small" for="ins_border"><?php echo FORMS_CANVAS_BORDER; ?></label>
                <input type="text" inputmode="decimal" class="form-control form-control-sm" id="ins_border">
                <?php // Zero is a hairline, not "off" — the PDF spec defines a
                      // zero line width as the thinnest the device can draw. An
                      // author reading the box as an on/off switch is wrong in
                      // the one direction that cannot be seen on screen and
                      // shows up on paper. ?>
                <div class="form-text"><?php echo FORMS_CANVAS_BORDER_NOTE; ?></div>
              </div>
              <div class="col-6 d-flex align-items-end" id="ins_bordershow_row">
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" id="ins_bordershow">
                  <label class="form-check-label small" for="ins_bordershow"><?php
                    echo FORMS_CANVAS_BORDER_SHOW; ?></label>
                </div>
              </div>
              <div class="col-6">
                <?php // Its own colour, because a rule and the text inside it
                      // are not the same colour on 16 of the shipped elements.
                      // Left alone, the rule follows the text. ?>
                <label class="form-label mb-0 small" for="ins_bordercolour"><?php
                  echo FORMS_CANVAS_BORDER_COLOUR; ?></label>
                <input type="color" class="form-control form-control-sm form-control-color"
                       id="ins_bordercolour">
              </div>
            </div>

            <div class="mb-2">
              <label class="form-label mb-0 small" for="ins_visible"><?php echo FORMS_CANVAS_VISIBLE; ?></label>
              <?php echo html_pull_down_menu('ins_visible', $forms_canvas_visibility, 'all',
                  'class="form-select form-select-sm"'); ?>
            </div>

            <?php // The line-item table is drawn AFTER the rest, whatever its
                  // place in this list: the other elements repeat on every page
                  // through the header, and the table is the body that decides
                  // where the pages break. The arrows are real, but they cannot
                  // put anything in front of it. ?>
            <div class="text-muted small mb-2 d-none" id="ins_table_note">
              <?php echo FORMS_CANVAS_TABLE_ON_TOP; ?>
            </div>

            <div class="d-block mb-2">
              <button type="button" class="btn btn-primary btn-sm" onclick="formsCanvasZ(-1)"
                      title="<?php echo FORMS_CANVAS_FORWARD; ?>">&uarr;</button>
              <button type="button" class="btn btn-primary btn-sm" onclick="formsCanvasZ(1)"
                      title="<?php echo FORMS_CANVAS_BACKWARD; ?>">&darr;</button>
              <button type="button" class="btn btn-primary btn-sm" onclick="formsCanvasDelete()"><?php
                echo TEXT_DELETE; ?></button>
            </div>
          </div>
        </div>

        <div class="fb-fieldset mb-2">
          <div class="fb-fieldset-legend"><?php echo FORMS_CANVAS_ADD; ?></div>
          <div class="d-block">
<?php foreach ($forms_canvas_addable as $addable) { ?>
            <?php // A data attribute and one delegated listener, not an inline
                  // handler carrying a quoted string — the same reason the
                  // document list uses data-url for its run buttons. An
                  // attribute VALUE escapes cleanly and cannot break out. ?>
            <button type="button" class="btn btn-primary btn-sm mb-1 fb-canvas-add"
                    data-add="<?php echo htmlspecialchars($addable['id'], ENT_QUOTES); ?>">
              <?php echo htmlspecialchars($addable['text']); ?>
            </button>
<?php } ?>
          </div>
          <p class="text-muted small mb-0 mt-1"><?php echo FORMS_CANVAS_ADD_NOTE; ?></p>
        </div>

        <div class="fb-fieldset">
          <div class="fb-fieldset-legend"><?php echo FORMS_CANVAS_ELEMENTS; ?></div>
          <p class="text-muted small mb-2" id="forms_canvas_count">&nbsp;</p>
          <div style="max-height:34vh;overflow:auto">
            <table class="table table-borderless table-hover table-sm mb-0">
              <tbody id="forms_canvas_list"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</form>
