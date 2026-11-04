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
//  Path: /modules/inventory/classes/item_costs.php
//
/**
 * Supplier costs and the item cost, in one place (issue #130).
 *
 * Each supplier row in inventory_purchase_details holds its cost in the row's own
 * currency_code. The item's cost (inventory.item_cost) and every price are in the
 * default currency. Anything that reads a supplier's cost to price or compare with,
 * or writes one from a journal, goes through here, so the conversion is done once and
 * the same way everywhere. docs/tools/check_supplier_costs.php reports code that reads
 * or writes the cost column some other way.
 *
 * THE ITEM'S SUPPLIER is the row marked preferred, when it has a cost; otherwise the
 * row with the lowest cost in the default currency. A row with no cost is never
 * chosen: a cost of nothing is a row nobody filled in, not a free part. The item's
 * cost, its main vendor, the margin on the item page and a price sheet's Last Cost
 * all follow that row.
 *
 * Conversions use the current exchange rate. The item cost is stored at the rate of
 * the day it was worked out, on an item save or a purchase from its supplier, and is
 * not refreshed when the rates change; a price sheet converts afresh each time.
 */
final class item_costs
{
	/**
	 * A cost in $code, in the default currency at the current rate. A currency
	 * with no rate is taken as the default currency.
	 */
	public static function to_home($cost, $code): float
	{
		$rate = self::rate($code);
		return $rate > 0 ? (float)$cost / $rate : (float)$cost;
	}

	/** A default-currency amount in $code at the current rate; null when $code has no rate. */
	public static function from_home($home, $code): ?float
	{
		$code = $code ?: DEFAULT_CURRENCY;
		if ($code == DEFAULT_CURRENCY) return (float)$home;
		$rate = self::rate($code);
		return $rate > 0 ? (float)$home * $rate : null;
	}

	/** Every supplier row of $sku, in id order, each with its cost in the default currency as home_cost. */
	public static function rows($sku): array
	{
		global $db;
		$rows   = [];
		$result = $db->Execute("SELECT * FROM " . TABLE_INVENTORY_PURCHASE
			. " WHERE sku = '" . db_input($sku) . "' ORDER BY id");
		while (!$result->EOF) {
			$rows[] = self::with_home($result->fields);
			$result->MoveNext();
		}
		return $rows;
	}

	/** The supplier row of $sku for $vendor_id, with home_cost, or null when it has none. */
	public static function vendor_row($sku, $vendor_id): ?array
	{
		foreach (self::rows($sku) as $row) {
			if ((int)$row['vendor_id'] == (int)$vendor_id) return $row;
		}
		return null;
	}

	/**
	 * The row whose cost the item uses (see the class comment), or null when no row
	 * has a cost. Each row needs item_cost, currency_code and preferred; a tie on the
	 * lowest cost goes to the earlier row.
	 */
	public static function choose(array $rows): ?array
	{
		$cheapest = null;
		foreach ($rows as $row) {
			$row = isset($row['home_cost']) ? $row : self::with_home($row);
			if ($row['home_cost'] <= 0) continue;
			if (!empty($row['preferred'])) return $row;
			if ($cheapest === null || $row['home_cost'] < $cheapest['home_cost']) $cheapest = $row;
		}
		return $cheapest;
	}

	/** The row whose cost $sku uses, or null. */
	public static function chosen($sku): ?array
	{
		return self::choose(self::rows($sku));
	}

	/** The lowest cost among $sku's suppliers in the default currency, or null when none has a cost. */
	public static function cheapest_home($sku): ?float
	{
		$lowest = null;
		foreach (self::rows($sku) as $row) {
			if ($row['home_cost'] > 0 && ($lowest === null || $row['home_cost'] < $lowest)) $lowest = $row['home_cost'];
		}
		return $lowest;
	}

	/**
	 * The cost a price sheet's Last Cost works from, in the default currency: the
	 * chosen supplier's cost at today's rate. An item with no supplier cost, such
	 * as an assembly, uses its own stored cost, $item_cost.
	 */
	public static function sheet_cost($sku, $item_cost): float
	{
		$row = self::chosen($sku);
		return $row ? $row['home_cost'] : (float)$item_cost;
	}

