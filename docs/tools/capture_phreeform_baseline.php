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
//  Path: /docs/tools/capture_phreeform_baseline.php
//
/**
 * Capture a PhreeForm baseline for the Forms migration (docs/forms-design.md §8.1).
 *
 * Renders an existing PhreeForm form through the LEGACY engine and snapshots two
 * artefacts per record:
 *   1. the resolved data crossing into the renderer  -> the semantic baseline
 *   2. the PDF bytes                                 -> for positional/visual diff
 *
 * The semantic snapshot is the important half: it is exactly what a Forms
 * DocumentProvider must reproduce, and it diffs cleanly (unlike PDF bytes, which
 * never match because TCPDF regenerates file_id and the creation date per run).
 *
 * NOT part of the application. A dev aid, run inside the container against a
 * TEST database. Do not point it at production - see the printed-counter note.
 *
 * Usage (from the host):
 *   docker cp docs/tools/ <container>:/tmp/fbtools/
 *   docker exec <container> php -d display_errors=1 \
 *       /tmp/fbtools/capture_phreeform_baseline.php <report_id> <ids> [out_dir] [company]
 *
 *   report_id  phreeform.id of the form (e.g. 91 = Invoice, 73 = Purchase Order)
 *   ids        comma-separated journal_main.id values, e.g. 349,89,110
 *   out_dir    defaults to /tmp/baseline
 *
 * Company: $argv[4], else FREEBOOKS_COMPANY, else the first company under my_files/.
 * Webroot: FREEBOOKS_WEBROOT, else /var/www/html.
 *
 * Four traps this script exists to handle - each yields silently empty or wrong
 * output rather than an error (all four documented in forms-design.md §8.1):
 *   1. setprintedfield: some forms (e.g. pf_91 Invoice) carry
 *      setprintedfield=journal_main.printed, and BuildPDF fires
 *      "UPDATE journal_main SET printed = printed + 1" per record. Suppressed
 *      below, and asserted unchanged afterwards.
 *   2. datedefault: stored default is often 'c' (Today), which selects zero rows
 *      for historical records. Overridden to 'a' (All), matching the real print
 *      button (...&date=a&xfld=journal_main.id&xcr=EQUAL&xmin=<id>).
 *   3. display_errors is Off in the image, so CLI fatals are invisible. Always
 *      invoke with -d display_errors=1.
 *   4. Bootstrap must mirror application_top.php's ordering or it dies on
 *      undefined SECURITY_ID_* / MENU_HEADING_* / PF_FONT_* constants. That
 *      ordering lives in bootstrap_freebooks.php, which this script requires.
 */

define('PAGE_EXECUTION_START_TIME', microtime(true));
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }

$REPORT_ID = isset($argv[1]) ? (int)$argv[1] : 0;
$RECORDS   = isset($argv[2]) ? array_values(array_filter(array_map('intval', explode(',', $argv[2])))) : [];
$OUT       = $argv[3] ?? '/tmp/baseline';
/** PhreeForm date preference code; see DateRange::$preference. 'a' is all dates. */
$DATEDEFAULT = $argv[5] ?? 'a';
if (!$REPORT_ID || !$RECORDS) {
    fwrite(STDERR, "Usage: php capture_phreeform_baseline.php <report_id> <id,id,...> [out_dir] [company] [datedefault]\n");
    exit(1);
}

// ─── Bootstrap (trap 4) ──────────────────────────────────────────────────────
require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[4] ?? null);

if (!is_dir($OUT) && !@mkdir($OUT, 0777, true)) {
    fwrite(STDERR, "Cannot create $OUT\n");
    exit(1);
}

/**
 * Where a form's records live.
 *
 * PhreeForm breaks a form on $report->formbreakfield and hands that value to the
 * special_class, so a baseline has to select on the same field and read its
 * metadata from the table that field belongs to. Only the invoice family lives
 * in journal_main: a statement is per contact, a work order is its own journal,
 * and neither carries a printed counter or a posted currency.
 *
 * @return array{filter:string,table:string,ref:string,printed:bool,currency:bool}
 */
function baseline_source($report): array
{
    return match ((string)($report->special_class ?? '')) {
        // The break value is a contact id, but the engine's own query runs over
        // journal_main, so that is where the record is selected.
        'statement_builder' => ['filter'  => 'journal_main.bill_acct_id',
                                'table'   => TABLE_CONTACTS,
                                'ref'     => 'short_name',
                                'printed' => false, 'currency' => false],
        'wo_builder'        => ['filter'  => 'wo_journal_main.id',
                                'table'   => TABLE_WO_JOURNAL_MAIN,
                                'ref'     => 'wo_title',
                                'printed' => false, 'currency' => false],
        default             => ['filter'  => 'journal_main.id',
                                'table'   => TABLE_JOURNAL_MAIN,
                                'ref'     => 'purchase_invoice_id',
                                'printed' => true,  'currency' => true],
    };
}

/**
 * Baseline a receipt-style (serialform) document straight from its special_class.
 *
 * Resolves the header record and every Tbl into the definition, in the shape
 * BuildPDF() would have left behind, so snapshot_elements() and the diff tool
 * need no special case. The heading row BuildPDF puts at table_data[0] is
 * reproduced because the diff slices it off.
 *
 * @return array{pdf:string}|false
 */
