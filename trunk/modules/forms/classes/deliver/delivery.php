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
//  Path: /modules/forms/classes/deliver/delivery.php
//
/**
 * Forms — the delivery layer (docs/forms-design.md §2).
 *
 * The last of the five layers: bytes in, somewhere out. It exists as its own
 * layer for one reason — the same `RenderedOutput` has to be able to go to a
 * browser download, an email attachment and a file on disk without any of those
 * paths knowing how the document was built or drawn. PhreeForm had no such
 * split: `BuildPDF()` chose the output method, set the headers and echoed the
 * body itself, so "the same report, but attached to an email" meant a second
 * code path through the whole engine.
 *
 * Every deliverer takes what it needs as an argument — a header sink, a mailer,
 * a directory. None of them reads a global or touches the database. That is not
 * dogma: it is what lets `verify_forms_delivery.php` assert the exact bytes and
 * headers of a download without an HTTP request, and what lets the email path be
 * tested without a mail server.
 *
 * Delivery sits ABOVE the seam, so HTTP and the mailer are legitimate here; it
 * is the Document and the renderers below it that must be free of them.
 */

namespace FreeBooks\Forms\Deliver;

use FreeBooks\Forms\Document\RenderedOutput;

/**
 * What happened. Delivery reports rather than throws, because a failed email or
 * an unwritable directory is an outcome a page has to show the user, not an
 * exception that should abandon a request whose document rendered perfectly.
 */
final readonly class DeliveryResult
{
    public function __construct(
        public bool    $ok,
        public string  $detail = '',
        /** Where it landed, when that is a meaningful question. */
        public ?string $path = null,
    ) {}

    public static function success(string $detail = '', ?string $path = null): self
    {
        return new self(true, $detail, $path);
    }

    public static function failure(string $detail): self
    {
        return new self(false, $detail);
    }
}

interface Delivery
{
    public function deliver(RenderedOutput $output): DeliveryResult;
}

/**
 * Filename and header-value hygiene.
 *
 * Both matter more than they look. PhreeForm wrote
 * `header("Content-disposition: attachment; filename=" . $report->title . ".csv")`
 * — unquoted, unescaped, and taken from a user-editable report title. A title
 * containing a newline injects arbitrary response headers; one containing a
 * quote or a semicolon truncates the filename. A filename is also the one string
 * in this layer that reaches a filesystem path, so it is where traversal would
 * enter.
 */
final class Filename
{
    private const MAX_LENGTH = 120;

    /**
     * Reduce a name to something safe as both a path component and an ASCII
     * header parameter: no directory separators, no control characters, no
     * quoting metacharacters, never empty, never `.` or `..`.
     */
    public static function sanitise(string $name, string $fallback = 'document'): string
    {
        // basename() first: a name of "../../etc/passwd" must lose its path
        // before anything else looks at it. It is not sufficient on its own —
        // it keeps backslashes on Linux — so the character filter follows.
        $name = basename(str_replace('\\', '/', $name));
        $name = (string)preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
        $name = trim($name, '_.');
        if ($name === '' || $name === '.' || $name === '..') return $fallback;

        if (strlen($name) <= self::MAX_LENGTH) return $name;

        // Truncate the stem, not the extension: a 200-character report title
        // must not produce a file the operating system will not open, and must
        // not lose the ".pdf" that tells the browser what it is.
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem      = pathinfo($name, PATHINFO_FILENAME);
        $keep      = max(1, self::MAX_LENGTH - strlen($extension) - 1);
        return $extension === '' ? substr($stem, 0, self::MAX_LENGTH)
                                 : substr($stem, 0, $keep) . '.' . $extension;
    }

    /**
     * A value safe to place after a header name: no CR, LF or NUL, so nothing
     * can start a header of its own.
     */
    public static function headerValue(string $value): string
    {
        return trim((string)preg_replace('/[\r\n\0]+/', '', $value));
    }

    /**
     * The `filename`/`filename*` pair for a Content-Disposition header (RFC 6266
     * and RFC 5987).
     *
     * Both forms are emitted deliberately. The plain one is ASCII, because that
     * is all the quoted form may legally carry; the extended one preserves the
     * original, so a company or customer name with an accent in it — nl_nl,
     * pt_br and ru_ru all ship — reaches the user intact instead of as
     * underscores. Clients that understand `filename*` prefer it; the rest fall
     * back.
     */
    public static function contentDisposition(string $disposition, string $filename): string
    {
        $ascii    = self::sanitise($filename);
        $original = self::headerValue(basename(str_replace('\\', '/', $filename)));

        $header = sprintf('%s; filename="%s"', $disposition, $ascii);
        if ($original !== '' && $original !== $ascii) {
            $header .= "; filename*=UTF-8''" . rawurlencode($original);
        }
        return 'Content-Disposition: ' . $header;
    }
}

/**
 * Send it to the browser — as a download, or inline for viewing.
 *
 * The header sink and body sink are injectable so the headers can be asserted
 * without an HTTP request. In the application both are omitted and it uses
 * `header()` and `echo`.
 */
final class HttpDelivery implements Delivery
{
    public const ATTACHMENT = 'attachment';
    public const INLINE     = 'inline';

