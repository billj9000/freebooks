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
//  Path: /modules/forms/pages/canvas/js_include.php
//
/**
 * The canvas: rasterize the real PDF, put the element boxes over it.
 *
 * pdf.js ships as an ES module, so this is `type="module"` — which also means
 * it is deferred, and the DOM is ready by the time it runs.
 *
 * ## The one conversion
 *
 *     pixels = millimetres × scale × 72 / 25.4
 *
 * A Box is millimetres from the page's top-left, because that is what
 * LayoutPdfRenderer hands TCPDF, whose SetXY and Rect take absolute page
 * coordinates. pdf.js rasterizes at `scale` pixels per point, and its canvas
 * origin is the page's top-left with y pointing down — the same corner and the
 * same direction. So there is no page size in this file, no margins and no
 * orientation: get those wrong and the boxes drift; not having them means there
 * is nothing to get wrong. docs/tools/verify_forms_canvas.php pins the constant
 * against a real render.
 */
if ($forms_canvas_definition === null) return;
?>
<script type="module">
import * as pdfjs from '<?php echo DIR_WS_FULL_PATH; ?>ext/pdfjs/pdf.min.mjs';

// The worker is fetched by URL, not bundled: both files have to be served from
// the same directory, which is why they are vendored together.
pdfjs.GlobalWorkerOptions.workerSrc = '<?php echo DIR_WS_FULL_PATH; ?>ext/pdfjs/pdf.worker.min.mjs';

const PT_PER_MM = 72 / 25.4;

const boxes  = <?php echo json_encode($forms_canvas_boxes,  JSON_UNESCAPED_SLASHES); ?>;
// The editable values, in the same order. Kept apart from the drawn geometry on
// purpose: `boxes[i]` is where a thing is on screen, `values[i]` is what will be
// written to the file, and for a Line or an Image those are not the same numbers
// (see CanvasLayout).
const values = <?php echo json_encode($forms_canvas_values, JSON_UNESCAPED_SLASHES); ?>;
// What each element is called when it has no name of its own. CanvasLayout
// describes an unnamed element by what it IS — its text, its binding, its table
// source — and that description is worth keeping, because an author who types a
// name and then clears it should get back what was there rather than a blank
// row or a second guess at the same rule written in JavaScript.
boxes.forEach(function (box) { box.described = box.label; });

const canEdit = <?php echo $security_level > 2 ? 'true' : 'false'; ?>;
const source = <?php echo json_encode(html_href_link(FILENAME_DEFAULT,
    'module=forms&page=ajax&op=canvas_pdf&rID=' . (int)$forms_canvas_id, 'SSL')); ?>;

const view    = document.getElementById('forms_canvas_page');
const layer   = document.getElementById('forms_canvas_boxes');
const stack   = document.getElementById('forms_canvas_stack');
const status  = document.getElementById('forms_canvas_status');
const listing = document.getElementById('forms_canvas_list');

let doc = null, pageNumber = 1, scale = 1.25;

// A colour per element type. The point is telling them apart at a glance on a
// form carrying seventy of them, not decoration.
const COLOURS = {
  text: '#0d6efd', field: '#198754', block: '#20c997', table: '#6f42c1',
  line: '#adb5bd', rect: '#adb5bd', image: '#fd7e14', pagenum: '#6c757d',
  barcode: '#d63384', unknown: '#dc3545'
};

function say(message) { status.textContent = message; }

/**
 * Fetch the page.
 *
 * With `edited` true the canvas as it stands is POSTed and the picture is drawn
 * from that; otherwise the stored form is drawn. Nothing is saved either way.
 * The csrf_token is required because index.php refuses every POST without one —
 * read off the editor's own form rather than duplicated here.
 */
async function load(edited) {
  try {
    let request = { credentials: 'same-origin' };
    if (edited) {
      const body  = new FormData();
      const token = document.querySelector('#forms_canvas_form input[name=csrf_token]');
      if (token) body.append('csrf_token', token.value);
      values.forEach(function (value, index) {
        Object.keys(value).forEach(function (key) {
          body.append('element[' + index + '][' + key + ']',
              value[key] === undefined || value[key] === null ? '' : value[key]);
        });
      });
      request = { method: 'POST', body: body, credentials: 'same-origin' };
    }

    // Abandon whatever was already in the air: its answer is about a canvas
    // that no longer exists, and letting it finish would paint the older page
    // over the newer one.
    if (flight) flight.abort();
    flight = new AbortController();
    request.signal = flight.signal;

    say(edited ? <?php echo json_encode(FORMS_CANVAS_DRAWING); ?>
               : <?php echo json_encode(FORMS_CANVAS_LOADING); ?>);
    const response = await fetch(source, request);
    if (!response.ok) throw new Error('HTTP ' + response.status);
    // The endpoint answers with HTML when it cannot build the document, because
    // pdf.js reports a failed fetch as an opaque parse error. Reading the type
    // is what turns that into the sentence the server actually sent.
    const type = response.headers.get('Content-Type') || '';
    if (!type.includes('pdf')) {
      const text = await response.text();
      say(text.replace(/<[^>]*>/g, ' ').trim() || <?php echo json_encode(FORMS_CANVAS_FAILED); ?>);
      return;
    }
    document.getElementById('forms_canvas_sampled').classList
        .toggle('d-none', response.headers.get('X-Forms-Sampled') !== '1');

    // A binding the form cannot supply is refused when the picture is drawn,
    // exactly as it would be on save. An author who only discovers that when
    // they save has been editing something that was never going to be kept.
    const warnings = response.headers.get('X-Forms-Warnings');
    const banner   = document.getElementById('forms_canvas_warnings');
    banner.textContent = warnings || '';
    banner.classList.toggle('d-none', !warnings);
    doc = await pdfjs.getDocument({ data: await response.arrayBuffer() }).promise;
    await draw();
  } catch (error) {
    // An abandoned request is the normal course of events here, not a failure.
    if (error.name === 'AbortError') return;
    say(<?php echo json_encode(FORMS_CANVAS_FAILED); ?> + ' ' + error.message);
  }
}

async function draw() {
  const page     = await doc.getPage(pageNumber);
  const viewport = page.getViewport({ scale: scale });

  // Rendered at device resolution and shown at CSS size, so the underlay is
  // sharp on a high-DPI screen. The BOX layer is positioned in CSS pixels, so
  // it must use the CSS size and not the backing-store size — mixing the two is
  // how an overlay ends up half-scale on exactly the machines it was not
  // developed on.
  const ratio = window.devicePixelRatio || 1;
  view.width  = Math.floor(viewport.width  * ratio);
  view.height = Math.floor(viewport.height * ratio);
  view.style.width  = Math.floor(viewport.width)  + 'px';
  view.style.height = Math.floor(viewport.height) + 'px';
  stack.style.width  = view.style.width;
  stack.style.height = view.style.height;

  const context = view.getContext('2d');
  context.setTransform(ratio, 0, 0, ratio, 0, 0);
  await page.render({ canvasContext: context, viewport: viewport }).promise;

  place();
  // The selection survives a redraw: an author mid-adjustment should not have
  // to find their element again every time the picture refreshes.
  if (selected !== null) highlight(selected);
  document.getElementById('forms_canvas_pages').textContent = pageNumber + ' / ' + doc.numPages;
  document.getElementById('forms_canvas_zoom').textContent  = Math.round(scale * 100) + '%';
  say('');
}

