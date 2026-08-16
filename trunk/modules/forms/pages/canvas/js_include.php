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
      // The resize corner is a visual affordance only; the pointer handler
      // decides by position, so there is nothing to keep in step.
      + (canEdit ? 'box-shadow:inset -6px -6px 0 -5px ' + colour + ';' : '');
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

let selected = null;

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
  return JSON.stringify({ boxes: boxes, values: values, selected: selected });
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

  place();
  rebuildList();
  select(selected);
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

/** Move an element by a delta in millimetres, drawn box and stored box alike. */
function shift(index, dx, dy) {
  boxes[index].x = rounded(boxes[index].x + dx);
  boxes[index].y = rounded(boxes[index].y + dy);
  values[index].x = String(rounded(parseFloat(values[index].x || 0) + dx));
  values[index].y = String(rounded(parseFloat(values[index].y || 0) + dy));
}

/** Resize by a delta. A blank stored side stays blank unless it is dragged. */
function stretch(index, dw, dh) {
  boxes[index].width  = Math.max(rounded(boxes[index].width  + dw), 0);
  boxes[index].height = Math.max(rounded(boxes[index].height + dh), 0);
  const w = values[index].w === '' ? null : parseFloat(values[index].w);
  const h = values[index].h === '' ? null : parseFloat(values[index].h);
  // Dragging a side is an author asking for that size, so a derived height —
  // every logo has one — becomes explicit at that moment and not before.
  values[index].w = String(Math.max(rounded((w === null ? boxes[index].width  - dw : w) + dw), 0));
  values[index].h = String(Math.max(rounded((h === null ? boxes[index].height - dh : h) + dh), 0));
}

function select(index) {
  selected = index === null ? null : Number(index);
  const has = selected !== null && values[selected] !== undefined;
  document.getElementById('forms_canvas_inspector').classList.toggle('d-none', !has);
  document.getElementById('forms_canvas_none').classList.toggle('d-none', has);
  if (!has) { highlight(null); return; }

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

function formsCanvasZ(by) {
  if (selected === null) return;
  const to = selected + by;
  if (to < 0 || to >= boxes.length) return;
  remember();
  [boxes[selected], boxes[to]]   = [boxes[to], boxes[selected]];
  [values[selected], values[to]] = [values[to], values[selected]];
  selected = to;
  place(); rebuildList(); highlight(selected);
  touched(0);
}

function formsCanvasDelete() {
  if (selected === null) return;
  if (!confirm(<?php echo json_encode(FORMS_CANVAS_CONFIRM_DELETE); ?>)) return;
  remember();
  boxes.splice(selected, 1);
  values.splice(selected, 1);
  selected = null;
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
      place(); rebuildList(); select(selected);
      touched(0);
    }
  } catch (error) {
    says.textContent = error.message || <?php echo json_encode(FORMS_UPLOAD_FAILED); ?>;
    says.className = 'small mt-1 text-danger';
  }
}

// Drag to move, drag the corner to resize. One pointer handler for both: the
// difference is only which numbers the delta is added to.
let dragging = null;

layer.addEventListener('pointerdown', function (event) {
  if (!canEdit) return;
  const div = event.target.closest('.fb-canvas-box');
  if (!div) return;
  const index = Number(div.dataset.index);
  select(index);

  // One snapshot per DRAG, taken as it starts — not one per pointermove, which
  // would fill the history with a hundred states nobody meant to make.
  remember();

  const rect    = div.getBoundingClientRect();
  const corner  = (event.clientX > rect.right - 10) && (event.clientY > rect.bottom - 10);
  dragging = { index: index, startX: event.clientX, startY: event.clientY,
               resize: corner, lastX: event.clientX, lastY: event.clientY };
  div.setPointerCapture(event.pointerId);
  event.preventDefault();
});

layer.addEventListener('pointermove', function (event) {
  if (!dragging) return;
  const dx = toMm(event.clientX - dragging.lastX);
  const dy = toMm(event.clientY - dragging.lastY);
  if (dx === 0 && dy === 0) return;
  dragging.lastX = event.clientX;
  dragging.lastY = event.clientY;
  if (dragging.resize) stretch(dragging.index, dx, dy);
  else                 shift(dragging.index, dx, dy);
  place();
  if (selected === dragging.index) select(selected);
  highlight(dragging.index);
});

['pointerup', 'pointercancel'].forEach(function (name) {
  layer.addEventListener(name, function () {
    if (!dragging) return;
    dragging = null;
    rebuildList();
    // On release, not on move: the page is under the pointer during a drag.
    touched(0);
  });
});

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

function highlight(index) {
  layer.querySelectorAll('.fb-canvas-box').forEach(function (div) {
    const on = String(div.dataset.index) === String(index);
    div.style.borderWidth = on ? '2px' : '1px';
    div.style.zIndex      = on ? '2' : '1';
  });
  Array.from(listing.rows).forEach(function (row) {
    row.classList.toggle('table-active', String(row.dataset.index) === String(index));
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
    row.addEventListener('mouseenter', function () { highlight(index); });
    row.addEventListener('click', function () { select(index); });
  });
  document.getElementById('forms_canvas_count').textContent =
      boxes.length + ' <?php echo addslashes(FORMS_CANVAS_ELEMENT_COUNT); ?>';
}

function escapeHtml(text) {
  const node = document.createElement('span');
  node.textContent = text;
  return node.innerHTML;
}

layer.addEventListener('mouseover', function (event) {
  const box = event.target.closest('.fb-canvas-box');
  if (box) highlight(box.dataset.index);
});

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
