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
//  Path: /modules/shipping/functions/address_tools.php
//
// Helpers for a label form's sender (sender_*) and recipient (ship_*) addresses: refill the
// sender with the company, fill the recipient from a customer or vendor, and find an address by
// postcode through the contacts module's lookup provider. Every field stays editable after a fill.
// A form prints shipping_address_tools() once, and the buttons where it wants them.

// Whether a postcode lookup provider is set up, the test the order form uses.
function shipping_postcode_lookup_enabled() {
  return defined('POSTCODE_LOOKUP_STATUS') && POSTCODE_LOOKUP_STATUS
      && defined('POSTCODE_LOOKUP_API_KEY') && POSTCODE_LOOKUP_API_KEY;
}

function shipping_company_address_button() {
  return '<button type="button" class="btn btn-primary btn-sm py-0" onclick="shipFillCompany()">'
       . SHIPPING_USE_COMPANY_ADDRESS . '</button>';
}

function shipping_find_contact_button() {
  return '<button type="button" class="btn btn-primary btn-sm py-0" data-bs-toggle="modal" data-bs-target="#shipContactModal">'
       . SHIPPING_FIND_CONTACT . '</button>';
}

// A Find Address button and its results for one address ('sender' or 'ship'), or nothing when no
// lookup provider is set up. The order form's endpoint reads <prefix>_postal_code and
// <prefix>_country_code, which are this form's names too.
function shipping_postcode_lookup($prefix) {
  if (!shipping_postcode_lookup_enabled()) return '';
  $hx = ' hx-get="index.php?module=phreebooks&amp;page=ajax&amp;op=postcode_lookup"'
      . ' hx-include="[name=\'' . $prefix . '_postal_code\'],[name=\'' . $prefix . '_country_code\']"'
      . ' hx-vals=\'{"prefix":"' . $prefix . '"}\' hx-target="#postcode_results_' . $prefix . '" hx-swap="innerHTML"';
  return '<div class="col-12"><button type="button" class="btn btn-primary btn-sm py-0"' . $hx . '>'
       . SHIPPING_FIND_ADDRESS . '</button><div id="postcode_results_' . $prefix . '"></div></div>';
}

// The customer search dialog and the scripts behind every button above.
function shipping_address_tools() {
  $company = json_encode(shipping_company_sender(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
  ob_start(); ?>
<div class="modal fade" id="shipContactModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title"><?php echo SHIPPING_FIND_CONTACT; ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="search" class="form-control form-control-sm mb-2" name="q" autocomplete="off"
               placeholder="<?php echo htmlspecialchars(SHIPPING_FIND_CONTACT_PROMPT, ENT_QUOTES); ?>"
               onkeydown="if (event.key === 'Enter') event.preventDefault();"
               hx-get="index.php?module=shipping&amp;page=ajax&amp;op=address_search"
               hx-trigger="input changed delay:300ms, search" hx-target="#shipContactResults" hx-swap="innerHTML">
        <div id="shipContactResults"></div>
      </div>
    </div>
  </div>
</div>
<script>
var shipCompanySender = <?php echo $company; ?>;
// Sets a field and fires its change event, so whatever watches it (the customs section) follows.
function shipSetField(name, value) {
  var el = document.querySelector('[name="' + name + '"]');
  if (!el) return;
  el.value = value;
  el.dispatchEvent(new Event('change', { bubbles: true }));
}
function shipFillCompany() {
  for (var name in shipCompanySender) shipSetField(name, shipCompanySender[name]);
}
function shipFillFromContact(btn) {
  ['primary_name', 'contact', 'address1', 'address2', 'city_town', 'state_province', 'postal_code',
   'country_code', 'telephone1', 'email', 'vat_number', 'eori_number'].forEach(function (f) {
    var key = f.replace(/_([a-z0-9])/g, function (m, c) { return c.toUpperCase(); });
    shipSetField('ship_' + f, btn.dataset[key] || '');
  });
  bootstrap.Modal.getInstance(document.getElementById('shipContactModal')).hide();
}
// Called by the postcode lookup's results. It fills the address; the name, phone and email stay,
// and the organisation's name only replaces the name when the address has one.
function fillOrderAddressFromLookup(el, prefix) {
  ['address1', 'address2', 'city_town', 'state_province', 'postal_code'].forEach(function (f) {
    shipSetField(prefix + '_' + f, el.dataset[f] || '');
  });
  if (el.dataset.primary_name) shipSetField(prefix + '_primary_name', el.dataset.primary_name);
  document.getElementById('postcode_results_' + prefix).innerHTML = '';
}
</script>
<?php
  return ob_get_clean();
}
