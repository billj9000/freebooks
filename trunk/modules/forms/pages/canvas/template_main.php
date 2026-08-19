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
// Back to the list of FORMS, not to the document list. Cancel means "stop
// editing this one", and the useful destination is the others — which is also
// where the author came from if they arrived by Tools -> Form Layout. Sending
// them to Documents was wrong from that door and right only by accident from
// the other. With nothing open this page IS that list, so Cancel then leaves
// the editing side altogether.
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \''
    . html_href_link(FILENAME_DEFAULT, $forms_canvas_definition !== null
        ? 'module=forms&page=canvas' : 'module=forms&page=main', 'SSL') . '\'"';
// New, the FreeBooks way of making a thing. Only on the LIST: with a form open
// the toolbar's actions belong to that form, and a New beside Save reads as
// "new element" on a page whose whole subject is elements.
if ($forms_canvas_definition === null && $security_level > 2) {
  $toolbar->add_icon('new', 'data-fb-doc="new"', 2);
}
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
        <?php // No Group column: the folder is the heading above each block, and
              // the raw code it used to print here is not a name. ?>
        <tr>
          <th><?php echo FORMS_COL_TITLE; ?></th>
          <th class="text-end">&nbsp;</th>
        </tr>
      </thead>
      <tbody>
<?php if (!$forms_canvas_choices) { ?>
        <tr><td colspan="2" class="text-muted p-2"><?php echo FORMS_NO_DOCUMENTS; ?></td></tr>
<?php } else { $row = 0; $folder = null; foreach ($forms_canvas_choices as $choice) {
        // Only folders holding a form get a heading — five of the twelve in a
        // stock install are empty, and a heading over nothing is furniture.
        if ($choice['folder'] !== $folder) { $folder = $choice['folder']; $row = 0; ?>
        <tr><td colspan="2" class="fw-bold text-muted pt-2"><?php
          echo htmlspecialchars($folder); ?></td></tr>
<?php   } ?>
        <tr class="<?php echo ($row++ % 2) ? 'odd' : 'even'; ?>">
          <td>
            <?php echo htmlspecialchars($choice['title']); ?>
<?php     if ($choice['serial']) { ?>
            <?php // Named in the list, not merely routed by it: the two editors
                  // look nothing alike, and arriving at an unexpected one is
                  // worse than being told which is coming. ?>
            <span class="badge text-bg-secondary"><?php echo FORMS_CANVAS_IS_RECEIPT; ?></span>
<?php     } ?>
          </td>
          <td class="text-end text-nowrap">
            <a class="btn btn-primary btn-sm" href="<?php echo html_href_link(FILENAME_DEFAULT,
              'module=forms&amp;page=' . $choice['page'] . '&amp;rID=' . $choice['id'], 'SSL'); ?>">
              <?php echo $choice['serial'] ? FORMS_CANVAS_OPEN_RECEIPT : FORMS_CANVAS_OPEN; ?>
            </a>
<?php     if ($security_level > 2) { ?>
            <?php // Data attributes, not an inline handler: nothing has to be
                  // global, and a title is escaped once rather than for HTML and
                  // for JavaScript at the same time. ?>
            <button type="button" class="btn btn-primary btn-sm"
                    data-fb-doc="copy"
                    data-id="<?php echo (int)$choice['id']; ?>"
                    data-title="<?php echo htmlspecialchars($choice['title'], ENT_QUOTES); ?>">
              <?php echo FORMS_DOC_COPY; ?></button>
<?php     } ?>
<?php     if ($security_level > 3) { ?>
            <?php // The consequence is on ANOTHER page — a Print button that
                  // stops working — so it is said HERE, while there is still a
                  // decision to make about it. ?>
            <button type="button" class="btn btn-primary btn-sm"
                    data-fb-doc="delete"
                    data-id="<?php echo (int)$choice['id']; ?>"
                    data-last="<?php echo $choice['last'] ? '1' : '0'; ?>">
              <?php echo FORMS_DOC_DELETE; ?></button>
<?php     } ?>
          </td>
        </tr>
<?php } } ?>
      </tbody>
    </table>
  </div>
</div>
<?php if ($security_level > 2) { ?>
<?php // The New icon on the toolbar is the FreeBooks convention for making a
      // thing, so it is where this belongs rather than in a panel under the
      // list. The three answers are collected BEFORE the editor opens, because
      // all three are properties of the document rather than of its layout —
      // and a canvas that opened first would have to ask for them afterwards,
      // or guess. ?>
<div class="modal fade" id="forms_new_modal" tabindex="-1"
     aria-labelledby="forms_new_title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <?php echo html_form('forms_new_form', FILENAME_DEFAULT,
          'module=forms&page=canvas&action=new'); ?>
      <div class="modal-header py-2">
        <h5 class="modal-title" id="forms_new_title"><?php echo FORMS_DOC_NEW_FORM; ?></h5>
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
<?php foreach ($forms_canvas_groups as $forms_new_code => $forms_new_name) { ?>
            <option value="<?php echo htmlspecialchars($forms_new_code); ?>"><?php
              echo htmlspecialchars($forms_new_name); ?></option>
<?php } ?>
          </select>
        </div>
        <div class="mb-1">
          <label class="form-label mb-0 small" for="forms_new_dataset"><?php echo FORMS_DOC_DATASET; ?></label>
          <select name="dataset" id="forms_new_dataset" class="form-select form-select-sm">
<?php foreach ($forms_canvas_datasets as $forms_new_code => $forms_new_name) { ?>
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
<?php } ?>

<?php // Copy and Delete post through their own form so the id and the new name
      // travel as POST rather than in a URL a browser would offer to repeat. ?>
