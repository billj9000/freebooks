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
//  Path: /docs/tools/js/fb_dom.js
//
// A browser small enough to run one of FreeBooks' scripts against, and no
// bigger. There is no jsdom here and no package manager to fetch one, so this
// is hand-built: a form, some controls, getElementById, addEventListener,
// classList, and the two window functions a page calls to print.
//
// It is deliberately not a DOM implementation. It is the part of one that the
// code under test touches, which is why it fits in a page. Anything that needs
// more than this needs a real browser, and the honest answer there is to click
// the page.
//
// The scripts are run through node's vm, unmodified, from their place in the
// repository - so what is exercised is the shipping file, not a copy of it.
'use strict';
const fs   = require('fs');
const vm   = require('vm');
const path = require('path');

const REPO = path.resolve(__dirname, '..', '..', '..');

/** One form control. `type` matters: the signature reads checkboxes by state. */
function El(spec) {
  this.name    = spec.name || '';
  this.id      = spec.id !== undefined ? spec.id : this.name;
  this.type    = spec.type || 'text';
  this.value   = spec.value !== undefined ? spec.value : '';
  this.checked = !!spec.checked;
  const classes = (spec.className || '').split(/\s+/).filter(Boolean);
  this.classList = {
    _c: classes,
    add(c)      { if (this._c.indexOf(c) < 0) this._c.push(c); },
    remove(c)   { const i = this._c.indexOf(c); if (i >= 0) this._c.splice(i, 1); },
    contains(c) { return this._c.indexOf(c) >= 0; },
  };
}

/**
 * A form whose `elements` is indexable both ways, as HTMLFormControlsCollection
 * is: by position for a walk, and by name for a lookup. It has no holes, which
 * is the one property of the real collection the signature relies on.
 */
function Form(name, specs) {
  this.name = name;
  this.id   = name;
  this.listeners = {};
  this.elements  = [];
  specs.forEach((s) => {
    const el = new El(s);
    el.form = this;
    this.elements.push(el);
    if (el.name && this.elements[el.name] === undefined) this.elements[el.name] = el;
  });
}
/** Add a control after construction - a line item row appearing, say. */
Form.prototype.addControl = function (spec) {
  const el = new El(spec);
  el.form = this;
  this.elements.push(el);
  if (el.name) this.elements[el.name] = el;
  return el;
};
Form.prototype.addEventListener = function (type, fn) {
  (this.listeners[type] = this.listeners[type] || []).push(fn);
};
/** What a real event would do: run the capture-phase listeners on the form. */
Form.prototype.fire = function (type) {
  (this.listeners[type] || []).forEach((fn) => fn({ type }));
};

/**
 * `loose` holds elements that are on the page but not in the form - the toolbar
 * icons. Whether tb_icon_save is among them is how the tests say "this person
 * may save this document", because that is exactly what the code asks.
 */
function makeDocument(forms, loose) {
  const byId = {}, formsMap = {};
  forms.forEach((f) => {
    byId[f.id] = f;
    formsMap[f.name] = f;
    f.elements.forEach((el) => { if (el.id) byId[el.id] = el; });
  });
  (loose || []).forEach((el) => { byId[el.id] = el; });
  return {
    forms: formsMap,
    images: null,
    getElementById: (id) => (byId[id] !== undefined ? byId[id] : null),
    getElementsByTagName: () => [],
    querySelector: () => null,
    addEventListener: () => {},
  };
}

/**
 * Load real script files into one context, in the order a browser would:
 * common.js (template_index.php puts it in the head) and then the page's own.
 * Returns the context, with three arrays recording what the page tried to do.
 */
function load(files, doc, extras) {
  const box = Object.assign({
    document: doc,
    console,
    setTimeout,
    Image: function () { return {}; },
    __alerts: [], __opened: [], __submitted: [],
  }, extras || {});
  box.alert  = (m) => box.__alerts.push(m);
  box.window = { setTimeout, open: (url) => { box.__opened.push(url); return { focus() {} }; } };
  box.globalThis = box;
  vm.createContext(box);
  for (const f of files) {
    vm.runInContext(fs.readFileSync(path.join(REPO, f), 'utf8'), box, { filename: f });
  }
  return box;
}

/** Run a file that was rendered out of a js_include.php, into an existing context. */
function loadRendered(file, box) {
  vm.runInContext(fs.readFileSync(file, 'utf8'), box, { filename: path.basename(file) });
}

// A deferred snapshot is scheduled with setTimeout(0); this lets it happen.
const settle = () => new Promise((r) => setTimeout(r, 5));

let passed = 0, failed = 0;
function check(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('  [PASS] ' + label); }
  else {
    failed++;
    console.log('  [FAIL] ' + label);
    console.log('         expected ' + e);
    console.log('         actual   ' + a);
  }
}
function report() {
  console.log('\n' + (failed === 0 ? `PASS - ${passed} checks passed`
                                   : `FAIL - ${failed} check(s) failed.`));
  process.exit(failed === 0 ? 0 : 1);
}

module.exports = { REPO, El, Form, makeDocument, load, loadRendered, settle, check, report };