    /**
     * @param \Closure(string):void|null $emitHeader
     * @param \Closure(string):void|null $emitBody
     * @param \Closure():bool|null       $headersSent
     */
    public function __construct(
        private readonly string    $disposition = self::ATTACHMENT,
        private readonly ?\Closure $emitHeader  = null,
        private readonly ?\Closure $emitBody    = null,
        private readonly ?\Closure $headersSent = null,
    ) {}

    public function deliver(RenderedOutput $output): DeliveryResult
    {
        $sent = $this->headersSent ?? static fn(): bool => headers_sent();
        if ($sent()) {
            // Refuse rather than warn. Output has already begun, so the headers
            // will not apply and the bytes would be appended to whatever page
            // was mid-render — a corrupt download that looks like a broken PDF
            // rather than a broken caller. This is the failure mode that made a
            // shipped form silently unprintable (§12.3.1).
            return DeliveryResult::failure(
                'Output had already started, so the document was not sent.');
        }

        $header = $this->emitHeader ?? static function (string $h): void { header($h); };
        $body   = $this->emitBody   ?? static function (string $b): void { echo $b; };

        $header('Content-Type: ' . Filename::headerValue($output->mimeType));
        $header('Content-Length: ' . $output->size());
        $header(Filename::contentDisposition($this->disposition, $output->filename));
        // A report is a snapshot of the moment it ran; a cached copy shown after
        // the underlying data changed is worse than a second render.
        $header('Cache-Control: private, max-age=0, must-revalidate');
        $header('Pragma: public');
        $body($output->bytes);

        return DeliveryResult::success(sprintf('Sent %d bytes as %s.',
            $output->size(), Filename::sanitise($output->filename)));
    }
}

/**
 * Write it to a directory — the archive copy, and the staging step the email
 * path needs because the application's mailer attaches by path.
 */
final class FileDelivery implements Delivery
{
    public function __construct(
        private readonly string $directory,
        /** Overwrite an existing file, or write alongside it as name-1, name-2. */
        private readonly bool   $overwrite = false,
    ) {}

    public function deliver(RenderedOutput $output): DeliveryResult
    {
        $directory = rtrim($this->directory, '/\\');
        if (!is_dir($directory)) {
            return DeliveryResult::failure("No such directory: $directory");
        }
        if (!is_writable($directory)) {
            // Names the likely cause: my_files/{company} has to belong to the
            // web server or nothing under it can be written.
            return DeliveryResult::failure(
                "Directory is not writable: $directory (check its ownership)");
        }

        $name = Filename::sanitise($output->filename);
        $path = $directory . '/' . $name;
        if (!$this->overwrite) $path = $this->unused($directory, $name);

        if (@file_put_contents($path, $output->bytes) === false) {
            return DeliveryResult::failure("Could not write $path");
        }
        return DeliveryResult::success(sprintf('Wrote %d bytes.', $output->size()), $path);
    }

    /** name.pdf, then name-1.pdf, name-2.pdf … so an export never silently replaces one. */
    private function unused(string $directory, string $name): string
    {
        $path = $directory . '/' . $name;
        if (!file_exists($path)) return $path;

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem      = pathinfo($name, PATHINFO_FILENAME);
        $suffix    = $extension === '' ? '' : '.' . $extension;
        for ($n = 1; $n < 1000; $n++) {
            $candidate = sprintf('%s/%s-%d%s', $directory, $stem, $n, $suffix);
            if (!file_exists($candidate)) return $candidate;
        }
        return $directory . '/' . $stem . '-' . bin2hex(random_bytes(4)) . $suffix;
    }
}

/**
 * Attach it to an email.
 *
 * The mailer is a closure taking the staged file's path and its filename, so
 * this class knows nothing about PHPMailer, `validate_send_mail()` or the
 * configuration they read. The module supplies the adapter; the tests supply a
 * recorder.
 *
 * The staged file is always removed, including when the mailer throws — a
 * document left behind in a temp directory is a copy of business data nobody is
 * watching.
 */
final class EmailDelivery implements Delivery
{
    /** @param \Closure(string $path, string $filename, RenderedOutput $output):bool $send */
    public function __construct(
        private readonly \Closure $send,
        private readonly ?string  $tempDirectory = null,
    ) {}

    public function deliver(RenderedOutput $output): DeliveryResult
    {
        $directory = $this->tempDirectory ?? sys_get_temp_dir();
        $name      = Filename::sanitise($output->filename);

        // Unique per attempt: two users emailing the same invoice at the same
        // moment must not stage over each other's bytes.
        $staged = sprintf('%s/%s-%s-%s', rtrim($directory, '/\\'),
            pathinfo($name, PATHINFO_FILENAME), bin2hex(random_bytes(6)),
            pathinfo($name, PATHINFO_EXTENSION) ?: 'bin');

        if (@file_put_contents($staged, $output->bytes) === false) {
            return DeliveryResult::failure("Could not stage the attachment in $directory");
        }

        try {
            $sent = ($this->send)($staged, $name, $output);
        } catch (\Throwable $e) {
            return DeliveryResult::failure('The mailer failed: ' . $e->getMessage());
        } finally {
            @unlink($staged);
        }

        return $sent
            ? DeliveryResult::success(sprintf('Emailed %s (%d bytes).', $name, $output->size()))
            : DeliveryResult::failure('The mailer declined to send the message.');
    }
}
