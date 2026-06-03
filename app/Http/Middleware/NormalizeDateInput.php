<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NormalizeDateInput
{
    public function handle(Request $request, Closure $next)
    {
        $input = $request->all();
        $request->merge($this->normalizePayloadDates($input));

        return $next($request);
    }

    private function normalizePayloadDates(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->normalizePayloadDates($value);
                continue;
            }

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            if (! $this->isDateFieldKey((string) $key)) {
                continue;
            }

            $payload[$key] = $this->normalizeDateValue($value);
        }

        return $payload;
    }

    private function isDateFieldKey(string $key): bool
    {
        $k = strtolower($key);

        return str_contains($k, 'date')
            || str_ends_with($k, '_at')
            || str_ends_with($k, '_from')
            || str_ends_with($k, '_to');
    }

    private function normalizeDateValue(string $value): string
    {
        $value = trim($value);

        // dd/mm/yyyy
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // dd/mm/yyyy HH:ii
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:{$m[5]}:00";
        }

        // dd/mm/yyyy HH:ii:ss
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2}):(\d{2})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:{$m[5]}:{$m[6]}";
        }

        return $value;
    }
}