/** Millimetres to CSS pixels at the current zoom. The whole of the alignment. */
function px(mm) { return mm * scale * PT_PER_MM; }

function place() {
  layer.textContent = '';
  boxes.forEach(function (box, index) {
    const div = document.createElement('div');
    const colour = COLOURS[box.kind] || COLOURS.unknown;
    div.className = 'fb-canvas-box';
    div.dataset.index = index;
    // A dashed bottom edge where the box is one LINE and the text flows on
    // below it — the height stored for a text element is a line height, not a
    // total. Solid on all four sides would claim the element ends there.
    // Dashed all round where the drawn size could not be worked out at all —
    // a missing image file, or one whose name comes from the record. A solid
    // box there would be a measurement the server never made.
    const sides = box.unmeasured
      ? 'border:1px dashed ' + colour + ';'
      : (box.grows
          ? 'border:1px solid ' + colour + ';border-bottom:1px dashed ' + colour + ';'
          : 'border:1px solid ' + colour + ';');
    div.style.cssText = 'position:absolute;pointer-events:auto;box-sizing:border-box;'
      + 'left:'   + px(box.x).toFixed(2) + 'px;'
      + 'top:'    + px(box.y).toFixed(2) + 'px;'
      + 'width:'  + Math.max(px(box.width),  2).toFixed(2) + 'px;'
      + 'height:' + Math.max(px(box.height), 2).toFixed(2) + 'px;'
      + sides + 'background:' + colour + '14;'
      + (canEdit ? 'cursor:move;' : '')
      // A mark in each corner, so the box says where it can be taken hold of.
      // All four resize, as do the four edges between them; the pointer handler
      // decides by position and the cursor changes to match, so this is an
      // affordance and never the rule.
      + (canEdit ? 'box-shadow:inset  6px  6px 0 -5px ' + colour + ','
                 +            'inset -6px  6px 0 -5px ' + colour + ','
                 +            'inset  6px -6px 0 -5px ' + colour + ','
                 +            'inset -6px -6px 0 -5px ' + colour + ';' : '');
    div.title = box.label + '  (' + box.x + ', ' + box.y + ') '
              + box.width + ' × ' + box.height + ' mm'
              + (box.grows ? '\n' + <?php echo json_encode(FORMS_CANVAS_GROWS); ?> : '')
              + (box.unmeasured ? '\n' + <?php echo json_encode(FORMS_CANVAS_UNMEASURED); ?> : '');
    layer.appendChild(div);
  });
  highlight(null);
}

// ── editing ─────────────────────────────────────────────────────────────────
//
// `boxes` and `values` are kept in step by INDEX, not by identity: a drag moves
// both by the same delta, so the drawn box and the stored one never disagree.
// Deleting splices both; adding pushes to both. The array ORDER is the drawing
// order, which is what the up/down buttons change.

// ── what is selected ────────────────────────────────────────────────────────
//
// TWO things, not one, and the difference matters at every call site.
//
// `chosen` is the whole selection — what a drag moves, what Delete removes,
// what the order arrows shuffle. `selected` is the ANCHOR: the one element the
// inspector describes and types back into, and the one a snapped drag is
// measured against. The anchor is always a member of the set.
//
// The inspector deliberately stays single. A mixed selection has no position,
// no font and no binding, so it would have to blank them; and a typed change
// applying to every member is a different and much larger promise than "these
// move together", which is what was asked for. Everything that IS shared — the
// drag, the delete, the z-order — acts on the set.

let selected = null;
const chosen = new Set();

// ── redrawing as the author works ───────────────────────────────────────────
//
// Three things make this safe to do on every change rather than on a button.
//
// 1. It fires on drag END, never during. A pointermove fires dozens of times a
//    second and the page is under the pointer anyway, so rendering mid-drag
//    would spend a PDF per pixel to show something nobody is looking at.
// 2. Requests are COALESCED and the older one is abandoned. Without that a slow
//    response can land after a fast one and paint an older page over a newer —
//    the picture would be wrong in a way that looks like a rendering bug rather
//    than a race.
// 3. It is a preference, remembered per browser. The author who asked for this
//    has a fast machine; the one on a slow link needs the button back.

// ── undo ────────────────────────────────────────────────────────────────────
//
// Whole-state snapshots rather than a command per action. The state is two
// small arrays of plain objects — 72 elements at the largest — so a snapshot is
// cheap, and it cannot drift: a command that knows how to reverse itself is one
// more thing to get wrong every time an edit is added, and getting it wrong
// means an undo that silently leaves the form in a state the author never made.
//
// Bounded, because a long session should not grow without limit, and cleared on
// leaving the page: this undoes edits, not saves.

const HISTORY_LIMIT = 60;
const history = [];
const future  = [];

function state() {
  return JSON.stringify({ boxes: boxes, values: values,
                          selected: selected, chosen: Array.from(chosen) });
}

/** Remember where we were. Called BEFORE a change, never after. */
function remember() {
  history.push(state());
  if (history.length > HISTORY_LIMIT) history.shift();
  // A fresh edit abandons the path forward: redo can only mean "the change I
  // just undid", never "a change from some other branch".
  future.length = 0;
  updateHistoryButtons();
}

function restore(snapshot) {
  const was = JSON.parse(snapshot);
  // The arrays are shared with everything that draws, so they are refilled in
  // place rather than replaced.
  boxes.length = 0;
  was.boxes.forEach(function (box) { boxes.push(box); });
  values.length = 0;
  was.values.forEach(function (value) { values.push(value); });
  selected = was.selected;
  // Restored, not rebuilt from the anchor: undoing a group drag should hand
  // back the group, or the next undo moves one element where the last moved
  // six. Older snapshots predate the set and carry only the anchor.
  chosen.clear();
  (was.chosen || (was.selected === null ? [] : [was.selected]))
      .forEach(function (index) { chosen.add(index); });

  place();
  rebuildList();
  describeSelection();
  updateHistoryButtons();
  touched(0);
}

function formsCanvasUndo() {
  if (!history.length) return;
  future.push(state());
  restore(history.pop());
}

function formsCanvasRedo() {
  if (!future.length) return;
  history.push(state());
  restore(future.pop());
}

function updateHistoryButtons() {
  document.getElementById('forms_canvas_undo').disabled = history.length === 0;
  document.getElementById('forms_canvas_redo').disabled = future.length === 0;
}

