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
//  Path: /modules/forms/classes/definition/loader.php
//
/**
 * Forms — loading a stored definition, whichever format it is in. See §13.3.
 *
 * The format-aware loader, and it is deliberately the ONLY place that knows a
 * definition can be in two formats. Everything above it receives a Definition
 * and cannot tell which file it came from, which is what makes step 6 a
 * deletion rather than a refactor: when the migration has converted the tree,
 * the legacy branch here goes and nothing else changes.
 *
 * Detection is by CONTENT, not by filename or by a database column. The
 * alternatives were both considered and both are worse:
 *
 *   - a filename suffix means the migration has to rename 57 files atomically
 *     while the catalog's `pf_<id>` convention is wired into ImportReport(),
 *     the designer, the backup and the restore;
 *   - a column on the catalog table means a row and a file that can disagree,
 *     and the disagreement is silent — a row saying "native" over legacy XML
 *     produces a parse error at print time rather than at migration time.
 *
 * The content answers it unambiguously: a native definition is a JSON object
 * and a PhreeForm one is XML, so the first non-whitespace byte decides.
 */

namespace FreeBooks\Forms\Definition;

use FreeBooks\Forms\Catalog\Catalog;
use FreeBooks\Forms\Legacy\LegacyReader;

final class DefinitionLoader
{
    /** @var list<string> */
    private array $warnings = [];

    private bool $wasLegacy = false;

    public function __construct(
        private readonly Catalog $catalog,
        /**
         * Where definitions live. Defaults to FORMS_DIR_DEFINITIONS, the
         * constant forms/defaults.php sets — passed explicitly so a tool can
         * point at a scratch copy without redefining a constant it cannot
         * undefine.
         */
        private readonly ?string $directory = null,
    ) {}

    /** A native definition is a JSON object; a PhreeForm one is XML. */
    public static function isNative(string $contents): bool
    {
        return str_starts_with(ltrim($contents), '{');
    }

    public function path(string|int $id): string
    {
        $directory = $this->directory ?? (defined('FORMS_DIR_DEFINITIONS') ? FORMS_DIR_DEFINITIONS : '');
        return $directory . 'pf_' . $id;
    }

    /**
     * The definition stored under this catalog id.
     *
     * @throws DefinitionException when the file is missing, unreadable, or in
     *   neither format — never a false, because a caller that treats "missing"
     *   as "empty" prints a blank document rather than an error.
     */
    public function load(string|int $id): Definition
    {
        $path = $this->path($id);
        if (!is_file($path)) {
            throw new DefinitionException("No definition is stored as 'pf_$id'.");
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new DefinitionException("The definition 'pf_$id' could not be read.");
        }
        return $this->loadContents($contents, (string)$id);
    }

    /** @throws DefinitionException */
    public function loadContents(string $contents, string $id = ''): Definition
    {
        $this->warnings  = [];
        $this->wasLegacy = !self::isNative($contents);

        if (!$this->wasLegacy) {
            return (new DefinitionCodec())->decode($contents, $id);
        }

        // Through Forms' own reader, which normalises the shapes a single-child
        // tag collapses to — one <tables> arrives as an object where two arrive
        // as an array — because every converter downstream assumes that has
        // been done. It is a class rather than these twelve lines because the
        // same twelve were also in get_report_details(), in the tool bootstrap
        // and in one verifier, and because reading a PhreeForm definition
        // outlives this branch: decision L keeps it as Import.
        $report = LegacyReader::parse($contents);
        if ($report === null) {
            throw new DefinitionException("The definition 'pf_$id' is neither native nor PhreeForm XML.");
        }

        $builder    = new DefinitionBuilder($this->catalog);
        $definition = $builder->fromLegacy($report, $id);
        $this->warnings = $builder->warnings();
        return $definition;
    }

    /**
     * Write a definition in the native format.
     *
     * Writes through a temporary file in the same directory and renames, so a
     * failed or interrupted write cannot leave a half-written definition where
     * a whole one was. rename() within a directory is atomic on every
     * filesystem FreeBooks runs on, which matters most during the one bulk
     * conversion this format exists for.
     */
    public function save(Definition $definition, string|int $id): void
    {
        $path      = $this->path($id);
        $temporary = $path . '.tmp' . getmypid();

        if (file_put_contents($temporary, (new DefinitionCodec())->encode($definition)) === false) {
            throw new DefinitionException("The definition 'pf_$id' could not be written.");
        }
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new DefinitionException("The definition 'pf_$id' could not be replaced.");
        }
    }

    /** True when the last load read PhreeForm XML and converted it on the way past. */
    public function wasLegacy(): bool { return $this->wasLegacy; }

    /**
     * Anything the last load could not convert faithfully. Always empty for a
     * native definition: there was nothing to convert.
     *
     * @return list<string>
     */
    public function warnings(): array { return $this->warnings; }
}
