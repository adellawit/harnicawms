<?php

declare(strict_types=1);

/**
 * Smoke test tool open_page chatbot TITANIE.
 *
 * Memastikan nama menu dipetakan ke URL, izin menu ditegakkan,
 * permintaan relatif ("halamannya") mengikuti topik percakapan,
 * dan widget punya hook navigasi same-origin. Tidak butuh API LLM.
 *
 * Jalankan: php scripts/ai-open-page-test.php
 */

use App\Models\User;
use App\Services\Ai\AgentContext;
use App\Services\Ai\AgentToolRegistry;
use App\Services\Ai\Tools\OpenPageTool;
use App\Services\Ai\Tour\AgentPageCatalog;
use App\Services\Ai\WmsAgentService;
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

$permissions = [
    'Category' => ['is_read' => 1],
    'Items' => ['is_read' => 1],
    'Product Item' => ['is_read' => 1],
    'Product' => ['is_read' => 1],
    'Stock' => ['is_read' => 1],
    'POS' => ['is_read' => 1],
    'Division' => ['is_read' => 1],
];

$user = new User;
$context = new AgentContext(
    user: $user,
    branchId: null,
    companyId: null,
    branchName: null,
    permissions: $permissions,
    channel: 'web',
    conversationId: null,
    pagePath: '/product/items',
    pageTitle: 'Product | Items',
    pageMenu: 'Items',
);

echo "\n=== 1. Tool terdaftar ===\n";

$allowed = (array) config('agent.allowed_tools', []);
check('open_page ada di AGENT_ALLOWED_TOOLS', in_array('open_page', $allowed, true));

$registry = app(AgentToolRegistry::class);
$toolNames = array_map(
    static fn (array $tool) => data_get($tool, 'function.name'),
    $registry->openAiToolsForContext($context),
);
check('open_page tersedia di registry', in_array('open_page', $toolNames, true));
check('guide_tour tetap tersedia', in_array('guide_tour', $toolNames, true));

echo "\n=== 2. Nama menu → URL ===\n";

/** @var OpenPageTool $tool */
$tool = app(OpenPageTool::class);
/** @var AgentPageCatalog $pages */
$pages = app(AgentPageCatalog::class);

$category = $tool->execute(['query' => 'kategori'], $context);
check('kategori sukses', ($category['success'] ?? false) === true);
check('kategori URL /product/category', ($category['url'] ?? '') === '/product/category');
check('kategori needs_navigation', ($category['needs_navigation'] ?? false) === true);

$items = $tool->execute(['query' => 'items'], $context);
check('items sukses', ($items['success'] ?? false) === true);
check('items URL /product/items', ($items['url'] ?? '') === '/product/items');
check('sudah di Items → tidak navigasi ulang', ($items['needs_navigation'] ?? true) === false);

$stock = $tool->execute(['query' => 'stok'], $context);
check('stok URL /product/stock', ($stock['url'] ?? '') === '/product/stock');

$stockFiltered = $tool->execute(['query' => 'stok', 'category' => 'Minuman'], $context);
$stockFilteredUrl = (string) ($stockFiltered['url'] ?? '');
check('stok + kategori append query string', str_starts_with($stockFilteredUrl, '/product/stock?') && (
    str_contains($stockFilteredUrl, 'category_id=') || str_contains($stockFilteredUrl, 'variant_search=Minuman') || str_contains($stockFilteredUrl, 'variant_search=minuman')
));
check('stok + filter → tab baru', ($stockFiltered['new_tab'] ?? false) === true);
check('stok tanpa filter tidak buka tab baru', ($stock['new_tab'] ?? true) === false);

$stockLeftover = $tool->execute(['query' => 'stok minuman'], $context);
$stockLeftoverUrl = (string) ($stockLeftover['url'] ?? '');
check('query "stok minuman" append filter', str_starts_with($stockLeftoverUrl, '/product/stock?') && (
    str_contains($stockLeftoverUrl, 'category_id=') || str_contains($stockLeftoverUrl, 'variant_search=')
));

$itemsFiltered = $tool->execute(['query' => 'items', 'search' => 'Pocky'], $context);
check('items + search → product=', str_contains((string) ($itemsFiltered['url'] ?? ''), '/product/items?') && str_contains((string) ($itemsFiltered['url'] ?? ''), 'product=Pocky'));

$pos = $tool->execute(['query' => 'POS'], $context);
check('POS URL /transaction/pos', ($pos['url'] ?? '') === '/transaction/pos');

