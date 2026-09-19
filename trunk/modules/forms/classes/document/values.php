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
//  Path: /modules/forms/classes/document/values.php
//
/**
 * Forms — seam primitives. See docs/forms-design.md §4.
 *
 * Everything here is an immutable value object. Nothing in this file may know
 * about the database, HTTP, or rendering: these types are the vocabulary the
 * data layer speaks and the render layer listens to, and nothing more.
 */

namespace FreeBooks\Forms\Document;

/** Where a binding resolves from. */
enum Scope
{
    case Record;     // a field on the current record (header value, or a total)
    case Context;    // company/document context, e.g. COMPANY_NAME
    case Aggregate;  // computed across the record's line items (PhreeForm's Ttl)
}

/**
 * What a value *is*, independent of how it will be shown.
 *
 * This is the type that fixes PhreeForm's core defect: it pre-formatted in the
 * data layer, so a field formatted for a readable PDF exported to CSV as
 * "$ 1,234.50". Here the renderer receives the raw value plus its type and
 * decides the representation.
 */
enum ValueType
{
    case Money;
    case Date;
    case Integer;
    case Decimal;
    case Text;
    case Boolean;
}

/** Horizontal alignment. PhreeForm stored these as 'L' / 'C' / 'R'. */
enum Align: string
{
    case Left   = 'L';
    case Center = 'C';
    case Right  = 'R';
}

/**
 * When an element appears, relative to pagination.
 *
 * ContinuedOnOverflow is the pagination-aware case (design doc open risk 1):
 * a total renders placeholder text on every page but the last, where it
 * resolves to the real value. Verified against PO 1234 — 8 pages, "Continued"
 * on 1–7, the real total on page 8.
 */
enum Visibility
{
    case AllPages;
    case FirstPageOnly;
    case LastPageOnly;
    case ContinuedOnOverflow;
}

/** A value together with its type. The renderer formats; the data layer does not. */
final readonly class TypedValue
{
    public function __construct(
        public mixed     $raw,
        public ValueType $type = ValueType::Text,
    ) {}

    public static function text(?string $v): self    { return new self($v, ValueType::Text); }
    public static function money(int|float|string|null $v): self { return new self($v, ValueType::Money); }
    public static function date(?string $v): self    { return new self($v, ValueType::Date); }
    public static function int(?int $v): self        { return new self($v, ValueType::Integer); }
    public static function decimal(int|float|string|null $v): self { return new self($v, ValueType::Decimal); }
    public static function bool(?bool $v): self      { return new self($v, ValueType::Boolean); }

    public function isEmpty(): bool
    {
        return $this->raw === null || $this->raw === '';
    }
}

/**
 * A named reference to a value, resolved against a Record or the Context.
 *
 * Collapses PhreeForm's Data/CDta pair (and TBlk/CBlk) into one concept: the
 * only difference between them was which bag the name was looked up in.
 */
final readonly class Binding
{
    /**
     * @param list<string> $sumOf for Aggregate scope, the fields to add up.
     *   PhreeForm's Ttl sums SEVERAL fields — `foreach ($Params['Seq'] as $f)
     *   $total += $this->$f` — so a total is not a single named value and
     *   keeping only the first silently under-reports it. Defaults to [$name].
     */
    public function __construct(
        public string $name,
        public Scope  $scope = Scope::Record,
        public array  $sumOf = [],
    ) {}

    public static function record(string $name): self  { return new self($name, Scope::Record); }
    public static function context(string $name): self { return new self($name, Scope::Context); }

    /** @param list<string> $fields fields to sum; defaults to [$name] */
    public static function aggregate(string $name, array $fields = []): self
    {
        return new self($name, Scope::Aggregate, $fields ?: [$name]);
    }

    /** @return list<string> the fields this aggregate adds up */
    public function aggregateFields(): array
    {
        return $this->sumOf ?: [$this->name];
    }
}

/**
 * How a Condition compares the value it resolved against the one the author
 * typed.
 *
 * A closed list, and that is the point rather than a limitation. The obvious
 * alternative — an expression typed into a box — is PhreeForm's mistake in a
 * new costume: it puts author-supplied syntax in front of an evaluator living
 * in the render layer, which is the shape §3.3 refuses for joins and §13.31
 * has already walked back once, when the format box turned out to be free text
 * over a vocabulary that was closed all along. An author picks a binding, picks
 * an operator, and types a value. They never supply syntax.
 *
 * `NotIn` is not symmetry for its own sake. It is the only way to say
 * "otherwise" without a grouping concept: three bank blocks conditioned on
 * GBP, EUR, and not-GBP-or-EUR cover every record between them, and the third
 * needs no knowledge of the other two.
 */
