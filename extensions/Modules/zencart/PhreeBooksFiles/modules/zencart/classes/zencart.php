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
        $product = $this->buildProductPayload($id, $inc_image);
        if ($product === null) return false;
        return zencart_api_call('updateProducts', ['products' => [$product]]) !== false;
    }

    // Builds the ZenCart 'products' payload entry for one inventory item, without sending it —
    // lets callers (e.g. bulk_upload.php) batch several products into one updateProducts call.
    // Returns null (with $messageStack populated) if the item can't be built.
    public function buildProductPayload(int $id, bool $inc_image = true): ?array
    {
        global $db, $currencies, $messageStack;

        $result = $db->Execute("SELECT * FROM " . TABLE_INVENTORY . " WHERE id = " . $id);
        if ($result->RecordCount() <> 1) {
            $messageStack->add(ZENCART_INVALID_SKU, 'error');
            return null;
        }
        $inv = $result->fields;
        $this->sku = $inv['sku'];

        $product = [
            'products_model'      => !empty($inv['ProductModel']) ? $inv['ProductModel'] : $inv['sku'],
            'languageCode'        => 'en',
            'productType'         => 'Product - General',
            // ZenCart's manufacturers_name column is varchar(32); FreeBooks' is varchar(64)
            'manufacturer'        => mb_substr($inv['manufacturer'] ?? '', 0, 32),
            'productCategory'     => $inv['category_id'] ?? '',
            'productName'         => $inv['description_short'] ?? '',
            'productDescription'  => $inv['description_sales'] ?? '',
            // Long HTML body shown on the ZenCart product page (products_description);
            // distinct from productDescription above, which the API maps to products_name.
            'productZencartDescription' => $inv['zencart_description'] ?? '',
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
            'quantity'            => max(0.0, (float)($inv['quantity_on_hand'] ?? 0) - (float)($inv['quantity_on_sales_order'] ?? 0)), // overridden below for masters
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
                return null;
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

        // Master Stock items: attach attribute/variant payload and use aggregate child stock.
        // mi (child) items are never pushed directly — they are represented under their master.
        if (in_array($inv['inventory_type'] ?? '', ['ms', 'mb'], true)) {
            $attrs = zencart_build_attributes($id);
            if ($attrs !== null) {
                $product['attributes'] = $attrs;
                // Replace product-level quantity with sum of all child available (on-hand less reserved) quantities
                $sum_row = $db->Execute(
                    "SELECT SUM(GREATEST(quantity_on_hand - quantity_on_sales_order, 0)) AS total FROM " . TABLE_INVENTORY
                    . " WHERE inventory_type = 'mi'"
                    . "   AND sku LIKE '" . db_input($inv['sku']) . "-%'"
                );
                $product['quantity'] = $sum_row->EOF ? 0.0 : (float)($sum_row->fields['total'] ?? 0);
            }
        }

        // Product Bundles: quantity_on_hand on the bundle's own row isn't the real stock
        // figure - available quantity is computed from the BOM components (same formula
        // the Inventory Manager list uses), less anything already committed to open sales
        // orders, the same way every other type's available quantity is computed above.
        if (($inv['inventory_type'] ?? '') === 'pb') {
            $vr = $db->Execute(
                "SELECT MIN(FLOOR(i.quantity_on_hand / a.qty)) AS v FROM " . TABLE_INVENTORY_ASSY_LIST . " a"
                . " INNER JOIN " . TABLE_INVENTORY . " i ON a.sku = i.sku"
                . " WHERE a.ref_id = " . $id . " AND a.qty > 0"
            );
            $virtual_stock = (!$vr->EOF && $vr->fields['v'] !== null) ? (float)$vr->fields['v'] : 0.0;
            $product['quantity'] = max(0.0, $virtual_stock - (float)($inv['quantity_on_sales_order'] ?? 0));

            // What the parcel actually contains, so ZenCart's box-packing can pack the
            // pieces separately instead of quoting for a carton nothing travels in. The
            // key is sent for every bundle: ZenCart tells "no pieces" ([]) from "not sent"
            // (absent) with array_key_exists, and an absent key means "leave what you have".
            // A bundle that ships complete IS one piece, so it sends [] and ZenCart falls
            // back to the product's own weight and dimensions.
            if (!empty($inv['ship_complete'])) {
                $product['bundleComponents'] = [];
            } else {
                $pieces = $this->buildBundleComponents($id);
                $product['bundleComponents'] = $pieces;

                // Derive the parcel weight from the very pieces being sent, rather than
                // trusting the stored column. item_weight is only recalculated when the
                // bundle is SAVED (pb::save()), so any bundle not re-saved since - which is
                // every one of them the day this ships - would otherwise send accurate
                // pieces alongside its old hand-entered placeholder. Deriving here makes the
                // two agree by construction: the declared weight is always exactly the sum
                // of what is in the parcel, whatever the save history.
                //
                // A ships-complete bundle is deliberately NOT derived: its weight is the
                // real packed parcel, packaging included, which is a fact only the user has.
                //
                // An empty BOM is left alone on purpose. Summing nothing gives 0, and a 0 kg
                // product quotes silently wrong on Royal Mail and Parcelforce, which read the
                // product's own weight - the exact failure this feature exists to remove.
                if (!empty($pieces)) {
                    $weight = 0.0;
                    foreach ($pieces as $piece) {
                        $weight += (float)$piece['qty'] * (float)$piece['weight'];
                    }
                    $product['productWeight'] = round($weight, 4);
                }
            }
        }

        // Extension hook for site-specific product attributes
        if (file_exists(DIR_FS_MODULES . 'zencart/custom/extra_product_attrs.php')) {
            include(DIR_FS_MODULES . 'zencart/custom/extra_product_attrs.php');
        }

        return $product;
    }

    // The shipping pieces of one bundle, as ZenCart's 'bundleComponents' entries: cm and
    // kg, matching productZencartLength / productWeight.
    //
    // Zero dimensions are sent through exactly as they are held. A component with no
    // measurements packs as zero volume, which is what happens today - substituting a
    // guess here would quietly change quotes on real orders.
    //
    // Kept separate from the pb branch above so an mb/mp master can attach the same shape
    // to each attributes.variants[] entry when per-variant bundles are pushed.
    public function buildBundleComponents(int $id): array
    {
        global $db;

        $rows   = [];
        $result = $db->Execute(
            "SELECT a.sku, a.qty, i.item_weight, i.item_length, i.item_width, i.item_height"
            . " FROM " . TABLE_INVENTORY_ASSY_LIST . " a"
            . " LEFT JOIN " . TABLE_INVENTORY . " i ON i.sku = a.sku"
            . " WHERE a.ref_id = " . $id
            . " ORDER BY a.id"
        );
        while (!$result->EOF) {
            $rows[] = [
                // Diagnostic only - nothing on the ZenCart side joins on it, and a piece
                // need not exist in the ZenCart catalogue at all.
                'sku'    => (string)$result->fields['sku'],
                // Float, not int: fractional quantities are real here - a length of hose
                // cut from continuous stock is stored as 0.27 of its component SKU.
                'qty'    => (float)$result->fields['qty'],
                'length' => (float)($result->fields['item_length'] ?? 0),
                'width'  => (float)($result->fields['item_width']  ?? 0),
                'height' => (float)($result->fields['item_height'] ?? 0),
                'weight' => (float)($result->fields['item_weight'] ?? 0),
            ];
            $result->MoveNext();
        }
        return $rows;
    }

    // Push current available quantity (quantity_on_hand less quantity_on_sales_order) and
    // retail price for all catalog=1 products.
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
            "SELECT id, sku, inventory_type, ProductModel, quantity_on_hand, quantity_on_sales_order, item_cost, full_price"
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
            $inv_id   = (int)$result->fields['id'];
            $inv_type = (string)($result->fields['inventory_type'] ?? '');
            $model    = !empty($result->fields['ProductModel'])
                ? $result->fields['ProductModel']
                : $result->fields['sku'];

            // mi (child) items are never pushed standalone — their master pushes them.
            if ($inv_type === 'mi') {
                $result->MoveNext();
                continue;
            }

            // Master Stock items always go through uploadProduct() (attribute-aware path).
            if (in_array($inv_type, ['ms', 'mb'], true)) {
                if ($this->uploadProduct($inv_id, false)) {
                    $n_tiered_ok++;
                } else {
                    $n_tiered_err++;
                }
                $result->MoveNext();
                continue;
            }

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

            // Product Bundles: same BOM-derived virtual stock as buildProductPayload() above -
            // quantity_on_hand on the bundle's own row isn't the real figure.
            if ($inv_type === 'pb') {
                $vr = $db->Execute(
                    "SELECT MIN(FLOOR(i.quantity_on_hand / a.qty)) AS v FROM " . TABLE_INVENTORY_ASSY_LIST . " a"
                    . " INNER JOIN " . TABLE_INVENTORY . " i ON a.sku = i.sku"
                    . " WHERE a.ref_id = " . $inv_id . " AND a.qty > 0"
                );
                $virtual_stock = (!$vr->EOF && $vr->fields['v'] !== null) ? (float)$vr->fields['v'] : 0.0;
                $qty = max(0.0, $virtual_stock - (float)$result->fields['quantity_on_sales_order']);
            } else {
                $qty = max(0.0, (float)$result->fields['quantity_on_hand'] - (float)$result->fields['quantity_on_sales_order']);
            }

            $bulk_products[] = [
                'products_model' => $model,
                'quantity'       => $qty,
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
            "SELECT sl.id, sl.ref_id, sl.carrier, sl.method, sl.tracking_id"
            . " FROM " . TABLE_SHIPPING_LOG . " sl"
            . " LEFT JOIN " . TABLE_ZC_SHIP_NOTIFY . " zn ON zn.shipping_log_id = sl.id"
            . " WHERE sl.ship_date LIKE '" . db_prepare_input($post_date) . " %'"
            . " AND zn.shipping_log_id IS NULL"
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
                    "SELECT closed, purchase_invoice_id, purch_order_id FROM " . TABLE_JOURNAL_MAIN
                    . " WHERE id = " . (int)$main->fields['so_po_ref_id']
                );
                if (!$so->EOF) {
                    // ZenCart order ID is stored as 'ZC-{id}' in purch_order_id
                    $zc_order_id = (strpos($so->fields['purch_order_id'], 'ZC-') === 0)
                        ? (int)substr($so->fields['purch_order_id'], 3)
                        : (int)$so->fields['purchase_invoice_id'];
                    $carrier       = $shipments->fields['carrier'];
                    $method        = $shipments->fields['method'];
                    $carrier_title = $methods[$carrier]['title'] ?: $carrier;
                    $method_title  = $methods[$carrier][$method] ?: $method;
                    $fmt         = (defined('ZENCART_NOTIFY_MESSAGE') && ZENCART_NOTIFY_MESSAGE !== '')
                        ? ZENCART_NOTIFY_MESSAGE
                        : ZENCART_CONFIRM_MESSAGE;
                    $tracking_raw = $shipments->fields['tracking_id'];
                    $url_const    = $carrier . '_TRACKING_URL';
                    $tracking_val = (defined($url_const) && $tracking_raw !== '')
                        ? "<a href='" . constant($url_const) . rawurlencode($tracking_raw) . "'>" . htmlspecialchars($tracking_raw, ENT_COMPAT, 'UTF-8') . '</a>'
                        : htmlspecialchars($tracking_raw, ENT_COMPAT, 'UTF-8');
                    $message = str_replace(
                        ['{date}', '{carrier}', '{service}', '{tracking}'],
                        [$post_date, $carrier_title, $method_title, $tracking_val],
                        $fmt
                    );
                    $status = $so->fields['closed']
                        ? (int)ZENCART_STATUS_CONFIRM_ID
                        : (int)ZENCART_STATUS_PARTIAL_ID;
                    zencart_api_call('updateOrderComments', [
                        'orderId'   => $zc_order_id,
                        'comment'   => $message,
                        'status'    => $status,
                        'updatedBy' => (defined('ZENCART_UPDATED_BY') && ZENCART_UPDATED_BY !== '') ? ZENCART_UPDATED_BY : 'FreeBooks',
                    ]);
                    $db->Execute(
                        "INSERT INTO " . TABLE_ZC_SHIP_NOTIFY . " (shipping_log_id, notified_at)"
                        . " VALUES (" . (int)$shipments->fields['id'] . ", NOW())"
                    );
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
            if ($m === '.' || $m === '..') continue;
            // In CLI (cron) context the module _STATUS constants are never loaded; fall back to
            // checking whether the module's main PHP file exists so all installed modules are found.
            if (defined('MODULE_SHIPPING_' . strtoupper($m) . '_STATUS')
                || file_exists(DIR_FS_MODULES . 'shipping/methods/' . $m . '/' . $m . '.php')) {
                $method_array[] = $m;
            }
        }
        $output  = [];
        $choices = array_keys($shipping_defaults['service_levels']);
        foreach ($method_array as $m) {
            // Language must load before the main module file — the main file aliases
            // service codes using language constants (e.g. royal_mail_CRL24 = MODULE_SHIPPING_ROYAL_MAIL_CRL24).
            load_method_language(DIR_FS_MODULES . 'shipping/methods/', $m);
            $main = DIR_FS_MODULES . 'shipping/methods/' . $m . '/' . $m . '.php';
            if (file_exists($main)) require_once($main);
            $output[$m]['title'] = constant('MODULE_SHIPPING_' . strtoupper($m) . '_TEXT_TITLE');
            foreach ($choices as $svc) {
                $output[$m][$svc] = defined($m . '_' . $svc) ? constant($m . '_' . $svc) : '';
            }
        }
        return $output;
    }
}
