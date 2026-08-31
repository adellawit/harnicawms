<?php

declare(strict_types=1);

/**
 * Uji ketahanan chatbot TITANIE terhadap kondisi gagal.
 *
 * Yang diperiksa: input kosong, input kelewat panjang, agent dimatikan, dan
 * API key sengaja dibuat salah. Semuanya harus menghasilkan pesan yang ramah
 * tanpa membocorkan detail teknis, dan tidak boleh melempar exception.
 *
 * Jalankan: php scripts/ai-chat-resilience-test.php
 * Butuh koneksi database karena percakapan disimpan.
 */

use App\Models\Ai\AgentConversation;
use App\Models\Ai\AgentMessage;
use App\Models\User;
use App\Services\Ai\AgentContext;
use App\Services\Ai\AgentConversationService;
use App\Services\Ai\AgentToolRegistry;
use App\Services\Ai\WmsAgentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

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

try {
    DB::connection()->getPdo();
} catch (Throwable $e) {
    echo "\nDatabase tidak tersedia, test dihentikan.\n";
    echo 'Penyebab: '.$e->getMessage()."\n";
    echo "Siapkan database sesuai README lalu jalankan ulang.\n";
    exit(2);
}

$user = User::query()->where('username', 'demo@wit.id')->first();

if (! $user) {
    echo "\nUser demo@wit.id tidak ditemukan. Jalankan php artisan db:seed lebih dulu.\n";
    exit(2);
}

/** @var WmsAgentService $agent */
$agent = app(WmsAgentService::class);

/**
 * Detail teknis yang tidak boleh muncul di pesan untuk user.
 */
function leaksInternals(string $message): bool
{
    $forbidden = ['api.deepseek', 'api.openai', 'Bearer', 'sk-', 'cURL', 'Client error', 'GuzzleHttp', 'Stack trace'];

    foreach ($forbidden as $needle) {
        if (str_contains($message, $needle)) {
            return true;
        }
    }

    return false;
}

echo "\n=== 1. Agent dimatikan ===\n";

Config::set('agent.enabled', false);
$result = $agent->handle($user, 'apa itu aplikasi ini');

check('mengembalikan hasil, bukan exception', is_array($result));
check('ditandai gagal', ($result['success'] ?? true) === false);

// Widget membaca field "message" saat success bernilai false — lihat
// public/assets/ai/chat.js.
check('ada pesan untuk ditampilkan widget', trim((string) ($result['message'] ?? '')) !== '');

echo "\n=== 2. Input kosong dan hanya spasi ===\n";

Config::set('agent.enabled', true);
Config::set('ai.provider', 'deepseek');
Config::set('ai.providers.deepseek.enabled', true);
Config::set('ai.providers.deepseek.api_key', 'sk-sengaja-salah-untuk-uji-ketahanan');

foreach (['' => 'string kosong', '    ' => 'hanya spasi', "\n\t" => 'hanya baris baru'] as $input => $label) {
    $result = $agent->handle($user, (string) $input);

    check("{$label} ditolak dengan sopan", ($result['success'] ?? true) === false);
    check("  {$label} tidak menyimpan percakapan kosong", ($result['conversation_id'] ?? '') === '');
}

echo "\n=== 3. Input kelewat panjang ===\n";

$result = $agent->handle($user, str_repeat('a', (int) config('agent.max_message_length', 2000) + 100));

check('pesan terlalu panjang ditolak', ($result['success'] ?? true) === false);
check('tidak melempar exception', is_array($result));

echo "\n=== 4. API key sengaja dibuat salah ===\n";

$result = $agent->handle($user, 'jelaskan alur replenishment order');
$message = (string) ($result['message'] ?? '');

check('mengembalikan hasil, bukan exception', is_array($result));
check('ditandai gagal', ($result['success'] ?? true) === false);
check('pesan untuk user tidak kosong', trim($message) !== '');
check('pesan tidak membocorkan detail teknis — "'.$message.'"', ! leaksInternals($message));

echo "\n=== 5. Tool docs terdaftar dan boleh dipakai ===\n";

$allowed = (array) config('agent.allowed_tools', []);
check('search_docs ada di AGENT_ALLOWED_TOOLS', in_array('search_docs', $allowed, true));
check('guide_tour ada di AGENT_ALLOWED_TOOLS', in_array('guide_tour', $allowed, true));
check('open_page ada di AGENT_ALLOWED_TOOLS', in_array('open_page', $allowed, true));

$registry = app(AgentToolRegistry::class);
$context = AgentContext::fromUser($user);
$toolNames = array_map(
    static fn (array $tool) => data_get($tool, 'function.name'),
    $registry->openAiToolsForContext($context),
);

check('search_docs tersedia untuk user demo', in_array('search_docs', $toolNames, true));
check('definisi tool terbentuk tanpa error ('.count($toolNames).' tool)', $toolNames !== []);

echo "\n=== 6. Riwayat tool_calls tidak terbalik meski created_at sama ===\n";

/** @var AgentConversationService $history */
$history = app(AgentConversationService::class);
$stamp = now()->startOfSecond();
$conversation = AgentConversation::query()->create([
    'user_id' => $user->id,
    'branch_id' => $user->getBranchIdForTransaction(),
    'title' => 'uji riwayat tool',
]);

try {
    AgentMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'tambahkan kategori makanan',
        'created_at' => $stamp,
    ]);

    // Insert tool FIRST so id sorts before the assistant if we only order by time.
    AgentMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => 'tool',
        'tool_name' => 'manage_record',
        'content' => '{"success":true,"applied":true,"message":"Kategori produk Makanan berhasil ditambahkan."}',
        'tool_payload' => ['tool_call_id' => 'call_test_makanan'],
        'created_at' => $stamp,
    ]);

    AgentMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Baik, saya buatkan.',
        'tool_payload' => [
            'tool_calls' => [[
                'id' => 'call_test_makanan',
                'type' => 'function',
                'function' => [
                    'name' => 'manage_record',
                    'arguments' => '{"operation":"create","entity":"category","name":"Makanan"}',
                ],
            ]],
        ],
        'created_at' => $stamp,
    ]);

    $built = $history->buildChatMessages($conversation, 'system uji');
    $roles = array_column($built, 'role');
    $assistantIdx = array_search('assistant', $roles, true);
    $toolIdx = array_search('tool', $roles, true);

    check('assistant dengan tool_calls muncul sebelum hasil tool', is_int($assistantIdx) && is_int($toolIdx) && $assistantIdx < $toolIdx);
    check('tool_call_id hasil tool cocok', ($built[$toolIdx]['tool_call_id'] ?? '') === 'call_test_makanan');
    check('tidak ada tool yatim di awal riwayat', ($built[1]['role'] ?? '') !== 'tool');
} finally {
    $conversation->delete();
}

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
