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
//  Path: /modules/shipping/classes/box_packer.php

use DVDoug\BoxPacker\Box;
use DVDoug\BoxPacker\Item;
use DVDoug\BoxPacker\Packer;
use DVDoug\BoxPacker\PackedBoxList;
use DVDoug\BoxPacker\Rotation;
use DVDoug\BoxPacker\Exception\NoBoxesAvailableException;

/**
 * Wraps a shipping_packages row as a BoxPacker Box.
 * All dimensions stored in SHIPPING_DEFAULT_PKG_DIM_UNIT; weights in SHIPPING_DEFAULT_WEIGHT_UNIT.
 */
class FbBox implements Box
{
    private string $reference;
    private string $name;
    private int $outerWidth;
    private int $outerLength;
    private int $outerDepth;
    private int $emptyWeight;
    private int $innerWidth;
    private int $innerLength;
    private int $innerDepth;
    private int $maxWeight;

    public function __construct(array $row)
    {
        $this->reference   = $row['package_code'];
        $this->name        = $row['name'] ?? $row['package_code'];
        $this->outerWidth  = self::toMm((float)$row['ext_width']);
        $this->outerLength = self::toMm((float)$row['ext_length']);
        $this->outerDepth  = self::toMm((float)$row['ext_height']);
        $this->emptyWeight = self::toGrams((float)$row['weight']);
        $this->innerWidth  = self::toMm((float)$row['int_width']);
        $this->innerLength = self::toMm((float)$row['int_length']);
        $this->innerDepth  = self::toMm((float)$row['int_height']);
        $this->maxWeight   = self::toGrams((float)$row['max_weight']);
    }

    public function getReference(): string   { return $this->reference; }
    public function getName(): string        { return $this->name; }
    public function getOuterWidth(): int      { return $this->outerWidth; }
    public function getOuterLength(): int     { return $this->outerLength; }
    public function getOuterDepth(): int      { return $this->outerDepth; }
    public function getEmptyWeight(): int     { return $this->emptyWeight; }
    public function getInnerWidth(): int      { return $this->innerWidth; }
    public function getInnerLength(): int     { return $this->innerLength; }
    public function getInnerDepth(): int      { return $this->innerDepth; }
    public function getMaxWeight(): int       { return $this->maxWeight; }

    private static function toMm(float $val): int
    {
        return (defined('SHIPPING_DEFAULT_PKG_DIM_UNIT') && SHIPPING_DEFAULT_PKG_DIM_UNIT === 'CM')
            ? (int) round($val * 10)
            : (int) round($val * 25.4);
    }

    private static function toGrams(float $val): int
    {
        return (defined('SHIPPING_DEFAULT_WEIGHT_UNIT') && SHIPPING_DEFAULT_WEIGHT_UNIT === 'KGS')
            ? (int) round($val * 1000)
            : (int) round($val * 453.592);
    }
}

/**
 * Wraps an inventory row as a BoxPacker Item.
 * Fields: sku, item_weight, item_length, item_width, item_height.
 */
class FbItem implements Item
{
    private string $description;
    private int $width;
    private int $length;
    private int $depth;
    private int $weight;

    public function __construct(array $row)
    {
        $this->description = $row['sku'];
        $this->width       = self::toMm((float)($row['item_width']  ?? 0));
        $this->length      = self::toMm((float)($row['item_length'] ?? 0));
        $this->depth       = self::toMm((float)($row['item_height'] ?? 0));
        $this->weight      = self::toGrams((float)($row['item_weight'] ?? 0));
    }

    public function getDescription(): string    { return $this->description; }
    public function getWidth(): int             { return $this->width; }
    public function getLength(): int            { return $this->length; }
    public function getDepth(): int             { return $this->depth; }
    public function getWeight(): int            { return $this->weight; }
    public function getAllowedRotation(): Rotation { return Rotation::BestFit; }

    private static function toMm(float $val): int
    {
        return (defined('SHIPPING_DEFAULT_PKG_DIM_UNIT') && SHIPPING_DEFAULT_PKG_DIM_UNIT === 'CM')
            ? (int) round($val * 10)
            : (int) round($val * 25.4);
    }

    private static function toGrams(float $val): int
    {
        return (defined('SHIPPING_DEFAULT_WEIGHT_UNIT') && SHIPPING_DEFAULT_WEIGHT_UNIT === 'KGS')
            ? (int) round($val * 1000)
            : (int) round($val * 453.592);
    }
}

/**
 * Runs box-packing for an order.
 *
 * Usage:
 *   $result = BoxPackingService::pack([['sku' => 'ABC', 'qty' => 2], ...]);
 *   // $result['packed']   => PackedBoxList
 *   // $result['unpacked'] => array of ['sku' => ..., 'qty' => ...] that could not be packed
 */
