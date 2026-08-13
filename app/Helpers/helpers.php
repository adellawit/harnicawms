<?php

use Carbon\Carbon;

if (! function_exists('format_number')) {
    /**
     * Format number for display: remove trailing zeros and optional thousand separators.
     * Use in Blade: {{ format_number($value) }} or {{ format_number($value, 10, true) }}
     *
     * Without thousands: 6.000000 → "6", 10.5 → "10.5"
     * With thousands (ID): 10000 → "10.000", 100000 → "100.000", 10000.5 → "10.000,5"
     *
     * @param  float|int|string|null  $value
     * @param  int  $maxDecimals  Maximum decimal places before trimming (avoids float precision noise)
     * @param  bool  $thousands  Use thousand separator (dot) and comma as decimal separator (ID style)
     */
    function format_number($value, int $maxDecimals = 10, bool $thousands = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $num = (float) $value;

        if ($thousands) {
            $formatted = number_format($num, $maxDecimals, ',', '.');
            // Only trim trailing zeros AFTER the decimal comma.
            // Never rtrim the whole string — that would turn "249.000" into "249.".
            if (str_contains($formatted, ',')) {
                $formatted = rtrim(rtrim($formatted, '0'), ',');
            }

            return $formatted === '' ? '0' : $formatted;
        }

        $formatted = number_format($num, $maxDecimals, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}

if (! function_exists('normalize_number_input')) {
    /**
     * Normalize user input for storage.
     * Handles Indonesian format (dot = thousands, comma = decimal) and standard format.
     *
     * "50.000"   → 50000    (ID thousands)
     * "50.000,5" → 50000.5  (ID thousands + decimal)
     * "50000"    → 50000    (plain)
     * "50000.5"  → 50000.5  (standard decimal)
     * "10,5"     → 10.5     (ID decimal only)
     *
     * @param  mixed  $value
     * @return float|null Null if input is empty/null
     */
    function normalize_number_input($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $trimmed = is_string($value) ? trim($value) : $value;
        if ($trimmed === '') {
            return null;
        }
        if (! is_string($trimmed)) {
            return (float) $trimmed;
        }

        $hasComma = str_contains($trimmed, ',');
        $hasDot = str_contains($trimmed, '.');

        if ($hasComma && $hasDot) {
            // Indonesian format: "50.000,5" → strip dots, replace comma with dot
            $trimmed = str_replace('.', '', $trimmed);
            $trimmed = str_replace(',', '.', $trimmed);
        } elseif ($hasComma && ! $hasDot) {
            // Comma as decimal: "10,5" → "10.5"
            $trimmed = str_replace(',', '.', $trimmed);
        } elseif ($hasDot && ! $hasComma) {
            // Could be "50.000" (ID thousands) or "50.5" (standard decimal)
            // Heuristic: if all groups after dots are exactly 3 digits → thousands separator
            $parts = explode('.', $trimmed);
            $isThousands = count($parts) > 1;
            for ($i = 1; $i < count($parts); $i++) {
                if (strlen($parts[$i]) !== 3) {
                    $isThousands = false;
                    break;
                }
            }
            if ($isThousands && count($parts) > 1) {
                $trimmed = str_replace('.', '', $trimmed);
            }
            // Otherwise keep as standard decimal (e.g. "50.5")
        }

        return (float) $trimmed;
    }
}

if (! function_exists('format_date_id')) {
    /**
     * Format date string/value to Indonesian display format (dd/mm/yyyy).
     *
     * @param  mixed  $value
     */
    function format_date_id($value, bool $withTime = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $date = $value instanceof Carbon ? $value : Carbon::parse($value);

            return $date->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}

if (! function_exists('product_print_name')) {
    /**
     * Product name for invoice/shipping print — strip trailing type/category suffix
     * e.g. "Foredi (Barang Jadi)" → "Foredi".
     */
    function product_print_name(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '-';
        }

        $clean = preg_replace('/\s*\([^)]*\)\s*$/u', '', $name);

        return trim((string) $clean) !== '' ? trim((string) $clean) : $name;
    }
}
