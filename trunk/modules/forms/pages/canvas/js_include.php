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
// The document actions, in a block of their OWN and BEFORE the return below.
// Two reasons, and both bit: this file returns early on the CHOOSER, which is
// the only place New, Copy and Delete appear at all; and the script that
// follows is a `type="module"`, where a function declaration is not global, so
// an inline handler could never have reached one (§13.29).
forms_document_actions_script();

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

// Which elements a condition kept off the page the server has just drawn
// (design 13.51). Positions into `boxes`, sent back by the render endpoint as a
// header, because the answer is the renderer's own rather than a second test
// written here that would be free to disagree with it.
//
// It can be one edit out of date, and knowingly so: place() runs the instant an
// element is dragged or deleted, while the picture underneath is still being
// fetched. The next draw() corrects it. Ghosting the wrong outline for a moment
// is a smaller cost than not ghosting at all, which is an author looking at
// three boxes stacked on one another with no way to tell which one is printing.
let suppressed = new Set();

// Which record the underlay is drawn from (§13.53), and a reference the author
// has typed but not yet been answered about.
//
// Held here rather than read off the controls at request time, because the two
// disagree in the case that matters: the select shows what was ASKED FOR, and
// the endpoint may have drawn something else — a record this form declines, a
// reference that matched nothing. The page is corrected from the answer, and
// `wantedRecord` is what is re-sent on every later redraw so that editing an
// element does not quietly return the canvas to the newest invoice.
let wantedRecord    = '';
let wantedReference = '';

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
 * The page corrected from the answer: which record the endpoint actually drew.
 *
 * Three cases, and only one of them is "what you asked for". A record can be
 * declined by the form's own filters — the newest row of journal_main is rarely
 * an invoice — and a typed reference can match nothing at all. Both fall back to
 * a record that does build, which is the right behaviour and is indistinguishable
 * from success unless it is said.
 *
 * The drawn record is added to the list when it is not already in it, because a
 * reference typed from memory reaches records older than the twenty the page was
 * built with, and a select that cannot show the answer would snap back to the
 * wrong entry on the next redraw.
 */