enum ConditionOp: string
{
    case Equals     = 'eq';
    case NotEquals  = 'ne';
    case In         = 'in';        // $value is a comma-separated list
    case NotIn      = 'nin';
    case IsEmpty    = 'empty';     // $value is ignored
    case IsNotEmpty = 'notempty';
    case Greater    = 'gt';
    case Less       = 'lt';
}

/**
 * Whether an element prints for the record being rendered.
 *
 * `Visibility` already answers "does this print on THIS PAGE"; this answers
 * "does this print for THIS RECORD", and the two are independent — a total may
 * be last-page-only AND only appear when there is a balance left to pay.
 *
 * The comparison is pure: it is handed a value somebody else resolved. That
 * split is deliberate, because resolving a binding means knowing about scopes,
 * records and aggregates, which is the renderer's business — and it is what
 * lets both renderers share one predicate without either of them owning it.
 *
 * Everything compares as TEXT, with two exceptions worth stating because they
 * are the cases an author will meet first:
 *
 *   - **Two numbers compare as numbers.** A Money value arriving from the
 *     database as the string "0.00" must satisfy `equals 0`, and by string it
 *     would not. This is also what makes `gt`/`lt` useful at all.
 *   - **Text compares case-insensitively.** `eur` matching nothing where the
 *     record says `EUR` is a trap with no case on the other side of it.
 *
 * A date needs no rule of its own: a TypedValue::date() holds `Y-m-d`, which
 * sorts correctly as a string, so `lt 2026-01-01` means what it looks like.
 */
final readonly class Condition
{
    public function __construct(
        public Binding     $bind,
        public ConditionOp $op,
        /** For In/NotIn, a comma-separated list. Ignored by IsEmpty/IsNotEmpty. */
        public string      $value = '',
    ) {}

    public function satisfiedBy(?TypedValue $value): bool
    {
        $left = self::text($value);

        return match ($this->op) {
            ConditionOp::IsEmpty    => $left === '',
            ConditionOp::IsNotEmpty => $left !== '',
            ConditionOp::Equals     => self::same($left, $this->value),
            ConditionOp::NotEquals  => !self::same($left, $this->value),
            ConditionOp::In         => self::anyOf($left, $this->value),
            ConditionOp::NotIn      => !self::anyOf($left, $this->value),
            ConditionOp::Greater    => self::compare($left, $this->value) > 0,
            ConditionOp::Less       => self::compare($left, $this->value) < 0,
        };
    }

    /**
     * The resolved value as text.
     *
     * A bool is spelled '1'/'0' rather than cast, because PHP casts false to
     * the empty string — which would make every false value indistinguishable
     * from a missing one, and `IsEmpty` quietly mean "false or absent".
     */
    private static function text(?TypedValue $value): string
    {
        $raw = $value?->raw;
        if ($raw === null)                      return '';
        if (is_bool($raw))                      return $raw ? '1' : '0';
        if ($raw instanceof \DateTimeInterface) return $raw->format('Y-m-d');
        return trim((string)$raw);
    }

    private static function same(string $left, string $right): bool
    {
        $right = trim($right);
        return is_numeric($left) && is_numeric($right)
            ? (float)$left === (float)$right
            : mb_strtolower($left) === mb_strtolower($right);
    }

    /**
     * Blank entries are skipped, so a trailing comma is forgiving rather than
     * load-bearing. A list that is entirely blank therefore matches nothing,
     * which shows up as an element that never prints — a loud authoring error,
     * where the alternative silently matched every empty value instead.
     */
    private static function anyOf(string $left, string $list): bool
    {
        foreach (explode(',', $list) as $candidate) {
            if (trim($candidate) === '') continue;
            if (self::same($left, $candidate)) return true;
        }
        return false;
    }

    private static function compare(string $left, string $right): int
    {
        $right = trim($right);
        return is_numeric($left) && is_numeric($right)
            ? (float)$left <=> (float)$right
            : strcmp(mb_strtolower($left), mb_strtolower($right));
    }
}

/**
 * A presentation hint applied at render time.
 *
 * Deliberately an open string rather than an enum: PhreeForm's 14 processing
 * hints are extended per-module through the pf_process_{module} hook, so the
 * vocabulary must stay open. Renderers ignore names they don't recognise.
 */
final readonly class Format
{
    public function __construct(
        public string $name,
        public array  $options = [],
    ) {}
}

/** Position and size on the page, in millimetres. */
final readonly class Box
{
    public function __construct(
        public float  $x,
        public float  $y,
        public ?float $width  = null,
        public ?float $height = null,
    ) {}
}

/** An RGB colour. PhreeForm stored these as "r:g:b" strings. */
final readonly class Colour
{
    public function __construct(
        public int $r = 0,
        public int $g = 0,
        public int $b = 0,
    ) {}

    /** Parse PhreeForm's "0:0:0" form; falls back to black on anything unexpected. */
    public static function fromLegacy(?string $rgb): self
    {
        $parts = array_map('intval', explode(':', (string)$rgb));
        return count($parts) === 3 ? new self($parts[0], $parts[1], $parts[2]) : new self();
    }
}

