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
 * Deliberately thin. It owns the Header()/Footer() callbacks, which TCPDF
 * invokes on page transitions and which are the sole hook for drawing content
 * that repeats on every page. Both delegate straight back to the renderer, so
 * the layout decisions stay readable and testable instead of being tangled into
 * a PDF subclass (docs/forms-design.md §2.2). It also wraps Cell(), MultiCell()
 * and GetStringWidth() to draw and measure text in a font that has its
 * characters, so neither renderer has to know which fonts cover what.
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

    /**
     * Font families the reader supplies for East Asian text. They are Adobe's
     * standard CID fonts, so the PDF names them and embeds nothing. STSong's
     * character collection (GB1) includes the traditional characters as well as
     * the simplified ones, so it serves any Chinese text; the traditional-only
     * collection would show simplified characters as gaps.
     */
    private const FONT_CHINESE  = 'stsongstdlight';
    private const FONT_JAPANESE = 'kozminproregular';
    private const FONT_KOREAN   = 'hysmyeongjostdmedium';

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '',
                         $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'M')
    {
        $args = func_get_args();
        return $this->inFontFor((string)$txt, fn() => parent::Cell(...$args));
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false, $ln = 1, $x = null, $y = null,
                              $reseth = true, $stretch = 0, $ishtml = false, $autopadding = true, $maxh = 0,
                              $valign = 'T', $fitcell = false)
    {
        $args = func_get_args();
        return $this->inFontFor((string)$txt, fn() => parent::MultiCell(...$args));
    }

    public function GetStringWidth($s, $fontname = '', $fontstyle = '', $fontsize = 0, $getarray = false)
    {
        $args = func_get_args();
        if ($fontname !== '') return parent::GetStringWidth(...$args);
        return $this->inFontFor((string)$s, fn() => parent::GetStringWidth(...$args));
    }

    /**
     * Run $draw in a font that has glyphs for $text, then restore the font.
     *
     * TCPDF's core fonts (Helvetica, Times, Courier) hold only the Windows
     * Latin-1 characters and print every other one as "?". Text with Chinese,
     * Japanese or Korean characters is drawn in the matching CID font; other
     * text the current font cannot show is drawn in the DejaVu family of the
     * same kind (sans, serif or mono), keeping bold and italic.
     */
    private function inFontFor(string $text, callable $draw): mixed
    {
        $family = $this->fallbackFamily($text);
        if ($family === null) return $draw();

        $saved = [$this->getFontFamily(), $this->getFontStyle(), $this->getFontSizePt()];
        $this->SetFont($family, $saved[1], $saved[2]);
        try {
            return $draw();
        } finally {
            $this->SetFont($saved[0], $saved[1], $saved[2]);
        }
    }

    /** The family to draw $text in, or null when the current font can show it. */
    private function fallbackFamily(string $text): ?string
    {
        if (!preg_match('/[^\x00-\x7F]/', $text)) return null;
        $current = $this->getFontFamily();

        if (preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\x{3000}-\x{303F}\x{FF00}-\x{FFEF}]/u', $text)) {
            $family = preg_match('/\p{Hangul}/u', $text) ? self::FONT_KOREAN
                : (preg_match('/[\p{Hiragana}\p{Katakana}]/u', $text) ? self::FONT_JAPANESE : self::FONT_CHINESE);
            return $current === $family ? null : $family;
        }

        if ($this->isUnicodeFont()) return null;
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $code = mb_ord($char, 'UTF-8');
            if ($code > 0xFF && !isset(\TCPDF_FONT_DATA::$uni_utf8tolatin[$code])) {
                if (str_starts_with($current, 'times'))   return 'dejavuserif';
                if (str_starts_with($current, 'courier')) return 'dejavusansmono';
                return 'dejavusans';
            }
        }
        return null;
    }

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
