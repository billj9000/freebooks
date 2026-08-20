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
//  Path: /docs/tools/make_forms_proving_templates.php
//
/**
 * Build (and remove) the scratch PhreeForm definitions used to prove the Forms
 * Layout renderer's pagination and totals.
 *
 * WHY THIS EXISTS. The renderer's riskiest behaviour is what happens when a
 * form runs past one page: rows breaking, the heading repeating, per-record page
 * numbering, and a total that reads "Continued" until the last page (design doc
 * risk 1). None of it can be proven against the real library:
 *
 *   - The only multi-page record in the data (PO 1234, 211 lines) belongs to
 *     pf_73, which has no special_class. Query-driven forms need the dataset
 *     catalog, which is step 5 — so no provider can supply it yet.
 *   - No entry_builder form paginates: the largest sales invoice is 18 lines and
 *     the table box holds far more.
 *   - No entry_builder form carries a Ttl either, and cannot: PhreeForm's
 *     entry_builder::load_total_results() reads $Params['Seq'] while BuildPDF
 *     passes an objectInfo, so a Ttl on ANY special_class form is a hard fatal
 *     ("Cannot use object of type objectInfo as array"). The legacy Ttl path
 *     works only for query-driven forms.
 *
 * So the same real record is made to paginate in BOTH engines by shrinking the
 * table box on a copy of pf_91. Same data, same geometry, two renderers — which
 * is a fair comparison rather than a renderer-flattering fixture.
 *
 *   pf_9991  pf_91 with the item table 112mm -> 25mm  (18 lines -> 6 pages)
 *   pf_9992  pf_9991 plus a Ttl summing two header fields
 *
 * These live in my_files/, are NOT committed, and would otherwise appear in the
 * reports UI as junk — so remove them when finished:
 *
 *   php make_forms_proving_templates.php create [company]
 *   php make_forms_proving_templates.php remove [company]
 *
 * NOT part of the application. A dev aid, run inside the container against a
 * TEST database.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');

$ACTION = $argv[1] ?? '';
if (!in_array($ACTION, ['create', 'remove'], true)) {
    fwrite(STDERR, "Usage: php make_forms_proving_templates.php create|remove [company]\n");
    exit(1);
}

fb_bootstrap($argv[2] ?? null);

$dir    = FORMS_DIR_DEFINITIONS;
$source = $dir . 'pf_91';
$multi  = $dir . 'pf_9991';
$withTtl= $dir . 'pf_9992';

if ($ACTION === 'remove') {
    foreach ([$multi, $withTtl] as $path) {
        if (file_exists($path)) { unlink($path); echo "removed " . basename($path) . "\n"; }
        else                    { echo basename($path) . " not present\n"; }
    }
    exit(0);
}

if (!file_exists($source)) { fwrite(STDERR, "Source $source not found.\n"); exit(1); }
$definition = file_get_contents($source);

// ── pf_9991: shrink only the item table, so the same rows span several pages ──
$tableAt   = strpos($definition, '<type><![CDATA[Tbl]]></type>');
if ($tableAt === false) { fwrite(STDERR, "No Tbl element in pf_91.\n"); exit(1); }
$blockFrom = strrpos(substr($definition, 0, $tableAt), '<fieldlist>');
$blockTo   = strpos($definition, '</fieldlist>', $tableAt);
$block     = substr($definition, $blockFrom, $blockTo - $blockFrom);

$shrunk = preg_replace('/<height><!\[CDATA\[[^\]]*\]\]><\/height>/',
                       '<height><![CDATA[25]]></height>', $block, 1, $count);
if (!$count) { fwrite(STDERR, "Could not find the table height.\n"); exit(1); }

$paginating = substr($definition, 0, $blockFrom) . $shrunk . substr($definition, $blockTo);
file_put_contents($multi, $paginating);
echo "created pf_9991  (item table 25mm -> paginates)\n";

// ── pf_9992: the same, plus a Ttl ─────────────────────────────────────────────
// Two boxfields, because a Ttl sums every field it names rather than just one.
$ttl = <<<'XML'
<fieldlist>
	<description><![CDATA[Ttl - Proving Total]]></description>
	<abscissa><![CDATA[100]]></abscissa>
	<ordinate><![CDATA[275]]></ordinate>
	<width><![CDATA[60]]></width>
	<height><![CDATA[5]]></height>
	<type><![CDATA[Ttl]]></type>
	<font><![CDATA[helvetica]]></font>
	<size><![CDATA[10]]></size>
	<align><![CDATA[R]]></align>
	<color><![CDATA[0:0:0]]></color>
	<boxfield>
		<fieldname><![CDATA[invoice_subtotal]]></fieldname>
		<processing><![CDATA[posted_cur]]></processing>
	</boxfield>
	<boxfield>
		<fieldname><![CDATA[invoice_freight]]></fieldname>
	</boxfield>
</fieldlist>

XML;
file_put_contents($withTtl, rtrim($paginating) . "\n" . $ttl);
echo "created pf_9992  (as pf_9991, plus a Ttl)\n";
echo "\nRemember: php make_forms_proving_templates.php remove\n";
