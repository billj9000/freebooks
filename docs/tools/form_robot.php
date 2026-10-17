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
//  Path: /docs/tools/form_robot.php
//
/**
 * THIS WRITES TO THE DATABASE. Save every form the application offers, three
 * ways, and say what the database refused.
 *
 * Reading the source can only say a value "cannot be proven non-blank". This
 * settles those candidates by making the writes actually happen. Forty-one pages carry a save action; each is rendered, its inputs and
 * csrf token harvested, and posted back:
 *
 *   as-rendered  the values the page itself supplied - a save that changes
 *                nothing, which must therefore succeed. A failure here is a
 *                fault in the page, not in the test.
 *   blanked      every field emptied but the record id, the action and the
 *                token. This is the strict-mode case: db_input() casts null,
 *                false and '' alike to '', and a server at its defaults refuses
 *                that for a date, a number or an enum.
 *   over-length  every text field filled past its maxlength attribute, or to
 *                300 characters where it declares none. A column narrower than
 *                its form truncates silently on a lax server and refuses the
 *                whole statement on a strict one.
 *
 * WHAT COUNTS AS A FAILURE. Not the HTTP code: a fatal is a truncated 200. Each
 * post is judged on new lines in sql_errors.log, errors.log and
 * empty_column_writes.log, on the body ending in </html>, and on the absence of
 * an <error> element. The log check is per request, so a line is attributed to
 * the post that caused it.
 *
 * ONLY 'save' IS EVER POSTED. Twenty-four pages also carry a delete action and
 * this never sends one.
 *
 *   docker exec <c> php /opt/repo/docs/tools/form_robot.php <company> <user> <password> [--go]
 *
 * Without --go it renders and reports what it would post, and writes nothing.
 * With --go it refuses any company whose database is not on the throwaway
 * server ($TEST_DB_HOST below), because this leaves rows behind by design.
 */

$company = $argv[1] ?? '';
$user    = $argv[2] ?? '';
$pass    = $argv[3] ?? '';
$go      = in_array('--go', $argv, true);
$verbose = in_array('--v', $argv, true);
$only    = '';
foreach ($argv as $a) if (strpos($a, '--only=') === 0) $only = substr($a, 7);
$webroot = getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html';
$base    = getenv('FREEBOOKS_URL') ?: 'http://localhost';
$TEST_DB_HOST = getenv('FREEBOOKS_TEST_DB_HOST') ?: 'fb-testdb';

if ($company === '' || $user === '' || $pass === '') {
    fwrite(STDERR, "usage: form_robot.php <company> <user> <password> [--go] [--only=x] [--v]\n");
    exit(2);
}

// The guard is the database host, not the company name: a name can be anything,
// but a company on the throwaway server is one nobody minds losing.
$cfg = $webroot . '/my_files/' . $company . '/config.php';
if (!file_exists($cfg)) { fwrite(STDERR, "no such company: $company\n"); exit(2); }
preg_match("/define\('DB_SERVER_HOST','([^']*)'/", file_get_contents($cfg), $h);
$db_host = $h[1] ?? '';
if ($go && $db_host !== $TEST_DB_HOST) {
    fwrite(STDERR, "REFUSING to write: '$company' is on '$db_host', not the throwaway server"
                 . " '$TEST_DB_HOST'.\nMake a copy with docs/tools/test_company.sh and point this at that.\n");
    exit(2);
}

