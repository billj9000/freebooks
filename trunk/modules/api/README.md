# FreeBooks REST API

Read-only JSON API with flexible query support.

## Installation

1. Go to **Company → Configuration → Modules** in the FreeBooks admin.
2. Click **Install** next to _REST API_.
3. The installer creates a central `freebooks_api` MySQL database (using the same server credentials as the application). If the DB user lacks `CREATE` privilege, pre-create the database manually:
   ```sql
   CREATE DATABASE freebooks_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   GRANT ALL ON freebooks_api.* TO '<db_user>'@'<db_host>';
   ```
4. After installation, go to **Company → Configuration → REST API** to generate keys.

## Authentication

All requests must include a Bearer token:

```
Authorization: Bearer fbk_live_<your-key>
```

The key identifies the company — no separate company parameter is required. Keys are managed per-company in the admin UI.

If your host strips the `Authorization` header, you can pass the key as a query parameter instead (less secure — avoid in production):
```
?api_key=fbk_live_<your-key>
```

HTTPS is required in production. For local development set the environment variable `FREEBOOKS_API_ALLOW_HTTP=1`.

## Request format

```
GET /api.php?v=1&resource={resource}[&fields=...][&filters=...][&order_by=...][&limit_start=N][&limit_page_length=N]
```

### Parameters

| Parameter         | Type       | Description                                                                 |
|-------------------|------------|-----------------------------------------------------------------------------|
| `v`               | required   | API version. Currently `1`.                                                 |
| `resource`        | required   | Resource name: `contacts`, `invoices`, `credit_memos`, or `tax_rates`.       |
| `fields`          | JSON array | Field names to return. Omit for the default set.                            |
| `filters`         | JSON array | Array of `[field, operator, value]` triples. AND-combined.                  |
| `order_by`        | string     | `field asc` or `field desc`.                                                |
| `limit_start`     | int        | Offset for pagination (default: 0).                                         |
| `limit_page_length` | int      | Page size (default: 100, max: 500).                                         |

### Supported operators

`=`, `!=`, `<`, `>`, `<=`, `>=`, `like`, `in`, `between`

### Response envelope

```json
{
  "ok": true,
  "data": [ ... ],
  "meta": {
    "limit_start": 0,
    "limit_page_length": 100,
    "count": 25,
    "total": 83
  }
}
```

On error:
```json
{ "ok": false, "error": "Human-readable message" }
```

HTTP status codes: `200` success, `400` bad request (invalid params), `401` auth failure, `404` unknown resource, `405` wrong method, `500` internal error, `503` service unavailable.

---

## Resources

### `contacts`

One row per contact (customers, vendors, employees, etc.), joined to the main address record.

**Default fields:** `id`, `type`, `short_name`, `primary_name`, `contact`, `telephone1`, `telephone2`, `email`, `inactive`, `modified`

**All fields:**

| Field           | Type   | Description                                            |
|-----------------|--------|--------------------------------------------------------|
| `id`            | int    | Contact primary key                                    |
| `type`          | string | `c`=customer, `v`=vendor, `e`=employee, `b`=branch, `j`=project, `i`=CRM |
| `short_name`    | string | Short/lookup name                                      |
| `first_name`    | string | First name (employees / CRM)                          |
| `last_name`     | string | Last name                                              |
| `inactive`      | string | `0`=active, `1`=inactive                              |
| `account_number`| string | Account number                                         |
| `modified`      | date   | Date last updated (`last_update`)                     |
| `primary_name`  | string | Company / primary name                                |
| `contact`       | string | Contact person at this address                        |
| `address1`      | string | Address line 1                                        |
| `address2`      | string | Address line 2                                        |
| `city`          | string | City / town                                           |
| `state`         | string | State / province                                      |
| `postal_code`   | string | Postal code                                           |
| `country`       | string | 3-letter country code                                 |
| `telephone1`    | string | Primary phone                                         |
| `telephone2`    | string | Secondary phone                                       |
| `telephone3`    | string | Third phone                                           |
| `telephone4`    | string | Fourth phone                                          |
| `email`         | string | Email address                                         |
| `website`       | string | Website URL                                           |

**Examples:**

Caller-ID pull — active customers with a phone number:
```
GET /api.php?v=1&resource=contacts
  &fields=["id","primary_name","telephone1","telephone2","type"]
  &filters=[["type","=","c"],["inactive","=","0"],["telephone1","!=",""]]
  &order_by=primary_name asc
  &limit_page_length=500
```

Incremental sync — contacts changed since a date:
```
GET /api.php?v=1&resource=contacts
  &filters=[["modified",">=","2026-07-01"]]
  &order_by=modified asc
```

---

### `invoices`

Sales invoices (journal_id = 12). The virtual `lines` field returns product line items.

> **Note on `modified`:** `journal_main` has no row-modified timestamp; `modified` maps to `post_date` (the invoice date). For incremental sync, filter on `date` instead.

**Default fields:** `id`, `invoice_number`, `reference`, `date`, `customer_id`, `customer_name`, `total`, `tax`, `freight`, `currency`, `closed`

