# FreeBooks ZenCart Module

Integrates FreeBooks with a ZenCart e-commerce store. Provides two-way sync:

- **ZenCart → FreeBooks**: imports orders as Sales Orders, optionally with linked Sales Invoices and Cash Receipts
- **FreeBooks → ZenCart**: uploads product data, syncs stock quantities and prices, confirms shipments

Compatible with ZenCart 1.5.8 – 2.x and PHP 7.4 – 8.3.

---

## Installation

### 1. ZenCart side — install the API endpoint

Copy `freebooks-zencart.php` (from the `zencart-api` repository) to your ZenCart web root (same directory as ZenCart's `index.php`).

Generate a strong secret key and insert it into the ZenCart `configuration` table:

```sql
INSERT INTO configuration
  (configuration_title, configuration_key, configuration_value,
   configuration_description, configuration_group_id, sort_order, date_added)
VALUES
  ('FreeBooks API Key', 'FREEBOOKS_API_KEY', '<your-key>',
   'Secret key for FreeBooks ERP integration', 1, 100, NOW());
```

### 2. FreeBooks side — install the module

Copy `PhreeBooksFiles/modules/zencart/` into `trunk/modules/`. The module will appear in the FreeBooks menu after the next page load.

---

## Configuration

Navigate to **Modules → ZenCart → Admin** to configure:

| Field | Description |
|---|---|
| ZenCart store URL | Base URL of the ZenCart store, no trailing slash (e.g. `https://shop.example.com`) |
| API key | Must match `FREEBOOKS_API_KEY` in ZenCart's configuration table |
| ZenCart Tax Class | Text name of the tax class in ZenCart for taxable products (must match exactly) |
| Use price sheets | Enable quantity-tiered pricing via a FreeBooks price sheet |
| Price sheet | The active price sheet to use when "Use price sheets" is Yes |
| Shipped status ID | ZenCart numeric order status code to set when a shipment is fully shipped |
| Partial status ID | ZenCart numeric order status code to set when an order is partially shipped |
| Catch-all customer ID | FreeBooks contact ID to use when a ZenCart customer cannot be matched or created; set to 0 to skip such orders |
| Fallback tax rate ID | FreeBooks tax rate ID when ZenCart's item tax % has no match in the Tax Rates table; 0 = untaxed |
| Create invoice | Also create a Sales Invoice (journal 12) for each imported order |
| Default sales rep | FreeBooks rep ID to assign to imported orders; 0 = none |
| Last imported order ID | Highest ZenCart order ID successfully imported; edit to re-import from a given point |
| Payment module map | Maps ZenCart payment module codes to FreeBooks payment modules and GL accounts (see below) |

### Payment module map format

```
zcmodule=fbmodule:glacct, ...
```

Example: `paypalexpress=paypal_nvp:1020, stripe=freecharger:1030`

Either `fbmodule` or `:glacct` may be omitted. The FreeBooks module code controls which Cash Receipts payment method label appears; the GL account overrides the default cash account. If a ZenCart payment module is not in the map, `freecharger` is used with the default cash account.

---

## Order Import (ZenCart → FreeBooks)

Orders are imported by the cron script `trunk/cron/zencart_sync.php`, which runs automatically at the end of each cron execution and can also be triggered manually.

```
php /var/www/html/cron/zencart_sync.php [company_name]
```

The company name can be omitted if there is only one company under `my_files/`.

### What gets created

For each new ZenCart order:

1. **Sales Order** (journal 10) — always created, with `purch_order_id = 'ZC-{orderId}'` for duplicate detection
2. **Sales Invoice** (journal 12) — created only when "Create invoice" is Yes
3. **Cash Receipt** (journal 18) — created automatically when an invoice is created and `totalAmount > 0`

If the import fails at the Sales Order stage, the loop stops to preserve order sequence. Invoice and payment failures are logged but do not stop the loop (the SO is already committed).

### Customer matching

Orders are matched to FreeBooks contacts by email address. If no match is found, a new contact is created automatically using the billing address from the order. If contact creation fails, the order is skipped (or assigned to the catch-all contact ID if configured).

### Tax handling

Each ZenCart product line includes its VAT percentage. The cron maps this percentage to the nearest whole-number FreeBooks tax rate ID. If no match is found, the fallback tax rate ID is used.

**Important**: Do not use `inventory.item_taxable` to determine VAT — FreeBooks defaults it to 0, which would silently strip VAT from every recognised SKU. ZenCart's per-item tax% is the authoritative source.

### Rounding adjustment

FreeBooks and ZenCart may differ by a few pence due to per-line vs. per-authority rounding. If the difference is between £0.001 and £0.99, a zero-tax line item with SKU `ROUNDING` is added to make the FreeBooks total agree exactly with the ZenCart paid total.

### Discount / coupon handling

ZenCart coupon discounts arrive as a line item with SKU `Discount`. The absolute discount amount is stored in `journal_main.discount` and applied to the order.

**VAT mismatch with coupons**: FreeBooks by default calculates VAT on the pre-discount subtotal (`AR_TAX_BEFORE_DISCOUNT = 1`), while ZenCart calculates VAT on the post-discount amount. For orders with coupons this causes a total mismatch of `VAT_rate × discount_amount` (e.g. £2.00 on a £10 coupon at 20% VAT).

To make FreeBooks totals match ZenCart, go to **PhreeBooks Admin → Customers tab** and set:
- "Enable percent/value discounts on order totals" → **Yes**
- "Calculate Customer Sales Tax Before Discount Applied" → **After Discount**

**Discount % display quirk**: FreeBooks back-calculates the displayed discount percentage as `discount ÷ (products + shipping subtotal)`. Because shipping is added to the order as a line item (see below), a product-only coupon (e.g. 10% off products) will display as a slightly lower percentage. This is a display-only issue; the monetary amounts are correct.

### Shipping

ZenCart shipping arrives as a line item with SKU `Shipping`. It is mapped to the `freight` field on the FreeBooks Sales Order, which keeps shipping separate from product revenue in the GL.

The shipping tax rate is taken from ZenCart's shipping tax percentage, mapped to a FreeBooks tax rate ID by the same mechanism as product lines. This allows shipping to carry a different VAT rate than the products — which is not possible with FreeBooks' built-in freight field, since that uses a single global freight tax rate.

**Note**: this means the displayed discount percentage will be slightly lower than expected when a product-only coupon is applied (see above).

---

## Product Upload (FreeBooks → ZenCart)

### Single product upload

From the Inventory list, select a product and use the **ZenCart Upload** toolbar button. The product's details, price, and image are sent to ZenCart via the `updateProducts` API endpoint.

### Bulk upload

From **Modules → ZenCart**, use the **Bulk Upload** button to upload all inventory items that have the `catalog` checkbox set. Tick "Include Images" to send product images; unticked is faster and suitable for price/description-only updates.

### Quantity sync

The **Synchronize** button (and the end of each cron run) pushes current `quantity_on_hand` and price to ZenCart for all catalog items:

- Items with **multiple quantity price tiers** are uploaded individually via `uploadProduct()` so ZenCart receives the full tier structure and can display quantity discounts.
- All other items are sent in a single bulk call to the `updateProductsQty` endpoint.

### Price sheets and quantity discounts

When "Use price sheets" is Yes and a price sheet is selected, each item's price is calculated from that sheet. Items with more than one active quantity tier are uploaded as full products (not bulk), which writes the tier data to ZenCart's `products_discount_quantity` table so quantity discounts appear on the product page.

If no global price sheet is configured, the module falls back to the item-level `price_sheet` field on each inventory record.

### Product images

Images are read from `my_files/{company}/inventory/images/` and sent as URL-safe base64 (RFC 4648). The image directory and filename are preserved so ZenCart stores the file in the same relative path.

### Custom product attributes

If `modules/zencart/custom/extra_product_attrs.php` exists, it is included after the standard product array is built. Use this to add site-specific fields (e.g. custom ZenCart attributes) without modifying the core module.

---

## Shipment Confirmation

From **Modules → ZenCart**, select a date and click **Confirm Shipments**. The module reads the FreeBooks shipping log for that date and updates the matching ZenCart orders with a comment containing the carrier, method, and tracking number. Fully shipped orders receive the "Shipped" status code; partially shipped orders receive the "Partial" status code.

The ZenCart order is matched via the Sales Order's `so_po_ref_id` back to the original `ZC-{orderId}` reference.

---

## Cron Schedule

Add to crontab (adjust path and company name as needed):

```
*/15 * * * * php /var/www/html/cron/zencart_sync.php >> /var/log/zencart_sync.log 2>&1
```

The script is safe to run frequently — it only imports orders with IDs higher than `ZENCART_LAST_ORDER` and always runs a stock quantity sync at the end.
