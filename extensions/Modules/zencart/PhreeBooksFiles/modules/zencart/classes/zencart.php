<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2014 PhreeSoft                       |
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
// Path: /modules/zencart/classes/zencart.php

class zencart {
    public $sku = '';

    // Upload a single inventory product to ZenCart via the REST API.
    public function uploadProduct(int $id, bool $inc_image = true): bool
    {
        global $db, $currencies, $messageStack;

        $result = $db->Execute("SELECT * FROM " . TABLE_INVENTORY . " WHERE id = " . $id);
        if ($result->RecordCount() <> 1) {
            $messageStack->add(ZENCART_INVALID_SKU, 'error');
            return false;
        }
        $inv = $result->fields;
        $this->sku = $inv['sku'];

        $product = [
            'products_model'      => !empty($inv['ProductModel']) ? $inv['ProductModel'] : $inv['sku'],
            'languageCode'        => 'en',
            'productType'         => 'Product - General',
            'manufacturer'        => $inv['manufacturer'] ?? '',
            'productCategory'     => $inv['category_id'] ?? '',
            'productName'         => $inv['description_short'] ?? '',
            'productDescription'  => $inv['description_sales'] ?? '',
            'productTaxable'      => !empty($inv['item_taxable']),
            'productStatus'       => empty($inv['inactive']) ? 1 : 0,
            'product_virtual'     => 0,
            'productFreeShipping' => 0,
            'productHidePrice'    => 0,
            'productSortOrder'    => (int)$inv['id'],
            'productWeight'           => (float)($inv['item_weight'] ?? 0),
            'productZencartLength'    => (float)($inv['item_length'] ?? 0),
            'productZencartWidth'     => (float)($inv['item_width'] ?? 0),
            'productZencartHeight'    => (float)($inv['item_height'] ?? 0),
            'productZencartReadyToShip' => !empty($inv['ships_in_own_box']) ? 1 : 0,
            'quantity'            => (float)($inv['quantity_on_hand'] ?? 0),
            'dateAvailable'       => $inv['creation_date'] ?? date('Y-m-d'),
            'dateUpdated'         => (($inv['last_update'] ?? '') >= ($inv['last_journal_date'] ?? ''))
                                      ? $inv['last_update'] : $inv['last_journal_date'],
        ];

        if (!empty($inv['ProductURL'])) {
            $product['productURL'] = $inv['ProductURL'];
        }

        // Price — global ZENCART_PRICE_SHEET takes priority; item-level inventory.price_sheet
        // is the fallback. Either way, tiers are sent as a sequential JSON array.
        $sheet_name = '';
        if (defined('ZENCART_USE_PRICE_SHEETS') && ZENCART_USE_PRICE_SHEETS == '1'
            && defined('ZENCART_PRICE_SHEET') && ZENCART_PRICE_SHEET != '' && ZENCART_PRICE_SHEET != '0') {
            $sheet_name = ZENCART_PRICE_SHEET;
        } elseif (!empty($inv['price_sheet'])) {
            $sheet_name = $inv['price_sheet'];
        }

        if ($sheet_name !== '') {
            $sheet = $db->Execute(
                "SELECT id, default_levels FROM " . TABLE_PRICE_SHEETS
                . " WHERE '" . date('Y-m-d') . "' >= effective_date"
                . " AND sheet_name = '" . db_input($sheet_name) . "' AND inactive = '0'"
            );
            if ($sheet->RecordCount() == 0) {
                $messageStack->add(ZENCART_ERROR_NO_PRICE_SHEET . $sheet_name, 'error');
                return false;
            }
            $special = $db->Execute(
                "SELECT price_levels FROM " . TABLE_INVENTORY_SPECIAL_PRICES
                . " WHERE inventory_id = " . $id . " AND price_sheet_id = " . (int)$sheet->fields['id']
            );
            $levels_str = $special->RecordCount() > 0
                ? $special->fields['price_levels']
                : $sheet->fields['default_levels'];
            $prices = inv_calculate_prices($inv['item_cost'], $inv['full_price'], $levels_str);
            // array_values() re-indexes so json_encode produces a JSON array, not an object.
            // Sparse keys arise when "Not Used" levels are skipped by inv_calculate_prices().
            $price_levels = array_values(array_map(fn($a) => [
                'qty'    => (int)$a['qty'],
                'amount' => $currencies->clean_value($a['price']),
            ], $prices ?: []));
            $product['priceDiscountType'] = 2;
            $product['price_levels']      = $price_levels;
        } else {
            $product['priceDiscountType'] = 0;
            $product['retailPrice']       = (float)($inv['full_price'] ?? 0);
        }

        // Image — encode with URL-safe base64 (RFC 4648) as expected by the API
        if ($inc_image && !empty($inv['image_with_path'])) {
            $parts = explode('/', $inv['image_with_path']);
            if (count($parts) > 1) {
                $img_dir  = $parts[0];
                $img_file = array_pop($parts);
            } else {
                $img_dir  = '';
                $img_file = $parts[0];
            }
            $full_path = DIR_FS_MY_FILES . $_SESSION['company'] . '/inventory/images/' . $inv['image_with_path'];
            if (file_exists($full_path)) {
                $contents = file_get_contents($full_path);
                if ($contents !== false) {
                    $product['productImageDirectory'] = $img_dir;
                    $product['productImageFileName']  = $img_file;
                    $product['productImageData']      = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($contents));
                }
            }
        }

