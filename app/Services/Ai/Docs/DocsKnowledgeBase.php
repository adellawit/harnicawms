<?php

namespace App\Services\Ai\Docs;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Sumber pengetahuan chatbot TITANIE: file markdown di dalam docs/.
 *
 * Jawaban chatbot harus berasal dari sini, bukan dari teks yang ditulis di
 * system prompt, sehingga dokumentasi repo tetap menjadi satu-satunya sumber
 * kebenaran dan kualitas dokumentasi langsung terlihat dari kualitas jawaban.
 */
class DocsKnowledgeBase
{
    /**
     * Kata umum bahasa Indonesia dan Inggris yang tidak membantu pencarian.
     */
    protected const STOPWORDS = [
        'apa', 'ini', 'itu', 'yang', 'dari', 'untuk', 'dan', 'atau', 'dengan',
        'pada', 'dalam', 'adalah', 'ada', 'bisa', 'gimana', 'bagaimana',
        'kenapa', 'mengapa', 'siapa', 'kapan', 'mana', 'saja', 'juga', 'akan',
        'sudah', 'belum', 'tidak', 'bukan', 'nya', 'kah', 'sih', 'dong',
        'aku', 'saya', 'kamu', 'kita', 'kami', 'mereka', 'tolong', 'jelasin',
        'jelaskan', 'the', 'and', 'for', 'what', 'how', 'why', 'does', 'are',
        'this', 'that', 'with', 'from', 'about',
    ];

    /**
     * Imbuhan akhir yang dilepas saat kata tidak ditemukan apa adanya.
     */
    protected const SUFFIXES = ['nya', 'kan', 'lah', 'ku', 'mu', 'an'];

    protected string $basePath;

    protected int $maxSectionChars;

    protected int $maxFileBytes;

    /**
     * @var array<int, string>
     */
    protected array $overviewDocuments;

    /**
     * @var array<int, string>
     */
    protected array $excludedFolders;

    /**
     * @var array<int, array{document: string, heading: string, content: string}>|null
     */
    protected ?array $cachedSections = null;

    protected ?string $cachedCorpus = null;

    public function __construct(
        ?string $basePath = null,
        ?int $maxSectionChars = null,
        ?int $maxFileBytes = null,
        ?array $overviewDocuments = null,
        ?array $excludedFolders = null,
    ) {
        $this->basePath = rtrim($basePath ?? (string) config('agent.docs.path', base_path('docs')), "/\\");
        $this->maxSectionChars = $maxSectionChars ?? (int) config('agent.docs.max_section_chars', 1600);
        $this->maxFileBytes = $maxFileBytes ?? (int) config('agent.docs.max_file_bytes', 512000);
        $this->overviewDocuments = $overviewDocuments ?? (array) config('agent.docs.overview', []);
        $this->excludedFolders = $excludedFolders ?? (array) config('agent.docs.exclude', []);
    }

