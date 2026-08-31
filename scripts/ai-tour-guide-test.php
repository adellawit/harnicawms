<?php

declare(strict_types=1);

/**
 * Smoke test room tour chatbot TITANIE.
 *
 * Memastikan katalog path→ruangan, tool guide_tour, dan allowed_tools
 * terpasang. Tidak butuh API LLM. Cache array dipakai untuk state tur.
 *
 * Jalankan: php scripts/ai-tour-guide-test.php
 */

use App\Models\User;
use App\Services\Ai\Actions\AgentTourStore;
use App\Services\Ai\AgentContext;
use App\Services\Ai\AgentToolRegistry;
use App\Services\Ai\Tools\GuideTourTool;
use App\Services\Ai\Tour\AgentTourCatalog;
use App\Services\Ai\Tour\AgentTourIntent;
use App\Services\Ai\Tour\AgentTourSequence;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

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

Config::set('cache.default', 'array');
Cache::flush();

/** @var AgentTourCatalog $catalog */
$catalog = app(AgentTourCatalog::class);

echo "\n=== 1. Katalog memetakan path ke ruangan ===\n";

$pos = $catalog->match('/transaction/pos', 'POS', 'POS');
check(' /transaction/pos → pos', ($pos['key'] ?? '') === 'pos');

$stock = $catalog->match('/product/stock', 'Stock', 'Stock');
check(' /product/stock → stock (bukan product)', ($stock['key'] ?? '') === 'stock');

$product = $catalog->match('/product/items', 'Product', 'Product');
check(' /product/items → product', ($product['key'] ?? '') === 'product');

$dist = $catalog->match('/agen-order', 'Replenishment', 'Replenishment');
check(' /agen-order → distribution', ($dist['key'] ?? '') === 'distribution');

$hr = $catalog->match('/human-resources/employee', 'Employee', 'Employee');
check(' /human-resources/employee → hr', ($hr['key'] ?? '') === 'hr');
check(' hr punya blurb', trim((string) ($hr['blurb'] ?? '')) !== '');
check(' hr punya narasi suara', trim((string) ($hr['voice'] ?? '')) !== '');
check(' hr punya submenu Division/Position/Employee', count($catalog->children('hr')) === 3);
check(' halaman insert dianggap form', $catalog->pageKind('/human-resources/division/insert') === 'form');
check(' halaman index dianggap daftar', $catalog->pageKind('/human-resources/division') === 'index');

$dashboard = $catalog->room('dashboard');
check(' narasi suara dashboard menyebut Titanie', stripos((string) ($dashboard['voice'] ?? ''), 'Titanie') !== false);

echo "\n=== 2. Tool guide_tour terdaftar ===\n";

$allowed = (array) config('agent.allowed_tools', []);
check('guide_tour ada di AGENT_ALLOWED_TOOLS', in_array('guide_tour', $allowed, true));
check('open_page ada di AGENT_ALLOWED_TOOLS', in_array('open_page', $allowed, true));

$registry = app(AgentToolRegistry::class);
$user = new User;
$context = new AgentContext(
    user: $user,
    branchId: null,
    companyId: null,
    branchName: null,
    permissions: [],
    channel: 'web',
    conversationId: '00000000-0000-4000-8000-000000000001',
    pagePath: '/transaction/pos',
    pageTitle: 'POS',
    pageMenu: 'POS',
);

$toolNames = array_map(
    static fn (array $tool) => data_get($tool, 'function.name'),
    $registry->openAiToolsForContext($context),
);
check('guide_tour tersedia di registry', in_array('guide_tour', $toolNames, true));

echo "\n=== 3. Operasi here (page-deep) / next / prev / stop ===\n";

/** @var GuideTourTool $tool */
$tool = app(GuideTourTool::class);

