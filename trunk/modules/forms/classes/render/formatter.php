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
//  Path: /modules/forms/classes/render/formatter.php
//
/**
 * Forms — turns a TypedValue into display text at RENDER time.
 *
 * This is the half of PhreeForm's ProcessData() that legitimately belongs below
 * the seam, and it is deliberately only that half.
 *
 * PhreeForm's "processing" vocabulary conflates two unrelated jobs:
 *
 *   PRESENTATION — date/currency/case formatting and separators. Pure functions
 *       of the value plus locale and currency. They belong here.
 *
 *   RESOLUTION — turning an id into a name by QUERYING THE DATABASE:
 *       rep_id    -> users.display_name       (pb_get_user_name)
 *       branch    -> contacts.short_name      (contacts_get_short_name)
 *       avg_cost  -> inventory_history        (pb_get_avg_cost)
 *       avg_total -> inventory_history        (pb_get_avg_total)
 *       ordr_qty  -> journal_item             (pb_pull_order_qty)
 *     These are data lookups wearing a formatting costume. They cannot run here
 *     without breaking the rule that nothing below the seam touches the database
 *     (docs/forms-design.md §2.1 rule 3) — note that rep_id reads `users`, one of
 *     the sensitive tables the whole security model exists to keep out of report
 *     authors' reach. Under Forms these become fields the PROVIDER resolves, so
 *     the document arrives carrying the name rather than the id.
 *
 * Resolution hints are therefore not implemented. They pass the value through
 * unchanged and are recorded by unresolvedHints(), so a conversion run can
 * report exactly which definitions still need a provider-side field instead of
 * silently rendering an id where a name belongs.
 *
 * A third group LOOKS like resolution but is not: j_desc, coa_type, cvt_iso2,
 * cvt_cntry and NewUsed map a code to a label through a constant or a static
 * array with no query involved. Those are presentation, and they stay here — but
 * their tables live in other modules, so they are supplied as an injected
 * `lookups` map rather than reached for directly. The renderer never has to know
 * that $coa_types_list came from phreebooks/defaults.php.
 *
 * The class depends on the app's `currencies` object and a few locale helpers,
 * which are presentation services rather than data access; inject a null
 * currencies to get plain numeric output in tests.
 */

namespace FreeBooks\Forms\Render;

use FreeBooks\Forms\Document\{Currency, Format, TypedValue, ValueType};

final class Formatter
{
    /** Hints that issue a database query, so the provider must resolve them. */
    public const RESOLUTION_HINTS = [
        'rep_id', 'branch', 'avg_cost', 'avg_total', 'ordr_qty',
    ];