**All fields:**

| Field              | Type   | Description                                                |
|--------------------|--------|------------------------------------------------------------|
| `id`               | int    | Internal record ID                                        |
| `invoice_number`   | string | Invoice number (shown on document)                        |
| `reference`        | string | Customer's PO / reference                                 |
| `date`             | date   | Invoice date                                              |
| `customer_id`      | int    | Customer contact ID (`contacts.id`)                       |
| `customer_name`    | string | Customer company name (denormalised snapshot)             |
| `customer_contact` | string | Customer contact person                                   |
| `bill_address1/2`  | string | Billing address                                           |
| `bill_city/state`  | string | Billing city / state                                      |
| `bill_postal_code` | string | Billing postal code                                       |
| `bill_country`     | string | Billing country code                                      |
| `bill_email`       | string | Billing email                                             |
| `bill_telephone`   | string | Billing phone                                             |
| `ship_country`     | string | Shipping country code (3-letter, e.g. `GBR`); blank if no shipping address recorded |
| `total`            | float  | Grand total                                               |
| `tax`              | float  | Total tax                                                 |
| `freight`          | float  | Shipping charge                                           |
| `freight_tax_id`   | int    | Raw per-order override typed on the order form (usually `0` = "use the company default"); **not** the effective rate — see `freight_taxable` |
| `freight_taxable`  | virtual | Effective tax rate ID FreeBooks actually applied to freight (from the posted `frt` journal_item row); mirrors a line item's `taxable` |
| `freight_tax_rate` | float  | Virtual — `freight_taxable` resolved to a percentage (see note below)  |
| `freight_gl_account` | string | Virtual — GL account the freight charge posts to (freight is its own journal_item row, gl_type `frt`, excluded from `lines`); blank if there's no freight |
| `discount`         | float  | Discount                                                  |
| `discount_gl_account` | string | Virtual — GL account the discount posts to (its own journal_item row, gl_type `dsc`, excluded from `lines`); blank if there's no discount |
| `payment_reference` | string | Virtual — reference (transaction ID, cheque number, etc) from the Cash Receipt that paid this invoice, **not** `reference` above (which is the invoice's own PO Number). Most recent receipt with a non-blank reference wins if there's more than one (partial payments). Blank if unpaid. |
| `currency`         | string | Currency code (e.g. `GBP`)                               |
| `currency_rate`    | float  | Exchange rate                                             |
| `closed`           | string | `0`=open, `1`=paid/closed                                |
| `terms`            | string | Payment terms                                             |
| `shipper_code`     | string | Carrier code                                             |
| `rep_id`           | int    | Sales rep contact ID                                      |
| `notes`            | string | Notes                                                     |
| `modified`         | date   | Maps to `post_date` — see note above                     |
| `lines`            | array  | Product line items (virtual — batch-loaded, no N+1)      |

**Line item fields** (each element of `lines`):

| Field        | Description                          |
|--------------|--------------------------------------|
| `sku`        | Product code                         |
| `description`| Line description                     |
| `qty`        | Quantity                             |
| `unit_price` | Unit price (amount ÷ qty)            |
| `amount`     | Extended amount                      |
| `gl_account` | GL account code                      |
| `taxable`    | Tax rate ID (0 = non-taxable)        |
| `tax_rate`   | `taxable` resolved to a percentage (see note below) |

**Note on `tax_rate` / `freight_tax_rate`:** `taxable`/`freight_taxable` are tax_rate_ids, not percentages — a tax_rate_id can combine multiple tax authorities. `tax_rate` and `freight_tax_rate` resolve them to the actual percentage FreeBooks would use to calculate tax (summing `tax_rates.rate_accounts` against `tax_authorities.tax_rate`, the same calculation as `gen_calculate_tax_rate()`), so callers don't need to duplicate that lookup or hardcode rates that could drift out of sync.

**Important:** use `freight_taxable`, not `freight_tax_id`, if you need the tax rate ID that actually applied to freight. `freight_tax_id` is the raw per-order override on the order form and is commonly left at `0` ("use the company default," `AR_ADD_SALES_TAX_TO_SHIPPING`) — the effective id FreeBooks used is only recorded on the posted freight line itself, which is what `freight_taxable` (and `freight_tax_rate`) resolve from.

**Examples:**

June invoices:
```
GET /api.php?v=1&resource=invoices
  &fields=["invoice_number","date","customer_name","total","tax","currency","lines"]
  &filters=[["date","between",["2026-06-01","2026-06-30"]]]
  &order_by=date asc
```

Unpaid invoices over £500:
```
GET /api.php?v=1&resource=invoices
  &filters=[["closed","=","0"],["total",">=","500"]]
  &order_by=date asc
```

---

### `credit_memos`

Customer credit memos (journal_id = 13). Same schema and virtual `lines` handling as `invoices` — see that section for field-by-field detail on the shared columns.

> **Note on `modified`:** as with `invoices`, `journal_main` has no row-modified timestamp; `modified` maps to `post_date` (the credit memo date). For incremental sync, filter on `date` instead.

> **Note on `qty` sign:** credit memo line item `qty` is stored positive (invoices negate `qty` for inventory draw-down). This does not affect `amount`/`unit_price`, which are derived from `debit_amount`/`credit_amount` exactly as for invoices.

**Default fields:** `id`, `credit_memo_number`, `reference`, `date`, `customer_id`, `customer_name`, `total`, `tax`, `freight`, `currency`, `closed`

**All fields:** identical to `invoices`, except `invoice_number` is named `credit_memo_number`. See the `invoices` field table above.

**Line item fields:** identical to `invoices` — see the `invoices` line item table above.

**Examples:**

June credit memos:
```
GET /api.php?v=1&resource=credit_memos
  &fields=["credit_memo_number","date","customer_name","total","tax","currency","lines"]
  &filters=[["date","between",["2026-06-01","2026-06-30"]]]
  &order_by=date asc
```

---

### `tax_rates`

Read-only lookup of the tax_rate_ids referenced by `invoices`/`credit_memos`' `taxable` / `freight_taxable` fields. FreeBooks' own admin UI (Company Setup > Tax Rates) never displays this numeric id — it only appears inside a hidden `onclick` attribute on the edit icon — so this is the only way for an external integration to discover which id corresponds to which real-world tax.

**Default fields:** `id`, `type`, `description_short`, `description_long`, `rate`, `freight_taxable`

**All fields:**

| Field                | Type    | Description                                                    |
|----------------------|---------|------------------------------------------------------------------|
| `id`                 | int     | tax_rate_id — matches `invoices`/`credit_memos`' `taxable`/`freight_taxable` |
| `type`               | string  | `c` = customer/sales, `v` = vendor/purchase; only `c` rows are relevant to `invoices`/`credit_memos` |
| `description_short`  | string  | Short label as shown in FreeBooks admin (e.g. `VAT`)             |
| `description_long`   | string  | Long label as shown in FreeBooks admin (e.g. `20% VAT on sales`) |
| `rate`               | float   | Virtual — computed percentage (sums `tax_rates.rate_accounts` against `tax_authorities.tax_rate`, same calculation as `gen_calculate_tax_rate()`) |
| `freight_taxable`    | string  | `1`/`0` — whether this rate is FreeBooks' UI default suggestion for freight (informational only; the effective per-invoice freight rate is `invoices`/`credit_memos`' own `freight_taxable` field) |