const LIVE_KEY = 'fbFormsCanvasLive';
let live    = window.localStorage ? localStorage.getItem(LIVE_KEY) !== '0' : true;
let timer   = null;
let flight  = null;   // AbortController for the request in the air

/** Something changed. Redraw soon, unless the author has turned that off. */
function touched(delay) {
  if (!live) return;
  clearTimeout(timer);
  // A short wait so a burst — typing in a box, nudging with the arrows —
  // becomes one render rather than one per keystroke.
  timer = setTimeout(function () { load(true); }, delay === undefined ? 250 : delay);
}

function snap() { return parseFloat(document.getElementById('forms_canvas_snap').value) || 0; }

function toMm(pixels) { return pixels / (scale * PT_PER_MM); }

function rounded(mm) {
  const step = snap();
  const value = step > 0 ? Math.round(mm / step) * step : mm;
  return Math.round(value * 1000) / 1000;
}

/** Three decimal places, which is what the server rounds boxes to. */
function fine(mm) { return Math.round(mm * 1000) / 1000; }

/**
 * Where each member of a drag started.
 *
 * A drag is applied to the position the elements had when it BEGAN, plus the
 * total delta — never to their current position plus the last increment. With
 * snapping on, an increment smaller than the step rounds away to nothing, so
 * the incremental form loses every slow movement: an author dragging carefully
 * across a 1 mm grid moves the element not at all. Measured from the origin,
 * the same slow drag crosses each step exactly once.
 *
 * @return object index -> the drawn box and the stored box, as they were
 */
function origins(indexes) {
  const was = {};
  indexes.forEach(function (index) {
    was[index] = { x: boxes[index].x, y: boxes[index].y,
                   width: boxes[index].width, height: boxes[index].height,
                   storeX: values[index].x, storeY: values[index].y,
                   storeW: values[index].w, storeH: values[index].h };
  });
  return was;
}

/**
 * Move every member of a drag by one delta, in millimetres.
 *
 * The delta is snapped ONCE, against the element the author actually grabbed,
 * and then applied to every member unrounded. Rounding each member separately
 * is what would pull a group apart: two elements 0.3 mm apart on a 1 mm grid
 * land either on the same line or a whole millimetre away from each other, and
 * the block the author was trying to keep together is precisely what gets bent.
 * Snapping the anchor keeps the grid meaningful; applying its delta to the rest
 * keeps the group rigid.
 */
function moveBy(was, anchor, dx, dy) {
  const from = was[anchor];
  const byX = rounded(from.x + dx) - from.x;
  const byY = rounded(from.y + dy) - from.y;

  Object.keys(was).forEach(function (key) {
    const index = Number(key), start = was[key];
    boxes[index].x = fine(start.x + byX);
    boxes[index].y = fine(start.y + byY);
    // The STORED position, moved by the same delta — a Line's origin is its
    // start point and may sit at either end of the drawn box (§13.11), so this
    // is not always the number above.
    values[index].x = String(fine(parseFloat(start.storeX || 0) + byX));
    values[index].y = String(fine(parseFloat(start.storeY || 0) + byY));
  });
}

/**
 * Resize one element by dragging a side or a corner.
 *
 * This works on the DRAWN box and derives the stored one from the result,
 * rather than adding the delta to the stored numbers. That is what makes a Line
 * behave. Its sides are a signed offset to the END POINT, so on a line drawn
 * leftward the drawn WEST edge is the stored origin and the drawn east edge is
 * the end: adding dx to the stored width there would stretch the wrong end and
 * the outline would part company with the ink. Deriving instead means the
 * direction is preserved by construction and there is no case to remember.
 *
 * @param zone one or two of n/s/e/w — which side or corner is being dragged
 */
function resize(index, zone, was, dx, dy) {
  const box = boxes[index], value = values[index], from = was[index];
  let x = from.x, y = from.y;
  let right = from.x + from.width, bottom = from.y + from.height;

  // A side that is not being dragged does not move, which is the whole point of
  // dragging a side rather than a corner. So the MOVING edge is what gets
  // snapped, and the fixed one is left exactly where it was. Snapping the width
  // instead would slide the far side about while the author was adjusting the
  // near one, on every element that does not already start on the grid — which,
  // after twenty years of hand-typed coordinates, is most of them.
  //
  // The moving edge is stopped at the fixed one rather than allowed through it:
  // a drag should not turn a box inside out, and a negative side means
  // something quite different on a Line.
  if (zone.indexOf('e') >= 0) right  = Math.max(rounded(right  + dx), x);
  if (zone.indexOf('w') >= 0) x      = Math.min(rounded(x      + dx), right);
  if (zone.indexOf('s') >= 0) bottom = Math.max(rounded(bottom + dy), y);
  if (zone.indexOf('n') >= 0) y      = Math.min(rounded(y      + dy), bottom);

  let w = Math.max(fine(right - x), 0);
  let h = Math.max(fine(bottom - y), 0);

  const sideways = zone.indexOf('e') >= 0 || zone.indexOf('w') >= 0;
  const upright  = zone.indexOf('n') >= 0 || zone.indexOf('s') >= 0;

  // An image stores a width and NO height, because TCPDF works the other side
  // out from the file (§13.11). Dragging its left or right edge is therefore
  // not silence about the height — it changes the height, by the file's aspect
  // ratio — so the OUTLINE has to follow, or it stops describing where the ink
  // lands the moment the width changes. The stored side stays blank: nothing
  // the author did said anything about it.
  if (!upright && from.storeH === '' && from.width > 0 && !box.unmeasured) {
    h = fine(from.height * (w / from.width));
  }
  if (!sideways && from.storeW === '' && from.height > 0 && !box.unmeasured) {
    w = fine(from.width * (h / from.height));
  }

  box.x = x; box.y = y; box.width = w; box.height = h;

  // The stored box, read back off the drawn one. A negative stored side means
  // the element is drawn from the far corner, so its origin follows that corner
  // and the sign is kept.
  const flipW = parseFloat(from.storeW) < 0;
  const flipH = parseFloat(from.storeH) < 0;
  // Taken as they are and never snapped again: the drawn edges have already
  // been put on the grid above, and re-rounding the far one here would move the
  // very side the author was holding still — the FIXED edge, on a line whose
  // origin happens to sit at that end.
  if (sideways) {
    value.x = String(fine(flipW ? x + w : x));
    // Dragging a side is an author asking for that size, so a derived height —
    // every logo has one — becomes explicit at that moment and not before.
    value.w = String(flipW ? -w : w);
  }
  if (upright) {
    value.y = String(fine(flipH ? y + h : y));
    value.h = String(flipH ? -h : h);
  }
}

/**
 * Choose an element.
 *
 * With `add` — shift, control or command held — the element joins the selection
 * or leaves it again, and the one just clicked becomes the anchor. Without it,
 * the selection becomes that element alone.
 */
