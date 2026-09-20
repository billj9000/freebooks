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
//  Path: /docs/tools/crawl_pages.php
//
/**
 * Open every page and every ajax operation as a logged-in user, and say which
 * ones are broken.
 *
 * READS ONLY - it issues GETs. That still matters for the strict-mode work,
 * because ONLY_FULL_GROUP_BY and the zero-date comparisons are read-side faults,
 * and because a page that will not open cannot be tested by anything later.
 *
 * WHY THE STATUS CODE IS NOT THE TEST. display_errors is off (index.php:24), so
 * a fatal produces a truncated page, not a message. An uncaught Error produces
 * HTTP 200 with an empty body - which is how a missing company title looked
 * earlier this week. And an ajax operation answers a SQL failure, a CSRF
 * rejection and a lost session all with 200 and an <error> element. So:
 *
 *   a page  must end with </html>, must not carry the login form, and must not
 *           land on the crash page
 *   an ajax must not contain an <error> element
 *   both    must add nothing to sql_errors.log or to errors.log
 *
 * The log check is per request, so a line is attributed to the URL that caused
 * it rather than to the run.
 *
 *   docker exec <c> php /opt/repo/docs/tools/crawl_pages.php <company> <user> <password> [--v]
 *
 * Run it against a disposable copy (docs/tools/test_company.sh), never against a
 * company anyone cares about: a GET still lets a page write.
 */

$company  = $argv[1] ?? '';
$user     = $argv[2] ?? '';
$pass     = $argv[3] ?? '';
$verbose  = in_array('--v', $argv, true);
$webroot  = getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html';
$base     = getenv('FREEBOOKS_URL') ?: 'http://localhost';
if ($company === '' || $user === '' || $pass === '') {
    fwrite(STDERR, "usage: crawl_pages.php <company> <user> <password> [--v]\n");
    exit(2);
}

$jar = tempnam(sys_get_temp_dir(), 'crawl');
function http($url, $post = null) {
    global $jar, $base;
    $ch = curl_init(strpos($url, 'http') === 0 ? $url : $base . $url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return array('body' => (string)$body, 'code' => $info['http_code'],
                 'to' => $info['redirect_url'] ?? '');
}

// ---- log in ---------------------------------------------------------------
// Three things are needed and none is guessable: the csrf token off the login
// page, the company as its INDEX in $_SESSION['companies'] rather than its name,
// and a company whose config defines <name>_TITLE.
$login = http('/index.php?module=phreedom&page=main');
if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $login['body'], $m)) {
    fwrite(STDERR, "no csrf token on the login page - is the app installed?\n"); exit(1);
}
$token = $m[1];
if (!preg_match('/<option value="(\d+)">[^<]*\(' . preg_quote($company, '/') . '\)/', $login['body'], $c)
    && !preg_match('/<option value="(\d+)">' . preg_quote($company, '/') . '/i', $login['body'], $c)) {
    fwrite(STDERR, "company '$company' is not in the selector\n"); exit(1);
}
// language is posted as its VALUE ('en_us'), unlike company which is an index.
// Posting a number instead put "1" in the session and the backup page then tried
// to include modules/phreedom/includes/bigdump/language/1/language.php - a fatal
// this crawler caused itself and briefly reported as a defect.
preg_match('/<select[^>]*name="language".*?<option value="([a-z_]+)"/s', $login['body'], $lang);
$r = http('/index.php?module=phreedom&page=main&action=validate', array(
    'csrf_token' => $token, 'admin_name' => $user, 'admin_pass' => $pass,
    'company' => $c[1], 'language' => $lang[1] ?? 'en_us'));
$check = http('/index.php?module=phreebooks&page=journal&jID=2');
if (strpos($check['body'], 'admin_pass') !== false || $check['body'] === '') {
    fwrite(STDERR, "login failed (POST returned {$r['code']})\n"); exit(1);
}
printf("logged in to %s as %s\n\n", $company, $user);

// ---- what to visit --------------------------------------------------------
$urls = array();
foreach (glob("$webroot/modules/*/pages/*", GLOB_ONLYDIR) as $dir) {
    $page   = basename($dir);
    $module = basename(dirname(dirname($dir)));
    $urls[] = array('page', "/index.php?module=$module&page=$page", "$module/$page");
}
foreach (glob("$webroot/modules/*/ajax/*.php") as $file) {
    $op     = basename($file, '.php');
    $module = basename(dirname(dirname($file)));
    $urls[] = array('ajax', "/index.php?module=$module&page=ajax&op=$op", "$module/ajax:$op");
}
// The journal pages are one page behind many documents; jID decides which, and
// a page that works for one journal can fail for another.
foreach (array(2, 3, 4, 6, 7, 9, 10, 12, 13, 14, 16, 18, 19, 20, 21, 22) as $jID) {
    $urls[] = array('page', "/index.php?module=phreebooks&page=journal&jID=$jID", "phreebooks/journal jID=$jID");
    $urls[] = array('page', "/index.php?module=phreebooks&page=orders&jID=$jID", "phreebooks/orders jID=$jID");
}

// ---- visit ----------------------------------------------------------------
$sql_log = "$webroot/my_files/sql_errors.log";
$err_log = "$webroot/my_files/errors.log";
$lines   = function ($f) { return file_exists($f) ? count(file($f)) : 0; };

