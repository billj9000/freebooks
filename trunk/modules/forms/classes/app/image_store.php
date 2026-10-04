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
//  Path: /modules/forms/classes/app/image_store.php
//
/**
 * Forms — the images a form may draw, and how a new one gets there.
 *
 * The canvas offers a fixed-filename image a list of what is in the reports
 * images folder. Without an upload the only way into that folder is the file
 * system, which is not something the user of an accounts package has, so a
 * logo could be positioned but never supplied.
 *
 * ## Why this is a class and not four lines in the ajax op
 *
 * A file arriving from a browser is the one input on this page that the web
 * server will later hand back out, so every decision about it is made in one
 * place and the same place decides what the LIST contains. When the writer and
 * the lister disagree about which extensions count, the folder acquires files
 * that cannot be chosen and the author is told to copy something they already
 * copied.
 *
 * ## What is actually checked, and why each one
 *
 *   - **The type is read out of the file, never off the name.** getimagesize()
 *     parses the header; a `.png` that is not a PNG fails here rather than
 *     inside TCPDF with the document half written.
 *   - **The stored extension comes from the type that was READ**, not from what
 *     was uploaded. This is the check that matters: a name is the only part of
 *     an upload that a web server acts on, so the name is not the uploader's to
 *     choose. `logo.php` carrying a valid GIF header is stored as a `.gif`.
 *   - **The rest of the name is reduced to letters, digits, dash, underscore
 *     and dot**, with a length cap. Directory separators, control characters,
 *     leading dots and `..` cannot survive that, and neither can a name so long
 *     it breaks the file system it lands on.
 *   - **Nothing is overwritten.** A name already in the folder may be the one
 *     another form draws; replacing its contents would change a document whose
 *     author never opened it. A number is appended instead.
 *   - **A size cap**, because the PDF carries the file and an author with a
 *     camera photograph will otherwise post a 30 MB invoice to a customer.
 *
 * PHP execution in this folder is denied by `my_files/.htaccess`, and the three
 * types accepted are raster formats a browser will not run as script — so an
 * appended payload has nothing to interpret it. The extension rule above is
 * what makes that true rather than merely likely.
 */

namespace FreeBooks\Forms\App;

final class ImageStoreException extends \RuntimeException {}

final class ImageStore
{
    /** Big enough for a photographed signature, small enough to email. */
    public const MAX_BYTES = 4194304;   // 4 MB

    /**
     * The types TCPDF places, and the extension each is STORED under.
     *
     * Keyed by the constant getimagesize() returns, so the mapping runs from
     * what was read to what is written and never the other way.
     */
    private const TYPES = [
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_GIF  => 'gif',
    ];

    /**
     * @param ?\Closure $accept how a temporary file becomes a stored one:
     *   `fn(string $from, string $to): bool`. Null — which is what the
     *   application passes — is PHP's own is_uploaded_file/move_uploaded_file
     *   pair, the only thing that can tell a genuine upload from a path the
     *   request merely named.
     *
     *   It is a parameter because there is no upload in a command line, and
     *   every other decision this class makes — the type, the name, the
     *   collision — is worth testing. `verify_forms_image_upload.php` supplies
     *   its own and separately holds the DEFAULT to refusing a file that was
     *   not uploaded, so substituting it cannot quietly remove the check.
     */
    public function __construct(
        private readonly string $directory,
        private readonly ?\Closure $accept = null,
    ) {}

    /**
     * The image files that are there, in the order a person would list them.
     *
     * The same list the canvas offers and TemplateForm enforces. Read with
     * getimagesize() rather than by extension: a file that is not an image
     * cannot be drawn, and offering it produces a form with a hole in it.
     *
     * @return list<string> basenames
     */
    public function names(): array
    {
        $names = [];
        foreach (glob(rtrim($this->directory, '/\\') . '/*') ?: [] as $path) {
            if (!is_file($path)) continue;
            $size = @getimagesize($path);
            if ($size === false || !isset(self::TYPES[(int)($size[2] ?? 0)])) continue;
            $names[] = basename($path);
        }
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return $names;
    }

