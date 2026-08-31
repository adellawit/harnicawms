<?php

namespace App\Services\Ai\Tour;

class AgentTourIntent
{
    /**
     * Short overlay commands that should keep an in-progress tour alive.
     *
     * @var list<string>
     */
    protected static array $controlPhrases = [
        'lanjut',
        'next',
        'kembali',
        'back',
        'stop',
        'cukup',
        'lewati',
        'selesai',
        'sebelumnya',
        'ruang berikutnya',
        'ruang sebelumnya',
        'stop tur',
        'stop tour',
        'hentikan tur',
        'hentikan turnya',
        'berhentiin turnya',
        'berhentikan turnya',
    ];

    public static function isControl(string $message): bool
    {
        $normalized = self::normalize($message);

        return $normalized !== '' && in_array($normalized, self::$controlPhrases, true);
    }

    public static function normalize(string $message): string
    {
        $text = mb_strtolower(trim($message));
        $text = preg_replace('/[?.!,]+$/u', '', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public static function recap(string $reason): string
    {
        return $reason === 'finish'
            ? 'Tur selesai. Kalau mau, tanya halaman ini atau mulai tur lagi.'
            : 'Tur dihentikan. Kalau mau, tanya halaman ini atau mulai tur lagi.';
    }
}
