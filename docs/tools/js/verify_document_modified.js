// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// +-----------------------------------------------------------------+
//  Path: /docs/tools/js/verify_document_modified.js
//
// Opening a document must not rewrite it (issue #64).
//
// Print, Save, and the buttons that carry you off a document all used to post
// the whole form, so looking at an invoice re-posted it: the journal rows were
// torn down and written again and the stock re-costed, for a document nobody
// had touched. What decides whether any of that happens now is fbDocModified()
// in includes/common.js, and this is what says it decides correctly.
//
// It is worth being plain about why this is a test and not a careful reading.
// The answer depends on three things at once - whether the record is stored,
// whether this person may save it, and whether a snapshot was taken before they
// touched anything - and the third is decided by two AJAX responses racing a
// person's hands. That is not a thing anyone reads correctly. The failed-post
// case below was found here, not by reading.
//
// The direction of every doubt is fixed: with no snapshot, fbDocModified()
// answers TRUE. A document wrongly called changed is only posted needlessly,
// which is what these pages always did. A document wrongly called unchanged
// loses the edit. So a check that fails should be read as a possible lost edit
// until shown otherwise.
//
//   node docs/tools/js/verify_document_modified.js
//
// Driven by docs/tools/verify_page_javascript.php, which runs it beside the
// syntax checks. Needs node; no database, no company, no browser.
'use strict';
const path = require('path');
const { El, Form, makeDocument, load, loadRendered, settle, check, report } = require('./fb_dom.js');

const RENDERED = process.argv[2]; // a work_orders js_include.php, already rendered

// ---------------------------------------------------------------------------
// The three document pages, cut down to the fields the tracking reasons about.
// Names are fictional; nothing here comes from a real installation.
// ---------------------------------------------------------------------------

function ordersScene(opts) {
  const form = new Form('orders', [
    { name: 'action',              type: 'hidden', value: '' },
    { name: 'doc_modified',        type: 'hidden', value: '1' },
    { name: 'id',                  type: 'hidden', value: opts.id },
    { name: 'purchase_invoice_id', value: 'INV-0001' },
    { name: 'bill_primary_name',   value: 'Example Customer Ltd' },
    { name: 'closed',              type: 'checkbox', checked: false },
    { name: 'qty_1',               value: '2' },
    { name: 'total',               value: '120.00' },
    { name: 'file_name',           type: 'file', value: '' },
  ]);
  return scene(form, opts, ['trunk/includes/common.js', 'trunk/modules/phreebooks/javascript/orders.js'], {
    printFormUrlBase: 'index.php?module=forms&page=print&gID=cust:inv&xmin=',
    nothing_to_print: 'nothing to print',
    securityLevel: 4, journalID: '12',
  });
}

function billsScene(opts) {
  const form = new Form('bills_form', [
    { name: 'action',       type: 'hidden', value: '' },
    { name: 'doc_modified', type: 'hidden', value: '1' },
    { name: 'id',           type: 'hidden', value: opts.id },
    { name: 'search',       value: 'Example Customer Ltd' },
    { name: 'total',        value: '99.00' },
    // Rendered with one option of value '0'; showNewPayment() replaces the list
    // and the value becomes ''. That is a change to the form that is nobody's
    // edit, and the reason fillBill() is not the last word on the snapshot.
    { name: 'payment_id',   type: 'select-one', value: '0' },
  ]);
  return scene(form, opts, ['trunk/includes/common.js', 'trunk/modules/phreebooks/javascript/banking.js'], {
    printFormUrlBase: 'index.php?module=forms&page=print&gID=bnk:rcpt&xmin=',
    nothing_to_print: 'nothing to print',
    securityLevel: 4, journalID: '18', account_type: 'c',
  });
}

function workOrderScene(opts) {
  const form = new Form('work_orders', [
    { name: 'action',       type: 'hidden', value: '' },
    { name: 'doc_modified', type: 'hidden', value: '1' },
    { name: 'id',           type: 'hidden', value: opts.id },
    { name: 'sku',          value: 'EXAMPLE-SKU' },
    { name: 'qty',          value: '10' },
    { name: 'notes',        value: '' },
  ]);
  const s = scene(form, opts, ['trunk/includes/common.js'], { nothing_to_print: 'nothing to print' });
  loadRendered(RENDERED, s.box);        // printDocument() lives in the js_include
  s.box.fetchXml = (url) => s.box.__opened.push(url);   // printWOrder()'s request
  return s;
}