$division = $tool->execute(['query' => 'divisi'], $context);
check('divisi URL /human-resources/division', ($division['url'] ?? '') === '/human-resources/division');

$bukaKategori = $tool->execute(['query' => 'buka halaman kategori'], $context);
check('frasa "buka halaman kategori" → category', ($bukaKategori['url'] ?? '') === '/product/category');

echo "\n=== 3. Permintaan relatif (halamannya) ===\n";

$fromCategoryTalk = $pages->resolve('halamannya', $context, 'tambahkan kategori makanan');
check('topik kategori makanan → /product/category', ($fromCategoryTalk['url'] ?? '') === '/product/category');

$fromItemTalk = $pages->resolve('halamannya', $context, 'jual item Pocky di katalog produk');
check('topik item Pocky → /product/items', ($fromItemTalk['url'] ?? '') === '/product/items');

$relative = $tool->execute(['query' => 'halamannya'], $context);
check('tanpa topik lain tetap ke halaman Items sekarang', ($relative['url'] ?? '') === '/product/items');

echo "\n=== 4. Izin menu ===\n";

$denied = $tool->execute(['query' => 'kategori'], new AgentContext(
    user: $user,
    branchId: null,
    companyId: null,
    branchName: null,
    permissions: ['Items' => ['is_read' => 1]],
    channel: 'web',
    conversationId: null,
    pagePath: '/product/items',
    pageTitle: 'Items',
    pageMenu: 'Items',
));
check('tanpa izin Category ditolak', ($denied['success'] ?? true) === false);
check('tanpa izin tidak mengirim URL navigasi', ($denied['needs_navigation'] ?? true) === false);

echo "\n=== 5. Attachment + widget + prompt ===\n";

$service = new ReflectionClass(WmsAgentService::class);
$method = $service->getMethod('buildPageNavigationAttachment');
$method->setAccessible(true);
$agent = app(WmsAgentService::class);
$attachment = $method->invoke($agent, $category);

check('attachment type page_navigation', ($attachment['type'] ?? '') === 'page_navigation');
check('attachment url /product/category', ($attachment['url'] ?? '') === '/product/category');
check('attachment needs_navigation', ($attachment['needs_navigation'] ?? false) === true);
check('attachment tanpa filter tidak new_tab', ($attachment['new_tab'] ?? true) === false);

$filteredAttachment = $method->invoke($agent, $stockFiltered);
check('attachment filter url punya query', str_contains((string) ($filteredAttachment['url'] ?? ''), '?'));
check('attachment filter new_tab', ($filteredAttachment['new_tab'] ?? false) === true);

$noNav = $method->invoke($agent, ['success' => true, 'url' => '/product/items', 'needs_navigation' => false]);
check('sudah di halaman → tidak ada attachment navigasi', $noNav === null);

$unsafe = $method->invoke($agent, [
    'success' => true,
    'url' => 'https://evil.example/phish',
    'needs_navigation' => true,
]);
check('URL eksternal tidak jadi attachment', $unsafe === null);

$chatJs = (string) file_get_contents(dirname(__DIR__).'/public/assets/ai/chat.js');
$prompt = (string) file_get_contents(dirname(__DIR__).'/app/Services/Ai/WmsAgentService.php');

check('chat.js assign same-origin', str_contains($chatJs, 'applyPageNavigation') && str_contains($chatJs, 'window.location.assign'));
check('chat.js buka tab baru same-origin', str_contains($chatJs, 'window.open') && str_contains($chatJs, "'_blank'"));
check('chat.js menolak origin lain', str_contains($chatJs, 'dest.origin !== window.location.origin'));
check('tour.js tidak diubah polanya (AgentTour.show tetap)', str_contains((string) file_get_contents(dirname(__DIR__).'/public/assets/ai/tour.js'), 'window.AgentTour'));
check('prompt wajib panggil open_page', str_contains($prompt, 'WAJIB panggil open_page'));
check('prompt dilarang bilang tidak bisa navigasi', str_contains($prompt, 'JANGAN bilang kamu tidak bisa navigasi'));
check('prompt open_page dengan filter', str_contains($prompt, 'buka stok minuman') && str_contains($prompt, 'category atau search'));
check('sanitizer sitasi tetap ada', str_contains($chatJs, 'stripSourceCitations'));
check('hide-chat tur tetap ada', str_contains($chatJs, 'hideChatForTour'));
check('suara chat tetap AgentSpeech', str_contains($chatJs, 'window.AgentSpeech'));

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
