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
//  Path: /modules/shipping/pages/packages/js_include.php

$vol_divisor = defined('SHIPPING_VOL_WEIGHT_DIVISOR') ? (float)SHIPPING_VOL_WEIGHT_DIVISOR : 5000;
?>
<script>
var volDivisor = <?php echo $vol_divisor; ?>;

function calcVolWeight() {
  var l = parseFloat(document.getElementById('int_length')  ? document.getElementById('int_length').value  : 0) || 0;
  var w = parseFloat(document.getElementById('int_width')   ? document.getElementById('int_width').value   : 0) || 0;
  var h = parseFloat(document.getElementById('int_height')  ? document.getElementById('int_height').value  : 0) || 0;
  var el = document.getElementById('vol_weight');
  if (!el) return;
  el.textContent = (l > 0 && w > 0 && h > 0 && volDivisor > 0)
    ? (l * w * h / volDivisor).toFixed(3) : '—';
}

function openPkgDialog(pkgID) {
  var row = pkgID ? document.querySelector('tr[data-pkg-id="' + pkgID + '"]') : null;
  var d   = row ? row.dataset : {};

  // Map: element id → dataset key (camelCase) → default
  var map = [
    ['fld_packageCode', 'packageCode', ''],
    ['fld_name',        'name',        ''],
    ['fld_description', 'description', ''],
    ['fld_carrier',     'carrier',     ''],
    ['fld_weight',      'weight',      '0'],
    ['fld_maxWeight',   'maxWeight',   '0'],
    ['fld_extLength',   'extLength',   '0'],
    ['fld_extWidth',    'extWidth',    '0'],
    ['fld_extHeight',   'extHeight',   '0'],
    ['int_length',      'intLength',   '0'],
    ['int_width',       'intWidth',    '0'],
    ['int_height',      'intHeight',   '0'],
    ['fld_cost',        'cost',        '0'],
    ['fld_sortOrder',   'sortOrder',   '0'],
  ];

  map.forEach(function(m) {
    var el = document.getElementById(m[0]);
    if (!el) return;
    el.value = row && d[m[1]] !== undefined ? d[m[1]] : m[2];
  });

  var inactiveEl = document.getElementById('fld_inactive');
  if (inactiveEl) inactiveEl.checked = row ? (d.inactive === '1') : false;

  document.getElementById('fld_action').value = pkgID ? 'update' : 'save';
  document.getElementById('fld_pkgID').value  = pkgID || '';

  var label = document.getElementById('pkgModalLabel');
  if (label) label.textContent = pkgID ? (d.name || '') : '<?php echo addslashes(TEXT_NEW); ?>';

  calcVolWeight();

  var modalEl = document.getElementById('pkgModal');
  var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
  modal.show();

  modalEl.addEventListener('shown.bs.modal', function focus() {
    var el = document.getElementById('fld_packageCode');
    if (el) el.focus();
    modalEl.removeEventListener('shown.bs.modal', focus);
  });
}

function init() {
  // htmx: close modal on successful save/update/delete
  document.body.addEventListener('closeModal', function() {
    var m = bootstrap.Modal.getInstance(document.getElementById('pkgModal'));
    if (m) m.hide();
  });

  // vol weight on every open / re-render
  function bindVolCalc() {
    ['int_length','int_width','int_height'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) { el.addEventListener('input', calcVolWeight); el.addEventListener('change', calcVolWeight); }
    });
  }
  bindVolCalc();

  // Enter key in modal → submit
  document.getElementById('pkgModal').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      var active = document.activeElement;
      if (active && (active.tagName === 'TEXTAREA')) return;
      e.preventDefault();
      document.getElementById('pkgSaveBtn').click();
    }
  });
}
</script>
