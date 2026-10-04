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
// Path: /modules/phreebooks/classes/purchase_export.php
//
// Builds an upload-ready file for a Purchase Order, in whatever line format
// the vendor's export profile (TABLE_PURCHASE_EXPORT_PROFILE) specifies. The
// vendor's own part number for each line is resolved live from
// inventory_purchase_details (see inv_vendor_sku() in
// inventory/functions/inventory.php) - never from what happens to be saved
// on the order - so the export always reflects the current item record.

class purchase_export {

	public $errors = array();

	// Resolves a saved Purchase Order into exportable lines. Returns
	// array('profile' => ..., 'order' => ..., 'vendor' => ..., 'lines' => ...)
	// on success, or array('error' => <message>) if nothing can be exported -
	// no profile assigned, or one or more lines have no vendor SKU.
	function resolve($order_id) {
		global $db;

		$order_id = (int)$order_id;
		$order = $db->Execute("select * from " . TABLE_JOURNAL_MAIN . " where id = '" . $order_id . "'");
		if ($order->EOF) return array('error' => PO_EXPORT_NO_ORDER);

		$vendor_id = (int)$order->fields['bill_acct_id'];
		$vendor = $db->Execute("select short_name, account_number, export_profile_id from " . TABLE_CONTACTS . " where id = '" . $vendor_id . "'");
		if ($vendor->EOF || !$vendor->fields['export_profile_id']) return array('error' => PO_EXPORT_NO_PROFILE);

		$profile = $db->Execute("select * from " . TABLE_PURCHASE_EXPORT_PROFILE . " where id = '" . (int)$vendor->fields['export_profile_id'] . "' and inactive = '0'");
		if ($profile->EOF) return array('error' => PO_EXPORT_NO_PROFILE);

		$items = $db->Execute("select * from " . TABLE_JOURNAL_ITEM . " where ref_id = '" . $order_id . "' and gl_type = 'poo' order by item_cnt, id");
		$lines   = array();
		$missing = array();
		$line_no = 0;
		while (!$items->EOF) {
			$line_no++;
			$vendor_sku = inv_vendor_sku($items->fields['sku'], $vendor_id);
			if ($vendor_sku === '') {
				$missing[] = $items->fields['sku'];
			} else {
				$qty = $items->fields['qty'] ?: 0;
				$amount = $items->fields['debit_amount'] + $items->fields['credit_amount'];
				$lines[] = array(
					'vendor_sku'  => $vendor_sku,
					'sku'         => $items->fields['sku'],
					'qty'         => $qty,
					'description' => $items->fields['description'],
					'cost'        => $qty ? round($amount / $qty, 4) : $amount,
					'line_no'     => $line_no,
					'package_qty' => $items->fields['purch_package_quantity'] ?: 1,
				);
			}
			$items->MoveNext();
		}
		if (empty($lines) && empty($missing)) return array('error' => PO_EXPORT_NO_LINES);
		if (!empty($missing)) return array('error' => sprintf(PO_EXPORT_MISSING_SKUS, implode(', ', array_unique($missing))));

		return array(
			'profile' => $profile->fields,
			'order'   => $order->fields,
			'vendor'  => $vendor->fields,
			'lines'   => $lines,
		);
	}

	// Renders the resolved data through the profile's header/line/footer
	// templates and returns the file body as a string.
	function render($profile, $order, $vendor, $lines) {
		$eol = ($profile['line_ending'] == 'lf') ? "\n" : "\r\n";
		$total_qty = 0;
		foreach ($lines as $line) $total_qty += $line['qty'];

		$common = array(
			'po_number'      => $order['purch_order_id'] ?: $order['id'],
			'po_date'        => $order['post_date'],
			'vendor_name'    => $vendor['short_name'],
			'account_number' => $vendor['account_number'],
			'line_count'     => count($lines),
			'total'          => $total_qty,
		);

		$body = '';
		if (trim((string)$profile['header_template']) !== '') {
			$body .= $this->substitute($profile['header_template'], $common) . $eol;
		}
		foreach ($lines as $line) {
			$body .= $this->substitute($profile['line_template'], array_merge($common, $line)) . $eol;
		}
		if (trim((string)$profile['footer_template']) !== '') {
			$body .= $this->substitute($profile['footer_template'], $common) . $eol;
		}
		return $body;
	}

	// Replaces {placeholder} tokens; anything not in $values renders empty
	// rather than throwing, so an unknown placeholder in a profile doesn't
	// break the export.
	function substitute($template, $values) {
		return preg_replace_callback('/\{(\w+)\}/', function ($match) use ($values) {
			return isset($values[$match[1]]) ? (string)$values[$match[1]] : '';
		}, (string)$template);
	}

	// A safe, portable filename for the Content-Disposition header.
	function safe_filename($name, $extension) {
		$name = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$name);
		$name = trim($name, '_');
		if ($name === '') $name = 'export';
		return $name . '.' . preg_replace('/[^A-Za-z0-9]+/', '', (string)$extension ?: 'txt');
	}
}
?>