$here = $tool->execute(['operation' => 'here'], $context);
check('here sukses', ($here['success'] ?? false) === true);
check('here mengenali POS', ($here['room']['key'] ?? '') === 'pos');
check('here mode page_deep', ($here['mode'] ?? '') === 'page_deep');
check('here punya selector', trim((string) data_get($here, 'highlight.selector')) !== '');
check('here punya blurb', trim((string) data_get($here, 'highlight.blurb')) !== '');
check('here punya narasi suara', trim((string) data_get($here, 'highlight.voice')) !== '');
check('here tidak navigasi (tetap di halaman ini)', data_get($here, 'highlight.navigate_url') === null);
check('here mengembalikan docs_query', trim((string) ($here['docs_query'] ?? '')) !== '');
check('here has_prev false di langkah pertama', ($here['has_prev'] ?? true) === false);
check('here total lebih dari 1 (submenu/page spots)', (int) ($here['total'] ?? 0) > 1);

$next = $tool->execute(['operation' => 'next'], $context);
check('next sukses', ($next['success'] ?? false) === true);
check('next dari here tetap di ruang POS (bukan loncat modul)', ($next['room']['key'] ?? '') === 'pos');
check('next has_prev true', ($next['has_prev'] ?? false) === true);

$prev = $tool->execute(['operation' => 'prev'], $context);
check('prev sukses', ($prev['success'] ?? false) === true);
check('prev kembali ke langkah POS awal', ($prev['room']['key'] ?? '') === 'pos');
check('prev has_prev false', ($prev['has_prev'] ?? true) === false);

$stop = $tool->execute(['operation' => 'stop'], $context);
check('stop sukses', ($stop['success'] ?? false) === true);
check('stop menonaktifkan tur', ($stop['active'] ?? true) === false);

/** @var AgentTourStore $store */
$store = app(AgentTourStore::class);
check('stop menghapus state cache', $store->get($context->conversationId) === null);

echo "\n=== 4. start selalu dari ruang 1; pertanyaan baru tidak resume ===\n";

$stockContext = new AgentContext(
    user: $user,
    branchId: null,
    companyId: null,
    branchName: null,
    permissions: [],
    channel: 'web',
    conversationId: '00000000-0000-4000-8000-000000000002',
    pagePath: '/product/stock',
    pageTitle: 'Stock',
    pageMenu: 'Stock',
);

$startFromStock = $tool->execute(['operation' => 'start'], $stockContext);
check('start dari halaman Stok tetap ruang 1', ($startFromStock['room']['key'] ?? '') === 'dashboard');
check('start step = 1', (int) ($startFromStock['step'] ?? 0) === 1);

$tool->execute(['operation' => 'next'], $stockContext);
$tool->execute(['operation' => 'next'], $stockContext);
$startAgain = $tool->execute(['operation' => 'start'], $stockContext);
check('start ulang dari ruang 1 meski sempat next', (int) ($startAgain['step'] ?? 0) === 1);
check('start ulang label Dashboard', ($startAgain['room']['key'] ?? '') === 'dashboard');

$store->forget((string) $stockContext->conversationId);
$orphanNext = $tool->execute(['operation' => 'next'], $stockContext);
check('next tanpa state tidak menyalakan overlay', ($orphanNext['active'] ?? true) === false);
check('next tanpa state operation idle', ($orphanNext['operation'] ?? '') === 'idle');

check('lanjut adalah kontrol tur', AgentTourIntent::isControl('lanjut'));
check('selesai adalah kontrol tur', AgentTourIntent::isControl('selesai'));
check('stop tur adalah kontrol tur', AgentTourIntent::isControl('stop tur'));
check('Tampilkan stok bukan kontrol tur', AgentTourIntent::isControl('Tampilkan stok') === false);
check('Turin fiturnya dong bukan kontrol (start baru)', AgentTourIntent::isControl('Turin fiturnya dong') === false);
check('recap finish menyebut Tur selesai', str_contains(AgentTourIntent::recap('finish'), 'Tur selesai'));
check('recap skip menyebut Tur dihentikan', str_contains(AgentTourIntent::recap('skip'), 'Tur dihentikan'));

