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
// +-----------------------------------------------------------------+
//  Path: /docs/tools/verify_forms_image_upload.php
//
/**
 * What an uploaded image is allowed to be, and what it is allowed to be called.
 *
 * The canvas can now put a file into the folder a form draws from, which is the
 * one place on this page where something a browser sent becomes something the
 * server keeps. Every claim in ImageStore's header is checked here against the
 * class rather than against a reading of it:
 *
 *   - the type is read out of the BYTES, so a `.png` that is not a PNG fails;
 *   - the stored extension comes from the type that was read, so a name ending
 *     `.php` cannot survive being uploaded — this is the check that matters,
 *     because a name is the only part of an upload a web server acts on;
 *   - paths, control characters, leading dots and `..` do not survive the name;
 *   - an existing file is never overwritten, because the folder is shared by
 *     every form in the company;
 *   - a file that was not uploaded is refused by the DEFAULT accept gate. That
 *     one is the reason the gate is substitutable at all: the rest of these
 *     tests need to supply their own, so the real one has to be pinned
 *     separately or substituting it would quietly remove it.
 *
 * Everything is done in a scratch directory of its own and cleaned up; nothing
 * touches the company's real images folder.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_image_upload.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/classes/app/image_store.php');

use FreeBooks\Forms\App\{ImageStore, ImageStoreException};

/**
 * Run the ajax op for real, at a given permission level.
 *
 * An ajax op ends in die(), so it cannot be exercised in the same process as
 * anything that comes after it — hence a child process, which this tool starts
 * by re-entering itself. It is the only way to check the gate by USING it: a
 * tool that searched the source for `SECURITY_ID_FORMS_AUTHOR` would pass on a
 * file that named the constant in a branch that never runs.
 */
if (($argv[2] ?? '') === '--endpoint') {
    $_SESSION['admin_security'] = array_fill(0, 1000, 0);
    $_SESSION['admin_security'][SECURITY_ID_FORMS_AUTHOR] = (int)($argv[3] ?? 0);
    gen_pull_language('phreeform');
    gen_pull_language('forms');
    // No $_FILES: an authorised caller should get as far as "nothing was sent",
    // which is what proves the gate opened rather than that it never closed.
    require(DIR_FS_MODULES . 'forms/ajax/canvas_image.php');
    die;   // unreachable — the op dies itself — and here in case it stops doing so
}

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

$scratch = sys_get_temp_dir() . '/forms_image_' . getmypid();
@mkdir($scratch . '/images', 0777, true);
@mkdir($scratch . '/incoming', 0777, true);

/** A real image of the given type, on disk. Real bytes, because the class reads them. */
function make_image(string $path, string $type, int $w = 60, int $h = 20): void {
    $image = imagecreatetruecolor($w, $h);
    imagefilledrectangle($image, 0, 0, $w, $h, imagecolorallocate($image, 200, 40, 40));
    match ($type) {
        'png'  => imagepng($image, $path),
        'jpeg' => imagejpeg($image, $path),
        'gif'  => imagegif($image, $path),
    };
    imagedestroy($image);
}

/**
 * An upload, as PHP would present it.
 *
 * The temporary file is COPIED for each attempt: the class moves it, and a
 * second test reusing the same path would be testing a file that is not there.
 */
function upload(string $scratch, string $source, string $name): array {
    $temporary = $scratch . '/incoming/' . basename($source) . '.' . mt_rand() . '.tmp';
    copy($source, $temporary);
    return ['name' => $name, 'tmp_name' => $temporary, 'error' => UPLOAD_ERR_OK,
            'size' => filesize($temporary)];
}

// The seam: the real one is is_uploaded_file + move_uploaded_file, neither of
// which can be true of a file a command line made. This is a plain move, and it
// is the ONLY thing these tests substitute.
$moves = static fn(string $from, string $to): bool => rename($from, $to);

$store = new ImageStore($scratch . '/images', $moves);

if (!function_exists('imagecreatetruecolor')) {
    echo "GD is not available; this tool needs it to make real images.\n";
    exit(1);
}

echo "== the type is read out of the file ==\n";

make_image($scratch . '/red.png',  'png');
make_image($scratch . '/red.jpg',  'jpeg');
make_image($scratch . '/red.gif',  'gif');
file_put_contents($scratch . '/notes.txt', "not an image at all\n");
// A PHP script wearing an image's name. This is the shape of the attack the
// extension rule exists for.
file_put_contents($scratch . '/evil.png', "<?php echo 'run'; ?>\n");

$stored = $store->store(upload($scratch, $scratch . '/red.png', 'red.png'));
ok('a PNG is stored', $stored['name'] === 'red.png', $stored['name']);
ok('its pixel size comes back', $stored['width'] === 60 && $stored['height'] === 20,
   $stored['width'] . 'x' . $stored['height']);

try {
    $store->store(upload($scratch, $scratch . '/notes.txt', 'notes.png'));
    ok('a text file named .png is refused', false, 'it was stored');
} catch (ImageStoreException $e) {
    ok('a text file named .png is refused', str_contains($e->getMessage(), 'not a PNG'),
       $e->getMessage());
}

