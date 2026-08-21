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
//
/**
 * Forms — creating, copying, renaming and deleting a document. See §13.29.
 *
 * Every check here MUTATES the catalogue, which no other tool in this suite
 * does, so it is written to leave nothing behind: it records the highest id
 * before it starts and removes everything above that in a `finally`, which is
 * the discipline `verify_paper_seeding.php` already uses and an ad-hoc script
 * once failed to (§13.26 — a stray row sat in a company for a day).
 *
 * It works on the REAL catalogue rather than a scratch copy on purpose. The
 * thing being tested is whether a row and a file stay in step, and a scratch
 * directory with no catalogue table behind it cannot answer that.
 *
 * Run:
 *       docker exec <c> php /opt/repo/docs/tools/verify_forms_documents.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
gen_pull_language('phreeform');
require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{DocumentStore, DocumentStoreException};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{Definition, DefinitionLoader};

$failures = 0;
function ok(string $what, bool $passed, string $detail = ''): void
{
    global $failures;
    if (!$passed) $failures++;
    printf("  [%s] %s%s\n", $passed ? 'ok' : 'FAIL', $what,
        $passed || $detail === '' ? '' : " — $detail");
}
/** @param callable():mixed $run  @return array{0:bool,1:string} refused, and why */
function refused(callable $run): array
{
    try { $run(); return [false, '']; }
    catch (DocumentStoreException $e) { return [true, $e->getMessage()]; }
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);
$loader = new DefinitionLoader($catalog);
$store  = new DocumentStore($db, $catalog, $loader, TABLE_PHREEFORM);

// Everything above this id is ours, and goes at the end whatever happens.
$high = (int)($db->Execute("select max(id) as id from " . TABLE_PHREEFORM)->fields['id'] ?? 0);
printf("  catalogue high-water mark: %d\n", $high);

// A title nothing in a real company would collide with, and recognisably ours
// if a run is ever killed between the write and the cleanup.
$TAG = 'zzz-verify-documents';