function select(index, add) {
  if (index === null) {
    chosen.clear();
    selected = null;
  } else {
    const which = Number(index);
    if (!add) {
      chosen.clear();
      chosen.add(which);
      selected = which;
    } else if (chosen.has(which)) {
      chosen.delete(which);
      // The anchor has left the selection, so the inspector follows whatever is
      // still in it rather than describing an element nothing else acts on.
      if (selected === which) {
        selected = chosen.size ? chosen.values().next().value : null;
      }
    } else {
      chosen.add(which);
      selected = which;
    }
  }
  describeSelection();
}

/** The inspector, filled from the anchor. Selection is decided above. */
function describeSelection() {
  const has = selected !== null && values[selected] !== undefined;
  document.getElementById('forms_canvas_inspector').classList.toggle('d-none', !has);
  document.getElementById('forms_canvas_none').classList.toggle('d-none', has);
  if (!has) { highlight(null); return; }

  // Said only when it is true, and said in the inspector rather than on the
  // page, because the surprise a group selection can cause is a typed change
  // going somewhere the author did not expect.
  const many = document.getElementById('forms_canvas_many');
  many.classList.toggle('d-none', chosen.size < 2);
  many.textContent = chosen.size < 2 ? ''
      : <?php echo json_encode(FORMS_CANVAS_MANY); ?>.replace('%d', chosen.size);

  const box = boxes[selected], value = values[selected];
  document.getElementById('forms_canvas_what').textContent = box.label;
  document.getElementById('forms_canvas_kind').textContent = ' — ' + box.kind;
  // A text element's stored height is a LINE height, not an extent, so the
  // field is named for what it is rather than for what a box usually has.
  document.getElementById('ins_h_label').textContent = box.grows
    ? <?php echo json_encode(FORMS_CANVAS_LINE_HEIGHT); ?>
    : <?php echo json_encode(FORMS_CANVAS_H); ?>;

  set('ins_label', value.label);
  set('ins_x', value.x); set('ins_y', value.y); set('ins_w', value.w); set('ins_h', value.h);
  set('ins_text', value.text); set('ins_font', value.font); set('ins_size', value.size);
  set('ins_border', value.borderSize);
  document.getElementById('ins_colour').value       = value.colour       || '#000000';
  document.getElementById('ins_bordercolour').value = value.borderColour || '#000000';
  document.getElementById('ins_align').value  = value.align || '';
  document.getElementById('ins_visible').value = value.visible || 'all';
  document.getElementById('ins_bordershow').checked = value.borderShow === '1';
  document.getElementById('ins_bold').checked   = value.bold   === '1';
  document.getElementById('ins_italic').checked = value.italic === '1';
  document.getElementById('ins_bind').value = value.bind || '';
  document.getElementById('ins_src').value  = value.src  || '';

  // Only what the element can actually carry: a rectangle has no text and a
  // static label has nothing to bind to.
  show('ins_text_row', box.kind === 'text');
  // An image is one of two things: a fixed FILE, or a path that comes with the
  // record. They take opposite controls, and showing both would invite an
  // author to set one and wonder why the other did nothing.
  // "Draw it" gates the border on TEXT and on a barcode. A rectangle and a
  // rule ARE an outline — they draw one whatever the box says — so offering the
  // control there is offering a switch that does nothing.
  show('ins_bordershow_row',
       ['text', 'field', 'block', 'pagenum', 'table', 'barcode'].indexOf(box.kind) >= 0);
  // Weight and slant are a property of TEXT. A rule and a box have none, and a
  // picture is not drawn in a font.
  show('ins_weight_row',
       ['text', 'field', 'block', 'pagenum', 'table', 'barcode'].indexOf(box.kind) >= 0);
  show('ins_table_note', box.kind === 'table');
  show('ins_src_row',  box.kind === 'image' && box.source === 'file');
  show('ins_bind_row', box.kind === 'field' || box.kind === 'barcode'
                       || (box.kind === 'image' && box.source === 'bound'));
  highlight(selected);
}

function set(id, text)  { document.getElementById(id).value = text === undefined ? '' : text; }
function show(id, on)   { document.getElementById(id).classList.toggle('d-none', !on); }

/** The inspector writing back. Geometry also moves the box on the page. */
function inspectorChanged() {
  if (selected === null) return;
  remember();
  const value = values[selected], box = boxes[selected];

  ['x', 'y', 'w', 'h'].forEach(function (side) {
    const raw = document.getElementById('ins_' + side).value.trim();
    value[side] = raw;
    if (raw !== '' && !isNaN(parseFloat(raw))) {
      const number = parseFloat(raw);
      if (side === 'x') box.x = number;
      if (side === 'y') box.y = number;
      if (side === 'w') box.width  = Math.max(number, 0);
      if (side === 'h') box.height = Math.max(number, 0);
    }
  });

  // The name, and what the list falls back to without one. `described` is the
  // server's own description, kept from when the boxes arrived, so clearing the
  // name puts back exactly what CanvasLayout::describe() said rather than a
  // second guess at it written here.
  value.label = document.getElementById('ins_label').value.trim();
  box.label   = value.label || box.described;
  document.getElementById('forms_canvas_what').textContent = box.label;

  value.text       = document.getElementById('ins_text').value;
  value.font       = document.getElementById('ins_font').value.trim();
  value.size       = document.getElementById('ins_size').value.trim();
  value.borderSize = document.getElementById('ins_border').value.trim();
  value.align      = document.getElementById('ins_align').value;
  value.visible    = document.getElementById('ins_visible').value;
  value.borderShow = document.getElementById('ins_bordershow').checked ? '1' : '';
  value.bold       = document.getElementById('ins_bold').checked   ? '1' : '';
  value.italic     = document.getElementById('ins_italic').checked ? '1' : '';
  // A colour input cannot say "unset": it shows black when nothing is stored.
  // Writing it back unconditionally would turn every element the author merely
  // SELECTED into one with an explicit black, changing the file on a save where
  // nothing about the colour was touched. So it is written only when it differs
  // from what was loaded into it.
  const colour = document.getElementById('ins_colour').value;
  if (colour !== (value.colour || '#000000')) value.colour = colour;
  const borderColour = document.getElementById('ins_bordercolour').value;
  if (borderColour !== (value.borderColour || '#000000')) value.borderColour = borderColour;
  const bind = document.getElementById('ins_bind').value;
  if (bind !== '') value.bind = bind;
  const src = document.getElementById('ins_src').value;
  if (src !== '') value.src = src;

  place();
  rebuildList();
  highlight(selected);
  touched();
}

/**
 * Move the whole selection one place through the drawing order.
 *
 * The members are stepped in the direction of travel — nearest the destination
 * first — so each swap is with an element that is not itself moving, and the
 * order WITHIN the selection is preserved. If any member would fall off the
 * end, nothing moves at all: sliding the rest past the one that is stuck is how
 * a block quietly rearranges itself.
 */
