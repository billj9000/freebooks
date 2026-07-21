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
// Path: /modules/zencart/classes/bulk_upload.php

class bulk_upload {
    // Products are batched into groups of this size and sent as one updateProducts
    // call each, instead of one HTTP round-trip per product.
    const BATCH_SIZE = 50;

    public function bulkUpload(bool $inc_image = false): bool
    {
        global $db, $messageStack;
        $result = $db->Execute(
            "SELECT id FROM " . TABLE_INVENTORY . " WHERE catalog = '1'"
        );
        $cnt   = 0;
        $batch = [];
        while (!$result->EOF) {
            $prod    = new zencart();
            $product = $prod->buildProductPayload((int)$result->fields['id'], $inc_image);
            if ($product === null) {
                return false;
            }
            $batch[] = $product;
            $cnt++;
            if (count($batch) >= self::BATCH_SIZE) {
                if (zencart_api_call('updateProducts', ['products' => $batch]) === false) {
                    return false;
                }
                $batch = [];
            }
            $result->MoveNext();
        }
        if (!empty($batch) && zencart_api_call('updateProducts', ['products' => $batch]) === false) {
            return false;
        }
        $messageStack->add(sprintf(ZENCART_BULK_UPLOAD_SUCCESS, $cnt), 'success');
        return true;
    }
}
