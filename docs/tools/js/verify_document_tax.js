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
//  Path: /docs/tools/js/verify_document_tax.js
//
// The tax and total a document screen shows must be the ones the ledger posts
// (issue #118). The ledger works tax out per authority, summed over the
// document and rounded once per authority (orders::add_tax_journal_rows); the
// screens total it in the browser, in updateTotalPrices(), and have to arrive
// at the same figure.
//
//   node docs/tools/js/verify_document_tax.js <documents.json>
//
// The documents come from acceptance scenario A6, which posts them through the
// real ledger and asserts what each tax account and the customer or vendor
// account holds afterwards; docs/tools/verify_page_javascript.php reads A6 and
// writes them out. So the expected figures here are the ledger's, not a second
// calculation of them: if A6 changes, this follows it.
//
// The order screen is run from orders.js as shipped. The point of sale keeps
// its total in its js_include.php, so its two functions are lifted out of that
// file's source; neither holds any PHP.
'use strict';
const fs   = require('fs');
const path = require('path');
const vm   = require('vm');
const { REPO, check, report } = require('./fb_dom.js');

const DOCS = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));

// The example in issue #118: three authorities, 2.5%, 5.1% and 1.1%, on 386.75.
// Rounded once on the combined 8.7% the screen showed 33.65; the ledger posts
// 9.67 + 19.72 + 4.25 = 33.64.
DOCS.rates['LOCAL'] = { rate: 8.7, auths: [{ id: 'L1', rate: 2.5 }, { id: 'L2', rate: 5.1 }, { id: 'L3', rate: 1.1 }] };
DOCS.documents.push({ label: 'issue #118 example', journal: '12', account: 'c',
  lines: [{ amount: 386.75, rate: 'LOCAL' }], tax: 33.64, total: 420.39 });

const money = (n) => (Math.round(n * 100) / 100).toFixed(2);

/** A control the totals read or write. A select carries its selectedIndex as well. */
function field(value, extra) { return Object.assign({ value: value === undefined ? '' : String(value) }, extra || {}); }

/** The tax_rates array the page is given, built as ord_js_tax_rates() builds it: a "none" entry first. */
function rateTable(names) {
  const table = [{ id: '0', text: 'None', rate: '0', auths: [] }];
  names.forEach((n, i) => table.push({ id: String(i + 1), text: n, rate: String(DOCS.rates[n].rate), auths: DOCS.rates[n].auths }));
  return table;
}

function context(byId, globals) {
  const box = Object.assign({
    console,
    document: {
      getElementById: (id) => (byId[id] !== undefined ? byId[id] : null),
      getElementsByTagName: () => [], querySelector: () => null, addEventListener: () => {},
    },
    addEventListener: () => {}, setTimeout,
    decimal_places: 2, decimal_precise: 4, decimal_point: '.', thousands_point: ',',
  }, globals);
  box.window = box;
  vm.createContext(box);
  vm.runInContext(fs.readFileSync(path.join(REPO, 'trunk/includes/common.js'), 'utf8'), box, { filename: 'common.js' });
  return box;
}

/** The order, invoice and purchase screens: orders.js updateTotalPrices(). */
function orderScreen(doc) {
  const names = Object.keys(DOCS.rates);
  const rates = rateTable(names);
  const index = (name) => names.indexOf(name) + 1;
  const subtotal = doc.lines.reduce((s, l) => s + l.amount, 0);
  const byId = {
    item_table:     { rows: { length: doc.lines.length } },
    discount:       field(''),
    disc_percent:   field(doc.discount ? (doc.discount / subtotal) * 100 : 0),
    freight:        field(doc.freight || 0),
    freight_tax_id: field(doc.freight_rate ? rates[index(doc.freight_rate)].id : '0'),
    sales_tax: field(), item_count: field(), weight: field(), subtotal: field(), total: field(),
  };
  doc.lines.forEach((l, i) => {
    const n = i + 1;
    byId['qty_' + n] = field(1); byId['pstd_' + n] = field(1); byId['weight_' + n] = field(0);
    byId['total_' + n] = field(money(l.amount));
    byId['tax_' + n] = field(l.rate ? index(l.rate) : '0', { selectedIndex: l.rate ? index(l.rate) : 0 });
  });
  const box = context(byId, {
    journalID: doc.journal, single_line_list: '1', tax_rates: rates,
    tax_before_discount: '0', default_sales_tax: '0', tax_freight: '0', applyCreditLimit: '0',
  });
  vm.runInContext(fs.readFileSync(path.join(REPO, 'trunk/modules/phreebooks/javascript/orders.js'), 'utf8'), box, { filename: 'orders.js' });
  box.updateTotalPrices();
  return { tax: byId.sales_tax.value.replace(/,/g, ''), total: byId.total.value.replace(/,/g, '') };
}