<?php echo html_form('forms_doc_action', FILENAME_DEFAULT, 'module=forms&page=canvas'); ?>
  <input type="hidden" name="action"    id="forms_doc_action_kind"  value="">
  <input type="hidden" name="rID"       id="forms_doc_action_id"    value="">
  <input type="hidden" name="doc_title" id="forms_doc_action_title" value="">
</form>
<?php
  return;
}
?>
<h1><?php echo htmlspecialchars($forms_canvas_definition->title); ?></h1>

<?php echo html_form('forms_canvas_form', FILENAME_DEFAULT,
    'module=forms&page=canvas&action=save&rID=' . (int)$forms_canvas_id); ?>
<?php // A field, not a hidden value. It was hidden because the canvas had no
      // way to rename anything, so posting the stored name back was the only
      // safe thing to do; renaming is now the store's job and this is where a
      // form's name is edited (§13.29). TemplateForm::apply() has always read
      // this key — it simply never saw a different value. ?>
<div class="mb-2 d-inline-block">
  <label class="form-label mb-0 small"><?php echo FORMS_CANVAS_NAME; ?></label>
  <input type="text" name="title" maxlength="64" size="40"
         value="<?php echo htmlspecialchars($forms_canvas_definition->title); ?>">
</div>
<?php // Every element is submitted as hidden fields written by the canvas: the
      // boxes are dragged, not typed, and the row ORDER is the drawing order. ?>
<div id="forms_canvas_fields"></div>

<div class="alert alert-info py-2 px-3 small mb-2">
  <?php echo FORMS_CANVAS_INTRO; ?>
  <br><?php echo FORMS_CANVAS_GROUPING; ?>
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
          <?php // user-select is off across the whole stack: a shift-click is
                // how a group is built here, and the browser's own meaning for
                // it is "extend the text selection", which paints a blue
                // swathe over the page and hides the thing that did happen.
                // Nothing on this stack is text worth selecting anyway — it is
                // a rasterized picture with outlines over it. ?>
          <div id="forms_canvas_stack" style="position:relative;margin:0 auto;width:max-content;
                      user-select:none;-webkit-user-select:none;
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

            <?php // Only when more than one element is selected. The inspector
                  // still describes ONE of them — the one last clicked — because
                  // a mixed selection has no single position, and a control that
                  // showed one element's numbers while a typed change went to
                  // all of them would be the worst of both. What the group DOES
                  // share is the drag, the delete and the order arrows, so that
                  // is what the sentence names. ?>
            <p class="small text-muted mb-2 d-none" id="forms_canvas_many"></p>

            <div class="mb-2">
              <?php // What the element is CALLED, in the list and in the heading
                    // above. An element with no name is described by what it is,
                    // which reads well for text and for a value and not at all
                    // for a picture: two images on one form are both "image". ?>
              <label class="form-label mb-0 small" for="ins_label"><?php
                echo FORMS_CANVAS_LABEL; ?></label>
              <input type="text" class="form-control form-control-sm" id="ins_label">
              <div class="form-text"><?php echo FORMS_CANVAS_LABEL_NOTE; ?></div>
            </div>

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
<?php if ($security_level > 2) { ?>
              <?php // Deliberately NAMELESS. It sits inside the form that saves
                    // the whole canvas, and that form is urlencoded — a named
                    // file input would post its filename as a stray field on
                    // every save. The upload builds its own FormData instead. ?>
              <label class="form-label mb-0 small mt-2" for="ins_upload"><?php
                echo FORMS_CANVAS_UPLOAD; ?></label>
              <div class="input-group input-group-sm">
                <input type="file" class="form-control form-control-sm" id="ins_upload"
                       accept="image/png,image/jpeg,image/gif">
                <button type="button" class="btn btn-primary btn-sm" id="ins_upload_go"><?php
                  echo FORMS_CANVAS_UPLOAD_GO; ?></button>
              </div>
              <?php // The cap comes from the class that enforces it, so the
                    // sentence cannot drift away from the rule. ?>
              <div class="form-text"><?php echo sprintf(FORMS_CANVAS_UPLOAD_NOTE,
                $forms_canvas_max_mb); ?></div>
              <div class="small mt-1" id="ins_upload_says"></div>
<?php } ?>
            </div>

            <div class="mb-2" id="ins_bind_row">
              <label class="form-label mb-0 small" for="ins_bind"><?php echo FORMS_CANVAS_BIND; ?></label>
              <?php echo html_pull_down_menu('ins_bind', array_merge(
                  array(array('id' => '', 'text' => FORMS_DESIGN_GROUP_NONE)), $forms_canvas_palette),
                  '', 'class="form-select form-select-sm"'); ?>
              <div class="form-text"><?php echo FORMS_CANVAS_BIND_NOTE; ?></div>
            </div>

            <!--
              A company constant is bound but not choosable: the palette holds
              what the RECORD can supply, and this is not the record's. The
              select above would show an empty box on an element that is bound
              perfectly well, so it is swapped for the name in words.
            -->
            <div class="mb-2 d-none" id="ins_bind_fixed_row">
              <label class="form-label mb-0 small"><?php echo FORMS_CANVAS_BIND; ?></label>
              <div class="form-control form-control-sm bg-body-secondary" id="ins_bind_fixed"></div>
              <div class="form-text"><?php echo FORMS_CANVAS_BIND_COMPANY; ?></div>
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
          <?php // Same reason as the stack: these rows take a shift-click too. ?>
          <div style="max-height:34vh;overflow:auto;user-select:none;-webkit-user-select:none">
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