function recordDrawn(key, label) {
  const picker = document.getElementById('forms_canvas_record');
  const says   = document.getElementById('forms_canvas_record_says');
  if (!picker || !says) return;          // no records to choose between

  const asked = wantedReference !== '' ? wantedReference : wantedRecord;
  key   = key   ? decodeURIComponent(key)   : '';
  label = label ? decodeURIComponent(label) : key;

  if (key !== '') {
    if (!Array.from(picker.options).some(function (o) { return o.value === key; })) {
      picker.add(new Option(label, key));
    }
    picker.value  = key;
    wantedRecord  = key;
  }
  // Answered, so it must not be re-sent: leaving it set would make every later
  // redraw repeat a search whose answer is already on screen, and would override
  // the list the moment the author used it.
  wantedReference = '';
  const find = document.getElementById('forms_canvas_record_find');
  if (find) find.value = '';

  let note = '';
  if (asked !== '' && key === '') {
    note = <?php echo json_encode(FORMS_CANVAS_RECORD_NONE); ?>.replace('%s', asked);
  } else if (asked !== '' && key !== '' && asked !== key && asked !== label) {
    note = <?php echo json_encode(FORMS_CANVAS_RECORD_OTHER); ?>.replace('%s', asked);
  }
  says.textContent = note;
}

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

    // On the URL either way. The endpoint reads $_REQUEST, so one spelling
    // serves both the stored-form GET and the edited-form POST — and a record
    // choice that travelled only on the POST would be forgotten by every
    // redraw that did not carry the elements with it.
    let url = source;
    if (wantedRecord !== '')    url += '&rcd='    + encodeURIComponent(wantedRecord);
    if (wantedReference !== '') url += '&rcdref=' + encodeURIComponent(wantedReference);

    // Abandon whatever was already in the air: its answer is about a canvas
    // that no longer exists, and letting it finish would paint the older page
    // over the newer one.
    if (flight) flight.abort();
    flight = new AbortController();
    request.signal = flight.signal;

    say(edited ? <?php echo json_encode(FORMS_CANVAS_DRAWING); ?>
               : <?php echo json_encode(FORMS_CANVAS_LOADING); ?>);
    const response = await fetch(url, request);
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

    // An empty header is no elements, not every element: split('') on '' gives
    // one empty string, which Number()s to 0 and would ghost the first box on
    // every form that has no conditions at all.
    recordDrawn(response.headers.get('X-Forms-Record'),
                response.headers.get('X-Forms-Record-Label'));

    const off = (response.headers.get('X-Forms-Suppressed') || '')
        .split(',').filter(function (part) { return part !== ''; });
    suppressed = new Set(off.map(Number));
    document.getElementById('forms_canvas_ghosted').classList
        .toggle('d-none', suppressed.size === 0);

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
    // Faded, not hidden and not restyled. An element whose condition does not
    // hold for the record on screen is still on the form and still has to be
    // selectable - it is the one the author has come to edit - so it keeps its
    // outline, its handles and its place in the order. Opacity is used rather
    // than a border treatment because dashed already means two other things
    // here, a line height and an unmeasured picture, and a third meaning on the
    // same channel is a legend nobody can hold in their head.
    if (suppressed.has(index)) div.style.opacity = '0.35';
    div.title = box.label + '  (' + box.x + ', ' + box.y + ') '
              + box.width + ' × ' + box.height + ' mm'
              + (box.grows ? '\n' + <?php echo json_encode(FORMS_CANVAS_GROWS); ?> : '')
              + (box.unmeasured ? '\n' + <?php echo json_encode(FORMS_CANVAS_UNMEASURED); ?> : '')
              + (suppressed.has(index)
                   ? '\n' + <?php echo json_encode(FORMS_CANVAS_WHEN_GHOSTED); ?> : '');
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
  document.getElementById('ins_colour').value       = value.colour || '#000000';
  document.getElementById('ins_bordercolour').value = borderColourShown(box, value);
  document.getElementById('ins_align').value  = value.align || '';
  document.getElementById('ins_visible').value = value.visible || 'all';
  document.getElementById('ins_when_bind').value  = value.when_bind  || '';
  document.getElementById('ins_when_op').value    = value.when_op    || 'eq';
  document.getElementById('ins_when_value').value = value.when_value || '';
  showWhenControls();
  document.getElementById('ins_bordershow').checked = value.borderShow === '1';
  set('ins_hdr_size', value.hdrSize);
  set('ins_hdr_border', value.hdrBorderSize);
  document.getElementById('ins_hdr_bordercolour').value = value.hdrBorderColour || '#000000';
  document.getElementById('ins_hdr_bordershow').checked = value.hdrBorderShow === '1';
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

  // A line and a box ARE the outline, and the width control IS their thickness
  // — the renderer's SetLineWidth for both comes from `borderSize`. Labelled
  // "Border width" it read as a property of something else, so the panel looked
  // as though a rule's thickness could not be set at all; the control an author
  // reached for instead was Size, which a rule has no use for. Both are fixed
  // here: the width is named for what it does, and the three settings the
  // renderer never looks at on these two kinds are taken off the panel
  // (design §13.49, issue #65).
  const outline = box.kind === 'line' || box.kind === 'rect';
  document.getElementById('ins_border_label').textContent = outline
    ? <?php echo json_encode(FORMS_CANVAS_THICKNESS); ?>
    : <?php echo json_encode(FORMS_CANVAS_BORDER); ?>;
  document.getElementById('ins_border_note').textContent = outline
    ? <?php echo json_encode(FORMS_CANVAS_THICKNESS_NOTE); ?>
    : <?php echo json_encode(FORMS_CANVAS_BORDER_NOTE); ?>;
  // Hidden, not cleared. inspectorChanged() writes all three back on every
  // edit, and describeSelection() has just loaded them from the element, so a
  // form opened, edited and saved returns the font it arrived with.
  show('ins_font_row',  !outline);
  show('ins_size_row',  !outline);
  show('ins_align_row', !outline);
  // And ONE colour, not two. The renderer reads `borderColour ?? colour`, so on
  // a line and a box the two swatches were two ways to say one thing with an
  // unstated precedence — and on every rectangle in the shipped set, where
  // `borderColour` is set and `colour` is not, the one labelled Colour could
  // not change anything at all. The surviving swatch is seeded with whichever
  // value is in force and writes to the one that wins.
  show('ins_colour_row', !outline);
  document.getElementById('ins_bordercolour_label').textContent = outline
    ? <?php echo json_encode(FORMS_DESIGN_COLOUR); ?>
    : <?php echo json_encode(FORMS_CANVAS_BORDER_COLOUR); ?>;
  show('ins_table_note', box.kind === 'table');
  show('ins_src_row',  box.kind === 'image' && box.source === 'file');

  // The four kinds the canvas learnt in §13.30. A page number's whole content
  // is one pattern and a barcode's is one symbology, so both are edited in
  // place; a table's columns and a block's lines are grids, and the button
  // carries the count so the sidebar says how many there are without opening
  // anything.
  show('ins_pattern_row',   box.kind === 'pagenum');
  show('ins_symbology_row', box.kind === 'barcode');
  set('ins_pattern', value.pattern);
  if (box.kind === 'barcode') {
    document.getElementById('ins_symbology').value = value.symbology || 'C128';
  }

  show('ins_columns_row', box.kind === 'table');
  show('ins_heading_row', box.kind === 'table');
  show('ins_parts_row',   box.kind === 'block');
  if (box.kind === 'table') {
    document.getElementById('ins_columns_open').textContent =
      <?php echo json_encode(FORMS_CANVAS_COLUMNS_EDIT); ?>
        .replace('%d', subRows(value.columns).length);
  }
  if (box.kind === 'block') {
    document.getElementById('ins_parts_open').textContent =
      <?php echo json_encode(FORMS_CANVAS_PARTS_EDIT); ?>
        .replace('%d', subRows(value.parts).length);
  }
  // One control for both kinds of binding (§13.55). A company detail used to be
  // shown as read-only words here — the palette held only what the RECORD
  // supplies, so the select had no option to match and showed an empty box on
  // an element bound perfectly well. That also made a company detail impossible
  // to ADD: the only ones on any form were the ones it was born with. The
  // palette now carries both groups and TemplateForm decides the scope from the
  // name, so the select is live whatever the element is currently bound to.
  const bindable = box.kind === 'field' || box.kind === 'barcode'
                   || (box.kind === 'image' && box.source === 'bound');
  show('ins_bind_row', bindable);
  highlight(selected);
}

