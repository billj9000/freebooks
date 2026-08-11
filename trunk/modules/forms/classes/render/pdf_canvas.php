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
 * a PDF subclass (docs/forms-design.md §2.2). PhreeForm's equivalent
 * (form_generator.php) inlined its whole drawing engine here and reached out to
 * $report/$FieldValues globals from inside Header().
 *
 * PER-RECORD PAGE NUMBERING uses TCPDF's own page groups, so an invoice reads
 * "2 of 3" within itself rather than counting across a 40-invoice batch.
 *
 * This is worth spelling out because it is a deliberate departure. PhreeForm
 * predates that feature and hand-rolled the equivalent: it tracked groups
 * itself, wrote {nb<n>} placeholders, and then overrode the protected
 * _putpages() to rewrite TCPDF's raw page buffers ($this->page, $this->pages)
 * before output. That single hack is what pins the phreeform module to the
 * TCPDF 6.x line — TCPDF 7 is a shim over tc-lib-pdf where _putpages() and
 * those properties do not exist.
 *
 * Using the documented API instead means Forms touches no TCPDF internals at
 * all, so the renderer is not an obstacle to a future TCPDF upgrade.
 */

namespace FreeBooks\Forms\Render;

/**
 * @internal Used by LayoutPdfRenderer. Not part of the seam.
 *
 * Extends TCPDF, autoloaded by Composer (tecnickcom/tcpdf).
 */
final class PdfCanvas extends \TCPDF
{
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
