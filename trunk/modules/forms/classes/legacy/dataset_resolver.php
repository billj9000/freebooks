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
//  Path: /modules/forms/classes/legacy/dataset_resolver.php
//
/**
 * Forms — which dataset serves a legacy report definition.
 *
 * A PhreeForm definition names the tables it reads (`<tables>`); a Forms report
 * names a dataset, and nothing in the definition says which. Naming the wrong
 * dataset does not fail cleanly: every column comes back UNMAPPED, the date
 * range is dropped, and the row counts differ by orders of magnitude, which
 * reads exactly like a broken converter (§12.2, trap 4). So the mapping is
 * decided here, by rule.
 *
 * The rule, in order:
 *
 *  1. A dataset is a CANDIDATE only if it can reach every table the report
 *     names. A dataset that covers three of four tables is not a near miss; it
 *     is the wrong dataset, and picking it would silently drop a column.
 *  2. Among candidates, prefer the one whose base table is the report's own
 *     first table. PhreeForm's first `<tablename>` is its FROM clause, so this
 *     is the definition telling us its grain — it is what separates the General
 *     Ledger report (from `journal_main`) from the Trial Balance (from
 *     `chart_of_accounts`) when both name overlapping tables.
 *  3. Then prefer the narrowest dataset, by table count. A wider one would
 *     serve the report correctly but offers its author joins the report never
 *     asked for.
 *
 * Ties are reported rather than broken arbitrarily: an ambiguous mapping is a
 * catalog problem to fix, not a coin to toss at runtime.
 */

namespace FreeBooks\Forms\Legacy;

use FreeBooks\Forms\Catalog\{Catalog, Dataset};

final class DatasetResolver
{
    /** @var list<string> */
    private array $notes = [];

    public function __construct(private readonly Catalog $catalog) {}

    /**
     * The dataset that serves this report, or null with a reason in notes().
     *
     * A report with a `special_class` that Forms serves by a provider has no
     * dataset at all; ask the caller to check that first — returning null for
     * both "structural" and "no idea" would conflate them.
     */
    public function resolve(object $report): ?Dataset
    {
        $this->notes = [];

        $wanted = $this->tables($report);
        if ($wanted === []) {
            $this->notes[] = 'The definition names no tables.';
            return null;
        }

        $candidates = [];
        foreach ($this->catalog->all() as $dataset) {
            $covered = $this->coverage($dataset);
            $missing = array_values(array_diff($wanted, $covered));
            if ($missing !== []) continue;
            $candidates[] = [
                'dataset' => $dataset,
                'primary' => $dataset->baseTable === $wanted[0] ? 0 : 1,
                'width'   => count($covered),
            ];
        }

        if ($candidates === []) {
            $this->notes[] = "No dataset reaches all of: " . implode(', ', $wanted) . '.';
            return null;
        }

        usort($candidates, static fn(array $a, array $b) =>
            [$a['primary'], $a['width']] <=> [$b['primary'], $b['width']]);

        $best = $candidates[0];
        if (isset($candidates[1])
            && [$candidates[1]['primary'], $candidates[1]['width']] === [$best['primary'], $best['width']]) {
            $tied = array_map(static fn(array $c) => $c['dataset']->id, $candidates);
            $this->notes[] = 'Ambiguous: ' . implode(' and ', $tied)
                           . ' serve this report equally well; the catalog should distinguish them.';
            return null;
        }

        if ($best['primary'] === 1) {
            // Served, but not from the grain the definition declared. Worth
            // saying out loud: it is the shape of mistake that still produces
            // plausible output.
            $this->notes[] = sprintf(
                "'%s' is served by dataset '%s', whose base table is %s rather than the report's %s.",
                (string)($report->title ?? '?'), $best['dataset']->id,
                $best['dataset']->baseTable, $wanted[0]);
        }
        return $best['dataset'];
    }

    /** @return list<string> anything explaining the answer, empty when it was clean */
    public function notes(): array
    {
        return $this->notes;
    }

    /**
     * Tables the definition names, in its own order, unprefixed.
     *
     * The XML parser hands back either a scalar or a single-element array per
     * property depending on how many siblings there were, which is why every
     * read here goes through the same flattening the converters use.
     *
     * @return list<string>
     */
    private function tables(object $report): array
    {
        $tables = [];
        foreach ((array)($report->tables ?? []) as $entry) {
            $name = is_object($entry) ? ($entry->tablename ?? null) : $entry;
            if (is_array($name)) $name = reset($name);
            $name = trim((string)$name);
            if ($name === '') continue;
            // A definition may carry the installation's table prefix; datasets
            // never do, because the query builder applies it.
            if (defined('DB_PREFIX') && DB_PREFIX !== '' && str_starts_with($name, DB_PREFIX)) {
                $name = substr($name, strlen(DB_PREFIX));
            }
            if (!in_array($name, $tables, true)) $tables[] = $name;
        }
        return $tables;
    }

    /**
     * Every table a dataset can reach: its base table and each join's.
     *
     * @return list<string>
     */
    private function coverage(Dataset $dataset): array
    {
        $tables = [$dataset->baseTable];
        foreach ($dataset->joins as $join) {
            if (!in_array($join->table, $tables, true)) $tables[] = $join->table;
        }
        return $tables;
    }
}