function set(id, text)  { document.getElementById(id).value = text === undefined ? '' : text; }
function show(id, on)   { document.getElementById(id).classList.toggle('d-none', !on); }

/**
 * How much of the condition to show, and what to say underneath it.
 *
 * Read off the CONTROLS rather than off the stored value, because it is called
 * from two places that disagree about which is current: describeSelection() has
 * just written the controls from the element, and inspectorChanged() has just
 * read them from the author. The controls are right in both.
 *
 * Two things are hidden rather than disabled. The test and the value do not
 * exist until a field is chosen - "Always" is the answer on almost every
 * element of almost every form, and an operator offered beside it is a question
 * nobody asked. And `is blank` takes no value, so the box beside it is a box an
 * author will type into and then wonder why it did nothing.
 */
function showWhenControls() {
  const bind = document.getElementById('ins_when_bind').value;
  const op   = document.getElementById('ins_when_op').value;
  show('ins_when_test_row', bind !== '');
  show('ins_when_value_col', bind !== '' && op !== 'empty' && op !== 'notempty');

  const note = bind === ''
    ? <?php echo json_encode(FORMS_CANVAS_WHEN_NOTE); ?>
    : ((op === 'in' || op === 'nin')
        ? <?php echo json_encode(FORMS_CANVAS_WHEN_LIST_NOTE); ?> : '');
  document.getElementById('ins_when_note').textContent = note;
  show('ins_when_note', note !== '');
}

/**
 * What the border-colour swatch shows, and so what a change to it is measured
 * against. ONE function because the two call sites have to agree exactly: a
 * colour input cannot say "unset", so the only way to tell a colour the author
 * chose from one the control merely displays is that it differs from what was
 * loaded into it.
 *
 * On a line and a box the effective colour is `borderColour ?? colour` — the
 * renderer's own rule — so that is what is shown, and the panel is not claiming
 * black on an element drawn in silver. Everywhere else the two are genuinely
 * separate: black text inside a silver rule is 16 elements of the shipped set.
 */
