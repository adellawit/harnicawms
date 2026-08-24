<?php

namespace App\Services\Ai\Actions;

/**
 * Baca name/code/fields_json dari argumen manage_record.
 *
 * Called from ProductChatService, StockChatService, PurchaseOrderChatService,
 * JournalChatService, ProductionChatService, ReplenishmentChatService.
 * EmployeeChatFieldMapper stays employee-specific.
 */
class ChatFields
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function extra(array $arguments): array
    {
        $raw = $arguments['fields_json'] ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $keys
     */
    public static function string(array $arguments, array $keys, mixed $direct = null): ?string
    {
        if (is_string($direct) && trim($direct) !== '') {
            return trim($direct);
        }

        $extra = self::extra($arguments);

        foreach ($keys as $key) {
            $value = $extra[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_int($value) || is_float($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $keys
     */
    public static function bool(array $arguments, array $keys): ?bool
    {
        $extra = self::extra($arguments);

        foreach ($keys as $key) {
            if (! array_key_exists($key, $extra)) {
                continue;
            }

            $parsed = self::parseBool($extra[$key]);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $keys
     */
    public static function float(array $arguments, array $keys): ?float
    {
        $extra = self::extra($arguments);

        foreach ($keys as $key) {
            $value = $extra[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                return (float) $value;
            }

            if (is_string($value)) {
                $normalized = str_replace([' ', ','], ['', '.'], $value);
                $normalized = preg_replace('/[^0-9.\-]/', '', $normalized) ?? '';
                if ($normalized !== '' && is_numeric($normalized)) {
                    return (float) $normalized;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $keys
     * @return array<int, mixed>|null
     */
    public static function array(array $arguments, array $keys): ?array
    {
        $extra = self::extra($arguments);

        foreach ($keys as $key) {
            $value = $extra[$key] ?? null;
            if (is_array($value) && $value !== []) {
                return $value;
            }
        }

        return null;
    }

    public static function parseBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) !== 0;
        }

        if (! is_string($value)) {
            return null;
        }

        $key = strtolower((string) preg_replace('/\s+/u', '', trim($value)));

        return match (true) {
            in_array($key, ['1', 'true', 'ya', 'yes', 'y', 'dijual', 'sale', 'jual', 'aktif'], true) => true,
            in_array($key, ['0', 'false', 'tidak', 'no', 'n', 'bukan', 'tidakdijual', 'nonsale'], true) => false,
            default => null,
        };
    }

    /**
     * @param  list<string>  $missing
     * @return array<string, mixed>
     */
    public static function missing(array $missing, string $message): array
    {
        return [
            'success' => false,
            'missing' => array_values($missing),
            'message' => $message,
        ];
    }
}