class BoxPackingService
{
    /**
     * Pack order items into available shipping boxes.
     *
     * Items with missing dimensions are excluded from geometric packing but their
     * weight is tracked separately so the caller can add it to the total.
     *
     * @param  array  $order_items  [ ['sku' => string, 'qty' => int], ... ]
     * @return array {
     *   packed:           PackedBoxList,
     *   unpacked:         array of items that couldn't fit in any box,
     *   weight_only_g:    int   — grams from items excluded due to missing dimensions,
     *   weight_only_skus: array — SKUs excluded from geometric packing,
     * }
     */
    /**
     * @param string $carrier_code  Filter boxes to this carrier ('' or carrier = 'all') plus all-carrier boxes.
     */
    public static function pack(array $order_items, string $carrier_code = ''): array
    {
        global $db;

        $packer = new Packer();

        $carrier_filter = ($carrier_code !== '')
            ? " AND (carrier = '' OR carrier = '" . db_input($carrier_code) . "')"
            : '';
        $boxes = $db->Execute(
            "SELECT * FROM " . TABLE_SHIPPING_PACKAGES
            . " WHERE inactive = '0'" . $carrier_filter
            . " ORDER BY sort_order ASC, id ASC"
        );
        while (!$boxes->EOF) {
            $packer->addBox(new FbBox($boxes->fields));
            $boxes->MoveNext();
        }

        $skus = array_unique(array_column($order_items, 'sku'));
        $inv  = [];
        if (!empty($skus)) {
            $in_list = implode(',', array_map(fn($s) => "'" . db_input($s) . "'", $skus));
            $rows = $db->Execute(
                "SELECT sku, item_weight, item_length, item_width, item_height"
                . " FROM " . TABLE_INVENTORY
                . " WHERE sku IN ($in_list)"
            );
            while (!$rows->EOF) {
                $inv[$rows->fields['sku']] = $rows->fields;
                $rows->MoveNext();
            }
        }

        $unpacked_items   = [];
        $weight_only_g    = 0;
        $weight_only_skus = [];

        foreach ($order_items as $line) {
            $sku = $line['sku'];
            $qty = max(1, (int)($line['qty'] ?? 1));

            if (!isset($inv[$sku])) {
                $unpacked_items[] = ['sku' => $sku, 'qty' => $qty, 'reason' => 'not_in_inventory'];
                continue;
            }

            $row      = $inv[$sku];
            $has_dims = ((float)($row['item_length'] ?? 0) > 0
                      && (float)($row['item_width']  ?? 0) > 0
                      && (float)($row['item_height'] ?? 0) > 0);

            if ($has_dims) {
                $packer->addItem(new FbItem($row), $qty);
            } else {
                // No geometry — still count the weight
                $weight_only_g   += self::toGrams((float)($row['item_weight'] ?? 0)) * $qty;
                $weight_only_skus[] = $sku;
            }
        }

        $weight_only_skus = array_unique($weight_only_skus);

        try {
            $packed = $packer->pack();
        } catch (NoBoxesAvailableException $e) {
            foreach ($e->getAffectedItems() as $item) {
                $unpacked_items[] = ['sku' => $item->getDescription(), 'qty' => 1, 'reason' => 'no_box_fits'];
            }
            $packed = new PackedBoxList();
        }

        return [
            'packed'           => $packed,
            'unpacked'         => $unpacked_items,
            'weight_only_g'    => $weight_only_g,
            'weight_only_skus' => $weight_only_skus,
        ];
    }

    /**
     * Summarise a PackedBoxList into a simple array for use by shipping modules.
     *
     * Each entry: box_code, box_name, weight_g (total incl. box), outer dimensions in mm, items[].
     */
    public static function summarise(PackedBoxList $packed): array
    {
        $result = [];
        foreach ($packed as $packedBox) {
            $box      = $packedBox->box;
            $sku_qtys = [];
            foreach ($packedBox->items as $packedItem) {
                $sku = $packedItem->item->getDescription();
                $sku_qtys[$sku] = ($sku_qtys[$sku] ?? 0) + 1;
            }
            $items = [];
            foreach ($sku_qtys as $sku => $qty) {
                $items[] = ['sku' => $sku, 'qty' => $qty];
            }
            $result[] = [
                'box_code'   => $box->getReference(),
                'box_name'   => ($box instanceof FbBox) ? $box->getName() : $box->getReference(),
                'weight_g'   => $packedBox->getWeight(),
                'length_mm'  => $box->getOuterLength(),
                'width_mm'   => $box->getOuterWidth(),
                'depth_mm'   => $box->getOuterDepth(),
                'items'      => $items,
            ];
        }
        return $result;
    }

    /** Convert configured dimension unit → mm. */
    public static function toMm(float $val): int
    {
        return (defined('SHIPPING_DEFAULT_PKG_DIM_UNIT') && SHIPPING_DEFAULT_PKG_DIM_UNIT === 'CM')
            ? (int)round($val * 10)
            : (int)round($val * 25.4);
    }

    /** Convert configured weight unit → grams. */
    public static function toGrams(float $val): int
    {
        return (defined('SHIPPING_DEFAULT_WEIGHT_UNIT') && SHIPPING_DEFAULT_WEIGHT_UNIT === 'KGS')
            ? (int)round($val * 1000)
            : (int)round($val * 453.592);
    }

    /** Convert mm → configured dimension unit (IN or CM). */
    public static function fromMm(int $mm): float
    {
        return (defined('SHIPPING_DEFAULT_PKG_DIM_UNIT') && SHIPPING_DEFAULT_PKG_DIM_UNIT === 'CM')
            ? round($mm / 10, 2)
            : round($mm / 25.4, 2);
    }

    /** Convert grams → configured weight unit (KGS or LBS). */
    public static function fromGrams(int $g): float
    {
        return (defined('SHIPPING_DEFAULT_WEIGHT_UNIT') && SHIPPING_DEFAULT_WEIGHT_UNIT === 'KGS')
            ? round($g / 1000, 3)
            : round($g / 453.592, 3);
    }
}