function borderColourShown(box, value) {
  const outline = box.kind === 'line' || box.kind === 'rect';
  return (outline ? (value.borderColour || value.colour) : value.borderColour) || '#000000';
}

/**
 * Commit whatever the inspector is still holding, before anything moves on.
 *
 * pointerdown on the canvas is preventDefault()ed — to refuse a shift-click's
 * text selection, and to own the drag that may follow — and a suppressed
 * pointerdown takes the browser's focus change with it. So a field the author
 * has just typed in never blurs, never fires `change`, and describeSelection()
 * paints the next element's numbers straight over the edit: the change is gone
 * without so much as a redraw to say it happened.
 *
 * The second half is worse, because it is the half that looks random. The
 * control KEEPS focus across the selection change, so the value `change` is
 * measured against — whatever the field held when focus first arrived — goes
 * stale, while describeSelection() rewrites the field underneath it. A later
 * edit that happens to land back on that stale value fires no `change` at all.
 * That is why setting a border to 0 was sometimes remembered and sometimes not:
 * it depended on what the border had been on whichever element the author last
 * clicked into the field on, which is not a thing anybody can see.
 *
 * Blurring here puts both right. The pending edit is committed against the
 * element it was typed for — inspectorChanged() runs while `selected` is still
 * that element — and the next focus starts a fresh baseline.
 */
