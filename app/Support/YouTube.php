<?php

namespace App\Support;

class YouTube
{
    /**
     * Extract the 11-char video id from any common YouTube URL form.
     * Supports: youtu.be/<id>, youtube.com/watch?v=<id>, /embed/<id>, /shorts/<id>.
     */
    public static function embedId(string $url): ?string
    {
        $url = trim($url);
        $patterns = [
            '~youtu\.be/([A-Za-z0-9_-]{11})~',
            '~youtube\.com/watch\?[^ ]*v=([A-Za-z0-9_-]{11})~',
            '~youtube\.com/embed/([A-Za-z0-9_-]{11})~',
            '~youtube\.com/shorts/([A-Za-z0-9_-]{11})~',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $url, $m)) {
                return $m[1];
            }
        }
        return null;
    }
}
