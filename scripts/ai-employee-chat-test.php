<?php

declare(strict_types=1);

/**
 * Smoke test create karyawan lewat chat (manage_record).
 *
 * Memastikan mapper field, pesan missing tanpa "klik Tambah",
 * prompt create-di-chat, dan fitur open_page/tour/suara/sanitizer tetap ada.
 * Tidak butuh API LLM.
 *
 * Jalankan: php scripts/ai-employee-chat-test.php
 */

use App\Models\User;
use App\Services\Ai\Actions\EmployeeChatFieldMapper;
use App\Services\Ai\AgentContext;
use App\Services\Ai\AgentToolRegistry;
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

$mapper = new EmployeeChatFieldMapper;
$today = '2026-08-16';

echo "\n=== 1. Mapper field karyawan ===\n";

$missing = $mapper->map(['operation' => 'create', 'entity' => 'employee'], $today);
check('tanpa nama → missing fullname', in_array('fullname', $missing['missing'], true));
check('pesan missing berbahasa Indonesia', is_string($missing['message']) && str_contains($missing['message'], 'Nama lengkap'));
check('pesan missing tidak menyuruh klik Tambah', is_string($missing['message']) && ! str_contains($missing['message'], 'Tambah') && ! str_contains(mb_strtolower($missing['message']), 'klik'));

$ok = $mapper->map([
    'name' => 'Budi Santoso',
    'fields_json' => json_encode([
        'email' => 'budi@example.test',
        'role' => 'Super Admin',
        'position' => 'Staff',
        'division' => 'IT',
        'join_date' => 'hari ini',
        'employee_status' => 'aktif',
    ], JSON_THROW_ON_ERROR),
], $today);

check('fullname dari name', $ok['fullname'] === 'Budi Santoso');
check('email tersimpan', $ok['email'] === 'budi@example.test');
check('username dari email', $ok['username'] === 'budi@example.test');
check('role nama Super Admin', $ok['role_name'] === 'Super Admin');
check('jabatan Staff', $ok['position_name'] === 'Staff');
check('divisi IT', $ok['division_name'] === 'IT');
check('join_date hari ini → Y-m-d', $ok['join_date'] === $today);
check('status aktif → Active', $ok['employee_status'] === 'Active');
check('nama lengkap tidak missing', $ok['missing'] === []);

$alias = $mapper->map([
    'fields_json' => json_encode([
        'nama' => 'Siti Aminah',
        'jabatan' => 'Kasir',
        'divisi' => 'Operasional',
        'role' => 'staff',
        'status' => 'aktif',
        'tanggal_bergabung' => 'today',
    ], JSON_THROW_ON_ERROR),
], $today);
check('alias nama/jabatan/divisi', $alias['fullname'] === 'Siti Aminah' && $alias['position_name'] === 'Kasir' && $alias['division_name'] === 'Operasional');
check('alias today → tanggal', $alias['join_date'] === $today);

$badEmail = $mapper->map([
    'name' => 'Budi',
    'fields_json' => json_encode(['email' => 'bukan-email'], JSON_THROW_ON_ERROR),
], $today);
check('email invalid → missing email', in_array('email', $badEmail['missing'], true));

check('parse employment tetap → Permanent', $mapper->parseEmploymentStatus('tetap') === 'Permanent');
check('parse join kemarin', $mapper->parseJoinDate('kemarin', $today) === '2026-08-15');

$question = $mapper->questionFor(['fullname', 'role']);
check('questionFor tidak menyebut Tambah', ! str_contains($question, 'Tambah') && ! str_contains(mb_strtolower($question), 'klik tombol'));

echo "\n=== 2. Tool + prompt + widget tidak pecah ===\n";

$user = new User;
$context = new AgentContext(
    user: $user,
    branchId: null,
    companyId: null,
    branchName: null,
    permissions: ['Employee' => ['is_read' => 1, 'is_create' => 1]],
    channel: 'web',
);

$registry = app(AgentToolRegistry::class);
$toolNames = array_map(
    static fn (array $tool) => data_get($tool, 'function.name'),
    $registry->openAiToolsForContext($context),
);

check('manage_record tetap ada', in_array('manage_record', $toolNames, true));
check('open_page tetap ada', in_array('open_page', $toolNames, true));
check('guide_tour tetap ada', in_array('guide_tour', $toolNames, true));

$prompt = (string) file_get_contents(dirname(__DIR__).'/app/Services/Ai/WmsAgentService.php');
$chatJs = (string) file_get_contents(dirname(__DIR__).'/public/assets/ai/chat.js');
$tourJs = (string) file_get_contents(dirname(__DIR__).'/public/assets/ai/tour.js');
$speechJs = (string) file_get_contents(dirname(__DIR__).'/public/assets/ai/speech.js');
$toolSrc = (string) file_get_contents(dirname(__DIR__).'/app/Services/Ai/Tools/ManageRecordTool.php');
$serviceSrc = (string) file_get_contents(dirname(__DIR__).'/app/Services/Ai/Actions/AgentRecordActionService.php');

check('prompt create master lewat manage_record', str_contains($prompt, 'HARUS selesai di chat lewat manage_record create'));
check('prompt dilarang klik tombol Tambah', str_contains($prompt, 'JANGAN minta user klik tombol Tambah'));
check('prompt dilarang open_page untuk create', str_contains($prompt, 'JANGAN panggil open_page hanya karena mau menambah data'));
check('prompt wajib panggil open_page', str_contains($prompt, 'WAJIB panggil open_page'));
check('prompt dilarang bilang tidak bisa navigasi', str_contains($prompt, 'JANGAN bilang kamu tidak bisa navigasi'));
check('employee create delegasi EmployeeChatService', str_contains($serviceSrc, '$this->employees->create'));
check('tool fields_json contoh karyawan', str_contains($toolSrc, 'join_date') && str_contains($toolSrc, 'hari ini'));
check('sanitizer sitasi tetap ada', str_contains($chatJs, 'stripSourceCitations'));
check('hide-chat tur tetap ada', str_contains($chatJs, 'hideChatForTour'));
check('suara chat tetap AgentSpeech', str_contains($chatJs, 'window.AgentSpeech'));
check('tour.js AgentTour.show tetap', str_contains($tourJs, 'window.AgentTour'));
check('speech.js tetap ada', str_contains($speechJs, 'AgentSpeech') || str_contains($speechJs, 'speechSynthesis'));

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