    /**
     * The number words, as language KEYS with English fallbacks.
     *
     * Keys because these are translatable; the fallbacks keep `n2wrd`
     * working where no language file defines them. TEXT_HUNDERD is spelled
     * as it is spelled in the language files.
     */
    private const UNITS = [1 => 'TEXT_ONE', 'TEXT_TWO', 'TEXT_THREE', 'TEXT_FOUR', 'TEXT_FIVE',
                           'TEXT_SIX', 'TEXT_SEVEN', 'TEXT_EIGHT', 'TEXT_NINE'];
    private const UNITS_EN = [1 => 'One', 'Two', 'Three', 'Four', 'Five',
                              'Six', 'Seven', 'Eight', 'Nine'];
    private const TEENS = [10 => 'TEXT_TEN', 'TEXT_ELEVEN', 'TEXT_TWELVE', 'TEXT_THIRTEEN',
                           'TEXT_FOURTEEN', 'TEXT_FIFTEEN', 'TEXT_SIXTEEN', 'TEXT_SEVENTEEN',
                           'TEXT_EIGHTEEN', 'TEXT_NINETEEN'];
    private const TEENS_EN = [10 => 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen',
                              'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    private const TENS = [2 => 'TEXT_TWENTY', 'TEXT_THIRTY', 'TEXT_FORTY', 'TEXT_FIFTY',
                          'TEXT_SIXTY', 'TEXT_SEVENTY', 'TEXT_EIGHTY', 'TEXT_NINETY'];
    private const TENS_EN = [2 => 'Twenty', 'Thirty', 'Forty', 'Fifty',
                             'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /** Code-to-label hints served from $lookups — no query, so they stay here. */
    public const LOOKUP_HINTS = ['coa_type', 'cvt_iso2', 'cvt_cntry'];

    /**
     * The hints an author may CHOOSE, grouped as the editors present them.
     *
     * This vocabulary is closed. `format()` ends in `default => plain()` and
     * this class has no extension point — the `pf_process_{module}` hook that
     * made PhreeForm's list open is PhreeForm's, and Forms does not call it. So
     * a name that is not in one of the three lists on this class does nothing
     * at all, silently, and an editor offering free text would invite authors
     * to type into a box where a misspelling and a decision look identical.
     *
     * Deliberately NOT the whole vocabulary. RESOLUTION_HINTS are excluded,
     * because a list is an offer and those five cannot be honoured by a format
     * alone: each needs a database lookup that the provider must have performed
     * before the value ever reaches here (§4.3.1). Offering `rep_id` would be
     * promising a salesperson's name and delivering a number. They are still
     * KEPT wherever a definition already carries one — the editors append what
     * is in use to what is on offer, which is the rule the font and symbology
     * lists already keep.
     *
     * Held to the class by verify_forms_formatter.php: this must be exactly
     * what format() performs, less the resolution hints. Adding an arm to the
     * match without adding it here is a format nobody can reach.
     */
    public const CHOOSABLE = [
        'money'  => ['posted_cur', 'null_pcur', 'def_cur', 'null_dcur',
                     'dlr', 'null-dlr', 'euro'],
        'number' => ['rnd_dec', 'rnd_pre', 'rnd2d', 'neg', 'n2wrd'],
        'text'   => ['date', 'uc', 'lc', 'blank'],
        'code'   => ['terms', 'ship_name', 'j_desc', 'yesBno', 'NewUsed',
                     'printed', 'coa_type', 'cvt_iso2', 'cvt_cntry'],
    ];

    /**
     * The language constant naming a hint, e.g. 'null-dlr' -> FORMS_FORMAT_NULL_DLR.
     *
     * A hint's stored name is not a label — `posted_cur` tells an author
     * nothing, and putting the raw names in a select would trade a spelling
     * problem for a comprehension one.
     */
    public static function labelConstant(string $hint): string
    {
        return 'FORMS_FORMAT_' . strtoupper(str_replace('-', '_', $hint));
    }

    /** @var array<string,true> hints seen that this class will not perform */
    private array $unresolved = [];

    /**
     * @param array<string,array<string|int,string>> $lookups hint => [code => label]
     */
    public function __construct(
        private readonly ?object $currencies      = null,
        private readonly string  $defaultCurrency = '',
        private readonly array   $lookups         = [],
    ) {}

    /**
     * Render one value for display.
     *
     * $recordCurrency is the currency the record was POSTED in, which is what
     * posted_cur/null_pcur need; it is per-record, not per-document.
     */
    public function format(TypedValue $value, ?Format $format = null, ?Currency $recordCurrency = null): string
    {
        $raw  = $value->raw;
        $name = $format?->name ?? '';

        if ($name === '') return $this->plain($value);

        if (in_array($name, self::RESOLUTION_HINTS, true)) {
            $this->unresolved[$name] = true;
            return (string)($raw ?? '');       // pass through; the provider should have resolved it
        }

        if (in_array($name, self::LOOKUP_HINTS, true)) {
            return $this->lookup($name, $raw);
        }

        // Non-numeric input falls through unchanged for every numeric hint,
        // matching ProcessData()'s is_numeric() guards.
        $numeric = is_numeric($raw);

        return match ($name) {
            'blank'                   => '',
            'uc'                      => $this->upper((string)($raw ?? '')),
            'lc'                      => $this->lower((string)($raw ?? '')),
            'neg'                     => $numeric ? (string)(-$raw) : (string)($raw ?? ''),
            'yesBno'                  => $raw ? $this->constant('TEXT_YES', 'Yes') : '',
            'printed'                 => $raw ? '' : $this->constant('TEXT_DUPLICATE', 'Duplicate'),
            'NewUsed'                 => $raw === 'u' ? $this->constant('TEXT_USED', 'Used') : $this->constant('TEXT_NEW', 'New'),
            'j_desc'                  => $this->journalDescription($raw),
            'date'                    => $this->date($raw),
            'rnd2d'                   => $numeric ? number_format(round((float)$raw, 2), 2, '.', '') : (string)($raw ?? ''),
            'dlr'                     => $numeric ? '$ ' . number_format(round((float)$raw, 2), 2) : (string)($raw ?? ''),
            'null-dlr'                => !$numeric ? (string)($raw ?? '') : (((float)$raw == 0) ? '' : '$ ' . number_format((float)$raw, 2)),
            'euro'                    => $numeric ? chr(128) . ' ' . number_format(round((float)$raw, 2), 2) : (string)($raw ?? ''),
            'n2wrd'                   => $this->words($raw),
            'terms'                   => function_exists('gen_terms_to_language') ? (string)gen_terms_to_language($raw) : (string)($raw ?? ''),
            'ship_name'               => $this->shipName((string)($raw ?? '')),
            'rnd_dec'                 => $numeric ? $this->cur('format',  (float)$raw) : (string)($raw ?? ''),
            'rnd_pre'                 => $numeric ? $this->cur('precise', (float)$raw) : (string)($raw ?? ''),
            'def_cur'                 => $numeric ? $this->money((float)$raw, null) : (string)($raw ?? ''),
            'null_dcur'               => !$numeric ? (string)($raw ?? '') : (((float)$raw == 0) ? '' : $this->money((float)$raw, null)),
            'posted_cur'              => $numeric ? $this->money((float)$raw, $recordCurrency) : (string)($raw ?? ''),
            'null_pcur'               => !$numeric ? (string)($raw ?? '') : (((float)$raw == 0) ? '' : $this->money((float)$raw, $recordCurrency)),
            default                   => $this->plain($value),   // unknown hint: leave the value alone
        };
    }

    /**
     * Join a rendered part to the next one — PhreeForm's AddSep().
     * 'del-nl' drops the line entirely when the value is empty, which is how
     * address blocks collapse missing address lines.
     */
    public function separator(string $value, string $separator): string
    {
        return match ($separator) {
            'sp'       => $value . ' ',
            '2sp'      => $value . '  ',
            'comma'    => $value . ',',
            'com-sp'   => $value . ', ',
            'nl'       => $value . "\n",
            'semi-sp'  => $value . '; ',
            'del-nl'   => $value === '' ? '' : $value . "\n",
            default    => $value,
        };
    }

    /** Hints encountered that this class deliberately does not perform. @return list<string> */
    public function unresolvedHints(): array
    {
        return array_keys($this->unresolved);
    }

    // ── internals ────────────────────────────────────────────────────────────

    /** No hint given: show the value in a reasonable default form for its type. */
    private function plain(TypedValue $value): string
    {
        if ($value->raw === null) return '';

        // Deliberately no per-type conversion, Boolean included. "Yes"/"" reads
        // better, and is wrong: with no hint PhreeForm's ProcessData() returns
        // the value untouched, and `yesBno` is how a definition asks for
        // Yes/blank. Converting anyway would change every unhinted boolean
        // column in every converted report — the Vendor List's Inactive column
        // of 0s and 1s would print blanks.
        return (string)$value->raw;
    }

    /** Code -> label from the injected table; unknown codes show as themselves. */
    private function lookup(string $hint, mixed $raw): string
    {
        $key = (string)($raw ?? '');
        return (string)($this->lookups[$hint][$key] ?? $key);
    }

    /** Journal number -> its language constant, e.g. 6 -> GEN_ADM_TOOLS_J06. */
    private function journalDescription(mixed $raw): string
    {
        $const = 'GEN_ADM_TOOLS_J' . str_pad((string)($raw ?? ''), 2, '0', STR_PAD_LEFT);
        return defined($const) ? (string)constant($const) : (string)($raw ?? '');
    }

    private function money(float $number, ?Currency $currency): string
    {
        if ($this->currencies === null) return number_format($number, 2, '.', '');
        $code = $currency->code ?? ($this->defaultCurrency ?: $this->constant('DEFAULT_CURRENCY', ''));
        $rate = $currency->rate ?? '';
        return (string)$this->currencies->format_full($number, true, $code, $rate);
    }

    private function cur(string $method, float $number): string
    {
        if ($this->currencies === null) return number_format($number, 2, '.', '');
        return (string)$this->currencies->$method($number);
    }

    private function date(mixed $raw): string
    {
        if ($raw === null || $raw === '') return '';
        return function_exists('gen_locale_date') ? (string)gen_locale_date((string)$raw) : (string)$raw;
    }

    private function shipName(string $id): string
    {
        // Falsy, not empty-string: a stored "0:" means "no carrier", and
        // shipping_get_name()'s `if (!$parts[0])` treats "0" as absent. An
        // === '' test instead renders the raw id in the Ship Via box.
        if (!$id) return '';
        $parts = explode(':', $id);
        if (!($parts[0] ?? '')) return '';
        $const = $parts[0] . '_' . ($parts[1] ?? '');
        return defined($const) ? (string)constant($const) : $id;
    }

    private function upper(string $s): string
    {
        return function_exists('strtoupper_utf8') ? strtoupper_utf8($s) : mb_strtoupper($s);
    }

    private function lower(string $s): string
    {
        return function_exists('strtolower_utf8') ? strtolower_utf8($s) : mb_strtolower($s);
    }

    /**
     * An amount written out in words, for the line on a cheque.
     *
     * Forms' own, reproducing PhreeForm's value_to_words_en_us() exactly — same
     * recursion, same order, same spacing, same hyphen between tens and units,
     * and the same `TEXT_HUNDERD` misspelling, which is a language KEY and not
     * a word — so converted cheques print unchanged. Nothing else supplies it:
     * without it `n2wrd` would print the NUMBER on every cheque, and nobody
     * would see a failure — they would see a cheque.
     *
     * en_us in its bones: it says DOLLARS, and it writes the cents as `NN/100`.
     * That is what the shipped cheque forms expect. A locale that needs
     * otherwise needs a different hint, not a different table.
     */
    private function words(mixed $raw): string
    {
        if (!is_numeric($raw)) return (string)($raw ?? '');

        $number  = round((float)$raw, 2);
        $dollars = (int)$number;
        $cents   = (string)round(($number - $dollars) * 100);
        if (strlen($cents) === 1) $cents = '0' . $cents;

        $output = $dollars < 1
            ? $this->constant('TEXT_ZERO', 'Zero')
            : $this->thousands($dollars, ['', ' ' . $this->constant('TEXT_THOUSAND', 'Thousand'),
                                              ' ' . $this->constant('TEXT_MILLION',  'Million'),
                                              ' ' . $this->constant('TEXT_BILLION',  'Billion'),
                                              ' ' . $this->constant('TEXT_TRILLION', 'Trillion')]);

        return $this->upper($output . ' ' . $this->constant('TEXT_DOLLARS', 'Dollars')
             . ' ' . $this->constant('TEXT_AND', 'and') . ' ' . $cents . '/100');
    }

    /** @param list<string> $position suffixes, smallest first; consumed by recursion */
    private function thousands(int $number, array $position): string
    {
        $output   = '';
        $suffix   = array_shift($position);
        $tens     = $number % 100;
        $number   = intdiv($number, 100);
        $hundreds = $number % 10;
        $number   = intdiv($number, 10);

        if ($number >= 1) $output = $this->thousands($number, $position);
        if ($hundreds >= 1) {
            $output .= ' ' . $this->digit($hundreds) . ' ' . $this->constant('TEXT_HUNDERD', 'Hundred');
        }
        return $output . $this->hundreds($tens) . $suffix;
    }

    private function hundreds(int $number): string
    {
        // The teens are their own words, not tens-plus-units.
        if ($number > 9 && $number < 20) {
            return ' ' . $this->constant(self::TEENS[$number], self::TEENS_EN[$number]);
        }
        $output = '';
        $tens   = intdiv($number, 10);
        if ($tens >= 2) $output .= ' ' . $this->constant(self::TENS[$tens], self::TENS_EN[$tens]);

        $ones = $number % 10;
        // A hyphen only when there is a tens word before it: "twenty-one", but
        // " one". The space is what the caller's concatenation expects.
        if ($ones >= 1) $output .= ($output !== '' ? '-' : ' ') . $this->digit($ones);
        return $output;
    }

    private function digit(int $n): string
    {
        return $this->constant(self::UNITS[$n] ?? '', self::UNITS_EN[$n] ?? (string)$n);
    }

    private function constant(string $name, string $fallback): string
    {
        return defined($name) ? (string)constant($name) : $fallback;
    }
}