    /**
     * Take an uploaded file into the images folder.
     *
     * @param array<string,mixed> $file one entry of $_FILES
     * @return array{name: string, width: int, height: int} the name it was
     *   stored under — which is NOT necessarily the name it arrived with — and
     *   its pixel size, which the canvas needs because an Image usually stores
     *   a width and no height and so changes shape when its file changes.
     * @throws ImageStoreException with a sentence an author can act on
     */
    public function store(array $file): array
    {
        $this->reject($file);

        $temporary = (string)($file['tmp_name'] ?? '');
        $size = @getimagesize($temporary);
        $type = (int)($size[2] ?? 0);
        if ($size === false || !isset(self::TYPES[$type])) {
            throw new ImageStoreException(
                'That file is not a PNG, JPEG or GIF image, whatever it is called.');
        }

        $directory = rtrim($this->directory, '/\\');
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new ImageStoreException('The reports images folder could not be created.');
        }

        $name = $this->free($directory, self::stem((string)($file['name'] ?? '')), self::TYPES[$type]);
        $path = $directory . '/' . $name;

        if (!$this->accept($temporary, $path)) {
            throw new ImageStoreException('The image could not be saved. Check the folder is writable.');
        }
        // Whatever the umask left, this is data and not a program.
        @chmod($path, 0644);

        return ['name' => $name, 'width' => (int)$size[0], 'height' => (int)$size[1]];
    }

    /**
     * Everything that disqualifies the upload before it is even read.
     *
     * PHP's own error code first: an upload larger than `upload_max_filesize`
     * arrives with an EMPTY tmp_name, so a size check written before this one
     * would report "not an image" for a file that is a perfectly good image.
     *
     * @param array<string,mixed> $file
     */
    private function reject(array $file): void
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new ImageStoreException(self::tooBig());
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new ImageStoreException('The image did not arrive in one piece; try again.');
        }
        if (!is_file((string)($file['tmp_name'] ?? ''))) {
            throw new ImageStoreException('The image did not arrive in one piece; try again.');
        }
        $bytes = (int)($file['size'] ?? 0);
        if ($bytes <= 0)              throw new ImageStoreException('That file is empty.');
        if ($bytes > self::MAX_BYTES) throw new ImageStoreException(self::tooBig());
    }

    /**
     * The temporary file, taken.
     *
     * is_uploaded_file() is the test that separates a genuine upload from a
     * path the request merely named, and move_uploaded_file() makes the same
     * test itself — so the pair is the gate, and it is here rather than in
     * reject() because that is what the substitutable seam has to replace
     * WHOLE. Half a gate is worse than none: it reads as a check.
     */
    private function accept(string $from, string $to): bool
    {
        if ($this->accept !== null) return (bool)($this->accept)($from, $to);
        return is_uploaded_file($from) && @move_uploaded_file($from, $to);
    }

    private static function tooBig(): string
    {
        return 'That image is larger than ' . (self::MAX_BYTES >> 20) . ' MB.';
    }

    /**
     * The part of the submitted name worth keeping.
     *
     * An author recognises their own file, so the name is kept where it can be
     * — but only the characters that mean nothing to a file system or a shell.
     * The extension is dropped here because the caller supplies the real one.
     */
    private static function stem(string $submitted): string
    {
        $stem = pathinfo(basename($submitted), PATHINFO_FILENAME);
        $stem = preg_replace('/[^A-Za-z0-9._-]+/', '-', $stem) ?? '';
        // A leading dot hides the file; a run of dots is the only way `..`
        // could reappear after the character filter above.
        $stem = trim(preg_replace('/\.{2,}/', '.', $stem) ?? '', '.-');
        $stem = substr($stem, 0, 60);
        return $stem === '' ? 'image' : $stem;
    }

    /**
     * A name in this folder that is not taken.
     *
     * Numbered rather than overwritten: the folder is shared by every form in
     * the company, so the file being replaced may be one another document
     * draws.
     */
    private function free(string $directory, string $stem, string $extension): string
    {
        $name = $stem . '.' . $extension;
        for ($n = 2; file_exists($directory . '/' . $name); $n++) {
            if ($n > 999) throw new ImageStoreException('Too many images share that name already.');
            $name = $stem . '-' . $n . '.' . $extension;
        }
        return $name;
    }
}
