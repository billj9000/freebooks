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
//  Path: /modules/forms/classes/render/pdf_canvas.php
//
/**
 * Forms — the TCPDF surface the Layout renderer draws on.
 *
 * Deliberately thin: it owns only the Header()/Footer() callbacks, which TCPDF
 * invokes on page transitions and which are the sole hook for drawing content
 * that repeats on every page. Both delegate straight back to the renderer, so
 * the layout decisions stay readable and testable instead of being tangled into
 * a PDF subclass (docs/forms-design.md §2.2).
 *
 * PER-RECORD PAGE NUMBERING uses TCPDF's own page groups, so an invoice reads
 * "2 of 3" within itself rather than counting across a 40-invoice batch.
 *
 * The documented API means Forms touches no TCPDF internals — no override of
 * the protected _putpages() and no rewriting of the raw page buffers, neither
 * of which exists in TCPDF 7, a shim over tc-lib-pdf — so the renderer is not
 * an obstacle to a future TCPDF upgrade.
 */

namespace FreeBooks\Forms\Render;

/**
 * @internal Used by LayoutPdfRenderer. Not part of the seam.
 *
 * Extends TCPDF, autoloaded by Composer (tecnickcom/tcpdf).
 */
final class PdfCanvas extends \TCPDF
{
    /**
     * A paper name TCPDF will actually recognise.
     *
     * TCPDF::getPageSizeFromFormat() looks the name up in a table keyed in
     * UPPERCASE and, finding nothing, silently returns A4. Definitions store
     * "Letter" — what PhreeForm's `Letter:216:282` reduces to — so without this
     * a Letter document prints on A4, 6 mm narrower and 18 mm longer, with
     * nothing failing. A4, A5 and the rest of the ISO names pass through
     * unchanged because they are already uppercase.
     *
     * An unknown name still falls back to A4 inside TCPDF. That is its choice to
     * make and this does not second-guess it — but it is worth knowing that a
     * definition naming a paper size nobody has heard of prints on A4 in
     * silence.
     */
    public static function paperFormat(mixed $size): mixed
    {
        return is_string($size) ? strtoupper(trim($size)) : $size;
    }

    /** Callback drawing everything that repeats on every page. */
    private $headerCallback = null;

    /** Callback drawing last-page-only content (totals). */
    private $footerCallback = null;

    /** Set while TCPDF is inside its own Header()/Footer() call. */
    private bool $inFurniture = false;

    public function setHeaderCallback(?callable $callback): void { $this->headerCallback = $callback; }
    public function setFooterCallback(?callable $callback): void { $this->footerCallback = $callback; }

    /** True while TCPDF is calling Header()/Footer() — guards against recursion. */
    public function isDrawingFurniture(): bool { return $this->inFurniture; }

    public function Header()
    {
        if ($this->headerCallback === null) return;
        $this->inFurniture = true;
        try { ($this->headerCallback)($this); }
        finally { $this->inFurniture = false; }
    }

    public function Footer()
    {
        if ($this->footerCallback === null) return;
        $this->inFurniture = true;
        try { ($this->footerCallback)($this); }
        finally { $this->inFurniture = false; }
    }
}
