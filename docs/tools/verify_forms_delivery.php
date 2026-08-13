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
//  Path: /docs/tools/verify_forms_delivery.php
//
/**
 * Verify the Forms delivery layer. See docs/forms-design.md §2.
 *
 * Delivery is the layer with the least interesting logic and the most dangerous
 * failure modes, because it is where a document meets a response header and a
 * filesystem path. The assertions here are almost all about hostile or careless
 * input reaching one of those two places:
 *
 *  - a report title with a newline in it must not inject a response header
 *    (PhreeForm concatenated the title straight into Content-Disposition);
 *  - a filename of "../../etc/passwd" must not escape the target directory;
 *  - writing an export must not silently replace an earlier one;
 *  - a staged email attachment must be removed even when the mailer throws;
 *  - and delivery after output has begun must be refused, not merely warned
 *    about, because the bytes would be appended to a half-rendered page.
 *
 * Every deliverer takes its sinks as arguments, so all of this runs with no HTTP
 * request, no mail server and no database — one require, on any PHP >= 8.2:
 *
 *     php docs/tools/verify_forms_delivery.php
 *
 * Exits non-zero if any assertion fails.
 */

$root    = getenv('FORMS_ROOT') ?: __DIR__ . '/../../trunk/modules/forms/classes';
$seam    = $root . '/document/load.php';
$deliver = $root . '/deliver/load.php';
foreach ([$seam, $deliver] as $file) {
    if (!file_exists($file)) {
        fwrite(STDERR, "Not found: $file - set FORMS_ROOT to override.\n");
        exit(1);
    }
}
require_once($seam);
require_once($deliver);

use FreeBooks\Forms\Deliver\{Delivery, DeliveryResult, EmailDelivery, FileDelivery, Filename, HttpDelivery};
use FreeBooks\Forms\Document\RenderedOutput;

$pass = 0; $fail = 0;
function ok(string $what, bool $cond, string $detail = '') {
    global $pass, $fail; $cond ? $pass++ : $fail++;
    printf("  [%s] %s%s\n", $cond ? 'ok' : 'FAIL', $what,
        $cond || $detail === '' ? '' : " — $detail");
}

/** Collects headers and body instead of sending them. */
final class Sink
{
    public array  $headers = [];
    public string $body    = '';
    public function header(string $h): void { $this->headers[] = $h; }
    public function write(string $b): void  { $this->body .= $b; }
    public function find(string $name): ?string {
        foreach ($this->headers as $h) {
            if (stripos($h, $name . ':') === 0) return $h;
        }
        return null;
    }
}

$pdf = new RenderedOutput("%PDF-1.4\nbody bytes\n", 'application/pdf', 'Balance Sheet.pdf');

echo "\n=== filename hygiene ===\n";
ok('a plain name survives', Filename::sanitise('Invoice-1234.pdf') === 'Invoice-1234.pdf');
ok('a path is reduced to its last component',
   Filename::sanitise('../../etc/passwd') === 'passwd',
   Filename::sanitise('../../etc/passwd'));
ok('a Windows path is reduced too',
   !str_contains(Filename::sanitise('..\\..\\windows\\system32\\config'), '\\'));
ok('a newline cannot survive into a name',
   !preg_match('/[\r\n]/', Filename::sanitise("Report\r\nSet-Cookie: x=1")));
ok('an empty name falls back rather than producing ""', Filename::sanitise('') === 'document');
ok('a name of dots falls back', Filename::sanitise('..') === 'document');
ok('a very long name keeps its extension',
   str_ends_with(Filename::sanitise(str_repeat('a', 400) . '.pdf'), '.pdf')
   && strlen(Filename::sanitise(str_repeat('a', 400) . '.pdf')) <= 120);
ok('a header value cannot carry CR or LF',
   Filename::headerValue("application/pdf\r\nX-Evil: 1") === 'application/pdfX-Evil: 1');

echo "\n=== Content-Disposition ===\n";
// The extended form is added exactly when sanitising changed something, so a
// name that survives intact does not carry a redundant second copy of itself.
$plain = Filename::contentDisposition('attachment', 'Invoice-1234.pdf');
ok('a name needing no change carries only the quoted form',
   $plain === 'Content-Disposition: attachment; filename="Invoice-1234.pdf"', $plain);

$cd = Filename::contentDisposition('attachment', 'Balance Sheet.pdf');
ok('quotes the ASCII filename', str_contains($cd, 'filename="Balance_Sheet.pdf"'), $cd);
ok('...and keeps the space the ASCII form had to drop',
   str_contains($cd, "filename*=UTF-8''Balance%20Sheet.pdf"), $cd);

$accented = Filename::contentDisposition('attachment', 'Fatura Núñez.pdf');
ok('an accented name still yields a safe ASCII filename',
   str_contains($accented, 'filename="Fatura_N_ez.pdf"'), $accented);
ok('...and preserves the original in filename* so it is not lost',
   str_contains($accented, "filename*=UTF-8''") && str_contains($accented, '%C3%BA'), $accented);

// The specific defect being guarded: PhreeForm built this header by
// concatenation from a user-editable report title.
$injected = Filename::contentDisposition('attachment', "x.pdf\r\nSet-Cookie: admin=1");
ok('a title carrying CRLF cannot inject a second header',
   !preg_match('/[\r\n]/', $injected), var_export($injected, true));