function formsCanvasZ(by) {
  if (!chosen.size) return;
  const order = Array.from(chosen).sort(function (a, b) { return by < 0 ? a - b : b - a; });
  if (order.some(function (index) {
        const to = index + by;
        return to < 0 || to >= boxes.length;
      })) return;

  remember();
  order.forEach(function (index) {
    const to = index + by;
    [boxes[index], boxes[to]]   = [boxes[to], boxes[index]];
    [values[index], values[to]] = [values[to], values[index]];
  });
  const moved = order.map(function (index) { return index + by; });
  chosen.clear();
  moved.forEach(function (index) { chosen.add(index); });
  selected = selected === null ? null : selected + by;

  place(); rebuildList(); describeSelection();
  touched(0);
}

function formsCanvasDelete() {
  if (!chosen.size) return;
  const asked = chosen.size > 1
    ? <?php echo json_encode(FORMS_CANVAS_CONFIRM_DELETE_MANY); ?>.replace('%d', chosen.size)
    : <?php echo json_encode(FORMS_CANVAS_CONFIRM_DELETE); ?>;
  if (!confirm(asked)) return;

  remember();
  // Highest index first, or every splice moves the ones still to come.
  Array.from(chosen).sort(function (a, b) { return b - a; }).forEach(function (index) {
    boxes.splice(index, 1);
    values.splice(index, 1);
  });
  place(); rebuildList(); select(null);
  touched(0);
}

function formsCanvasAdd(kind) {
  remember();

  // An image stores a width and NO height — the shape comes from the file — so
  // a new one submits a blank height and is drawn at the same placeholder size
  // CanvasLayout uses for an image it cannot measure. Choosing a file replaces
  // both numbers with the real ones.
  const image  = kind === 'image';
  const height = image ? '' : (kind === 'line' ? '0' : '5');
  const drawn  = image ? 25 : (kind === 'line' ? 0 : 5);

  // Dropped at a fixed spot rather than under the pointer: the author is about
  // to drag it anyway, and a new element hidden under the one they clicked is
  // worse than one they can see.
  const box = { index: boxes.length, kind: kind, x: 20, y: 20,
                width: 40, height: drawn,
                label: <?php echo json_encode(FORMS_CANVAS_NEW); ?>,
                described: <?php echo json_encode(FORMS_CANVAS_NEW); ?>, visible: 'all',
                grows: (kind === 'text' || kind === 'field'), unmeasured: image,
                // Which of the two image controls the inspector offers. A new
                // one always names a file: binding it to the record needs a
                // path prefix and a value, neither of which is set here.
                source: image ? 'file' : '',
                store: { x: 20, y: 20, w: 40, h: height } };
  boxes.push(box);
  values.push({ index: '', kind: kind, x: '20', y: '20', w: '40',
                h: height, bind: '', text: '', visible: 'all',
                label: '', font: '', size: '', colour: '', borderColour: '', align: '',
                borderSize: '', borderShow: '', truncate: '', bold: '', italic: '', src: '' });
  place(); rebuildList(); select(boxes.length - 1);
  touched(0);
}

/** Every element as hidden fields, in drawing order. */
function formsCanvasSave() {
  if (!canEdit) return;
  const holder = document.getElementById('forms_canvas_fields');
  holder.textContent = '';
  values.forEach(function (value, index) {
    Object.keys(value).forEach(function (key) {
      const field = document.createElement('input');
      field.type  = 'hidden';
      field.name  = 'element[' + index + '][' + key + ']';
      field.value = value[key] === undefined || value[key] === null ? '' : value[key];
      holder.appendChild(field);
    });
  });
  document.getElementById('forms_canvas_form').submit();
}

// ── adding an image ─────────────────────────────────────────────────────────
//
// The element can point at a file; this is how a file gets there. The server
// decides the NAME — it is derived from the type actually read out of the
// bytes, and numbered if it collides — so the answer is read back rather than
// assumed, and the whole list comes with it.

const uploadTo = <?php echo json_encode(html_href_link(FILENAME_DEFAULT,
    'module=forms&page=ajax&op=canvas_image', 'SSL')); ?>;

/**
 * How big the image will be DRAWN, given what the element stores.
 *
 * The same rule as CanvasLayout::imageSize, because it is the same question and
 * a second answer would put the outline somewhere the ink is not. An Image
 * usually stores a width and no height — TCPDF derives the rest — so changing
 * the file changes the shape, which is why an upload has to do this at all.
 */
function imageDrawn(storeW, storeH, pixelW, pixelH) {
  const w = storeW === '' || isNaN(parseFloat(storeW)) ? 0 : parseFloat(storeW);
  const h = storeH === '' || isNaN(parseFloat(storeH)) ? 0 : parseFloat(storeH);
  if (w > 0 && h > 0) return [w, h];
  if (!pixelW || !pixelH) return [w || 25, h || 25];
  const ratio = pixelH / pixelW;
  if (w > 0) return [w, w * ratio];
  if (h > 0) return [h / ratio, h];
  // Neither given: TCPDF's natural size, pixels read as 72 dpi.
  return [pixelW * 25.4 / 72, pixelH * 25.4 / 72];
}

/** Rebuild the file menu from what the SERVER has, keeping the choice made. */
function fillImages(names, chosen) {
  const menu = document.getElementById('ins_src');
  if (!menu) return;
  // Option 0 is the "none" entry, written by the page. Everything after it is
  // the folder, so the folder is what gets replaced.
  while (menu.options.length > 1) menu.remove(1);
  names.forEach(function (name) {
    const option = document.createElement('option');
    option.value = name;
    option.textContent = name;
    menu.appendChild(option);
  });
  menu.value = chosen || '';
}

async function formsCanvasUpload() {
  const chooser = document.getElementById('ins_upload');
  const says    = document.getElementById('ins_upload_says');
  if (!chooser || !chooser.files || !chooser.files.length) {
    says.textContent = <?php echo json_encode(FORMS_UPLOAD_NOTHING); ?>;
    says.className = 'small mt-1 text-danger';
    return;
  }

  const body  = new FormData();
  const token = document.querySelector('#forms_canvas_form input[name=csrf_token]');
  if (token) body.append('csrf_token', token.value);
  body.append('image', chooser.files[0]);

  says.textContent = <?php echo json_encode(FORMS_CANVAS_UPLOADING); ?>;
  says.className = 'small mt-1 text-muted';
  try {
    const response = await fetch(uploadTo,
        { method: 'POST', body: body, credentials: 'same-origin' });
    // Every answer is JSON, refusals included — so the message shown is the
    // server's own sentence about which check failed, not a status code.
    const answer = await response.json();
    if (!response.ok) throw new Error(answer.error || ('HTTP ' + response.status));

    fillImages(answer.images || [], answer.name);
    says.textContent = <?php echo json_encode(FORMS_CANVAS_UPLOADED); ?>
        .replace('%s', answer.name);
    says.className = 'small mt-1 text-success';
    chooser.value = '';

    // The selected element takes the new file, and changes shape with it: the
    // stored height is usually blank, so the outline has to be re-measured
    // here or it keeps the proportions of the file that was there before.
    if (selected !== null && values[selected] !== undefined) {
      remember();
      values[selected].src = answer.name;
      const drawn = imageDrawn(values[selected].w, values[selected].h,
                               answer.width, answer.height);
      boxes[selected].width      = Math.round(drawn[0] * 1000) / 1000;
      boxes[selected].height     = Math.round(drawn[1] * 1000) / 1000;
      boxes[selected].unmeasured = false;
      place(); rebuildList(); describeSelection();
      touched(0);
    }
  } catch (error) {
    says.textContent = error.message || <?php echo json_encode(FORMS_UPLOAD_FAILED); ?>;
    says.className = 'small mt-1 text-danger';
  }
}