echo "\n=== 5. Page-deep HR vs overview yang tidak meledak ===\n";

$hrContext = new AgentContext(
    user: $user,
    branchId: null,
    companyId: null,
    branchName: null,
    permissions: [],
    channel: 'web',
    conversationId: '00000000-0000-4000-8000-000000000003',
    pagePath: '/human-resources/division',
    pageTitle: 'Division',
    pageMenu: 'Division',
);

$hereHr = $tool->execute(['operation' => 'here'], $hrContext);
check('here di Division mode page_deep', ($hereHr['mode'] ?? '') === 'page_deep');
check('here Division tidak navigasi', data_get($hereHr, 'highlight.navigate_url') === null);
check('here Division kind sidebar dulu', data_get($hereHr, 'highlight.kind') === 'sidebar');

$hrState = $store->get((string) $hrContext->conversationId);
$hrSteps = is_array($hrState['steps'] ?? null) ? $hrState['steps'] : [];
$hrLabels = array_map(static fn ($step) => (string) ($step['label'] ?? ''), $hrSteps);
$hrKinds = array_map(static fn ($step) => (string) ($step['kind'] ?? ''), $hrSteps);
check('page-deep HR menyorot submenu Division', in_array('Division', $hrLabels, true));
check('page-deep HR menyorot submenu Position', in_array('Position', $hrLabels, true));
check('page-deep HR menyorot submenu Employee', in_array('Employee', $hrLabels, true));
check('page-deep HR punya spot halaman', in_array('page', $hrKinds, true));
check('page-deep HR 3-6 spot halaman', count(array_filter($hrKinds, static fn ($kind) => $kind === 'page')) >= 3
    && count(array_filter($hrKinds, static fn ($kind) => $kind === 'page')) <= 6);

$formHere = $tool->execute(['operation' => 'here'], new AgentContext(
    user: $user,
    branchId: null,
    companyId: null,
    branchName: null,
    permissions: [],
    channel: 'web',
    conversationId: '00000000-0000-4000-8000-000000000004',
    pagePath: '/human-resources/division/insert',
    pageTitle: 'Add Division',
    pageMenu: 'Division',
));
$formState = $store->get('00000000-0000-4000-8000-000000000004');
$formSpotKeys = array_values(array_filter(array_map(
    static fn ($step) => (string) ($step['spot_key'] ?? ''),
    is_array($formState['steps'] ?? null) ? $formState['steps'] : [],
)));
check('form insert menyorot field', in_array('fields', $formSpotKeys, true));
check('form insert menyorot simpan', in_array('save', $formSpotKeys, true));

$sequence = app(AgentTourSequence::class);
$overview = $sequence->overview();
$overviewCount = count($overview);
$roomCount = count($catalog->tourKeys());
check('overview punya semua ruang plus spot halaman', $overviewCount > $roomCount);
check('overview tidak meledak (max 3 langkah/modul)', $overviewCount <= $roomCount * 3);

$hrOverview = array_values(array_filter(
    $overview,
    static fn ($step) => ($step['room_key'] ?? '') === 'hr',
));
$hrOverviewChildren = array_values(array_filter(
    $hrOverview,
    static fn ($step) => str_contains((string) ($step['id'] ?? ''), '.child.'),
));
$hrOverviewPages = array_values(array_filter(
    $hrOverview,
    static fn ($step) => ($step['kind'] ?? '') === 'page',
));
check('overview HR tidak menelusuri semua submenu', $hrOverviewChildren === []);
check('overview HR paling banyak 2 spot halaman', count($hrOverviewPages) <= 2);

$startOverview = $tool->execute(['operation' => 'start'], $stockContext);
check('start mode overview', ($startOverview['mode'] ?? '') === 'overview');
check('start ruang Dashboard', ($startOverview['room']['key'] ?? '') === 'dashboard');

echo "\n=== 6. TTS chat memakai picker suara tur ===\n";