        // Extension hook for site-specific product attributes
        if (file_exists(DIR_FS_MODULES . 'zencart/custom/extra_product_attrs.php')) {
            include(DIR_FS_MODULES . 'zencart/custom/extra_product_attrs.php');
        }

        return zencart_api_call('updateProducts', ['products' => [$product]]) !== false;
    }

    // Push current quantity_on_hand and retail price for all catalog=1 products.
    // Items with quantity-tiered price sheets are uploaded individually via uploadProduct()
    // so ZenCart receives the full tier structure. All other items go through the bulk
    // updateProductsQty endpoint.
    public function syncQuantities(): bool
    {
        global $db, $messageStack;

        $use_sheet  = defined('ZENCART_USE_PRICE_SHEETS') && ZENCART_USE_PRICE_SHEETS == '1'
                   && defined('ZENCART_PRICE_SHEET') && ZENCART_PRICE_SHEET != '' && ZENCART_PRICE_SHEET != '0';
        $sheet_id     = 0;
        $sheet_levels = '';
        if ($use_sheet) {
            $sheet = $db->Execute(
                "SELECT id, default_levels FROM " . TABLE_PRICE_SHEETS
                . " WHERE '" . date('Y-m-d') . "' >= effective_date"
                . " AND sheet_name = '" . db_input(ZENCART_PRICE_SHEET) . "' AND inactive = '0'"
            );
            if ($sheet->RecordCount() == 0) {
                $messageStack->add(ZENCART_ERROR_NO_PRICE_SHEET . ZENCART_PRICE_SHEET, 'error');
                return false;
            }
            $sheet_id     = (int)$sheet->fields['id'];
            $sheet_levels = $sheet->fields['default_levels'];
        }

        $result = $db->Execute(
            "SELECT id, sku, ProductModel, quantity_on_hand, item_cost, full_price"
            . " FROM " . TABLE_INVENTORY . " WHERE catalog = '1'"
        );
        if ($result->RecordCount() == 0) {
            $messageStack->add(ZENCART_ERROR_NO_ITEMS, 'error');
            return false;
        }

        $bulk_products = [];
        $n_tiered_ok   = 0;
        $n_tiered_err  = 0;

        while (!$result->EOF) {
            $inv_id = (int)$result->fields['id'];
            $model  = !empty($result->fields['ProductModel'])
                ? $result->fields['ProductModel']
                : $result->fields['sku'];

            if ($use_sheet) {
                $special = $db->Execute(
                    "SELECT price_levels FROM " . TABLE_INVENTORY_SPECIAL_PRICES
                    . " WHERE inventory_id = " . $inv_id
                    . " AND price_sheet_id = " . $sheet_id
                );
                $levels = $special->RecordCount() > 0
                    ? $special->fields['price_levels']
                    : $sheet_levels;
                $tiers = inv_calculate_prices($result->fields['item_cost'], $result->fields['full_price'], $levels);

                if (is_array($tiers) && count($tiers) > 1) {
                    // Multiple quantity tiers — upload full product so ZenCart gets qty discounts
                    if ($this->uploadProduct($inv_id, false)) {
                        $n_tiered_ok++;
                    } else {
                        $n_tiered_err++;
                    }
                    $result->MoveNext();
                    continue;
                }

                $price = is_array($tiers) && !empty($tiers)
                    ? (float)$tiers[0]['price']
                    : (float)$result->fields['full_price'];
            } else {
                $price = (float)$result->fields['full_price'];
            }

            $bulk_products[] = [
                'products_model' => $model,
                'quantity'       => (float)$result->fields['quantity_on_hand'],
                'price'          => $price,
            ];
            $result->MoveNext();
        }

        // Bulk update for non-tiered items
        $n_bulk_ok  = 0;
        $n_bulk_err = 0;
        if (!empty($bulk_products)) {
            $response = zencart_api_call('updateProductsQty', ['products' => $bulk_products]);
            if ($response === false) return false;
            $n_bulk_ok  = count($response['updated']    ?? []);
            $n_bulk_err = count($response['notUpdated'] ?? []);
            if ($n_bulk_err > 0) {
                $messageStack->add(
                    sprintf(ZENCART_SYNC_QTY_PARTIAL, $n_bulk_ok, $n_bulk_err)
                    . ' ' . implode(', ', $response['notUpdated']),
                    'caution'
                );
            }
        }

        $n_ok  = $n_bulk_ok  + $n_tiered_ok;
        $n_err = $n_bulk_err + $n_tiered_err;
        if ($n_err == 0) {
            $messageStack->add(sprintf(ZENCART_SYNC_QTY_SUCCESS, $n_ok), 'success');
        }
        return true;
    }

    // Confirm shipments in ZenCart for all orders shipped on $post_date.
    public function confirmShipments(string $post_date): bool
    {
        global $db, $messageStack;

        $shipments = $db->Execute(
            "SELECT ref_id, carrier, method, tracking_id FROM " . TABLE_SHIPPING_LOG
            . " WHERE ship_date LIKE '" . db_prepare_input($post_date) . " %'"
        );
        if ($shipments->RecordCount() == 0) {
            $messageStack->add(ZENCART_ERROR_CONFRIM_NO_DATA, 'caution');
            return false;
        }

        $methods = $this->loadShippingMethods();
        $sent    = 0;
        while (!$shipments->EOF) {
            $ref_id     = $shipments->fields['ref_id'];
            $invoice_id = (strpos($ref_id, '-') !== false)
                ? substr($ref_id, 0, strpos($ref_id, '-'))
                : $ref_id;

            $main = $db->Execute(
                "SELECT so_po_ref_id FROM " . TABLE_JOURNAL_MAIN
                . " WHERE journal_id = 12 AND purchase_invoice_id = '" . db_prepare_input($invoice_id) . "'"
                . " ORDER BY id DESC LIMIT 1"
            );
            if (!$main->EOF && !empty($main->fields['so_po_ref_id'])) {
                $so = $db->Execute(
                    "SELECT closed, purchase_invoice_id FROM " . TABLE_JOURNAL_MAIN
                    . " WHERE id = " . (int)$main->fields['so_po_ref_id']
                );
                if (!$so->EOF) {
                    $carrier       = $shipments->fields['carrier'];
                    $method        = $shipments->fields['method'];
                    $carrier_title = $methods[$carrier]['title'] ?? $carrier;
                    $method_title  = $methods[$carrier][$method] ?? $method;
                    $message       = sprintf(
                        ZENCART_CONFIRM_MESSAGE,
                        $post_date,
                        $carrier_title,
                        $method_title,
                        $shipments->fields['tracking_id']
                    );
                    $status = $so->fields['closed']
                        ? (int)ZENCART_STATUS_CONFIRM_ID
                        : (int)ZENCART_STATUS_PARTIAL_ID;
                    zencart_api_call('updateOrderComments', [
                        'orderId' => (int)$so->fields['purchase_invoice_id'],
                        'comment' => $message,
                        'status'  => $status,
                    ]);
                    $sent++;
                }
            }
            $shipments->MoveNext();
        }

        if ($sent == 0) {
            $messageStack->add(ZENCART_ERROR_CONFRIM_NO_DATA, 'caution');
            return false;
        }
        return true;
    }

    private function loadShippingMethods(): array
    {
        global $shipping_defaults;
        $method_array = [];
        foreach (scandir(DIR_FS_MODULES . 'shipping/methods/') as $m) {
            if ($m !== '.' && $m !== '..' && defined('MODULE_SHIPPING_' . strtoupper($m) . '_STATUS')) {
                $method_array[] = $m;
            }
        }
        $output  = [];
        $choices = array_keys($shipping_defaults['service_levels']);
        foreach ($method_array as $m) {
            load_method_language(DIR_FS_MODULES . 'shipping/methods/', $m);
            $output[$m]['title'] = constant('MODULE_SHIPPING_' . strtoupper($m) . '_TEXT_TITLE');
            foreach ($choices as $svc) {
                $output[$m][$svc] = defined($m . '_' . $svc) ? constant($m . '_' . $svc) : '';
            }
        }
        return $output;
    }
}