// ── the pointer ─────────────────────────────────────────────────────────────
//
// Three gestures through one pair of handlers. Press a box and drag: the whole
// selection moves. Press one of its edges or corners: that element resizes.
// Press empty page and drag: a band sweeps up everything it encloses.
//
// Which box a press lands on is decided by boxAt(), NOT by what the browser puts
// under the pointer — see the note there.

/**
 * Which part of a box the pointer is over — '' for the middle, or one or two of
 * n/s/e/w for a side or a corner.
 *
 * The grip is never more than a third of the side it sits on, and an axis with
 * less than THIN pixels to give has no grip at all. A horizontal rule is drawn
 * two pixels tall: a grip at each end of that swallows the middle, so the
 * element could be resized and never moved — and on a Line, worse than that.
 * Its stored height is the y offset of its END POINT, so a drag the author
 * meant as a move would turn the rule into a diagonal.
 */
const GRIP = 6;
const THIN = 8;

function zoneAt(div, clientX, clientY) {
  const rect = div.getBoundingClientRect();
  const gx = rect.width  < THIN ? 0 : Math.min(GRIP, rect.width  / 3);
  const gy = rect.height < THIN ? 0 : Math.min(GRIP, rect.height / 3);
  let zone = '';
  // A zero grip means NO grip, so the comparison has to be guarded: a pointer
  // landing exactly on the edge would otherwise still resize.
  if (gy > 0) {
    if      (clientY <= rect.top    + gy) zone += 'n';
    else if (clientY >= rect.bottom - gy) zone += 's';
  }
  if (gx > 0) {
    if      (clientX <= rect.left   + gx) zone += 'w';
    else if (clientX >= rect.right  - gx) zone += 'e';
  }
  return zone;
}

/**
 * Which box a pointer at this point is aimed at: the SMALLEST one containing it.
 *
 * Not `event.target.closest()`, which is the uppermost one, and uppermost is the
 * wrong answer on a form. Every box is a filled surface (see place()), and the
 * array order is the drawing order, so a frame drawn around a block of fields
 * covers all of them: clicking any field inside it selected the frame instead,
 * every time, with no way in from the page at all. Reported from use, and the
 * reason the Elements list had to be the answer.
 *
 * Smallest-wins reads the author's intent directly — a container is essentially
 * never what someone is pointing at when they point inside it — and costs the
 * frame nothing, because a frame is mostly empty: it is still grabbed anywhere
 * no smaller box covers, and always from the list.
 *
 * The exception is a grip on something already selected. Without it, selecting a
 * frame and then reaching for its corner hands back a field standing in front of
 * that corner, and the resize the author aimed at silently becomes a drag of
 * something else. A held box keeps the pointer while the pointer is on a handle.
 *
 * Stacking no longer decides anything here, which is what makes the hover z-index
 * bump in highlight() safe: it is now purely something to look at.
 */
function boxAt(clientX, clientY) {
  let best = null, bestArea = Infinity;
  let held = null, heldArea = Infinity;
  layer.querySelectorAll('.fb-canvas-box').forEach(function (div) {
    const rect = div.getBoundingClientRect();
    if (clientX < rect.left || clientX > rect.right
     || clientY < rect.top  || clientY > rect.bottom) return;
    // Never zero: a rule is drawn two pixels tall and one pixel wide would still
    // have to be comparable against the block sitting over it.
    const area = Math.max(rect.width, 1) * Math.max(rect.height, 1);
    if (chosen.has(Number(div.dataset.index))
        && zoneAt(div, clientX, clientY) !== '' && area < heldArea) {
      held = div; heldArea = area;
    }
    if (area < bestArea) { best = div; bestArea = area; }
  });
  return held || best;
}

// The conventional cursors: a diagonal double arrow on a corner, named for the
// axis it lies along, and a straight one on a side.
const CURSORS = {
  '':   'move',
  n:    'ns-resize',   s:  'ns-resize',
  e:    'ew-resize',   w:  'ew-resize',
  nw:   'nwse-resize', se: 'nwse-resize',
  ne:   'nesw-resize', sw: 'nesw-resize'
};

/**
 * Hold one cursor over the whole page while a drag is in progress.
 *
 * Each box carries its own, so without this a resize that carried the pointer
 * across a neighbour would show that neighbour's — the cursor reporting a
 * gesture nobody is making.
 */
function dragCursor(cursor) {
  stack.style.cursor = cursor;
  layer.querySelectorAll('.fb-canvas-box').forEach(function (div) {
    div.style.cursor = cursor || 'move';
  });
}

let dragging = null;
let band     = null;
// Which box the pointer was last over, so the hover highlight is only redrawn
// when the answer changes. Cleared whenever something else has taken it over —
// a drag, or a row in the Elements list — so the next movement redraws.
let hovering = null;

layer.addEventListener('pointerdown', function (event) {
  if (!canEdit) return;
  // The PRIMARY button only. A right-click otherwise begins a drag or a band
  // that the context menu immediately interrupts, leaving the canvas holding a
  // gesture the author cannot finish.
  if (event.button !== 0) return;
  // A gesture owns the highlight from here until it ends.
  hovering = null;

  const div = boxAt(event.clientX, event.clientY);
  const add = event.shiftKey || event.ctrlKey || event.metaKey;

  if (!div) {
    // Empty page. A band, unless the author is only clearing the selection —
    // which is what a press and release in the same place turns out to be.
    const at = layer.getBoundingClientRect();
    band = { x: event.clientX - at.left, y: event.clientY - at.top,
             add: add, was: Array.from(chosen), div: null, swept: false };
    layer.setPointerCapture(event.pointerId);
    stack.style.cursor = 'crosshair';
    event.preventDefault();
    return;
  }

  const index = Number(div.dataset.index);
  if (add) {
    // The default action of a shift-click is to EXTEND THE DOCUMENT'S TEXT
    // SELECTION. Left alone it paints a blue swathe across the canvas, which
    // is what an author sees the first time they try to build a group — the
    // element does join the selection, but the browser's own behaviour buries
    // the evidence. Refusing it here, and `user-select: none` on the stack,
    // are the two halves of the same fix.
    event.preventDefault();
    // Building a selection, not moving one: a modified click adds or removes
    // and stops there, so the element it just removed cannot be dragged by the
    // same gesture that took it out.
    select(index, true);
    return;
  }
  // Pressing a member of a group keeps the group — otherwise a group could
  // never be dragged, because grabbing it would collapse it first. The anchor
  // follows the press so the inspector describes what is under the pointer.
  if (!chosen.has(index)) select(index, false);
  else if (selected !== index) { selected = index; describeSelection(); }

  const zone = zoneAt(div, event.clientX, event.clientY);
  // A resize acts on the element whose handle was grabbed, a move on all of
  // them. Scaling a group from one corner is a different feature with different
  // questions — what happens to a text element's line height, to a line's
  // direction — and guessing at it here would be the wrong place to answer them.
  dragging = { index: index, zone: zone, moved: false,
               startX: event.clientX, startY: event.clientY,
               was: origins(zone === '' ? Array.from(chosen) : [index]) };
  layer.setPointerCapture(event.pointerId);
  dragCursor(CURSORS[zone] || 'move');
  event.preventDefault();
});

