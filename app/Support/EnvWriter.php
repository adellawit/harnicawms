<?php

namespace App\Support;

use RuntimeException;

class EnvWriter
{
    public function __construct(
        protected ?string $path = null,
    ) {
        $this->path = $path ?? base_path('.env');
    }

    public function get(string $key, ?string $default = null): ?string
    {
        if (! is_readable($this->path)) {
            return $default;
        }

        $content = (string) file_get_contents($this->path);

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $content, $matches)) {
            return $this->parseValue(trim($matches[1]));
        }

        return $default;
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    public function setMany(array $values): void
    {
        if (! is_readable($this->path) || ! is_writable($this->path)) {
            throw new RuntimeException('.env tidak dapat dibaca atau ditulis.');
        }

        $content = (string) file_get_contents($this->path);

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $line = $this->formatLine($key, $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';

            if (preg_match($pattern, $content)) {
                $content = (string) preg_replace($pattern, $line, $content);
            } else {
                $content = rtrim($content).PHP_EOL.$line.PHP_EOL;
            }
        }

        file_put_contents($this->path, $content);
    }

    public function maskSecret(?string $value, int $visibleChars = 18): string
    {
        if (! filled($value)) {
            return '';
        }

        $length = strlen($value);
        $visible = min($visibleChars, max(4, $length - 8));

        return substr($value, 0, $visible).str_repeat('x', max(8, $length - $visible));
    }

    protected function formatLine(string $key, mixed $value): string
    {
        $stringValue = $this->stringify($value);

        if (preg_match('/[\s#"]/', $stringValue) === 1) {
            return $key.'="'.str_replace('"', '\"', $stringValue).'"';
        }

        return $key.'='.$stringValue;
    }

    protected function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    protected function parseValue(string $raw): string
    {
        if (
            (str_starts_with($raw, '"') && str_ends_with($raw, '"'))
            || (str_starts_with($raw, "'") && str_ends_with($raw, "'"))
        ) {
            return substr($raw, 1, -1);
        }

        return $raw;
    }
}
