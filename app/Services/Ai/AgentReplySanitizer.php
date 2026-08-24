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
}