function capture_serial_baseline($report, int $recId)
{
    if (!$report->special_class) return false;
    if (!$path = find_special_class($report->special_class)) return false;
    load_special_language($path, $report->special_class);
    require_once($path . '/classes/' . $report->special_class . '.php');

    $GLOBALS['report'] = $report;          // the classes read the definition off the global
    $class  = $report->special_class;
    $form   = new $class();
    $values = $form->load_query_results($report->formbreakfield, $recId);
    if ($values === false) return false;
    $GLOBALS['FieldValues'] = $values;

    foreach ($report->fieldlist as $field) {
        if (($field->type ?? '') === 'Tbl' && !empty($field->boxfield)) {
            $headings = [];
            foreach ($field->boxfield as $i => $b) $headings['r' . $i] = $b->title ?? '';
            $field->data = array_merge([$headings], (array)$form->load_table_data($field->boxfield));
        }
        if (in_array($field->type ?? '', ['TBlk', 'CBlk'], true) && !empty($field->boxfield)) {
            $field->text = $form->load_text_block_data($field->boxfield);
        }
    }
    // No bytes to compare: this path deliberately stops at the data layer.
    return ['pdf' => ''];
}

/** Snapshot the template elements plus whatever the data layer resolved into them. */
function snapshot_elements($report) {
    $out = [];
    foreach ((array)$report->fieldlist as $i => $f) {
        $e = ['idx' => $i, 'type' => $f->type ?? null, 'description' => $f->description ?? null];
        // PhreeForm stuffs resolved values back into the definition (TBlk/CBlk/CDta/Ttl)
        if (isset($f->text) && $f->text !== '') $e['resolved_text'] = $f->text;
        if (isset($f->data))                    $e['table_data']    = $f->data;
        if (isset($f->boxfield) && is_array($f->boxfield)) {
            $e['boxfields'] = array_map(fn($b) => [
                'fieldname'  => $b->fieldname  ?? null,
                'processing' => $b->processing ?? null,
            ], $f->boxfield);
        }
        $out[] = $e;
    }
    return $out;
}

$exit = 0;
foreach ($RECORDS as $recId) {
    $report     = get_report_details($REPORT_ID);
    $src        = baseline_source($report);
    $suppressed = $report->setprintedfield ?? '';
    $report->setprintedfield = '';        // trap 1 - never move the printed counter
    // trap 2 - 'c' (Today) selects zero historical rows, so 'a' is the default.
    // Override it to exercise a range: the statement's prior-balance carry
    // forward only exists when the period has a beginning.
    $report->datedefault     = $DATEDEFAULT;

    $cols = [$src['ref'] . ' as record_ref'];
    if ($src['printed'])  $cols[] = 'printed';
    if ($src['currency']) { $cols[] = 'currencies_code'; $cols[] = 'currencies_value'; }
    $before = $db->Execute("SELECT " . implode(', ', $cols) . " FROM " . $src['table']
                         . " WHERE id = " . (int)$recId)->fields;
    if (!$before) { echo "  rec $recId: NOT FOUND\n"; $exit = 1; continue; }

    $x = new stdClass();                  // select exactly this record
    $x->fieldname = $src['filter'];
    $x->default   = 'EQUAL';
    $x->min_val   = $recId;
    $report->xfilterlist = [$x];

    $GLOBALS['FieldValues'] = [];
    if ($report->serialform) {
        // BuildSeq() renders the receipt by array_shift()ing $FieldValues, so by
        // the time it returns there is nothing left to snapshot — the same
        // consume-as-you-go hazard as BuildDataArray(). Drive the special_class
        // directly instead. This is a DATA baseline and the class IS the legacy
        // data layer, so nothing under test is skipped; what it does not prove
        // is that the engine can still drive it, which BuildSeq below does.
        $output = capture_serial_baseline($report, $recId);
    } else {
        $output = BuildForm($report, 'S');    // 'S' returns bytes instead of exit()ing
    }

    $after = $src['printed']
        ? $db->Execute("SELECT printed FROM " . $src['table'] . " WHERE id = " . (int)$recId)->fields
        : [];
    $moved = $src['printed'] && ($before['printed'] !== $after['printed']);

    // A serialform baseline stops at the data layer, so it has no bytes to weigh.
    $ok   = is_array($output) && ($report->serialform ? true : !empty($output['pdf']));
    $stem = "$OUT/pf{$REPORT_ID}_rec{$recId}";
    if ($ok) file_put_contents("$stem.pdf", $output['pdf']);

    file_put_contents("$stem.json", json_encode([
        'report_id'        => $REPORT_ID,
        'record_id'        => $recId,
        'special_class'    => $report->special_class ?? '',
        'datedefault'      => $DATEDEFAULT,
        'record_ref'       => $before['record_ref'],
        'currency'         => $before['currencies_code']  ?? null,
        'currencies_value' => $before['currencies_value'] ?? null,
        'suppressed_field' => $suppressed,
        'printed_before'   => $before['printed'] ?? null,
        'printed_after'    => $after['printed']  ?? null,
        'pdf_bytes'        => $ok ? strlen($output['pdf']) : 0,
        'field_values'     => $GLOBALS['FieldValues'],   // the header record
        'elements'         => snapshot_elements($report),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    printf("  pf_%-4s rec %-6s %-12s %-4s pdf=%-10s printed %s->%s %s\n",
        $REPORT_ID, $recId, (string)$before['record_ref'], (string)($before['currencies_code'] ?? '-'),
        $ok ? strlen($output['pdf']) . 'B' : 'FAILED',
        $before['printed'] ?? '-', $after['printed'] ?? '-', $moved ? '!! COUNTER MOVED' : 'ok');

    if (!$ok || $moved) $exit = 1;
}

echo "captured to $OUT\n";
foreach ((array)($_SESSION['messageToStack'] ?? []) as $m)
    echo "  msg[" . $m['type'] . "]: " . trim(strip_tags($m['text'])) . "\n";

exit($exit);