try {

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== a new document is a row and a file that agree ===\n";

$datasetId = null;
foreach ($catalog->all() as $dataset) { $datasetId = $dataset->id; break; }
if ($datasetId === null) {
    ok('a dataset exists to build a document on', false, 'the catalog registered none');
} else {
    $groups = $store->groups(false);
    $group  = array_key_first($groups) ?? 'misc';

    $reportId = $store->create(Definition::REPORT, "$TAG report", $group, $datasetId);
    ok('a new report is catalogued', $reportId > $high, "id $reportId");
    ok('and its definition is on disk', is_file($loader->path($reportId)));

    $made = $loader->load($reportId);
    ok('the definition reads back as a report', $made->isReport(), 'it is not a report');
    ok('and knows the dataset it was made on', $made->datasetId === $datasetId, (string)$made->datasetId);
    ok('and carries the title it was given', $made->title === "$TAG report", $made->title);
    ok('and the row carries the same title',
        (string)$db->Execute("select doc_title from " . TABLE_PHREEFORM
            . " where id = $reportId")->fields['doc_title'] === "$TAG report");
    ok('a new document is filed as custom, not as shipped',
        (string)$db->Execute("select doc_type from " . TABLE_PHREEFORM
            . " where id = $reportId")->fields['doc_type'] === 'c');

    $formGroups = $store->groups(true);
    $formGroup  = array_key_first($formGroups) ?? 'misc:misc';
    $formId = $store->create(Definition::FORM, "$TAG form", $formGroup, $datasetId);
    ok('a new form is catalogued as a form',
        (string)$db->Execute("select doc_ext from " . TABLE_PHREEFORM
            . " where id = $formId")->fields['doc_ext'] === 'frm');
    ok('and reads back as a form with an empty template',
        $loader->load($formId)->isForm() && $loader->load($formId)->template !== null
        && $loader->load($formId)->template->elements === []);

    // ─────────────────────────────────────────────────────────────────────────
    echo "\n=== a copy is the same document under another name ===\n";

    $copyId = $store->copy($reportId, "$TAG copy");
    ok('the copy is a new document', $copyId !== $reportId, "both are $copyId");
    ok('and does not disturb the original', is_file($loader->path($reportId)));

    $copy = $loader->load($copyId);
    ok('the copy carries the new title', $copy->title === "$TAG copy", $copy->title);
    ok('and everything else is the original',
        $copy->datasetId === $made->datasetId && $copy->type === $made->type
        && $copy->group === $made->group && count($copy->columns) === count($made->columns));

    // A copy of something SHIPPED is the case that matters: it is how an author
    // starts from the invoice that is nearly right, and the original must be
    // left in whatever format it was in so PhreeForm can still print it.
    $stock = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM . "
        where doc_type <> '0' and doc_ext = 'frm' order by id limit 1");
    if (!$stock->EOF) {
        $stockId  = (int)$stock->fields['id'];
        $before   = (string)@file_get_contents($loader->path($stockId));
        $stockCopy = $store->copy($stockId, "$TAG stock copy");
        ok('a shipped document can be copied', $stockCopy > $high, "id $stockCopy");
        ok('and the original is byte-for-byte untouched',
            (string)@file_get_contents($loader->path($stockId)) === $before,
            'copying rewrote the document it copied');
        ok('the copy is written in the native format',
            DefinitionLoader::isNative((string)@file_get_contents($loader->path($stockCopy))));
    }

    // ─────────────────────────────────────────────────────────────────────────
    echo "\n=== a rename moves the name in both places ===\n";

    $store->rename($copyId, "$TAG renamed");
    ok('the definition carries the new name', $loader->load($copyId)->title === "$TAG renamed");
    ok('and so does the catalogue row',
        (string)$db->Execute("select doc_title from " . TABLE_PHREEFORM
            . " where id = $copyId")->fields['doc_title'] === "$TAG renamed",
        'the list and the printed document would disagree');

    // ─────────────────────────────────────────────────────────────────────────
    echo "\n=== what is refused ===\n";

    [$was, $why] = refused(fn() => $store->create(Definition::REPORT, "$TAG renamed", $group, $datasetId));
    ok('a duplicate title is refused', $was, 'two documents of one name resolve by collation');
    [$was, $why] = refused(fn() => $store->create(Definition::REPORT, '   ', $group, $datasetId));
    ok('an empty name is refused', $was);
    [$was, $why] = refused(fn() => $store->create(Definition::REPORT, str_repeat('x', 65), $group, $datasetId));
    ok('a name longer than the column is refused', $was, 'the database would cut it silently');
    [$was, $why] = refused(fn() => $store->create(Definition::REPORT, "$TAG nodataset", $group, null));
    ok('a document with no dataset is refused', $was, 'it would list as unrunnable from birth');
    [$was, $why] = refused(fn() => $store->create(Definition::REPORT, "$TAG badset", $group, 'no-such-dataset'));
    ok('an unknown dataset is refused', $was);
    [$was, $why] = refused(fn() => $store->copy(999999, "$TAG ghost"));
    ok('copying a document that does not exist is refused', $was);
    [$was, $why] = refused(fn() => $store->delete(999999));
    ok('deleting a document that does not exist is refused', $was);

    $folder = $db->Execute("select id from " . TABLE_PHREEFORM . " where doc_type = '0' limit 1");
    if (!$folder->EOF) {
        [$was, $why] = refused(fn() => $store->delete((int)$folder->fields['id']));
        ok('deleting a folder is refused', $was, 'it would orphan everything filed under it');
    }

    // The guard that matters most, because what it prevents is SILENT: a
    // setting left pointing at nothing stops emailing invoices without failing.
    $named = null;
    // The store's OWN pattern, not a copy of it: a check that restates the rule
    // it is checking passes whenever the two drift apart together.
    $cfg = $db->Execute("select configuration_key, configuration_value from " . TABLE_CONFIGURATION . "
        where configuration_value regexp '^[0-9]+$'
          and configuration_key regexp '" . DocumentStore::SETTING_NAMES . "'");
    while (!$cfg->EOF) {
        $id  = (int)$cfg->fields['configuration_value'];
        $doc = $id > 0 ? $db->Execute("select doc_type from " . TABLE_PHREEFORM . " where id = $id") : null;
        // A document, not a folder — a folder is refused for its own reason and
        // would prove nothing about this guard.
        if ($doc && !$doc->EOF && (string)$doc->fields['doc_type'] !== '0') {
            $named = [$id, (string)$cfg->fields['configuration_key']];
            break;
        }
        $cfg->MoveNext();
    }
    if ($named === null) {
        echo "  [--] no setting on this company names a document; that guard is untested here\n";
    } else {
        [$id, $key] = $named;
        ok("the setting $key is seen to name document $id", $store->settingNaming($id) === $key);
        [$was, $why] = refused(fn() => $store->delete($id));
        ok('deleting a document a setting names is refused', $was, $why);
        ok('and the refusal names the setting', str_contains($why, $key), $why);
        ok('and the document is still there', is_file($loader->path($id)));
    }

    // ─────────────────────────────────────────────────────────────────────────
    echo "\n=== a delete takes the row, the file and the kept original ===\n";

    // The `.pre-forms` copy is what makes a first save reversible. It must go
    // WITH its document — left behind, the next document given that id inherits
    // it as its own history.
    @file_put_contents($loader->path($copyId) . '.pre-forms', '<?xml version="1.0"?><PhreeformReport/>');
    ok('a kept original is there to be removed', is_file($loader->path($copyId) . '.pre-forms'));

    $store->delete($copyId);
    ok('the row is gone',
        $db->Execute("select id from " . TABLE_PHREEFORM . " where id = $copyId")->EOF);
    ok('the definition is gone', !is_file($loader->path($copyId)));
    ok('and so is the kept original', !is_file($loader->path($copyId) . '.pre-forms'),
        'it would become the next document\'s history');
}

    // ─────────────────────────────────────────────────────────────────────────
    echo "\n=== the pages offer the actions, gated on the author key ===\n";

    // Not a substitute for clicking, which no CLI harness can do — but the
    // handler both lists call is shared, and a page that never calls it offers
    // buttons that do nothing.
    $canvas  = (string)@file_get_contents(DIR_FS_MODULES . 'forms/pages/canvas/pre_process.php');
    $mainPre = (string)@file_get_contents(DIR_FS_MODULES . 'forms/pages/main/pre_process.php');
    ok('the form list acts on New, Copy and Delete',
        str_contains($canvas, 'forms_document_action('));
    ok('and so does the report list',
        str_contains($mainPre, 'forms_document_action('));

    $canvasTpl = (string)@file_get_contents(DIR_FS_MODULES . 'forms/pages/canvas/template_main.php');
    $mainTpl   = (string)@file_get_contents(DIR_FS_MODULES . 'forms/pages/main/template_main.php');
    foreach (['forms/pages/canvas' => $canvasTpl, 'forms/pages/main' => $mainTpl] as $where => $tpl) {
        ok("$where posts the destructive actions rather than linking them",
            str_contains($tpl, "name=\"action\"    id=\"forms_doc_action_kind\""),
            'a delete in a URL is one the browser offers to repeat');
    }

    // The FreeBooks convention for making a thing is a New icon on the
    // toolbar, not a panel under the list, and the three answers a document
    // needs are collected before an editor opens rather than guessed at after.
    foreach (['forms/pages/canvas' => $canvasTpl, 'forms/pages/main' => $mainTpl] as $where => $tpl) {
        ok("$where offers New on the toolbar",
            str_contains($tpl, "add_icon('new'"),
            'a New panel under the list is not the convention');
        ok("$where asks for the name, the folder and the dataset first",
            str_contains($tpl, 'name="doc_title"') && str_contains($tpl, 'name="doc_group"')
            && str_contains($tpl, 'name="dataset"'),
            'an editor that opens first has to guess at them');
        ok("$where collects them in a Bootstrap modal",
            str_contains($tpl, 'id="forms_new_modal"') && str_contains($tpl, 'modal fade'));
    }

    // EVERY trigger in a page must be reachable by that page's own script.
    // This is the check the suite did not have, and two separate faults hid
    // behind its absence — both of which rendered perfectly and did nothing:
    // the canvas's script is a `type="module"`, where a function declaration is
    // not global, so an inline handler found nothing; and this file returns
    // early when no form is open, which is the ONLY state the buttons appear
    // in, so the handler was never emitted on the one page that needed it.
    foreach (['canvas', 'main'] as $listPage) {
        $tpl = (string)@file_get_contents(DIR_FS_MODULES . "forms/pages/$listPage/template_main.php");
        $js  = (string)@file_get_contents(DIR_FS_MODULES . "forms/pages/$listPage/js_include.php");

        preg_match_all('/data-fb-doc="([a-z]+)"/', $tpl, $triggers);
        $wanted = array_values(array_unique($triggers[1]));
        ok("forms/pages/$listPage triggers the actions by data attribute",
            $wanted !== [], 'no data-fb-doc trigger in the page');
        ok("forms/pages/$listPage emits the handler for them",
            str_contains($js, 'forms_document_actions_script()'),
            'the buttons render and nothing listens');

        // Before any early return, or the chooser gets no handler at all.
        //
        // The script blocks are stripped FIRST, because these files are mostly
        // JavaScript and a `return;` inside a function there is not an early
        // return from the file. Matching one was this check's own first bug.
        $phpOnly  = preg_replace('#<script\b[^>]*>.*?</script>#s', '', $js);
        $callAt   = strpos($phpOnly, 'forms_document_actions_script()');
        $returnAt = strpos($phpOnly, 'return;');
        ok("forms/pages/$listPage emits it before any early return",
            $callAt !== false && ($returnAt === false || $callAt < $returnAt),
            'the page returns before the handler is written');

        ok("forms/pages/$listPage has no inline handler naming a function",
            !preg_match('/onclick="formsDoc/', $tpl),
            'a module-scoped function can never be reached from one');
    }

    // A form's name is editable, which is what makes rename reachable for a
    // form at all — it used to be posted back as a hidden value.
    ok('a form can be renamed from the canvas',
        (bool)preg_match('/<input type="text" name="title"/', $canvasTpl),
        'the title is still a hidden field');

    // Every editor's title goes through the store, so none of them can write a
    // name another document already has.
    foreach (['canvas', 'design', 'receipt'] as $editor) {
        $src = (string)@file_get_contents(DIR_FS_MODULES . "forms/pages/$editor/pre_process.php");
        ok("the $editor editor renames through the store",
            str_contains($src, '$forms_store->updateTitle('),
            'it writes doc_title directly, so it can create a duplicate name');
    }

} finally {
    // Everything this run made, whatever happened above. Bracketed by id rather
    // than by title: a half-written document may carry no title at all.
    $made = [];
    $rows = $db->Execute("select id from " . TABLE_PHREEFORM . " where id > $high");
    while ($rows && !$rows->EOF) { $made[] = (int)$rows->fields['id']; $rows->MoveNext(); }
    foreach ($made as $id) {
        $db->Execute("delete from " . TABLE_PHREEFORM . " where id = " . $id);
        if (is_file($loader->path($id)))                @unlink($loader->path($id));
        if (is_file($loader->path($id) . '.pre-forms')) @unlink($loader->path($id) . '.pre-forms');
    }
    printf("\n  cleaned up %d document(s) this run created\n", count($made));

    $left = $db->Execute("select count(*) as n from " . TABLE_PHREEFORM . " where id > $high");
    ok('the catalogue is as it was found', (int)$left->fields['n'] === 0,
        $left->fields['n'] . ' row(s) left behind');
}

echo "\n" . ($failures === 0 ? "all checks passed\n" : "$failures check(s) FAILED\n");
exit($failures === 0 ? 0 : 1);