layer.addEventListener('pointermove', function (event) {
  if (band) {
    const at = layer.getBoundingClientRect();
    sweep(event.clientX - at.left, event.clientY - at.top);
    return;
  }

  if (!dragging) {
    // Nothing under way: the cursor and the highlight are what have to keep up,
    // and the cursor is how an author discovers the sides can be dragged at all.
    if (!canEdit) return;
    // The box a PRESS would take, which is not the one the browser has under the
    // pointer. Two different divs, and each is needed for a different half:
    //
    //  - the zone, and so the cursor shape, belong to the box that would be
    //    resized — `over`;
    //  - the cursor has to be WRITTEN on the topmost box, because that is the
    //    one the browser consults. Writing it on `over` while a frame stands in
    //    front leaves the frame's own `cursor: move` showing, and the pointer
    //    then describes a gesture nobody can make.
    const over = boxAt(event.clientX, event.clientY);
    // Off everything: the highlight goes out. What is SELECTED keeps its ring —
    // highlight() reads that from `chosen`, not from the pointer.
    if (!over) {
      if (hovering !== null) { hovering = null; highlight(null); }
      return;
    }
    const top = event.target.closest('.fb-canvas-box') || over;
    top.style.cursor = CURSORS[zoneAt(over, event.clientX, event.clientY)] || 'move';
    // Only on a change: this runs on every mouse movement across the page, and
    // highlight() walks every box and every row in the list.
    if (over.dataset.index !== hovering) {
      hovering = over.dataset.index;
      highlight(hovering);
    }
    return;
  }

  const dx = toMm(event.clientX - dragging.startX);
  const dy = toMm(event.clientY - dragging.startY);

  // The snapshot is taken on the first MOVEMENT rather than on the press: a
  // click that selects an element is not a change, and recording one would fill
  // the history with states nobody meant to make. Still one per drag — nothing
  // has been altered yet at this point, so it is still "before".
  if (!dragging.moved) { remember(); dragging.moved = true; }

  if (dragging.zone === '') moveBy(dragging.was, dragging.index, dx, dy);
  else                      resize(dragging.index, dragging.zone, dragging.was, dx, dy);

  place();
  // place() builds the boxes afresh, each with the plain move cursor written
  // into its style, so the drag cursor has to be put back or a resize reverts
  // to reporting a move the moment the element is redrawn under the pointer.
  dragCursor(CURSORS[dragging.zone] || 'move');
  describeSelection();
  highlight(dragging.index);
});

['pointerup', 'pointercancel'].forEach(function (name) {
  layer.addEventListener(name, function (event) {
    hovering = null;
    if (band) {
      if (band.div) band.div.remove();
      // A press and release with no sweep between them is a click on the page,
      // and on the page there is nothing to select.
      if (!band.swept && !band.add) select(null);
      band = null;
      stack.style.cursor = '';
      rebuildList();
      return;
    }
    if (!dragging) return;
    // A press that never moved is a click. On a member of a group that means
    // "just this one" — the ordinary way back out of a selection.
    if (!dragging.moved && chosen.size > 1) select(dragging.index, false);
    const wasMoved = dragging.moved;
    dragging = null;
    dragCursor('');
    if (!wasMoved) { rebuildList(); return; }
    rebuildList();
    // On release, not on move: the page is under the pointer during a drag.
    touched(0);
  });
});

/**
 * The rubber band: draw it, and select everything it wholly encloses.
 *
 * Enclosing, not touching. A form is a dense thing — a band drawn across the
 * middle of a page brushes the rules above and below it, the full-width heading
 * it passes under and the table border it ends inside, and the author gets a
 * selection they did not ask for and cannot see the edges of. Requiring
 * containment costs a longer drag for a wide element and gives an exact answer:
 * what is inside the band is what is selected.
 *
 * The 2 px floor is the one the boxes are DRAWN with (see place()), so a rule
 * with no height is judged by the line the author can see, not by a zero.
 */
function sweep(toX, toY) {
  const x1 = Math.min(band.x, toX), x2 = Math.max(band.x, toX);
  const y1 = Math.min(band.y, toY), y2 = Math.max(band.y, toY);
  if (x2 - x1 < 3 && y2 - y1 < 3) return;
  band.swept = true;

  if (!band.div) {
    band.div = document.createElement('div');
    band.div.style.cssText = 'position:absolute;pointer-events:none;z-index:3;'
      + 'border:1px dashed #0d6efd;background:#0d6efd14';
    layer.appendChild(band.div);
  }
  band.div.style.left   = x1 + 'px';
  band.div.style.top    = y1 + 'px';
  band.div.style.width  = (x2 - x1) + 'px';
  band.div.style.height = (y2 - y1) + 'px';

  // Rebuilt from what the band encloses on every move, plus whatever was already
  // held if the author is adding to a selection. Recomputing rather than
  // accumulating is what lets the band be made SMALLER again: an element the
  // author has dragged back off should leave, and one that only ever grew a set
  // would keep it.
  chosen.clear();
  if (band.add) band.was.forEach(function (index) { chosen.add(index); });
  boxes.forEach(function (box, index) {
    const bx = px(box.x), by = px(box.y);
    const bw = Math.max(px(box.width), 2), bh = Math.max(px(box.height), 2);
    if (bx >= x1 && bx + bw <= x2 && by >= y1 && by + bh <= y2) chosen.add(index);
  });
  if (selected === null || !chosen.has(selected)) {
    selected = chosen.size ? chosen.values().next().value : null;
  }
  describeSelection();
}

document.addEventListener('click', function (event) {
  const button = event.target.closest('.fb-canvas-add');
  if (button) formsCanvasAdd(button.dataset.add);
});