echo "\n=== HTTP delivery ===\n";
$sink   = new Sink();
$result = (new HttpDelivery(HttpDelivery::ATTACHMENT,
    $sink->header(...), $sink->write(...), static fn() => false))->deliver($pdf);
ok('reports success', $result->ok, $result->detail);
ok('body is the bytes, unchanged', $sink->body === $pdf->bytes);
ok('declares the mime type', $sink->find('Content-Type') === 'Content-Type: application/pdf');
ok('declares the true length',
   $sink->find('Content-Length') === 'Content-Length: ' . strlen($pdf->bytes));
ok('sends it as an attachment', str_contains((string)$sink->find('Content-Disposition'), 'attachment;'));
ok('forbids a cached copy of a report', str_contains((string)$sink->find('Cache-Control'), 'must-revalidate'));
ok('no header contains a bare CR or LF',
   !preg_match('/[\r\n]/', implode('', $sink->headers)));

$inline = new Sink();
(new HttpDelivery(HttpDelivery::INLINE, $inline->header(...), $inline->write(...),
    static fn() => false))->deliver($pdf);
ok('inline disposition is available for viewing',
   str_contains((string)$inline->find('Content-Disposition'), 'inline;'));

// The failure that made a shipped form silently unprintable: something echoed
// before the document was sent.
$late   = new Sink();
$result = (new HttpDelivery(HttpDelivery::ATTACHMENT, $late->header(...), $late->write(...),
    static fn() => true))->deliver($pdf);
ok('refuses to deliver once output has started', !$result->ok, $result->detail);
ok('...and writes nothing at all rather than a corrupt file',
   $late->body === '' && $late->headers === []);

echo "\n=== file delivery ===\n";
$dir = sys_get_temp_dir() . '/forms-delivery-' . bin2hex(random_bytes(4));
mkdir($dir);

$result = (new FileDelivery($dir))->deliver($pdf);
ok('writes the file', $result->ok, $result->detail);
ok('reports where it landed', $result->path !== null && file_exists((string)$result->path));
ok('the bytes on disk are the bytes rendered',
   file_get_contents((string)$result->path) === $pdf->bytes);
ok('the name is sanitised on disk', basename((string)$result->path) === 'Balance_Sheet.pdf');

$second = (new FileDelivery($dir))->deliver($pdf);
ok('a second export does not silently replace the first',
   $second->ok && $second->path !== $result->path, (string)$second->path);
ok('...and is named predictably', basename((string)$second->path) === 'Balance_Sheet-1.pdf',
   basename((string)$second->path));

$overwritten = (new FileDelivery($dir, overwrite: true))->deliver($pdf);
ok('overwrite: true reuses the name when that is what the caller asked for',
   $overwritten->path === $result->path, (string)$overwritten->path);

// The traversal attempt, end to end rather than only through the sanitiser.
$escape = (new FileDelivery($dir))->deliver(
    new RenderedOutput('x', 'text/csv', '../../../../tmp/forms-escape.csv'));
ok('a traversing filename lands inside the target directory',
   $escape->ok && str_starts_with((string)$escape->path, $dir . '/'), (string)$escape->path);
ok('...and nothing was written outside it', !file_exists('/tmp/forms-escape.csv'));

$missing = (new FileDelivery($dir . '/does-not-exist'))->deliver($pdf);
ok('a missing directory is reported, not fatal', !$missing->ok, $missing->detail);

echo "\n=== email delivery ===\n";
$seen = [];
$mail = new EmailDelivery(function (string $path, string $name, RenderedOutput $out) use (&$seen): bool {
    $seen = ['path' => $path, 'name' => $name, 'bytes' => file_get_contents($path), 'existed' => file_exists($path)];
    return true;
}, $dir);
$result = $mail->deliver($pdf);
ok('reports success when the mailer accepts', $result->ok, $result->detail);
ok('the mailer is handed a real file', ($seen['existed'] ?? false) === true);
ok('the staged file holds the rendered bytes', ($seen['bytes'] ?? '') === $pdf->bytes);
ok('the mailer is told the sanitised name', ($seen['name'] ?? '') === 'Balance_Sheet.pdf');
ok('the staged file is removed afterwards', !file_exists((string)($seen['path'] ?? '')));

$declined = (new EmailDelivery(static fn(): bool => false, $dir))->deliver($pdf);
ok('a declining mailer is a failure, not a silent success', !$declined->ok, $declined->detail);

$stagedPath = null;
$threw = (new EmailDelivery(function (string $path) use (&$stagedPath): bool {
    $stagedPath = $path;
    throw new \RuntimeException('SMTP refused the connection');
}, $dir))->deliver($pdf);
ok('a throwing mailer is caught and reported', !$threw->ok, $threw->detail);
ok('...and mentions why', str_contains($threw->detail, 'SMTP refused'));
ok('...and still removes the staged copy of the document',
   $stagedPath !== null && !file_exists((string)$stagedPath));

// Delivery works on bytes alone — it must never need to know how they were made.
echo "\n=== the layer boundary ===\n";
$source = file_get_contents(__DIR__ . '/../../trunk/modules/forms/classes/deliver/delivery.php');
ok('the delivery layer references no renderer',
   !preg_match('/\bRenderer\b|\bTableDocument\b|\bLayoutDocument\b/', (string)$source));
ok('the delivery layer touches no database',
   !preg_match('/queryFactory|->Execute\(|TABLE_/', (string)$source));

foreach (glob($dir . '/*') ?: [] as $file) unlink($file);
rmdir($dir);

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