try {
    $store->store(upload($scratch, $scratch . '/evil.png', 'evil.png'));
    ok('a PHP script named .png is refused', false, 'it was stored');
} catch (ImageStoreException $e) {
    ok('a PHP script named .png is refused', true);
}

echo "\n== the stored extension comes from the type that was read ==\n";

// The same real GIF, offered under four names a web server would treat very
// differently. Every one of them must come to rest as a .gif.
foreach (['logo.php', 'logo.phtml', 'logo.png', 'logo'] as $index => $claimed) {
    $result = $store->store(upload($scratch, $scratch . '/red.gif', $claimed));
    ok("'$claimed' is stored as a .gif",
       str_ends_with($result['name'], '.gif'), $result['name']);
    ok("'$claimed' does not keep a second extension",
       substr_count($result['name'], '.') === 1, $result['name']);
}

$jpeg = $store->store(upload($scratch, $scratch . '/red.jpg', 'photo.gif'));
ok('a JPEG offered as .gif is stored as .jpg', $jpeg['name'] === 'photo.jpg', $jpeg['name']);

echo "\n== the name cannot escape the folder ==\n";

$cases = [
    '../../../../etc/passwd.png' => 'passwd',
    '/etc/shadow'                => 'shadow',
    '..'                         => 'image',
    // pathinfo() reads a leading dot as ALL extension and no name, so this one
    // arrives with nothing left to keep — which is the right answer for it.
    '.htaccess'                  => 'image',
    "we\x00ird.png"              => 'we-ird',
];
// Matched with the collision suffix allowed: two of these reduce to the same
// stem, and the second one being numbered is the no-overwrite rule doing its
// job rather than the name rule failing at it.
foreach ($cases as $claimed => $expected) {
    $result = $store->store(upload($scratch, $scratch . '/red.png', $claimed));
    $shown  = str_replace("\x00", '\\0', $claimed);
    ok("'$shown' becomes '$expected.png'",
       (bool)preg_match('/^' . preg_quote($expected, '/') . '(-\d+)?\.png$/', $result['name']),
       $result['name']);
    ok("'$shown' lands in the images folder",
       is_file($scratch . '/images/' . $result['name']), $result['name']);
}

// Nothing anywhere else. The strongest form of the claim above: not "the name
// looks safe" but "the directory holds exactly what was put in it".
$strays = array_diff(scandir($scratch . '/images') ?: [], ['.', '..']);
ok('every stored file is a plain name in the folder',
   count(array_filter($strays, fn($n) => str_contains($n, '/') || str_starts_with($n, '.'))) === 0,
   implode(' ', $strays));

echo "\n== a very long name is cut, not refused ==\n";

$long = $store->store(upload($scratch, $scratch . '/red.png', str_repeat('a', 300) . '.png'));
ok('a 300-character name is shortened', strlen($long['name']) <= 64, (string)strlen($long['name']));
ok('and it is still a png', str_ends_with($long['name'], '.png'), $long['name']);

echo "\n== nothing is overwritten ==\n";

$first  = $store->store(upload($scratch, $scratch . '/red.png',  'shared.png'));
$before = md5_file($scratch . '/images/' . $first['name']);
make_image($scratch . '/wide.png', 'png', 200, 50);
$second = $store->store(upload($scratch, $scratch . '/wide.png', 'shared.png'));

ok('a second file of the same name is numbered',
   $second['name'] !== $first['name'], $second['name']);
ok('the first file is untouched',
   md5_file($scratch . '/images/' . $first['name']) === $before);
ok('the second file is the one just sent',
   $second['width'] === 200 && $second['height'] === 50,
   $second['width'] . 'x' . $second['height']);

echo "\n== what is refused before the bytes are read ==\n";

$tooBig = upload($scratch, $scratch . '/red.png', 'huge.png');
$tooBig['size'] = ImageStore::MAX_BYTES + 1;
try {
    $store->store($tooBig);
    ok('an oversized file is refused', false, 'it was stored');
} catch (ImageStoreException $e) {
    ok('an oversized file is refused', str_contains($e->getMessage(), 'larger than'),
       $e->getMessage());
}

// PHP reports its own limit with an EMPTY tmp_name, so an implementation that
// checked the bytes first would call a perfectly good photograph "not an image".
try {
    $store->store(['name' => 'huge.png', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE,
                   'size' => 0]);
    ok("PHP's own size limit is reported as a size", false, 'it was stored');
} catch (ImageStoreException $e) {
    ok("PHP's own size limit is reported as a size",
       str_contains($e->getMessage(), 'larger than'), $e->getMessage());
}

try {
    $store->store(['name' => 'nothing.png', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE,
                   'size' => 0]);
    ok('an empty upload is refused', false, 'it was stored');
} catch (ImageStoreException $e) {
    ok('an empty upload is refused', true);
}

echo "\n== the default gate refuses a file that was not uploaded ==\n";