/** Visual treatment of an element. All optional — renderers supply their own defaults. */
final readonly class Style
{
    public function __construct(
        public ?string  $font       = null,
        public ?float   $size       = null,
        public ?Colour  $colour     = null,
        public ?Align   $align      = null,
        public ?float   $borderSize = null,
        public ?Colour  $borderColour = null,
        public bool     $borderShow = false,
        public bool     $truncate   = false,
        /**
         * Weight and slant, as TCPDF's font style.
         *
         * New in Forms: PhreeForm passed '' for the style on every SetFont it
         * ever made, and no shipped definition carries a tag for either — so
         * this is not a setting that was lost in the port but one the old
         * format could not express. It is the first thing the native format
         * exists to allow (§13.3).
         */
        public bool     $bold       = false,
        public bool     $italic     = false,
    ) {}

    /** TCPDF's style string: '', 'B', 'I' or 'BI'. */
    public function fontStyle(): string
    {
        return ($this->bold ? 'B' : '') . ($this->italic ? 'I' : '');
    }
}

/** The currency a record was posted in, needed to format its monetary values. */
final readonly class Currency
{
    public function __construct(
        public string $code,
        public float  $rate = 1.0,
    ) {}
}

/** Paper size, orientation and margins. */
final readonly class PageSetup
{
    public function __construct(
        public string $size        = 'A4',
        public string $orientation = 'P',
        public float  $marginTop    = 10.0,
        public float  $marginBottom = 10.0,
        public float  $marginLeft   = 10.0,
        public float  $marginRight  = 10.0,
    ) {}
}

/**
 * How a report's page furniture is styled — the banded areas above the table.
 *
 * This is the report author's data, not a renderer preference. PhreeForm stores
 * a font, size, colour and alignment per band in each report definition, and
 * they genuinely vary: across the 36 shipped report definitions there are eight
 * distinct style signatures. Five reports colour the company heading blue and
 * their first title red; seven centre their data columns where the rest align
 * left. A renderer with fixed defaults would silently restyle about a fifth of
 * the library, so the values cross the seam rather than living in the renderer.
 *
 * Every field is optional. A renderer applies its own default wherever one is
 * null, which is what lets a document built by hand — or by a provider that has
 * no legacy definition behind it — render sensibly with no styling at all.
 *
 * `CsvRenderer` ignores this entirely; that is the seam working as intended.
 */
final readonly class ReportStyle
{
    public function __construct(
        /** COMPANY_NAME band. */
        public ?Style  $heading     = null,
        public bool    $showHeading = true,
        /** First title band. `$title1Text` may contain %date% / %reportname%. */
        public ?Style  $title1      = null,
        public bool    $showTitle1  = true,
        public ?string $title1Text  = null,
        /** Second title band. */
        public ?Style  $title2      = null,
        public bool    $showTitle2  = true,
        public ?string $title2Text  = null,
        /** The filter description drawn beneath the titles. */
        public ?Style  $filter      = null,
        /** Column headings and data rows. */
        public ?Style  $data        = null,
        /** Group and report total rows. */
        public ?Style  $totals      = null,
        /**
         * Clip cell text to the column width instead of wrapping it.
         * PhreeForm's report-level `truncate`; `Style::$truncate` is the
         * per-element equivalent used by LayoutDocument.
         */
        public bool    $truncate       = false,
        /** Start a new page after each group total (PhreeForm's `grpbreak`). */
        public bool    $groupPageBreak = false,
    ) {}
}

/**
 * Document-wide values that are not per-record — company name, address, locale.
 *
 * PhreeForm read these straight from global constants (COMPANY_NAME and friends)
 * inside the render layer. Passing them explicitly is what lets a renderer be
 * tested without booting the application.
 */
final readonly class Context
{
    public function __construct(
        public array   $values = [],   // string => TypedValue
        public string  $locale = 'en_us',
    ) {}

    public function get(string $name): ?TypedValue
    {
        return $this->values[$name] ?? null;
    }
}

/**
 * A side effect to run *after* the document is delivered.
 *
 * Declarative on purpose — data, not a closure — so a Document stays a plain
 * value object that can be serialised, cached and compared. PhreeForm fired
 * these from inside the PDF builder (setprintedfield incrementing
 * journal_main.printed), which meant a preview or a diff run mutated business
 * data. Hooks are executed by the caller, never by a renderer, and must fire on
 * delivery rather than render.
 */
final readonly class AfterRenderHook
{
    public function __construct(
        public string $type,
        public array  $params = [],
    ) {}
}
