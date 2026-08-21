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
//  Path: /modules/forms/classes/app/after_render.php
//
/**
 * Runs a document's after-render hooks, once it has actually been delivered.
 *
 * A Document declares its side effects as data (AfterRenderHook) and performs
 * none of them. This is the only thing in Forms that performs one, and it is
 * called by the four places that put a document in front of somebody: the print
 * page's view, download and email, and forms_render_to_string() for the callers
 * with no page — the emailed PayPal invoice and the point-of-sale receipt.
 *
 * ## Why not in the renderer, where PhreeForm had it
 *
 * PhreeForm incremented journal_main.printed from inside BuildPDF(), so
 * anything that built a form marked its records printed: a preview, a diff
 * harness, a tool measuring output. Ours cannot, because a renderer is handed
 * hooks it has no way to run. What that buys is concrete — the canvas preview,
 * the receipt preview and the field-discovery probe in forms_binding_names()
 * all build real documents from real records, and none of them may touch the
 * counter.
 *
 * ## What a failure means
 *
 * The document is already delivered. A counter that could not be incremented is
 * therefore reported, never thrown: refusing after the fact would tell the user
 * their invoice failed to print when it did not. The count of hooks that ran is
 * returned so a caller — or a verification tool — can tell "nothing to do" from
 * "tried and failed".
 */

namespace FreeBooks\Forms\App;

use FreeBooks\Forms\Document\Document;

final class AfterRenderRunner
{
    /** @var list<string> */
    private array $failures = [];

    public function __construct(private readonly \queryFactory $db) {}

    /**
     * Perform every hook the document declares. Returns how many were done.
     */
    public function run(Document $document): int
    {
        $done = 0;
        foreach ($document->afterRender() as $hook) {
            $ok = match ($hook->type) {
                'increment_field' => $this->incrementField($hook->params),
                default           => $this->unknown($hook->type),
            };
            if ($ok) $done++;
        }
        return $done;
    }

    /** @return list<string> reasons, for a caller that wants to say so */
    public function failures(): array { return $this->failures; }

    /**
     * `update <table> set <field> = <field> + 1 where <key> = <id>`.
     *
     * Identifiers are checked here as well as in Definition::printedTarget(),
     * because this class is reachable from any hook-bearing document and a
     * check that is only made upstream is one an added caller can walk past.
     * The id is bound; the identifiers cannot be, which is why they are
     * matched against a pattern rather than escaped.
     *
     * @param array<string,mixed> $params
     */
    private function incrementField(array $params): bool
    {
        $table = (string)($params['table'] ?? '');
        $field = (string)($params['field'] ?? '');
        $key   = (string)($params['key']   ?? 'id');
        $id    = $params['id'] ?? null;

        foreach (['table' => $table, 'field' => $field, 'key' => $key] as $what => $name) {
            if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
                $this->failures[] = "The printed counter names '$name' as its $what,"
                    . ' which is not a table or column name.';
                return false;
            }
        }
        if ($id === null || $id === '') {
            $this->failures[] = 'A printed counter was asked for with no record to count.';
            return false;
        }

        $prefixed = (defined('DB_PREFIX') ? DB_PREFIX : '') . $table;

        // ExecutePrepared() throws rather than returning false — an unknown
        // column here would otherwise become an uncaught PDOException on a
        // response whose PDF bytes have already been sent.
        try {
            $result = $this->db->ExecutePrepared(
                "update `$prefixed` set `$field` = `$field` + 1 where `$key` = ?",
                [(string)$id]);
        } catch (\Throwable $e) {
            $this->failures[] = "The printed counter on $table.$field could not be updated"
                . " for record $id: " . $e->getMessage();
            return false;
        }

        // No matching row is a real miss, not a no-op: it means the id the
        // document was built from is not one this table holds, so the counter
        // would never move however often the form was printed.
        if (($result->_row_count ?? 0) < 1) {
            $this->failures[] = "No row of $table has $key = $id, so its"
                . " $field counter was not incremented.";
            return false;
        }
        return true;
    }

    private function unknown(string $type): bool
    {
        $this->failures[] = "No after-render hook of type '$type' is known, so it was not run.";
        return false;
    }
}
