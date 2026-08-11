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
//  Path: /modules/forms/classes/document/renderer.php
//
/**
 * Forms — the render contract. See docs/forms-design.md §2.2.
 *
 * A renderer turns a Document into bytes. It must not touch the database, emit
 * HTTP headers, call exit(), or run side effects — those are the caller's job
 * (§2.1 rules 3–5). PhreeForm's generators did all four, which is why they
 * could not be swapped, tested, or reused for email.
 *
 * Because renderers sit *below* the seam, replacing one is not a breaking
 * change: it alters no stored definition, no permission and no API.
 */

namespace FreeBooks\Forms\Document;

/** Bytes plus just enough metadata for a caller to deliver them. */
final readonly class RenderedOutput
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public string $filename,
    ) {}

    public function size(): int
    {
        return strlen($this->bytes);
    }
}

/**
 * Per-render options that are not part of the document.
 *
 * Kept separate so the same Document can be rendered at different sizes or for
 * different audiences without rebuilding it.
 */
final readonly class RenderOptions
{
    public function __construct(
        public ?string $filename = null,     // override the suggested filename
        public array   $extra    = [],       // renderer-specific knobs
    ) {}
}

/** Thrown when a renderer is handed a document it cannot draw. */
class UnsupportedDocumentException extends \RuntimeException {}

interface FormsRenderer
{
    /**
     * Can this renderer draw that document? CSV supports TableDocument but not
     * LayoutDocument; a PDF renderer may support both.
     */
    public function supports(Document $doc): bool;

    /** @throws UnsupportedDocumentException when supports() is false. */
    public function render(Document $doc, RenderOptions $options = new RenderOptions()): RenderedOutput;
}

/**
 * Resolves a document to a renderer.
 *
 * This is the whole of the swap mechanism: register a different renderer for a
 * format and every caller gets it, with no change above the seam.
 */
final class RendererRegistry
{
    /** @var array<string,FormsRenderer> */
    private array $renderers = [];

    public function register(string $format, FormsRenderer $renderer): void
    {
        $this->renderers[strtolower($format)] = $renderer;
    }

    public function has(string $format): bool
    {
        return isset($this->renderers[strtolower($format)]);
    }

    /** @return list<string> formats able to render this document */
    public function formatsFor(Document $doc): array
    {
        $out = [];
        foreach ($this->renderers as $format => $renderer) {
            if ($renderer->supports($doc)) $out[] = $format;
        }
        return $out;
    }

    public function get(string $format, Document $doc): FormsRenderer
    {
        $key = strtolower($format);
        if (!isset($this->renderers[$key])) {
            throw new UnsupportedDocumentException("No renderer registered for format '$format'.");
        }
        $renderer = $this->renderers[$key];
        if (!$renderer->supports($doc)) {
            throw new UnsupportedDocumentException(
                "Renderer '$format' does not support " . $doc::class . '.');
        }
        return $renderer;
    }

    public function render(string $format, Document $doc, RenderOptions $options = new RenderOptions()): RenderedOutput
    {
        return $this->get($format, $doc)->render($doc, $options);
    }
}

/**
 * Supplies a Document. Replaces PhreeForm's special_class escape hatch.
 *
 * A special_class returned a pre-formatted array welded to one output format;
 * a provider returns a Document, so it gets every registered renderer for free.
 * The nine classes to port (entry_builder, statement_builder, pos_builder,
 * wo_builder, balance_sheet, is_budget, aged_receivables, aged_summary,
 * acct_recon) each become one of these — see docs/forms-design.md §5 and §7.
 */
interface DocumentProvider
{
    /** @param array<string,mixed> $params runtime parameters (record ids, date range, filters) */
    public function build(array $params): Document;
}