/** opts.saveIcon === false means the page did not put tb_icon_save on it. */
function scene(form, opts, files, globals) {
  const loose = opts.saveIcon === false ? [] : [new El({ id: 'tb_icon_save' })];
  const doc = makeDocument([form], loose);
  const box = load(files, doc, globals);
  box.check_form     = () => true;
  box.form_submitted = false;
  box.submitToDo     = (action) => { box.fbDocPublish(); box.__submitted.push(action); };
  return { form, doc, box };
}

/** Open the document: start watching, then take the snapshot a fill would. */
async function opened(s, { snapshot = true } = {}) {
  s.box.fbDocInit(s.form.name);
  if (snapshot) s.box.fbDocBaselineSoon();
  await settle();
  return s;
}

// ---------------------------------------------------------------------------
(async function () {

console.log('printDocument() - which route each state takes');
for (const c of [
  { at: 'never saved, may save',           id: '',     saveIcon: true,  edit: false, want: { submitted: ['print'], opened: [] } },
  { at: 'stored, unchanged',               id: '1234', saveIcon: true,  edit: false, want: { submitted: [], opened: ['index.php?module=forms&page=print&gID=cust:inv&xmin=1234'] } },
  { at: 'stored, changed, may save',       id: '1234', saveIcon: true,  edit: true,  want: { submitted: ['print'], opened: [] } },
  { at: 'stored, changed, may not save',   id: '1234', saveIcon: false, edit: true,  want: { submitted: [], opened: ['index.php?module=forms&page=print&gID=cust:inv&xmin=1234'] } },
]) {
  const s = await opened(ordersScene(c));
  if (c.edit) s.form.elements['bill_primary_name'].value = 'Another Example Ltd';
  s.box.printDocument();
  check(`orders: ${c.at}`, { submitted: s.box.__submitted, opened: s.box.__opened }, c.want);
}
{
  // The one case with nothing to do: no record to print and no right to make one.
  const s = await opened(ordersScene({ id: '', saveIcon: false }));
  s.box.printDocument();
  check('orders: never saved, may not save -> says there is nothing to print',
        { submitted: s.box.__submitted, alerts: s.box.__alerts }, { submitted: [], alerts: ['nothing to print'] });
}

console.log('\nsaveDocument() - refuses only when there is nothing to save');
for (const c of [
  { at: 'never saved, untouched (nothing is stored yet)', id: '',     edit: false, want: ['save'] },
  { at: 'stored, unchanged',                              id: '1234', edit: false, want: [] },
  { at: 'stored, changed',                                id: '1234', edit: true,  want: ['save'] },
]) {
  const s = await opened(ordersScene(c));
  if (c.edit) s.form.elements['qty_1'].value = '3';
  s.box.saveDocument();
  check(`orders: ${c.at}`, s.box.__submitted, c.want);
}

console.log('\ndoc_modified - what the server is told, and so whether it re-posts');
for (const c of [
  { at: 'no snapshot was taken (falls back to posting)', snapshot: false, want: '1' },
  { at: 'snapshot, nothing changed',                     snapshot: true,  want: '0' },
  { at: 'snapshot, a field edited',   edit:   true, snapshot: true, want: '1' },
  { at: 'snapshot, a box ticked',     tick:   true, snapshot: true, want: '1' },
  { at: 'snapshot, a line added',     addRow: true, snapshot: true, want: '1' },
  { at: 'snapshot, a file attached',  file:   true, snapshot: true, want: '1' },
]) {
  const s = await opened(ordersScene({ id: '1234', saveIcon: true }), { snapshot: c.snapshot });
  if (c.edit)   s.form.elements['total'].value = '130.00';
  if (c.tick)   s.form.elements['closed'].checked = true;
  if (c.file)   s.form.elements['file_name'].value = 'C:\\fakepath\\attachment.pdf';
  if (c.addRow) s.form.addControl({ name: 'qty_2', value: '' });
  s.box.submitToDo('save');
  check(`orders: ${c.at}`, s.form.elements['doc_modified'].value, c.want);
}

console.log('\nthe Save icon follows the same answer');
{
  const s = await opened(ordersScene({ id: '1234', saveIcon: true }));
  const icon = s.doc.getElementById('tb_icon_save');
  check('greyed while the stored document is unchanged', icon.classList.contains('fb-icon-disabled'), true);
  s.form.elements['qty_1'].value = '4';
  s.form.fire('input');
  check('un-greyed the moment something is edited', icon.classList.contains('fb-icon-disabled'), false);

  const fresh = await opened(ordersScene({ id: '', saveIcon: true }));
  check('never greyed on a document that was never stored',
        fresh.doc.getElementById('tb_icon_save').classList.contains('fb-icon-disabled'), false);
}

console.log('\nthe snapshot must never be taken over somebody\'s work');
{
  // fillOrder() re-snapshots when its response lands. If the person got there
  // first, it must not: their edit would become the state it opened in.
  const s = await opened(ordersScene({ id: '1234', saveIcon: true }));
  s.form.elements['qty_1'].value = '9';
  s.form.fire('input');
  s.box.fbDocBaselineSoon();          // a late fill asks to re-snapshot
  await settle();
  check('an edit made before a late re-snapshot is still seen', s.box.fbDocModified(), true);
}
{
  // And the case this found. After a failed post the page redisplays unsaved
  // work and takes no snapshot. The person re-picks the contact from the popup
  // - which writes back with insertValue(), firing no event - and that runs
  // loadNewPayment() -> showNewPayment(), which asks for a snapshot. Taking one
  // there would grey out the Save that has to happen next. The click on the
  // search icon is what has to stop it, so the click has to count even though
  // no snapshot exists yet.
  const s = billsScene({ id: '', saveIcon: true });
  s.box.fbDocInit('bills_form');                 // no snapshot: the post failed
  s.form.fire('click');                          // the contact-search icon
  s.form.elements['payment_id'].value = '';      // the picker and showNewPayment write back
  s.box.fbDocBaselineSoon();                     // showNewPayment() asks
  await settle();
  check('after a failed post, unsaved work is still seen as changed', s.box.fbDocModified(), true);
  check('after a failed post, Save is not greyed',
        s.doc.getElementById('tb_icon_save').classList.contains('fb-icon-disabled'), false);
  s.box.saveDocument();
  check('after a failed post, Save still submits', s.box.__submitted, ['save']);
}

console.log('\nbills - the same rules, and the payment list that arrives late');
for (const c of [
  { at: 'stored, unchanged',                 id: '4242', saveIcon: true,  edit: false, want: { submitted: [], opened: ['index.php?module=forms&page=print&gID=bnk:rcpt&xmin=4242'] } },
  { at: 'stored, changed',                   id: '4242', saveIcon: true,  edit: true,  want: { submitted: ['print'], opened: [] } },
  { at: 'read-only user, changed on screen', id: '4242', saveIcon: false, edit: true,  want: { submitted: [], opened: ['index.php?module=forms&page=print&gID=bnk:rcpt&xmin=4242'] } },
  { at: 'new receipt raised from an invoice', id: '',    saveIcon: true,  edit: false, want: { submitted: ['print'], opened: [] } },
]) {
  const s = await opened(billsScene(c));
  if (c.edit) s.form.elements['total'].value = '105.00';
  s.box.printDocument();
  check(`bills: ${c.at}`, { submitted: s.box.__submitted, opened: s.box.__opened }, c.want);
}
{
  const s = await opened(billsScene({ id: '4242', saveIcon: true }));   // fillBill()
  s.form.elements['payment_id'].value = '';                             // showNewPayment()
  s.box.fbDocBaselineSoon();
  await settle();
  check('bills: the stored-payment list arriving is not mistaken for an edit', s.box.fbDocModified(), false);
  s.form.elements['total'].value = '105.00';
  check('bills: a real edit after it is still caught', s.box.fbDocModified(), true);
}

if (RENDERED) {
  console.log('\nwork orders - prints by asking for its detail, rather than by posting');
  for (const c of [
    { at: 'stored, unchanged',              id: '77', saveIcon: true, edit: false, want: { submitted: [], fetched: 1 } },
    { at: 'stored, changed',                id: '77', saveIcon: true, edit: true,  want: { submitted: ['print'], fetched: 0 } },
    { at: 'an id of 0 counts as not stored', id: '0', saveIcon: true, edit: false, want: { submitted: ['print'], fetched: 0 } },
  ]) {
    const s = await opened(workOrderScene(c));
    if (c.edit) s.form.elements['qty'].value = '12';
    s.box.printDocument();
    check(`work orders: ${c.at}`, { submitted: s.box.__submitted, fetched: s.box.__opened.length }, c.want);
  }
} else {
  console.log('\n  [--] work orders skipped: no rendered js_include.php was passed');
}

report();
})();