function commitInspector() {
  const active = document.activeElement;
  if (active && typeof active.blur === 'function'
      && document.getElementById('forms_canvas_inspector').contains(active)) {
    active.blur();
  }
}

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
  // Written for EVERY element and unconditionally, which is the opposite of the
  // rule the colour swatches follow - and deliberately. A colour control cannot
  // say "unset", so writing it always would invent one; these three CAN say it,
  // and "Always" is exactly what an empty field means. Writing them only
  // sometimes would make clearing a condition impossible: TemplateForm carries
  // an absent key, so a cleared field that was never sent would come back.
  value.when_bind  = document.getElementById('ins_when_bind').value;
  value.when_op    = document.getElementById('ins_when_op').value;
  value.when_value = document.getElementById('ins_when_value').value;
  showWhenControls();
  value.borderShow = document.getElementById('ins_bordershow').checked ? '1' : '';
  // The heading row's four. Written for EVERY element, not only a table: the
  // binder decides what to do with them by element kind, and a value posted
  // for a text box is ignored there — whereas a field posted only sometimes is
  // a field the binder cannot tell from one deliberately cleared.
  value.hdrSize       = document.getElementById('ins_hdr_size').value.trim();
  value.hdrBorderSize = document.getElementById('ins_hdr_border').value.trim();
  value.hdrBorderShow = document.getElementById('ins_hdr_bordershow').checked ? '1' : '';
  value.bold       = document.getElementById('ins_bold').checked   ? '1' : '';
  value.italic     = document.getElementById('ins_italic').checked ? '1' : '';
  // A colour input cannot say "unset": it shows black when nothing is stored.
  // Writing it back unconditionally would turn every element the author merely
  // SELECTED into one with an explicit black, changing the file on a save where
  // nothing about the colour was touched. So it is written only when it differs
  // from what was loaded into it.
  const colour = document.getElementById('ins_colour').value;
  if (colour !== (value.colour || '#000000')) value.colour = colour;
  // Against what describeSelection() PUT there, which on a line or a box is not
  // simply `value.borderColour`. Comparing against the wrong thing would write
  // an explicit colour onto every outline element merely selected — the file
  // changing on a save where nothing about the colour was touched, which is the
  // accident this three-line dance exists to avoid.
  const borderColour = document.getElementById('ins_bordercolour').value;
  if (borderColour !== borderColourShown(box, value)) value.borderColour = borderColour;
  const hdrBorderColour = document.getElementById('ins_hdr_bordercolour').value;
  if (hdrBorderColour !== (value.hdrBorderColour || '#000000')) value.hdrBorderColour = hdrBorderColour;
  // Written for every bindable element, company details included. The guard
  // that used to stand here skipped context elements because their control was
  // not the one on screen; now it is, and skipping would make a company detail
  // the one binding an author could see and not change.
  const bind = document.getElementById('ins_bind').value;
  if (bind !== '') value.bind = bind;
  const src = document.getElementById('ins_src').value;
  if (src !== '') value.src = src;

  // Written only for the element that HAS one. Both controls keep whatever the
  // last page number or barcode put in them, so writing unconditionally would
  // give a rectangle a symbology and a save something to store about it.
  if (box.kind === 'pagenum') value.pattern   = document.getElementById('ins_pattern').value;
  if (box.kind === 'barcode') value.symbology = document.getElementById('ins_symbology').value;

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
  const table  = kind === 'table';
  // A table is a REGION, not a line of text, and these have to agree with
  // TemplateForm::create() — the canvas draws the outline, the binder stores the
  // box, and a form whose new table jumped size on its first save would be one
  // the author had to re-place having already placed it.
  const height = image ? '' : (table ? '100' : (kind === 'line' ? '0' : '5'));
  const drawn  = image ? 25 : (table ? 100 : (kind === 'line' ? 0 : 5));
  const width  = table ? 180 : 40;

  // Dropped at a fixed spot rather than under the pointer: the author is about
  // to drag it anyway, and a new element hidden under the one they clicked is
  // worse than one they can see.
  const box = { index: boxes.length, kind: kind, x: 20, y: 20,
                width: width, height: drawn,
                label: <?php echo json_encode(FORMS_CANVAS_NEW); ?>,
                described: <?php echo json_encode(FORMS_CANVAS_NEW); ?>, visible: 'all',
                // Which elements grow past the box they declare, because their
                // box is a line height rather than an extent. The same list
                // CanvasLayout::boxes() uses on the server.
                grows: ['text', 'field', 'block', 'pagenum'].indexOf(kind) >= 0,
                unmeasured: image,
                // Which of the two image controls the inspector offers. A new
                // one always names a file: binding it to the record needs a
                // path prefix and a value, neither of which is set here.
                source: image ? 'file' : '',
                store: { x: 20, y: 20, w: width, h: height } };
  boxes.push(box);
  values.push({ index: '', kind: kind, x: '20', y: '20', w: String(width),
                h: height, bind: '', text: '', visible: 'all',
                label: '', font: '', size: '', colour: '', borderColour: '', align: '',
                // A line and a box are given a thickness rather than left
                // blank. Blank draws the renderer's own default — a hairline
                // for a rule — so the one control that matters on a new
                // element started out empty and looked like one that did not
                // apply. 1 point is the thin rule the manual has always
                // described, and matches what an unset box has always drawn
                // (§13.49).
                borderSize: (kind === 'line' || kind === 'rect') ? '1' : '',
                borderShow: '', truncate: '', bold: '', italic: '', src: '',
                hdrSize: '', hdrBorderSize: '', hdrBorderColour: '', hdrBorderShow: '',
                // Unconditional, and the keys PRESENT rather than absent. The
                // binder treats an absent key as "says nothing" and carries
                // what was there, which for a new element is nothing either
                // way — but the inspector reads these to fill its controls, and
                // an undefined here would leave a new element showing whatever
                // condition the last one selected had.
                when_bind: '', when_op: 'eq', when_value: '',
                // Empty, and explicitly so. `[]` is what tells the binder the
                // author means none rather than means nothing — a blank is read
                // as "say nothing about it", which for a NEW element would
                // leave it with no columns anyway, but the two must stay
                // distinguishable or deleting the last column of an existing
                // table would be impossible.
                columns: table ? '[]' : '',
                parts:   kind === 'block' ? '[]' : '',
                pattern: kind === 'pagenum' ? '{page} / {pages}' : '',
                symbology: kind === 'barcode' ? 'C128' : '' });
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

