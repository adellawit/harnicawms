<?php

declare(strict_types=1);

/**
 * Smoke test basis pengetahuan chatbot TITANIE.
 *
 * Memastikan tool search_docs benar-benar mengambil jawaban dari file markdown
 * di docs/, bukan dari teks yang ditulis di system prompt, dan tidak crash saat
 * input kosong atau pertanyaan di luar cakupan.
 *
 * Jalankan: php scripts/ai-docs-knowledge-test.php
 * Tidak butuh koneksi database.
 */

use App\Models\User;
use App\Services\Ai\AgentContext;
use App\Services\Ai\AgentReplySanitizer;
use App\Services\Ai\Docs\DocsKnowledgeBase;
use App\Services\Ai\Tools\SearchDocsTool;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$failures = [];
$checks = 0;

function check(string $label, bool $condition): void
{
    global $failures, $checks;

    $checks++;

    if ($condition) {
        echo "  [OK]   {$label}\n";

        return;
    }

    echo "  [FAIL] {$label}\n";
    $failures[] = $label;
}

/** @var DocsKnowledgeBase $docs */
$docs = app(DocsKnowledgeBase::class);

echo "\n=== 1. Dokumen wajib sprint terbaca ===\n";

$documents = $docs->documents();
$required = ['SCOPE.md', 'PRD.md', 'ARCHITECTURE.md', 'AI_CONTEXT.md', 'PROMPTS.md'];

foreach ($required as $file) {
    check("{$file} terbaca sebagai sumber pengetahuan", in_array($file, $documents, true));
}

echo "\n=== 2. Lima pertanyaan Product Knowledge ===\n";

$questions = [
    'Jelasin, apa sih ini?',
    'Proses bisnisnya gimana?',
    'Fitur apa yang kalian tambahkan?',
    'Apa yang kalian implementasikan?',
    'Pengembangannya ke mana ke depannya?',
];

$directMatches = 0;

foreach ($questions as $question) {
    $results = $docs->search($question);

    if ($results === []) {
        // Pertanyaan terlalu umum — harus jatuh ke ringkasan, bukan kosong.
        check("\"{$question}\" dijawab lewat ringkasan docs", $docs->overview() !== []);

        continue;
    }

    $directMatches++;
    $documents = implode(', ', array_unique(array_column($results, 'document')));
    check("\"{$question}\" menemukan kutipan langsung di {$documents}", true);
}

check("minimal 3 dari 5 pertanyaan menemukan kutipan langsung (dapat {$directMatches})", $directMatches >= 3);

echo "\n=== 3. Pertanyaan spesifik menemukan dokumen yang tepat ===\n";

$expectations = [
    'alur replenishment order distributor ke agen' => 'replenishment',
    'apa saja yang tidak dikerjakan di sprint ini' => null,
    'konvensi database multi schema' => 'schema',
];

foreach ($expectations as $query => $mustContain) {
    $results = $docs->search($query, 3);
    $joined = mb_strtolower(json_encode($results, JSON_UNESCAPED_UNICODE) ?: '');

    check("\"{$query}\" mengembalikan hasil", $results !== []);

    if ($mustContain !== null) {
        check("  hasilnya memuat kata \"{$mustContain}\"", str_contains($joined, $mustContain));
    }
}

echo "\n=== 4. Ketahanan terhadap input bermasalah ===\n";

$edgeCases = [
    'string kosong' => '',
    'hanya spasi' => '   ',
    'hanya stopword' => 'apa ini',
    'hanya tanda baca' => '!!!???',
    'sangat panjang' => str_repeat('lorem ipsum ', 400),
    'karakter aneh' => "\x00\x01 <script>alert(1)</script>",
    'emoji' => 'apa ini 🤖🌿',
];

foreach ($edgeCases as $label => $input) {
    try {
        $results = $docs->search($input);
        check("input {$label} tidak error (".count($results).' hasil)', true);
    } catch (Throwable $e) {
        check("input {$label} tidak error — malah: ".$e->getMessage(), false);
    }
}

echo "\n=== 5. Tool search_docs berperilaku benar ===\n";

$tool = app(SearchDocsTool::class);

check('nama tool adalah search_docs', $tool->name() === 'search_docs');
check('tool tidak butuh permission (bisa dipakai semua user)', $tool->requiredPermission() === null);
check('parameter query wajib', in_array('query', $tool->parameters()['required'] ?? [], true));

echo "\n=== 6. Batas pengetahuan dihormati ===\n";

// Pertanyaan yang kosakatanya asing bagi dokumentasi harus ditolak di lapisan
// pencarian.
$outOfScopeQuestions = [
    'resep rendang padang sapi',
    'cuaca besok hujan atau tidak',
    'harga bitcoin sekarang berapa dollar',
];

foreach ($outOfScopeQuestions as $question) {
    check(
        "\"{$question}\" ditolak di lapisan pencarian",
        $docs->search($question) === [],
    );
}

// Pertanyaan luar cakupan yang kebetulan memakai kata umum — misalnya "pertama"
// dan "indonesia" yang memang ada di dokumentasi — tidak bisa disaring oleh
// pencarian kata kunci. Yang dijamin di sini: hasil tool selalu membawa perintah
// eksplisit agar model menjawab hanya dari kutipan, sehingga tidak mengarang.
$context = new AgentContext(
    user: new User,
    branchId: null,
    companyId: null,
    branchName: null,
    permissions: [],
);

$result = $tool->execute(['query' => 'siapa presiden pertama indonesia'], $context);

check('hasil tool selalu menyebut sumbernya docs/', ($result['source'] ?? null) === 'docs/');
check(
    'hasil tool memerintahkan model menjawab hanya dari kutipan',
    str_contains(mb_strtolower((string) ($result['note'] ?? '')), 'kutipan')
        || str_contains(mb_strtolower((string) ($result['message'] ?? '')), 'jangan mengarang'),
);
check(
    'hasil tool tidak menyuruh menyebut nama dokumen ke user',
    ! str_contains(mb_strtolower((string) ($result['note'] ?? '')), 'sebutkan nama dokumen'),
);

echo "\n=== 7. Sitasi dokumen disembunyikan dari user ===\n";

$sanitized = AgentReplySanitizer::stripSourceCitations(
    'TITANIE adalah WMS (sumber: TASK_BY_MODULE_SPREADSHEET.md). Alur order ada di portal (sumber: EPIC_TASK_BY_MODULE.md).',
);
check(
    'sanitizer menghapus (sumber: ....md)',
    $sanitized === 'TITANIE adalah WMS. Alur order ada di portal.',
);

$sanitizedEmptyish = AgentReplySanitizer::stripSourceCitations('(sumber: ARCHITECTURE.md)');
check('sitasi saja menjadi string kosong', $sanitizedEmptyish === '');

$promptSource = file_get_contents(__DIR__.'/../app/Services/Ai/WmsAgentService.php');
check(
    'system prompt tidak menyuruh sebutkan nama dokumen sumbernya',
    is_string($promptSource) && ! str_contains($promptSource, 'sebutkan nama dokumen sumbernya'),
);
check(
    'system prompt melarang atribusi (sumber: ...) ke user',
    is_string($promptSource) && str_contains($promptSource, 'DILARANG menyebut'),
);

echo "\n".str_repeat('=', 60)."\n";

if ($failures === []) {
    echo "SEMUA LOLOS — {$checks} pemeriksaan\n";
    exit(0);
}

echo 'GAGAL '.count($failures)." dari {$checks} pemeriksaan:\n";

foreach ($failures as $failure) {
    echo "  - {$failure}\n";
}

exit(1);
