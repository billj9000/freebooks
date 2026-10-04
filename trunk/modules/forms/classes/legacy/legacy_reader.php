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
//  Path: /modules/forms/classes/legacy/legacy_reader.php
//
/**
 * Forms — reading a PhreeForm definition. See §13.33.
 *
 * The one place that turns PhreeForm XML into the object every converter in
 * this directory expects. One copy of the normalisation, so every converter is
 * handed the same shape — a mismatch is not an exception but a form that
 * renders with one table instead of two.
 *
 * Reading a PhreeForm definition stays necessary without PhreeForm: decision L
 * keeps the converter as an **Import** feature, for the definition that turns
 * up on a USB stick years from now, and `.pre-forms` originals stay on disk as
 * the record of what a migrated document was.
 *
 * ## What "normalise" means, and why nothing works without it
 *
 * `xml_to_object()` collapses a single child to an object where two or more
 * give an array, so a report with one `<tables>` and a report with two arrive
 * in different shapes. Every converter downstream iterates, so the one-table
 * report either fails or silently converts nothing. The lists are wrapped back
 * into arrays here, once.
 *
 * ## Why xml_to_object() is wrapped rather than replaced
 *
 * It is core — `includes/common_functions.php` — so there is nothing to rescue.
 * Copying ninety lines of hand-rolled parser into this module to own it would
 * add a second parser that has to agree with the first about CDATA,
 * self-closing tags and attributes, and would be tested by nothing. What Forms
 * needs to own is the SHAPE, which is what this file is.
 */

namespace FreeBooks\Forms\Legacy;

final class LegacyReader
{
    /**
     * The suffix a conversion keeps the PhreeForm original under.
     *
     * Spelled here rather than in each caller because it is the difference
     * between a tool that compares two engines and a tool that compares
     * nothing: once a company is migrated the stored file is native, and every
     * check that reads only the stored file goes quietly to zero (§13.32).
     */
    public const KEPT = '.pre-forms';

    /**
     * Lists a definition carries that must be arrays whatever their length.
     * `fieldlist` is in both this and the boxfield pass below — it is a list of
     * things that each carry a list.
     */
    private const LISTS = ['tables', 'grouplist', 'sortlist', 'filterlist', 'fieldlist'];

    /**
     * PhreeForm XML as the converters expect it, or null.
     *
     * Null rather than false for every kind of "not this": empty, native JSON,
     * or unparseable. A caller that has to tell those apart has asked the wrong
     * question — there is no definition here either way.
     *
     * There is no separate test for a native file, and there does not need to
     * be one: `xml_to_object()` hands back a STRING for JSON rather than
     * failing, so `is_object()` catches it. That is worth knowing rather than
     * guarding against, because it is also the trap — a caller that skips the
     * test gets a string where an object is expected and dies on "must be of
     * type object, string given", from a file that is perfectly valid and just
     * not this engine's.
     */
    public static function parse(string $contents): ?object
    {
        $report = xml_to_object(trim($contents));
        if (!is_object($report)) return null;

        if (isset($report->PhreeformReport) && is_object($report->PhreeformReport)) {
            $report = $report->PhreeformReport;          // drop the container tag
        }
        foreach (self::LISTS as $list) {
            if (isset($report->$list) && is_object($report->$list)) $report->$list = [$report->$list];
        }
        if (isset($report->fieldlist) && is_array($report->fieldlist)) {
            foreach ($report->fieldlist as $key => $field) {
                if (isset($field->boxfield) && is_object($field->boxfield)) {
                    $report->fieldlist[$key]->boxfield = [$field->boxfield];
                }
            }
        }
        return $report;
    }

    /** The definition stored at this path, if it is PhreeForm XML. */
    public static function fromFile(string $path): ?object
    {
        if (!is_file($path)) return null;
        $contents = @file_get_contents($path);
        return $contents === false ? null : self::parse($contents);
    }

    /**
     * The PhreeForm original for a stored definition, migrated or not.
     *
     * Reads the stored file while it is still XML, and the kept original once
     * it is not. Null when there is nothing left to read: a company seeded
     * native never had an original, and that is not a fault.
     *
     * @param string $path the stored definition — `<dir>pf_<id>`, not the kept copy
     */
    public static function original(string $path): ?object
    {
        return self::fromFile($path) ?? self::fromFile($path . self::KEPT);
    }
}