// ── a table's columns, and an address block's lines (§13.30) ───────────────
//
// Both structures travel as JSON in ONE canvas value — `values[i].columns`,
// `values[i].parts` — for the reason TemplateForm gives: the save writes hidden
// fields from a flat object and the live preview POSTs the same object, and a
// nested `element[i][column][j][bind]` would mean teaching both of those, and
// undo, to walk a tree.
//
// While a grid is open the DOM IS the model. Every mutation re-reads the whole
// table rather than patching the row it touched, which is the rule the report
// editor and the receipt editor both keep, and it removes a whole class of
// bug: there is no second copy of the list to fall out of step.
//
// What the grid does NOT show still has to survive it. A column carries a
// style, a `total` flag no renderer reads, and a format's options; a part
// carries its scope. Those ride on the <tr> as a property rather than as hidden
// inputs — the row element is what moves when a row is reordered, so they move
// with it, and they cannot be reached by anything that posts.

/** A sub-structure value as an array. A blank is none; unreadable is none. */
function subRows(text) {
  if (!text) return [];
  try {
    const rows = JSON.parse(text);
    return Array.isArray(rows) ? rows : [];
  } catch (e) { return []; }
}

// Which element the open grid belongs to. Held apart from `selected` because a
// modal can outlive a change of selection in ways that are not worth relying
// on, and writing a table's columns onto whatever happens to be selected when
// the dialog closes would be the worst bug on this page.
let subIndex = null;

const SUB = {
  columns: { modal: 'forms_columns_modal', rows: 'forms_columns_rows',
             tpl: 'forms_columns_template', empty: 'forms_columns_empty',
             add: 'forms_columns_add', title: 'forms_columns_title',
             heading: <?php echo json_encode(FORMS_CANVAS_COLUMNS_TITLE); ?>,
             button: 'ins_columns_open',
             label: <?php echo json_encode(FORMS_CANVAS_COLUMNS_EDIT); ?>,
             fields: ['bind', 'heading', 'width', 'align', 'format'],
             blank: { bind: '', scope: 'record', sumOf: [], heading: '', width: '',
                      align: '', format: '', formatOptions: [], total: '', style: null } },
  parts:   { modal: 'forms_parts_modal', rows: 'forms_parts_rows',
             tpl: 'forms_parts_template', empty: 'forms_parts_empty',
             add: 'forms_parts_add', title: 'forms_parts_title',
             heading: <?php echo json_encode(FORMS_CANVAS_PARTS_TITLE); ?>,
             button: 'ins_parts_open',
             label: <?php echo json_encode(FORMS_CANVAS_PARTS_EDIT); ?>,
             fields: ['bind', 'separator', 'format'],
             blank: { bind: '', scope: 'record', sumOf: [], separator: 'nl',
                      format: '', formatOptions: [] } }
};

/** Draw the grid for the selected element. */
function subOpen(key) {
  const spec = SUB[key];
  const body = document.getElementById(spec.rows);
  body.textContent = '';
  subIndex = selected;
  if (subIndex === null || values[subIndex] === undefined) return;

  document.getElementById(spec.title).textContent =
      spec.heading.replace('%s', boxes[subIndex].label);
  subRows(values[subIndex][key]).forEach(function (entry) {
    body.appendChild(subRow(spec, entry));
  });
  subShowEmpty(spec);
}

/** One row, from the page's own <template>. */
function subRow(spec, entry) {
  const tpl = document.getElementById(spec.tpl);
  const row = tpl.content.firstElementChild.cloneNode(true);

  // Everything the grid has no control for. Kept on the row, so a reorder
  // carries it and a delete takes it away.
  row._extra = { scope: entry.scope || 'record', sumOf: entry.sumOf || [],
                 name: entry.bind || '', total: entry.total || '',
                 style: entry.style === undefined ? null : entry.style,
                 formatOptions: entry.formatOptions || [] };

  spec.fields.forEach(function (field) {
    const control = row.querySelector('[data-col=' + field + ']');
    if (!control) return;
    control.value = entry[field] === undefined || entry[field] === null ? '' : entry[field];
  });

  // A part bound to one of the company's own details takes no select: the
  // palette holds what the RECORD supplies and has no option for it, so the
  // control would read empty on a line that is bound perfectly well. It says
  // what it is in words instead — and TemplateForm refuses to re-point one
  // whatever is posted, so nothing is lost by not offering it.
  if (row._extra.scope !== 'record') {
    const select = row.querySelector('[data-col=bind]');
    const fixed  = row.querySelector('[data-col=fixed]');
    if (select) select.classList.add('d-none');
    if (fixed) {
      fixed.classList.remove('d-none');
      fixed.textContent = row._extra.name;
      fixed.title = <?php echo json_encode(FORMS_CANVAS_PART_FIXED); ?>;
    }
  }
  return row;
}

