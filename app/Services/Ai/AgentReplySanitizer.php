<?php

namespace App\Services\Ai;

/**
 * Bersihkan atribusi dokumen dari balasan asisten sebelum sampai ke user.
 *
 * Model tetap wajib memakai search_docs; sitasi file hanya untuk pemakaian
 * internal dan tidak ditampilkan di UI.
 */
class AgentReplySanitizer
{
    public static function stripSourceCitations(string $content): string
    {
        $cleaned = preg_replace('/\(\s*sumber\s*:\s*[^)]+\)/iu', '', $content) ?? $content;
        $cleaned = preg_replace('/[ \t]{2,}/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/[ \t]+([,.;:!?])/u', '$1', $cleaned) ?? $cleaned;
        $cleaned = preg_replace("/[ \t]+\n/u", "\n", $cleaned) ?? $cleaned;
        $cleaned = preg_replace("/\n{3,}/u", "\n\n", $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    public static function asksToPressConfirmationButton(string $content): bool
    {
        return (bool) preg_match(self::confirmationCtaPattern(), $content);
    }

    /**
     * Tool messages sometimes instruct the model ("User harus menekan tombol…").
     * Those must not be shown verbatim in the widget.
     */
    public static function userFacingConfirmationMessage(string $content): string
    {
        $rewritten = preg_replace(
            '/User harus menekan tombol konfirmasi di chat sebelum[^.!\n]*[.!]?/iu',
            'Tekan Konfirmasi di kartu di bawah.',
            $content
        ) ?? $content;

        return trim($rewritten);
    }

    /**
     * If the model asked the user to press a chat button but no action_card
     * was attached, strip that CTA so the user is not left looking for buttons.
     */
    public static function withoutOrphanConfirmationCta(string $content): string
    {
        if (! self::asksToPressConfirmationButton($content)) {
            return trim($content);
        }

        $cleaned = preg_replace(
            '/[^\n]*(?:tekan tombol konfirmasi|tombol konfirmasi di chat|silakan tekan tombol(?:\s+konfirmasi)?|kartu konfirmasi)[^\n]*/iu',
            '',
            $content
        ) ?? $content;

        $cleaned = preg_replace(
            '/[^.!\n]*(?:tekan tombol konfirmasi|tombol konfirmasi di chat|silakan tekan tombol(?:\s+konfirmasi)?)[^.!\n]*[.!]?/iu',
            '',
            $cleaned
        ) ?? $cleaned;

        $cleaned = preg_replace("/[ \t]+\n/u", "\n", $cleaned) ?? $cleaned;
        $cleaned = preg_replace("/\n{3,}/u", "\n\n", $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        if ($cleaned === '' || self::asksToPressConfirmationButton($cleaned)) {
            return 'Kartu konfirmasi belum siap. Kirim ulang permintaan Anda.';
        }

        return $cleaned;
    }

    protected static function confirmationCtaPattern(): string
    {
        return '/tekan tombol konfirmasi|tombol konfirmasi di chat|silakan tekan tombol|kartu konfirmasi/iu';
    }
}
