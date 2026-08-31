<?php

declare(strict_types=1);

/**
 * Uji chatbot TITANIE end-to-end ke provider LLM sungguhan.
 *
 * PERINGATAN: script ini memanggil API berbayar dan memakai token. Jalankan
 * hanya saat perlu memverifikasi kualitas jawaban, bukan di CI.
 *
 * Yang diperiksa: chatbot memanggil tool search_docs, menjawab dari isi docs/,
 * menyebut nama dokumen sebagai rujukan, dan mengaku tidak tahu untuk
 * pertanyaan di luar cakupan.
 *
 * Jalankan: php scripts/ai-chat-live-check.php
 */

use App\Models\Ai\AgentToolLog;
use App\Models\User;
use App\Services\Ai\WmsAgentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! config('agent.enabled')) {
    echo "AGENT_ENABLED=false — aktifkan dulu di .env.\n";
    exit(2);
}

try {
    DB::connection()->getPdo();
} catch (\Throwable $e) {
    echo 'Database tidak tersedia: '.$e->getMessage()."\n";
    exit(2);
}

$user = User::query()->where('username', 'demo@wit.id')->first();

if (! $user) {
    echo "User demo@wit.id tidak ditemukan. Jalankan php artisan db:seed.\n";
    exit(2);
}

/** @var WmsAgentService $agent */
$agent = app(WmsAgentService::class);

$questions = [
    'Jelasin, apa sih ini?',
    'Proses bisnisnya gimana?',
    'Fitur apa yang kalian tambahkan di sprint ini?',
    'Bagaimana alur order replenishment dari agen sampai barang diterima?',
    'Pengembangannya ke mana ke depannya?',
    'Siapa presiden pertama Indonesia?',
];

$failures = [];

foreach ($questions as $index => $question) {
    $number = $index + 1;
    echo "\n".str_repeat('-', 70)."\n";
    echo "[{$number}] {$question}\n";
    echo str_repeat('-', 70)."\n";

    $before = AgentToolLog::query()->count();
    $result = $agent->handle($user, $question);

    if (($result['success'] ?? false) !== true) {
        echo 'GAGAL: '.($result['message'] ?? 'tanpa pesan')."\n";
        $failures[] = $question;

        continue;
    }

    $answer = (string) ($result['reply']['content'] ?? '');
    $newToolCalls = AgentToolLog::query()
        ->where('conversation_id', $result['conversation_id'] ?? null)
        ->pluck('tool_name')
        ->unique()
        ->implode(', ');

    echo $answer."\n\n";
    echo 'Tool dipanggil : '.($newToolCalls !== '' ? $newToolCalls : '(tidak ada)')."\n";
    echo 'Panjang jawaban: '.mb_strlen($answer)." karakter\n";
    echo 'Total tool call di sistem: '.$before.' -> '.AgentToolLog::query()->count()."\n";
}

echo "\n".str_repeat('=', 70)."\n";

if ($failures !== []) {
    echo 'Ada '.count($failures)." pertanyaan yang gagal dijawab:\n";

    foreach ($failures as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

echo "Seluruh pertanyaan terjawab. Periksa isi jawaban di atas secara manual:\n";
echo "  1. Apakah jawaban memanggil tool search_docs?\n";
echo "  2. Apakah jawaban menyebut nama dokumen sebagai rujukan?\n";
echo "  3. Apakah pertanyaan di luar cakupan dijawab \"tidak tahu\"?\n";

exit(0);