/** The grid, back into the element's value. */
function subWrite(key) {
  const spec = SUB[key];
  if (subIndex === null || values[subIndex] === undefined) return;

  const out = [];
  Array.from(document.getElementById(spec.rows).rows).forEach(function (row) {
    const extra = row._extra || { scope: 'record' };
    const entry = { bind: extra.scope === 'record'
                            ? (row.querySelector('[data-col=bind]') || {}).value || ''
                            : extra.name,
                    scope: extra.scope, sumOf: extra.sumOf || [] };
    spec.fields.forEach(function (field) {
      if (field === 'bind') return;
      const control = row.querySelector('[data-col=' + field + ']');
      entry[field] = control ? control.value : '';
    });
    entry.formatOptions = extra.formatOptions || [];
    if (key === 'columns') { entry.total = extra.total || ''; entry.style = extra.style; }
    out.push(entry);
  });

  remember();
  values[subIndex][key] = JSON.stringify(out);
  // The count in the sidebar, without redrawing the whole inspector — which
  // would close nothing but would reset every control the author is looking at.
  const button = document.getElementById(spec.button);
  if (button) button.textContent = spec.label.replace('%d', out.length);
  subShowEmpty(spec);
  rebuildList();
  touched();
}

/**
 * Say so when there is nothing in it.
 *
 * An empty table and an empty block both draw NOTHING, and an element that
 * draws nothing looks on the canvas exactly like one that is broken. Since both
 * can now be created empty, the state is reachable on purpose and has to be
 * explained where it is reached rather than discovered at the printer.
 */
function subShowEmpty(spec) {
  const empty = document.getElementById(spec.empty);
  if (empty) empty.classList.toggle('d-none',
      document.getElementById(spec.rows).rows.length > 0);
}

/** Add, move and remove, for both grids. */
function subWire(key) {
  const spec = SUB[key];
  const modal = document.getElementById(spec.modal);
  if (!modal) return;

  // Populated as it opens rather than when the selection changes: the grid
  // belongs to whichever element is selected, and building it eagerly would
  // rebuild it on every click on the page.
  modal.addEventListener('show.bs.modal', function () { subOpen(key); });

  document.getElementById(spec.add).addEventListener('click', function () {
    if (subIndex === null) return;
    const body = document.getElementById(spec.rows);
    body.appendChild(subRow(spec, Object.assign({}, spec.blank)));
    subWrite(key);
  });

  const body = document.getElementById(spec.rows);
  body.addEventListener('click', function (event) {
    const button = event.target.closest('[data-sub]');
    if (!button) return;
    const row  = button.closest('tr');
    const rows = Array.from(body.rows);
    const at   = rows.indexOf(row);

    if (button.dataset.sub === 'remove') row.remove();
    // insertBefore with a null reference APPENDS, which is what moving the last
    // row down would otherwise do to the first one.
    if (button.dataset.sub === 'up'   && at > 0)               body.insertBefore(row, rows[at - 1]);
    if (button.dataset.sub === 'down' && at < rows.length - 1) body.insertBefore(rows[at + 1], row);
    subWrite(key);
  });

  // One listener for every control in the grid, because they are created and
  // destroyed as rows come and go and a per-control listener would have to be
  // attached each time — and forgetting once is a field that silently does not
  // save.
  // `change`, not `input` — the same event every other control on this page
  // commits on. Binding `input` would put one undo entry, one redraw and one
  // preview request on the wire per KEYSTROKE while a heading is typed.
  body.addEventListener('change', function (event) {
    if (event.target.closest('[data-col]')) subWrite(key);
  });
}

subWire('columns');
subWire('parts');

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
  // Before the selection moves, and before place() rebuilds what boxAt() is
  // about to look through: a field the author was typing in has to be written
  // back to the element it was typed for.
  commitInspector();
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