$jar = tempnam(sys_get_temp_dir(), 'robot');
function http($url, $post = null) {
    global $jar, $base;
    $ch = curl_init(strpos($url, 'http') === 0 ? $url : $base . $url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 120,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return array('body' => (string)$body, 'code' => $info['http_code']);
}

// ---- log in ---------------------------------------------------------------
// Same three things the page crawl needs: the token off the login page, the
// company as its INDEX in $_SESSION['companies'], and the language as its value.
$login = http('/index.php?module=phreedom&page=main');
if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $login['body'], $m)) {
    fwrite(STDERR, "no csrf token on the login page - is the app installed?\n"); exit(1);
}
if (!preg_match('/<option value="(\d+)">[^<]*\(' . preg_quote($company, '/') . '\)/', $login['body'], $c)
    && !preg_match('/<option value="(\d+)">' . preg_quote($company, '/') . '/i', $login['body'], $c)) {
    fwrite(STDERR, "company '$company' is not in the selector\n"); exit(1);
}
preg_match('/<select[^>]*name="language".*?<option value="([a-z_]+)"/s', $login['body'], $lang);
http('/index.php?module=phreedom&page=main&action=validate', array(
    'csrf_token' => $m[1], 'admin_name' => $user, 'admin_pass' => $pass,
    'company' => $c[1], 'language' => $lang[1] ?? 'en_us'));
$check = http('/index.php?module=phreebooks&page=journal&jID=2');
if (strpos($check['body'], 'admin_pass') !== false || $check['body'] === '') {
    fwrite(STDERR, "login failed\n"); exit(1);
}
// The session token is minted once per session, so one harvest serves every post.
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $check['body'], $t);
$csrf = $t[1] ?? '';
if ($csrf === '') { fwrite(STDERR, "no session csrf token\n"); exit(1); }
printf("logged in to %s as %s%s\n\n", $company, $user, $go ? '' : '   (dry run - nothing will be posted)');

// ---- what to save ---------------------------------------------------------
// Only pages that actually handle a save. A page without one has nothing for
// this layer to exercise and would just be a second page crawl.
// Three saves are not form writes and are left to their own layer. backup dumps
// the whole database and encryption sets the session key, which would change the
// behaviour of every post after it. phreedom/admin is company management: its
// save rewrites my_files/<company>/config.php and re-runs every module installer
// against the database it names, which leaves the rest of the run measuring a
// company this tool broke. Company create is Layer 5's.
$skip = array('phreedom/backup', 'phreedom/encryption', 'phreedom/admin');

$targets = array();
foreach (glob("$webroot/modules/*/pages/*/pre_process.php") as $f) {
    if (strpos(file_get_contents($f), "case 'save':") === false) continue;
    $page   = basename(dirname($f));
    $module = basename(dirname(dirname(dirname($f))));
    if (in_array("$module/$page", $skip, true)) continue;
    $targets[] = array($module, $page, "/index.php?module=$module&page=$page", "$module/$page");
}
// The journal pages are one page behind many documents and render a different
// form for each, so each id is its own target.
foreach (array(2, 4, 6, 10, 12, 18, 20) as $jID) {
    $targets[] = array('phreebooks', 'journal', "/index.php?module=phreebooks&page=journal&jID=$jID",
                       "phreebooks/journal jID=$jID");
}
sort($targets);

// ---- harvest one page's form ----------------------------------------------
/**
 * Returns the fields a browser would submit: every named input, select and
 * textarea, with the value the page rendered. A select contributes its selected
 * option, or its first, because a browser submits one either way.
 */
function harvest($html) {
    $prev = libxml_use_internal_errors(true);
    $doc  = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $forms = array();
    foreach ($doc->getElementsByTagName('form') as $form) {
        $fields = array(); $maxlen = array();
        foreach (array('input', 'select', 'textarea') as $tag) {
            foreach ($form->getElementsByTagName($tag) as $el) {
                $name = $el->getAttribute('name');
                if ($name === '') continue;
                $type = strtolower($el->getAttribute('type'));
                // A browser submits neither an unchecked box nor a file input.
                if ($tag === 'input' && in_array($type, array('checkbox', 'radio'))
                    && !$el->hasAttribute('checked')) continue;
                if ($type === 'file') continue;
                if ($tag === 'select') {
                    $value = '';
                    foreach ($el->getElementsByTagName('option') as $opt) {
                        if ($value === '') $value = $opt->getAttribute('value');
                        if ($opt->hasAttribute('selected')) { $value = $opt->getAttribute('value'); break; }
                    }
                } elseif ($tag === 'textarea') {
                    $value = $el->textContent;
                } else {
                    $value = $el->getAttribute('value');
                }
                $fields[$name] = $value;
                if ($el->hasAttribute('maxlength')) $maxlen[$name] = (int)$el->getAttribute('maxlength');
            }
        }
        if ($fields) $forms[] = array('name' => $form->getAttribute('name'),
                                      'action' => $form->getAttribute('action'),
                                      'fields' => $fields, 'maxlen' => $maxlen);
    }
    return $forms;
}

