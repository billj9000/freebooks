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

const boxes  = <?php echo json_encode($forms_canvas_boxes, JSON_UNESCAPED_SLASHES); ?>;
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

async function load() {
  try {
    const response = await fetch(source, { credentials: 'same-origin' });
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
    if (response.headers.get('X-Forms-Sampled') === '1') {
      document.getElementById('forms_canvas_sampled').classList.remove('d-none');
    }
    doc = await pdfjs.getDocument({ data: await response.arrayBuffer() }).promise;
    await draw();
  } catch (error) {
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
  document.getElementById('forms_canvas_pages').textContent = pageNumber + ' / ' + doc.numPages;
  document.getElementById('forms_canvas_zoom').textContent  = Math.round(scale * 100) + '%';
  say('');
}

/** Millimetres to CSS pixels at the current zoom. The whole of the alignment. */
function px(mm) { return mm * scale * PT_PER_MM; }

function place() {
  layer.textContent = '';
  boxes.forEach(function (box) {
    const div = document.createElement('div');
    const colour = COLOURS[box.kind] || COLOURS.unknown;
    div.className = 'fb-canvas-box';
    div.dataset.index = box.index;
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
      + sides + 'background:' + colour + '14;';
    div.title = box.label + '  (' + box.x + ', ' + box.y + ') '
              + box.width + ' × ' + box.height + ' mm'
              + (box.grows ? '\n' + <?php echo json_encode(FORMS_CANVAS_GROWS); ?> : '')
              + (box.unmeasured ? '\n' + <?php echo json_encode(FORMS_CANVAS_UNMEASURED); ?> : '');
    layer.appendChild(div);
  });
  highlight(null);
}

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

function fillList() {
  boxes.forEach(function (box) {
    const row = listing.insertRow();
    row.dataset.index = box.index;
    row.style.cursor = 'pointer';
    row.innerHTML =
        '<td style="width:8px;padding-right:0"><span style="display:inline-block;width:8px;height:8px;'
      + 'background:' + (COLOURS[box.kind] || COLOURS.unknown) + '"></span></td>'
      + '<td class="small">' + escapeHtml(box.label) + '</td>'
      + '<td class="small text-muted text-end text-nowrap">'
      + box.x + ', ' + box.y + '</td>';
    row.addEventListener('mouseenter', function () { highlight(box.index); });
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

fillList();
load();
</script>