// Every control in the inspector, or the one left out is a control that looks
// editable and does nothing — no error, no console, just a form that will not
// redraw. The heading-row four were added to the panel and forgotten here, and
// the only symptom was that changing them appeared to have no effect.
['ins_label','ins_x','ins_y','ins_w','ins_h','ins_text','ins_font','ins_size','ins_border',
 'ins_align','ins_visible','ins_bordershow','ins_colour','ins_bordercolour',
 'ins_when_bind','ins_when_op','ins_when_value',
 'ins_bold','ins_italic','ins_bind','ins_src',
 'ins_pattern','ins_symbology',
 'ins_hdr_size','ins_hdr_border','ins_hdr_bordershow','ins_hdr_bordercolour'].forEach(function (id) {
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
      + '<td class="small">' + escapeHtml(box.label)
      // Marked in the LIST because that is where stacked elements are reached
      // from: three bank blocks at one position are one box on the page and
      // three rows here, and without a mark the rows are three identical
      // labels. The dot is titled rather than lettered, so it needs no room and
      // no translation.
      + (values[index] && values[index].when_bind
          ? ' <span title="' + escapeHtml(<?php echo json_encode(FORMS_CANVAS_WHEN_MARK); ?>)
            + '">&#9671;</span>' : '')
      + '</td>'
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

// The record picker (§13.53). Both ways in end at the same redraw, and BOTH
// redraw with `edited` true: the author has a canvas in front of them that may
// differ from what is stored, and answering "show me the euro invoice" by
// throwing away their unsaved layout would be a worse surprise than the one
// they asked to fix.
const recordPicker = document.getElementById('forms_canvas_record');
if (recordPicker) {
  recordPicker.addEventListener('change', function () {
    commitInspector();
    wantedRecord    = this.value;
    wantedReference = '';
    clearTimeout(timer);
    load(true);
  });

  const findRecord = function () {
    const typed = document.getElementById('forms_canvas_record_find').value.trim();
    if (typed === '') return;
    commitInspector();
    wantedReference = typed;
    clearTimeout(timer);
    load(true);
  };
  document.getElementById('forms_canvas_record_go').addEventListener('click', findRecord);
  // Enter in the reference box searches rather than submitting the form it sits
  // inside — which would SAVE, from a key pressed to ask a question (§13.52).
  document.getElementById('forms_canvas_record_find')
      .addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    findRecord();
  });
}

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

/**
 * Delete removes the selection.
 *
 * Backspace too, and not as an afterthought: a MacBook has no Delete key at all
 * — its key marked "delete" IS Backspace, and forward delete needs Fn. Binding
 * only the one would mean the feature simply does not exist on that hardware.
 *
 * Both go through formsCanvasDelete(), which asks before it removes anything and
 * says how many. So the cost of an accidental press is a dialog, which is what
 * makes the more easily-pressed key safe to accept.
 *
 * Three things must NOT be deleted through:
 *
 *   1. a text box, where both keys mean what they always mean;
 *   2. any other focused control — a <button> passes the tag test above but a
 *      row's ↑ ↓ × buttons live INSIDE the column grid, and deleting the whole
 *      table because the author had just clicked one of its own controls is the
 *      worst version of this feature;
 *   3. an open modal. The grids are modals, and while one is up the subject on
 *      screen is a column or a line, not the element that owns it.
 *
 * Deliberately not preventDefault()ed when it declines: the browser's own
 * meaning for the key is the right one in every case above, Backspace's history
 * of navigating back included — which is why the guard is a bail rather than a
 * swallow.
 */
document.addEventListener('keydown', function (event) {
  if (event.key !== 'Delete' && event.key !== 'Backspace') return;
  if (event.ctrlKey || event.metaKey || event.altKey) return;

  // Nothing selected is not an error and not a beep: the author pressed a key
  // that has no subject.
  if (!chosen.size) return;

  const target = event.target;
  if (target && target !== document.body) {
    if (/^(INPUT|TEXTAREA|SELECT|BUTTON|A)$/.test(target.tagName)) return;
    if (target.isContentEditable) return;
  }
  // Bootstrap puts this on <body> for as long as a dialog is up.
  if (document.body.classList.contains('modal-open')) return;

  event.preventDefault();
  formsCanvasDelete();
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
