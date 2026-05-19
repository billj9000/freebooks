<?php
// Path: /modules/zencart/classes/bulk_upload.php

class bulk_upload {
    public function bulkUpload(bool $inc_image = false): bool
    {
        global $db, $messageStack;
        $result = $db->Execute(
            "SELECT id FROM " . TABLE_INVENTORY . " WHERE catalog = '1'"
        );
        $cnt = 0;
        while (!$result->EOF) {
            $prod = new zencart();
            if (!$prod->uploadProduct((int)$result->fields['id'], $inc_image)) {
                return false;
            }
            $cnt++;
            $result->MoveNext();
        }
        $messageStack->add(sprintf(ZENCART_BULK_UPLOAD_SUCCESS, $cnt), 'success');
        return true;
    }
}