$fail = array(); $skip = array(); $blocked = array(); $pass = 0; $noise = array();
foreach ($urls as $u) {
    list($kind, $url, $name) = $u;
    $s0 = $lines($sql_log); $e0 = $lines($err_log);
    $res = http($url);
    $s1 = $lines($sql_log); $e1 = $lines($err_log);
    $why = array();

    if ($res['code'] >= 500)                                    $why[] = "HTTP {$res['code']}";
    if (strpos($res['to'], 'action=crash') !== false)            $why[] = 'redirected to the crash page';
    if (strpos($res['to'], '/install/') !== false)               $why[] = 'redirected to the installer';
    if ($s1 > $s0)                                               $why[] = ($s1 - $s0) . ' line(s) in sql_errors.log';

    // errors.log needs reading, not counting. Opening a page without the
    // parameters it expects produces "Undefined array key" warnings that this
    // crawler caused itself, and treating those as faults turns the whole run
    // red - which is how a check stops being read at all. Only a fatal or an
    // uncaught exception is a failure here; warnings and deprecations are
    // tallied and reported once at the end, where the deprecation debt is
    // actually useful (PHP 9 turns several of them into errors).
    if ($e1 > $e0) {
        foreach (array_slice(file($err_log), $e0) as $l) {
            if (preg_match('/\b(FATAL|Exception|Uncaught)\b/i', $l)) {
                $why[] = 'fatal: ' . substr(trim(preg_replace('/\s+/', ' ', $l)), 0, 70);
            } elseif (preg_match("/'([^']{0,60})/", $l, $wm)) {
                $noise[$wm[1]] = ($noise[$wm[1]] ?? 0) + 1;
            }
        }
    }

    if ($kind === 'page' && $res['code'] == 200) {
        if (trim($res['body']) === '')                           $why[] = 'empty body with HTTP 200';
        elseif (strpos($res['body'], 'admin_pass') !== false)     $why[] = 'session lost - showed the login form';
        elseif (!preg_match('~</html>\s*$~i', $res['body']))      $why[] = 'body does not end with </html> - truncated';
    }
    // An ajax operation that declines a request is not broken, and it is not
    // working either - it was never exercised. A GET carries none of the
    // parameters these expect, so "Bad record submitted" is the operation
    // behaving correctly and saying so. Counting that as a failure buries the
    // real ones; counting it as a pass claims a test that did not happen.
    $refused = '';
    if ($kind === 'ajax' && preg_match('~<error>(.*?)</error>~s', $res['body'], $em)) {
        $msg = trim(strip_tags(preg_replace('~<!\[CDATA\[|\]\]>~', '', $em[1])));
        if ($why) $why[] = 'ajax error: ' . substr($msg, 0, 60);   // broken AND complained
        // A permission refusal is a different thing again: no parameters would
        // help, because this user does not hold the security id. That is the
        // crawl account's doing, not the application's, and it means the
        // operation cannot be covered until the account is given the right.
        elseif (stripos($msg, 'permission') !== false) { $blocked[] = array($name, substr($msg, 0, 60)); $refused = '-'; }
        else      $refused = $msg;
    }

    if ($why)            $fail[] = array($name, $url, implode('; ', $why));
    // '-' marks a URL already recorded under $blocked: it must not fall through
    // to the pass count as well. The four labels operations were being counted
    // in both places, and the totals gave it away - 203 outcomes for 199 URLs.
    elseif ($refused === '-') { /* counted under $blocked */ }
    elseif ($refused)    $skip[] = array($name, substr($refused, 0, 72));
    else { $pass++; if ($verbose) printf("  ok    %s\n", $name); }
}

// ---- report ---------------------------------------------------------------
printf("\n%d of %d opened cleanly; %d declined the request, %d refused this user.\n",
       $pass, count($urls), count($skip), count($blocked));
if ($noise) {
    arsort($noise);
    printf("\nwarnings and deprecations seen along the way (%d kinds, %d lines)\n%s\n",
           count($noise), array_sum($noise), str_repeat('-', 76));
    $shown = 0;
    foreach ($noise as $what => $n) {
        printf("  %6d  %s\n", $n, $what);
        if (++$shown >= 12) { printf("  ... and %d more kinds\n", count($noise) - $shown); break; }
    }
    echo "  (not failures - but several become errors in PHP 9)\n";
}
if ($skip) {
    printf("\nnot exercised - the operation declined the request (%d)\n"
         . "a GET carries no parameters, so these need driving properly before anything is known about them\n%s\n",
           count($skip), str_repeat('-', 76));
    foreach ($skip as $k) printf("  %-34s %s\n", $k[0], $k[1]);
}
if ($blocked) {
    printf("\nnot exercised - this account lacks the permission (%d)\n"
         . "grant the crawl user these security ids, or they stay untested for ever\n%s\n",
           count($blocked), str_repeat('-', 76));
    foreach ($blocked as $b) printf("  %-34s %s\n", $b[0], $b[1]);
}
if ($fail) {
    printf("\n%d need looking at\n%s\n", count($fail), str_repeat('-', 76));
    foreach ($fail as $f) printf("  %-34s %s\n      %s\n", $f[0], $f[2], $f[1]);
}
@unlink($jar);
exit($fail ? 1 : 0);