**Example:** find the sales tax rates and their percentages:
```
GET /api.php?v=1&resource=tax_rates
  &filters=[["type","=","c"]]
```

---

## Development / testing

Set `FREEBOOKS_API_ALLOW_HTTP=1` in the environment (e.g. Docker Compose `environment:` or `.env`) to bypass the HTTPS requirement for local testing.

### Sample curl test script

```bash
#!/usr/bin/env bash
BASE="http://localhost/api.php"
KEY="fbk_live_yourkey"
H="Authorization: Bearer $KEY"

# Active customers
curl -s -H "$H" "$BASE?v=1&resource=contacts&filters=%5B%5B%22type%22%2C%22%3D%22%2C%22c%22%5D%2C%5B%22inactive%22%2C%22%3D%22%2C%220%22%5D%5D" | python3 -m json.tool

# June invoices with lines
curl -s -H "$H" "$BASE?v=1&resource=invoices&fields=%5B%22invoice_number%22%2C%22date%22%2C%22customer_name%22%2C%22total%22%2C%22lines%22%5D&filters=%5B%5B%22date%22%2C%22between%22%2C%5B%222026-06-01%22%2C%222026-06-30%22%5D%5D%5D" | python3 -m json.tool

# June credit memos with lines
curl -s -H "$H" "$BASE?v=1&resource=credit_memos&fields=%5B%22credit_memo_number%22%2C%22date%22%2C%22customer_name%22%2C%22total%22%2C%22lines%22%5D&filters=%5B%5B%22date%22%2C%22between%22%2C%5B%222026-06-01%22%2C%222026-06-30%22%5D%5D%5D" | python3 -m json.tool

# Bad field (expect 400)
curl -s -H "$H" "$BASE?v=1&resource=contacts&fields=%5B%22nonexistent%22%5D"

# Bad token (expect 401)
curl -s -H "Authorization: Bearer bad_token" "$BASE?v=1&resource=contacts"
```

---

## Extending the API

### Adding a new operator

Add one entry to `$operators` in `modules/api/classes/api_query.php`. No other code changes needed.

### Adding a new resource

1. Create `modules/api/classes/resources/{name}.php` implementing `field_map()`, `base_from()`, `base_where()`, `default_fields()`, `post_process()`.
2. Add `'{name}' => 'api_resource_{name}'` to the `$resources` array in `api_request.php`.

### Adding `or_filters`

Parse `?or_filters=` in `api_query::parse_filters()` as a second group, OR-combine within the group, then AND with the existing filter group.