// The one test that must NOT substitute the seam, because the seam is what it
// is testing. Every other test above passes its own; if that were the only way
// the class were ever exercised, removing is_uploaded_file() would break
// nothing and be noticed by nobody.
$real = new ImageStore($scratch . '/images');
try {
    $real->store(upload($scratch, $scratch . '/red.png', 'sneaky.png'));
    ok('a file that was never uploaded is refused', false, 'it was stored');
} catch (ImageStoreException $e) {
    ok('a file that was never uploaded is refused',
       str_contains($e->getMessage(), 'could not be saved'), $e->getMessage());
}
ok('and nothing of that name was written',
   !is_file($scratch . '/images/sneaky.png'));

echo "\n== the list is the same set of files the writer produced ==\n";

$listed = (new ImageStore($scratch . '/images'))->names();
$onDisk = array_values(array_diff(scandir($scratch . '/images') ?: [], ['.', '..']));
sort($onDisk, SORT_NATURAL | SORT_FLAG_CASE);
ok('every stored image is offered', $listed == $onDisk,
   count($listed) . ' listed of ' . count($onDisk) . ' on disk');
ok('the list is not empty', count($listed) > 0, (string)count($listed));

// A file that is not an image cannot be chosen, because it cannot be drawn.
file_put_contents($scratch . '/images/README.txt', "put images here\n");
$listed = (new ImageStore($scratch . '/images'))->names();
ok('a non-image in the folder is not offered', !in_array('README.txt', $listed, true));

echo "\n== the folder is made if it is not there ==\n";

$fresh = new ImageStore($scratch . '/brand/new/images', $moves);
$made  = $fresh->store(upload($scratch, $scratch . '/red.png', 'first.png'));
ok('a missing images folder is created',
   is_file($scratch . '/brand/new/images/' . $made['name']), $made['name']);

echo "\n== the endpoint the canvas posts to ==\n";

// An ajax op is never rendered by verify_forms_pages.php — it is required and
// it dies — so the constants it names are checked here instead. A message
// written with a constant that does not exist is a fatal error thrown while
// answering a refusal, which is the one moment nobody is watching.
$endpoint = DIR_FS_MODULES . 'forms/ajax/canvas_image.php';
ok('it exists', is_file($endpoint));

gen_pull_language('phreeform');
gen_pull_language('forms');
require_once(DIR_FS_MODULES . 'phreeform/defaults.php');

// Over tokens, not raw text: the file's own header explains the checks in
// prose, and prose is not a reference.
$code = '';
foreach (token_get_all((string)file_get_contents($endpoint)) as $token) {
    if (!is_array($token)) { $code .= $token; continue; }
    if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING,
                             T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)) continue;
    $code .= $token[1];
}
$missing = [];
if (preg_match_all('/\b(PF_[A-Z0-9_]+|FORMS_[A-Z0-9_]+|SECURITY_ID_[A-Z0-9_]+|DIR_FS_[A-Z0-9_]+)\b/',
                   $code, $names)) {
    foreach (array_unique($names[1]) as $constant) {
        if (!defined($constant)) $missing[] = $constant;
    }
}
ok(sprintf('every constant it names is defined (%d checked)', count(array_unique($names[1] ?? []))),
   $missing === [], implode(', ', $missing));

/** The op, run in a child process at the given level. @return array{0:int,1:string} */
function endpoint_at(string $company, int $level): array {
    $command = escapeshellarg(PHP_BINARY) . ' -d display_errors=1 '
             . escapeshellarg(__FILE__) . ' ' . escapeshellarg($company)
             . ' --endpoint ' . (int)$level . ' 2>&1';
    $output = [];
    $status = 0;
    exec($command, $output, $status);
    return [$status, implode("\n", $output)];
}

// The level, not merely a session. validate_ajax_user() with no argument returns
// 1 for anybody signed in, so an endpoint that trusted it would let any user
// with a login write files into the company's folder. Run rather than read: a
// tool that searched for the constant would pass on a file that named it in a
// branch that never executes.
[, $refused] = endpoint_at($company, 1);
ok('a user who may run reports but not author them is refused',
   str_contains($refused, FORMS_DESIGN_DENIED), substr($refused, 0, 120));
ok('and the refusal is JSON, which is what the canvas reads',
   is_array(json_decode(substr($refused, strpos($refused, '{') ?: 0), true)),
   substr($refused, 0, 120));

// The other half: at the authoring level it gets past the gate and complains
// about the thing that is actually missing. Without this the check above would
// pass just as well on an endpoint that refused everybody.
[, $allowed] = endpoint_at($company, 3);
ok('an author gets past the gate', !str_contains($allowed, FORMS_DESIGN_DENIED),
   substr($allowed, 0, 120));
ok('and is told what is missing', str_contains($allowed, FORMS_UPLOAD_NOTHING),
   substr($allowed, 0, 120));

// ── clean up ────────────────────────────────────────────────────────────────
function sweep(string $path): void {
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
        $full = $path . '/' . $entry;
        is_dir($full) ? sweep($full) : @unlink($full);
    }
    @rmdir($path);
}
sweep($scratch);

echo "\n" . ($failures === 0
    ? "All checks passed.\n"
    : "$failures check(s) FAILED.\n");
exit($failures === 0 ? 0 : 1);
