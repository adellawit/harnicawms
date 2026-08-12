<?php

namespace App\Services\Theme;

use App\Services\Ai\LlmProviderManager;
use RuntimeException;
use Throwable;

class ThemePaletteGenerator
{
    public function __construct(
        protected LlmProviderManager $llm,
        protected AppThemeService $theme,
    ) {}

    /**
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function generate(?string $prompt = null): array
    {
        $provider = $this->llm->current();

        if (! $provider->isConfigured()) {
            throw new RuntimeException('AI provider belum dikonfigurasi. Atur di Settings → AI Chat Configuration.');
        }

        $keys = implode(', ', $this->theme->tokenKeys());
        $hint = trim((string) $prompt);
        $userPrompt = 'Generate a cohesive UI color theme as JSON only (no markdown). '
            .'Shape: {"light":{...},"dark":{...}}. '
            .'Each of light/dark must include exactly these keys with #RRGGBB values: '.$keys.'. '
            .'Brand context: Harnica ERP / WMS admin (green-leaning professional). '
            .($hint !== '' ? 'User hint: '.$hint : 'Prefer accessible contrast.');

        try {
            $response = $provider->chat([
                ['role' => 'system', 'content' => 'You output valid JSON only. No prose.'],
                ['role' => 'user', 'content' => $userPrompt],
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal generate theme: '.$e->getMessage(), 0, $e);
        }

        $content = (string) data_get($response, 'choices.0.message.content', '');
        $json = $this->extractJson($content);

        if (! is_array($json) || ! isset($json['light'], $json['dark']) || ! is_array($json['light']) || ! is_array($json['dark'])) {
            throw new RuntimeException('Respons AI tidak berisi palette light/dark yang valid.');
        }

        return [
            'light' => $this->theme->normalizeTokens($json['light'], 'light'),
            'dark' => $this->theme->normalizeTokens($json['dark'], 'dark'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJson(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $m)) {
            $content = $m[1];
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($content, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}
