<?php
// Path: /modules/phreebooks/updates/R31toR32.php
// Adds freight_tax_id column to journal_main for per-order freight tax rate selection.
// Existing rows default to 0, which preserves prior behaviour (falls back to AR_ADD_SALES_TAX_TO_SHIPPING).

if (!db_field_exists(TABLE_JOURNAL_MAIN, 'freight_tax_id')) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_MAIN
        . " ADD COLUMN freight_tax_id SMALLINT NOT NULL DEFAULT 0 AFTER freight");
}
?>
