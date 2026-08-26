<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AgentContext;
use App\Services\Ai\Docs\DocsKnowledgeBase;

/**
 * Tool FAQ: menjawab pertanyaan seputar aplikasi dari dokumentasi di docs/.
 *
 * Tidak butuh permission karena isinya dokumentasi produk, bukan data operasional.
 */
class SearchDocsTool extends AbstractAgentTool
{
    public function __construct(
        protected DocsKnowledgeBase $docs,
    ) {}

    public function name(): string
    {
        return 'search_docs';
    }

    public function description(): string
    {
        return 'Cari jawaban di dokumentasi resmi project (folder docs/). Gunakan untuk semua '
            .'pertanyaan tentang aplikasi ini: apa itu aplikasinya, alur bisnis, cara pakai '
            .'sebuah fitur, arsitektur, modul yang tersedia, scope pengerjaan, dan rencana '
            .'pengembangan. Selalu panggil tool ini sebelum menjawab pertanyaan seputar produk.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['query'],
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Kata kunci pertanyaan user, contoh: "alur replenishment order", '
                        .'"cara agen membuat pesanan", "modul apa saja yang ada".',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?array
    {
        return null;
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        $results = $query === '' ? [] : $this->docs->search($query);
        $isFallback = $results === [];

        if ($isFallback) {
            $results = $this->docs->overview();
        }

        if ($results === []) {
            return [
                'success' => false,
                'query' => $query,
                'message' => 'Dokumentasi tidak ditemukan. Sampaikan ke user bahwa informasi ini '
                    .'belum ada di dokumentasi, jangan mengarang jawaban.',
                'available_documents' => $this->docs->documents(),
            ];
        }

        return [
            'success' => true,
            'query' => $query,
            'is_fallback' => $isFallback,
            'source' => 'docs/',
            'note' => $isFallback
                ? 'Tidak ada kecocokan kata kunci. Ini ringkasan umum project — pakai hanya bila relevan.'
                : 'Jawab hanya berdasarkan kutipan di bawah. Jangan sebutkan nama file, path docs/, atau "(sumber: ...)" kepada user.',
            'sections' => $results,
            'available_documents' => $this->docs->documents(),
        ];
    }
}