// ---- the three variants ---------------------------------------------------
/**
 * The record id is kept in the blanked variant so the post updates the row the
 * page rendered rather than inserting a new one - blanking it would test a
 * different path and leave a row behind every run.
 */
function variant($which, $fields, $maxlen, $csrf) {
    $keep = array('csrf_token', 'action', 'id', 'module', 'page', 'jID', 'oID', 'rID', 'cID', 'sID');
    $out  = array();
    foreach ($fields as $k => $v) {
        $bare = rtrim($k, '[]');
        if ($which === 'as-rendered' || in_array($bare, $keep, true)) { $out[$k] = $v; continue; }
        if ($which === 'blanked') { $out[$k] = ''; continue; }
        // over-length: only the fields that carry text at all.
        $width  = $maxlen[$k] ?? 0;
        $out[$k] = str_repeat('X', $width > 0 ? $width + 50 : 300);
    }
    $out['csrf_token'] = $csrf;
    $out['action']     = 'save';
    return $out;
}

// ---- run ------------------------------------------------------------------
$logs = array('sql' => "$webroot/my_files/sql_errors.log",
              'err' => "$webroot/my_files/errors.log",
              'blank' => "$webroot/my_files/empty_column_writes.log");
// Byte offsets, not line counts. errors.log has no rotation and no cap, so it
// reaches hundreds of megabytes on a machine that has been testing for a while;
// file() loads the whole thing and dies on its own memory limit.
$lines = function ($f) { clearstatcache(true, $f); return file_exists($f) ? filesize($f) : 0; };
// Capped: one post can emit warnings in a loop until the request dies, and a
// delta of several hundred megabytes is not something to load in order to read
// the first few lines of it. The cap is reported rather than hidden.
define('TAIL_CAP', 2 * 1024 * 1024);
$tail  = function ($f, $from) {
    clearstatcache(true, $f);
    if (!file_exists($f) || filesize($f) <= $from) return array();
    $grew = filesize($f) - $from;
    $fh = fopen($f, 'rb'); fseek($fh, $from);
    $new = fread($fh, min($grew, TAIL_CAP)); fclose($fh);
    $out = explode("\n", rtrim($new, "\n"));
    if ($grew > TAIL_CAP) $out[] = sprintf("... and %.0f MB more written by this one request",
                                           ($grew - TAIL_CAP) / 1048576);
    return $out;
};