$speechJs = (string) file_get_contents(dirname(__DIR__).'/public/assets/ai/speech.js');
$chatJs = (string) file_get_contents(dirname(__DIR__).'/public/assets/ai/chat.js');
$tourJs = (string) file_get_contents(dirname(__DIR__).'/public/assets/ai/tour.js');

check('speech.js menolak Damayanti/Gadis', str_contains($speechJs, 'damayanti') && str_contains($speechJs, 'gadis'));
check('speech.js prefer Ardi/Damir/Agus', str_contains($speechJs, 'ardi') && str_contains($speechJs, 'damir') && str_contains($speechJs, 'agus'));
check('speech.js rate karakter 1.02', str_contains($speechJs, 'TITANIE_RATE = 1.02'));
check('speech.js pitch karakter 1.05', str_contains($speechJs, 'TITANIE_PITCH = 1.05'));
check('speech.js sapaan Titanie laki-laki', str_contains($speechJs, 'TITANIE_GREETING') && str_contains($speechJs, 'aku Titanie'));
check('chat.js memakai AgentSpeech, bukan TTS terpisah', str_contains($chatJs, 'window.AgentSpeech') && ! str_contains($chatJs, 'new SpeechSynthesisUtterance'));
check('chat.js menyapa saat speaker nyala', str_contains($chatJs, 'speakTitanieGreeting') && str_contains($chatJs, 'speakGreeting'));
check('tour.js tetap memakai AgentSpeech.speak', str_contains($tourJs, 'speech().speak'));
check('tour.js punya target halaman (bukan hanya sidebar)', str_contains($tourJs, 'findPageTarget') && str_contains($tourJs, "kind || '') === 'page'"));
check('prompt here adalah page-deep', str_contains((string) file_get_contents(dirname(__DIR__).'/app/Services/Ai/WmsAgentService.php'), 'jelasin halaman ini'));
check('tombol Selesai overlay memakai finish, bukan next', str_contains($tourJs, 'requestFinish') && str_contains($tourJs, "data-tour-action', isLast ? 'finish'"));
check('chat.js onFinish tidak POST lanjut', str_contains($chatJs, "finishTour('finish')") && str_contains($chatJs, 'onFinish:'));
check('chat.js Lewati tidak POST lanjut', str_contains($chatJs, "finishTour('skip')"));
check('chat.js Selesai langkah terakhir tidak sendMessage lanjut', ! preg_match("/is_last \\? 'Selesai'[\\s\\S]{0,180}sendMessage\\('lanjut'\\)/", $chatJs));
check('chat.js silent stop tidak lewat /agent/chat', str_contains($chatJs, 'silentStopTour') && str_contains($chatJs, 'tourStopUrl'));

echo "\n=== 7. Langkah terakhir next = stop; Selesai bukan lanjut ===\n";

$lastContext = new AgentContext(
    user: $user,
    branchId: null,
    companyId: null,
    branchName: null,
    permissions: [],
    channel: 'web',
    conversationId: '00000000-0000-4000-8000-000000000007',
    pagePath: '/transaction/pos',
    pageTitle: 'POS',
    pageMenu: 'POS',
);

$hereLast = $tool->execute(['operation' => 'here'], $lastContext);
$totalLast = (int) ($hereLast['total'] ?? 0);
$cursor = $hereLast;
for ($i = 1; $i < $totalLast && $i < 40; $i++) {
    $cursor = $tool->execute(['operation' => 'next'], $lastContext);
}
check('page-deep mencapai langkah terakhir', ($cursor['is_last'] ?? false) === true);
$afterLast = $tool->execute(['operation' => 'next'], $lastContext);
check('next setelah langkah terakhir operation stop', ($afterLast['operation'] ?? '') === 'stop');
check('next setelah langkah terakhir active false', ($afterLast['active'] ?? true) === false);
check('pesan stop tidak menyuruh bilang masih menekan Lanjut', str_contains((string) ($afterLast['message'] ?? ''), 'bukan Lanjut'));

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