    /**
     * Cari potongan dokumentasi yang paling relevan dengan pertanyaan.
     *
     * Bagian yang hanya menyenggol satu kata dari pertanyaan panjang sengaja
     * dibuang. Kutipan lemah lebih berbahaya daripada tidak ada kutipan, karena
     * bisa memancing chatbot mengarang jawaban.
     *
     * @return array<int, array{document: string, heading: string, content: string}>
     */
    public function search(string $query, int $limit = 4): array
    {
        $tokens = $this->tokenize($query);

        if ($tokens === []) {
            return [];
        }

        $known = $this->knownTokens($tokens);

        // Mayoritas kata tidak dikenal di dokumentasi — anggap pertanyaannya
        // di luar cakupan dan biarkan chatbot menjawab tidak tahu.
        if (count($known) * 2 < count($tokens)) {
            return [];
        }

        $tokens = $known;
        $minimumMatched = (int) ceil(count($tokens) / 2);
        $scored = [];

        foreach ($this->sections() as $section) {
            $relevance = $this->relevance($section, $tokens);

            if ($relevance['matched'] < $minimumMatched) {
                continue;
            }

            $scored[] = ['score' => $relevance['score'], 'section' => $section];
        }

        usort($scored, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_map(
            fn (array $entry) => $this->present($entry['section']),
            array_slice($scored, 0, max($limit, 1)),
        );
    }

    /**
     * Ringkasan pembuka dari dokumen prioritas.
     *
     * Dipakai ketika pertanyaan terlalu umum untuk dicari kata kuncinya,
     * misalnya "jelasin, apa sih ini?", supaya jawaban tetap bersumber docs.
     *
     * @return array<int, array{document: string, heading: string, content: string}>
     */
    public function overview(int $limit = 3): array
    {
        $sections = $this->sections();
        $result = [];

        foreach ($this->overviewDocuments as $document) {
            foreach ($sections as $section) {
                if ($section['document'] !== $document) {
                    continue;
                }

                $result[] = $this->present($section);
                break;
            }

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * Daftar dokumen yang tersedia, untuk memberi tahu batas pengetahuan.
     *
     * @return array<int, string>
     */
    public function documents(): array
    {
        return array_values(array_unique(array_column($this->sections(), 'document')));
    }

    /**
     * @return array<int, array{document: string, heading: string, content: string}>
     */
    protected function sections(): array
    {
        if ($this->cachedSections !== null) {
            return $this->cachedSections;
        }

        $sections = [];

        foreach ($this->files() as $path) {
            $markdown = @file_get_contents($path);

            if ($markdown === false) {
                continue;
            }

            $document = $this->relativeName($path);

            foreach ($this->splitIntoSections($markdown, $document) as $section) {
                $sections[] = $section;
            }
        }

        return $this->cachedSections = $sections;
    }

    /**
     * @return array<int, string>
     */
    protected function files(): array
    {
        if (! is_dir($this->basePath)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->basePath, FilesystemIterator::SKIP_DOTS)
        );

        $files = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }

            if ($file->getSize() > $this->maxFileBytes) {
                continue;
            }

            if ($this->isExcluded($file->getPathname())) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    protected function isExcluded(string $path): bool
    {
        $relative = $this->relativeName($path);

        foreach ($this->excludedFolders as $folder) {
            if (str_starts_with($relative, trim((string) $folder, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pecah markdown menjadi bagian per heading (level 1 sampai 3).
     *
     * @return array<int, array{document: string, heading: string, content: string}>
     */
    protected function splitIntoSections(string $markdown, string $document): array
    {
        $sections = [];
        $heading = 'Ringkasan';
        $buffer = [];

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $matches) !== 1) {
                $buffer[] = $line;

                continue;
            }

            $section = $this->makeSection($document, $heading, $buffer);

            if ($section !== null) {
                $sections[] = $section;
            }

            $heading = trim($matches[2]);
            $buffer = [];
        }

        $section = $this->makeSection($document, $heading, $buffer);

        if ($section !== null) {
            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * @param  array<int, string>  $lines
     * @return array{document: string, heading: string, content: string}|null
     */
    protected function makeSection(string $document, string $heading, array $lines): ?array
    {
        $content = trim(implode("\n", $lines));

        if ($content === '') {
            return null;
        }

        return [
            'document' => $document,
            'heading' => $heading,
            'content' => $content,
        ];
    }

    /**
     * @param  array{document: string, heading: string, content: string}  $section
     * @param  array<int, string>  $tokens
     * @return array{score: int, matched: int}
     */
    protected function relevance(array $section, array $tokens): array
    {
        $heading = mb_strtolower($section['heading'].' '.$section['document']);
        $content = mb_strtolower($section['content']);
        $score = 0;
        $matched = 0;

        foreach ($tokens as $token) {
            $inHeading = substr_count($heading, $token);
            $inContent = substr_count($content, $token);

            if ($inHeading === 0 && $inContent === 0) {
                continue;
            }

            $matched++;
            $score += ($inHeading * 8) + min($inContent, 6);
        }

        if ($matched === count($tokens)) {
            $score += 5;
        }

        return ['score' => $score, 'matched' => $matched];
    }

    /**
     * Ambil hanya kata yang benar-benar ada di dokumentasi, sekaligus
     * mengembalikannya ke bentuk yang dipakai dokumen.
     *
     * Tanpa ini, kata seperti "pengembangannya" tidak akan pernah cocok dengan
     * "pengembangan" yang tertulis di dokumen, dan kata seperti "kalian" ikut
     * dihitung sebagai kata yang harus cocok padahal cuma gaya bahasa penanya.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    protected function knownTokens(array $tokens): array
    {
        $corpus = $this->corpus();
        $known = [];

        foreach ($tokens as $token) {
            $normalized = $this->normalizeToken($token, $corpus);

            if ($normalized !== null) {
                $known[] = $normalized;
            }
        }

        return array_values(array_unique($known));
    }

    /**
     * Cocokkan kata apa adanya, lalu coba lepas imbuhan umum Bahasa Indonesia.
     */
    protected function normalizeToken(string $token, string $corpus): ?string
    {
        if (str_contains($corpus, $token)) {
            return $token;
        }

        foreach (self::SUFFIXES as $suffix) {
            if (! str_ends_with($token, $suffix)) {
                continue;
            }

            $stem = mb_substr($token, 0, mb_strlen($token) - mb_strlen($suffix));

            if (mb_strlen($stem) >= 4 && str_contains($corpus, $stem)) {
                return $stem;
            }
        }

        return null;
    }

    protected function corpus(): string
    {
        if ($this->cachedCorpus !== null) {
            return $this->cachedCorpus;
        }

        $parts = [];

        foreach ($this->sections() as $section) {
            $parts[] = $section['document'].' '.$section['heading'].' '.$section['content'];
        }

        return $this->cachedCorpus = mb_strtolower(implode("\n", $parts));
    }

    /**
     * @return array<int, string>
     */
    protected function tokenize(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = array_filter(
            $words,
            static fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, self::STOPWORDS, true),
        );

        return array_values(array_unique($tokens));
    }

    /**
     * @param  array{document: string, heading: string, content: string}  $section
     * @return array{document: string, heading: string, content: string}
     */
    protected function present(array $section): array
    {
        return [
            'document' => $section['document'],
            'heading' => $section['heading'],
            'content' => $this->truncate($section['content']),
        ];
    }

    protected function truncate(string $content): string
    {
        if (mb_strlen($content) <= $this->maxSectionChars) {
            return $content;
        }

        return mb_substr($content, 0, $this->maxSectionChars).' …';
    }

    protected function relativeName(string $path): string
    {
        $relative = ltrim(str_replace($this->basePath, '', $path), "/\\");

        return str_replace('\\', '/', $relative);
    }
}