$no_form = array(); $findings = array(); $ok = 0; $posted = 0;
$noise = array(); $coerced = array();
foreach ($targets as $t) {
    list($module, $page, $url, $name) = $t;
    if ($only !== '' && strpos($name, $only) === false) continue;
    $res = http($url);
    $forms = harvest($res['body']);
    // A page that will not render has nothing to save; the page crawl owns that.
    if (!$forms) { $no_form[] = array($name, $res['code'] . ', ' . strlen($res['body']) . ' bytes'); continue; }
    $form = $forms[0];
    foreach ($forms as $f) if (count($f['fields']) > count($form['fields'])) $form = $f;

    if (!$go) {
        printf("  %-34s %d field(s)%s\n", $name, count($form['fields']),
               $form['maxlen'] ? ', ' . count($form['maxlen']) . ' with maxlength' : '');
        continue;
    }
    if ($verbose) { printf("  %-34s ", $name); flush(); }
    foreach (array('as-rendered', 'blanked', 'over-length') as $which) {
        $before = array_map($lines, $logs);
        $post   = variant($which, $form['fields'], $form['maxlen'], $csrf);
        $r      = http($url, $post);
        $posted++;
        $why    = array();
        // A line in sql_errors.log IS the finding: it is a statement the server
        // refused, which is what this layer exists to provoke. errors.log needs
        // reading rather than counting - the menu alone emits warnings on every
        // request - so only a fatal or an uncaught exception fails a post, and
        // the rest is tallied. A line in empty_column_writes.log is a blank the
        // coercion caught on its way into a column that cannot hold one: not a
        // failure, but the answer to one of the sweep's candidates.
        foreach ($tail($logs['sql'], $before['sql']) as $l) {
            $l = trim($l);
            if ($l !== '') $why[] = 'REFUSED ' . (strlen($l) > 160 ? substr($l, 0, 160) . '...' : $l);
        }
        foreach ($tail($logs['err'], $before['err']) as $l) {
            if (preg_match('/\b(FATAL|Exception|Uncaught)\b/i', $l)) {
                $why[] = 'fatal: ' . substr(trim(preg_replace('/\s+/', ' ', $l)), 0, 120);
            } elseif (preg_match("/'([^']{0,60})/", $l, $wm)) {
                $noise[$wm[1]] = ($noise[$wm[1]] ?? 0) + 1;
            }
        }
        foreach ($tail($logs['blank'], $before['blank']) as $l) {
            if (preg_match('/([a-z0-9_]+\.[a-z0-9_]+) given/i', $l, $bm)) {
                $coerced[$bm[1]][$which] = ($coerced[$bm[1]][$which] ?? 0) + 1;
            }
        }
        if ($r['body'] !== '' && strpos($r['body'], '</html>') === false
            && strpos($r['body'], '<?xml') === false) $why[] = 'truncated - does not end with </html>';
        if ($why) $findings[] = array($name, $which, $why); else $ok++;
        if ($verbose) { printf("%s ", $why ? strtoupper(substr($which, 0, 1)) . '!' : '.'); flush(); }
        // Re-render so the next variant works from a form the page still agrees
        // with; a save may have changed what it offers.
        if ($verbose && $which === 'over-length') { printf("\n"); flush(); }
        if ($which !== 'over-length') {
            $res2 = http($url); $f2 = harvest($res2['body']);
            if ($f2) { $form = $f2[0]; foreach ($f2 as $f) if (count($f['fields']) > count($form['fields'])) $form = $f; }
        }
    }
}

if (!$go) {
    printf("\n%d page(s) with a save form. Pass --go to post them.\n", count($targets) - count($no_form));
    if ($no_form) { printf("\n%d rendered no form:\n", count($no_form));
                    foreach ($no_form as $n) printf("  %-34s %s\n", $n[0], $n[1]); }
    exit(0);
}

printf("%d posts, %d clean, %d with something to say\n", $posted, $ok, count($findings));
if ($no_form) {
    printf("\nrendered no form, so nothing was posted (%d)\n%s\n", count($no_form), str_repeat('-', 74));
    foreach ($no_form as $n) printf("  %-34s %s\n", $n[0], $n[1]);
}
if ($coerced) {
    printf("\nblanks the coercion caught on their way into a column that cannot hold one (%d)\n", count($coerced));
    printf("each is a blank settled by a real post\n%s\n", str_repeat('-', 74));
    ksort($coerced);
    foreach ($coerced as $col => $by) {
        $parts = array();
        foreach ($by as $w => $n) $parts[] = "$w x$n";
        printf("  %-46s %s\n", $col, implode(', ', $parts));
    }
}
if ($noise) {
    arsort($noise);
    printf("\nwarnings seen along the way (%d kinds)\n%s\n", count($noise), str_repeat('-', 74));
    $i = 0;
    foreach ($noise as $what => $n) { printf("  %6d  %s\n", $n, substr($what, 0, 60)); if (++$i >= 8) break; }
    if (count($noise) > 8) printf("  ... and %d more kinds\n", count($noise) - 8);
}
if ($findings) {
    printf("\nwhat the database or the page said (%d)\n%s\n", count($findings), str_repeat('-', 74));
    foreach ($findings as $f) {
        printf("  %-34s %s\n", $f[0], $f[1]);
        foreach ($f[2] as $w) printf("      %s\n", $w);
    }
}