/** Lift one top-level function out of a page's script by matching its braces. */
function liftFunction(source, name) {
  const start = source.indexOf('function ' + name + '(');
  if (start < 0) throw new Error(name + ' not found');
  let depth = 0;
  for (let i = source.indexOf('{', start); i < source.length; i++) {
    if (source[i] === '{') depth++;
    else if (source[i] === '}' && --depth === 0) return source.substring(start, i + 1);
  }
  throw new Error(name + ' is not closed');
}

/**
 * The point of sale: its own updateTotalPrices() in phreepos/pages/main/js_include.php.
 * fromTotal is the till's PHREEPOS_DISCOUNT_OF: with tax after the discount, a
 * percentage off the total must come to the same sale as one off the subtotal.
 */
function posScreen(doc, fromTotal) {
  const names = Object.keys(DOCS.rates);
  const rates = rateTable(names);
  const byId = {
    item_table_body: { rows: { length: doc.lines.length } }, payment_table_body: { rows: { length: 0 } },
    discount: field('0.00'),
    disc_percent: field(doc.discount ? (doc.discount / doc.lines.reduce((s, l) => s + l.amount, 0)) * 100 : 0),
    sales_tax: field(), subtotal: field(), total: field(), pmt_recvd: field(), bal_due: field(), rounded_of: field(),
  };
  doc.lines.forEach((l, i) => {
    byId['total_' + (i + 1)] = field(money(l.amount));
    byId['tax_' + (i + 1)]   = field(l.rate ? names.indexOf(l.rate) + 1 : 0);
  });
  const box = context(byId, { tax_rates: rates, tax_before_discount: '0', discount_from_total: fromTotal ? 1 : 0, rounding_of: 0, popupStatus: 0 });
  const source = fs.readFileSync(path.join(REPO, 'trunk/modules/phreepos/pages/main/js_include.php'), 'utf8');
  vm.runInContext(liftFunction(source, 'calculateRoundingOf') + '\n' + liftFunction(source, 'updateTotalPrices'), box, { filename: 'phreepos js_include.php' });
  box.updateTotalPrices();
  return { tax: byId.sales_tax.value.replace(/,/g, ''), total: byId.total.value.replace(/,/g, '') };
}

console.log('Order screens (orders.js)');
DOCS.documents.filter((d) => d.screen !== 'pos').forEach((doc) => {
  const shown = orderScreen(doc);
  check(`${doc.label}: tax shown ${money(doc.tax)} and total ${money(doc.total)}`, shown, { tax: money(doc.tax), total: money(doc.total) });
});

// The point of sale: A9's sales, which it posts, and A6's invoices that a till
// could ring up - no freight, no order discount. A discounted sale is run with
// the discount taken from the subtotal and from the total (issue #121).
console.log('\nPoint of sale (phreepos js_include.php)');
DOCS.documents.filter((d) => d.screen === 'pos' || (d.account === 'c' && d.journal === '12' && !d.freight && !d.discount)).forEach((doc) => {
  (doc.discount ? [false, true] : [false]).forEach((fromTotal) => {
    const shown = posScreen(doc, fromTotal);
    const mode  = doc.discount ? (fromTotal ? ', discount off the total' : ', discount off the subtotal') : '';
    check(`${doc.label}${mode}: tax shown ${money(doc.tax)} and total ${money(doc.total)}`, shown, { tax: money(doc.tax), total: money(doc.total) });
  });
});

report();
