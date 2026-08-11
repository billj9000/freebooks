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

    /** Code-to-label hints served from $lookups — no query, so they stay here. */
    public const LOOKUP_HINTS = ['coa_type', 'cvt_iso2', 'cvt_cntry'];

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
            'n2wrd'                   => function_exists('value_to_words_en_us') ? value_to_words_en_us($raw) : (string)($raw ?? ''),
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
        return match ($value->type) {
            ValueType::Boolean => $value->raw ? $this->constant('TEXT_YES', 'Yes') : '',
            default            => (string)$value->raw,
        };
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

    private function constant(string $name, string $fallback): string
    {
        return defined($name) ? (string)constant($name) : $fallback;
    }
}