// A module has its OWN scope: nothing declared in here is global, so an inline
// onclick — including the toolbar's Save, whose markup is generated by
// build_toolbar() and cannot carry a listener of its own — sees no such
// function and does nothing at all. Not an error the page shows; nothing.
// pdf.js ships as a module, which is what makes this file one.
window.formsCanvasSave   = formsCanvasSave;
window.formsCanvasZ      = formsCanvasZ;
window.formsCanvasDelete = formsCanvasDelete;
window.formsCanvasAdd    = formsCanvasAdd;
window.formsCanvasUndo   = formsCanvasUndo;
window.formsCanvasRedo   = formsCanvasRedo;

['ins_label','ins_x','ins_y','ins_w','ins_h','ins_text','ins_font','ins_size','ins_border',
 'ins_align','ins_visible','ins_bordershow','ins_colour','ins_bordercolour',
 'ins_bold','ins_italic','ins_bind','ins_src'].forEach(function (id) {
  const control = document.getElementById(id);
  if (control) control.addEventListener('change', inspectorChanged);
});

/**
 * Thicken what is selected, and whatever the pointer is resting on.
 *
 * `index` is the hover, which is not the selection: pointing at a row in the
 * list should find that element on the page without disturbing the six the
 * author has spent a minute gathering. So both are drawn, and the ANCHOR — the
 * one the inspector is describing — is ringed as well, because in a group of
 * six otherwise identical outlines there is nothing else to say which one the
 * numbers on the right belong to.
 */
function highlight(index) {
  layer.querySelectorAll('.fb-canvas-box').forEach(function (div) {
    const which = Number(div.dataset.index);
    const held  = chosen.has(which);
    const hovered = String(which) === String(index);
    div.style.borderWidth = (held || hovered) ? '2px' : '1px';
    // Raised by the HOVER only, never by being selected. Lifting a selected box
    // above its neighbours changes which one a click lands on, so on a form
    // where elements overlap — the cheque does — shift-clicking to add a second
    // element keeps hitting the first and toggles it straight back off. That
    // reads as "the modifier does nothing". The ring below is what makes a
    // selection visible; it does not need to be on top to be seen.
    div.style.zIndex = hovered ? '2' : '1';
    // A ring, not a slightly thicker border. Every outline on this page is
    // already coloured and 1 px, so "2 px instead of 1" is not a signal an
    // author can read at a glance — and a selection they cannot see is one
    // they will not believe they made. SOLID on the anchor, DASHED on the
    // others: everything ringed is selected, and the solid one is the element
    // the inspector on the right is describing.
    div.style.outline = held
        ? (which === selected ? '2px solid #212529' : '2px dashed #212529')
        : '';
    div.style.outlineOffset = '1px';
  });
  Array.from(listing.rows).forEach(function (row) {
    const which = Number(row.dataset.index);
    row.classList.toggle('table-active',
        chosen.has(which) || String(which) === String(index));
  });
}

function rebuildList() { listing.textContent = ''; fillList(); }

function fillList() {
  boxes.forEach(function (box, index) {
    const row = listing.insertRow();
    // The POSITION in the array, not the element's original index: the array is
    // the drawing order and the original index is only how it was loaded.
    row.dataset.index = index;
    row.style.cursor = 'pointer';
    row.innerHTML =
        '<td style="width:8px;padding-right:0"><span style="display:inline-block;width:8px;height:8px;'
      + 'background:' + (COLOURS[box.kind] || COLOURS.unknown) + '"></span></td>'
      + '<td class="small">' + escapeHtml(box.label) + '</td>'
      + '<td class="small text-muted text-end text-nowrap">'
      + box.x + ', ' + box.y + '</td>';
    // Clearing `hovering` too: the canvas highlight has been taken over from
    // here, so returning the pointer to the box it was already on must redraw.
    row.addEventListener('mouseenter', function () { hovering = null; highlight(index); });
    // The same modifiers as the page, so a group can be gathered from the list
    // — which is the only way to reach an element sitting under another one.
    row.addEventListener('click', function (event) {
      select(index, event.shiftKey || event.ctrlKey || event.metaKey);
    });
  });
  document.getElementById('forms_canvas_count').textContent =
      boxes.length + ' <?php echo addslashes(FORMS_CANVAS_ELEMENT_COUNT); ?>';
}

function escapeHtml(text) {
  const node = document.createElement('span');
  node.textContent = text;
  return node.innerHTML;
}

// The hover highlight is driven from pointermove, not from `mouseover`, because
// the two now disagree: mouseover fires on entering a DIV, and the div entered is
// the frame in front. Inside one frame the pointer can cross a dozen fields
// without a single mouseover, so the highlight would name the frame throughout.
layer.addEventListener('pointerleave', function () { hovering = null; });

document.getElementById('forms_canvas_show').addEventListener('change', function () {
  layer.style.display = this.checked ? '' : 'none';
});
document.getElementById('forms_canvas_in').addEventListener('click', function () {
  scale = Math.min(scale * 1.25, 4); draw();
});
document.getElementById('forms_canvas_out').addEventListener('click', function () {
  scale = Math.max(scale / 1.25, 0.4); draw();
});
document.getElementById('forms_canvas_prev').addEventListener('click', function () {
  if (doc && pageNumber > 1) { pageNumber--; draw(); }
});
document.getElementById('forms_canvas_next').addEventListener('click', function () {
  if (doc && pageNumber < doc.numPages) { pageNumber++; draw(); }
});

document.getElementById('forms_canvas_render').addEventListener('click', function () {
  clearTimeout(timer);
  load(true);
});

// Only present when this author may write: the control is not rendered at all
// below level 3, and the endpoint refuses at the same level.
const uploadGo = document.getElementById('ins_upload_go');
if (uploadGo) uploadGo.addEventListener('click', formsCanvasUpload);

document.getElementById('forms_canvas_undo').addEventListener('click', formsCanvasUndo);
document.getElementById('forms_canvas_redo').addEventListener('click', formsCanvasRedo);

document.addEventListener('keydown', function (event) {
  if (!(event.ctrlKey || event.metaKey)) return;
  // Inside a text box the browser's own undo is what the author means; taking
  // it over would make typing feel broken to fix a problem it does not have.
  const target = event.target;
  if (target && /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName)) return;

  const key = event.key.toLowerCase();
  if (key === 'z' && !event.shiftKey) { event.preventDefault(); formsCanvasUndo(); }
  else if (key === 'y' || (key === 'z' && event.shiftKey)) { event.preventDefault(); formsCanvasRedo(); }
});

const liveBox = document.getElementById('forms_canvas_live');
liveBox.checked = live;
liveBox.addEventListener('change', function () {
  live = this.checked;
  if (window.localStorage) localStorage.setItem(LIVE_KEY, live ? '1' : '0');
  // Turning it on should show the current state at once, not wait for the next
  // change — otherwise the first thing it does is look broken.
  if (live) touched(0);
});

fillList();
load(false);
</script>