	/**
	 * sheet_cost() for many items in one query: $items maps sku => stored item cost,
	 * and the answer maps each sku to its cost.
	 */
	public static function sheet_costs(array $items): array
	{
		global $db;
		$costs = [];
		foreach ($items as $sku => $item_cost) $costs[$sku] = (float)$item_cost;
		if (empty($items)) return $costs;
		$by_sku = [];
		foreach (array_chunk(array_keys($items), 500) as $chunk) {
			$list   = implode(',', array_map(fn($s) => "'" . db_input((string)$s) . "'", $chunk));
			$result = $db->Execute("SELECT * FROM " . TABLE_INVENTORY_PURCHASE . " WHERE sku IN ($list) ORDER BY id");
			while (!$result->EOF) {
				$by_sku[$result->fields['sku']][] = self::with_home($result->fields);
				$result->MoveNext();
			}
		}
		foreach ($by_sku as $sku => $rows) {
			$row = self::choose($rows);
			if ($row) $costs[$sku] = $row['home_cost'];
		}
		return $costs;
	}

	/**
	 * Write a purchase's unit cost into $vendor_id's rows for $sku, each in the row's
	 * own currency. $home_cost is the journal's figure, in the default currency. A row
	 * in the order's currency takes the order's own unit price; any other row is
	 * converted at its currency's current rate, and a row whose currency has no rate
	 * is left as it is.
	 */
	public static function record_purchase($sku, $vendor_id, $home_cost, $order_code, $order_rate): void
	{
		global $db;
		$order_code = $order_code ?: DEFAULT_CURRENCY;
		$result = $db->Execute("SELECT id, currency_code FROM " . TABLE_INVENTORY_PURCHASE
			. " WHERE sku = '" . db_input($sku) . "' AND vendor_id = " . (int)$vendor_id);
		while (!$result->EOF) {
			$code = $result->fields['currency_code'] ?: DEFAULT_CURRENCY;
			if ($code == DEFAULT_CURRENCY) {
				$cost = (float)$home_cost;
			} elseif ($code == $order_code && (float)$order_rate > 0) {
				$cost = (float)$home_cost * (float)$order_rate;
			} else {
				$cost = self::from_home($home_cost, $code);
			}
			if ($cost !== null) {
				$db->Execute("UPDATE " . TABLE_INVENTORY_PURCHASE . " SET item_cost = " . (float)$cost
					. " WHERE id = " . (int)$result->fields['id']);
			}
			$result->MoveNext();
		}
	}

	/**
	 * Set $sku's cost and main vendor from its supplier, after its supplier rows have
	 * been written. An item none of whose suppliers has a cost is left as it is.
	 */
	public static function refresh_item($sku): ?array
	{
		global $db;
		$row = self::chosen($sku);
		if ($row) {
			$db->Execute("UPDATE " . TABLE_INVENTORY . " SET item_cost = " . (float)$row['home_cost']
				. ", vendor_id = " . (int)$row['vendor_id'] . " WHERE sku = '" . db_input($sku) . "'");
		}
		return $row;
	}

	/**
	 * The fields of a supplier row copied to another item: everything but its id
	 * and sku, so the copy keeps the row's currency and preference.
	 */
	public static function copy_fields(array $row): array
	{
		$fields = [];
		foreach (['vendor_id', 'vendor_sku', 'description_purchase', 'item_cost', 'currency_code',
		          'purch_package_quantity', 'purch_taxable', 'price_sheet_v', 'preferred'] as $key) {
			if (array_key_exists($key, $row)) $fields[$key] = $row[$key];
		}
		if (($fields['currency_code'] ?? '') === '') $fields['currency_code'] = DEFAULT_CURRENCY;
		$fields['preferred'] = empty($fields['preferred']) ? 0 : 1;
		return $fields;
	}

	private static function with_home(array $row): array
	{
		$row['home_cost'] = self::to_home($row['item_cost'] ?? 0, $row['currency_code'] ?? '');
		return $row;
	}

	private static function rate($code): float
	{
		global $currencies;
		$code = $code ?: DEFAULT_CURRENCY;
		if ($code == DEFAULT_CURRENCY || !is_object($currencies)) return 0.0;
		return (float)($currencies->currencies[$code]['value'] ?? 0);
	}
}
