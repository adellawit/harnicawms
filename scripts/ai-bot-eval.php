<?php

declare(strict_types=1);

/**
 * Eval singkat chatbot TITANIE tanpa LLM.
 *
 * Cek registry tool, missing-fields manage_record, open_page, guide_tour stop,
 * bentuk token konfirmasi, sanitizer sitasi, AGENT_ALLOWED_TOOLS, create agen
 * partner (missing name + kartu konfirmasi), replenishment wajib agen, dan
 * get_stock overview (query kosong/null, max 10 item) vs filter keyword,
 * prompt/help tidak klaim stok keyword-only, kartu action_card wajib token,
 * orphan CTA tanpa kartu dibersihkan, riwayat percakapan widget, dan
 * "tambahkan produk plastik 10 pcs" tidak jadi kartu increment stok (alur PO).
 *
 * Jalankan: php scripts/ai-bot-eval.php
 */

use App\Models\User;
use App\Services\Ai\Actions\AgentPendingActionStore;
use App\Services\Ai\Actions\AgentRecordActionService;
use App\Services\Ai\Actions\ChatFields;
use App\Services\Ai\Actions\EmployeeChatFieldMapper;
use App\Services\Ai\Actions\ProductChatService;
use App\Services\Ai\Actions\PurchaseOrderChatService;
use App\Services\Ai\Actions\StockChatService;
use App\Services\Ai\AgentContext;
use App\Services\Ai\AgentConversationService;
use App\Services\Ai\AgentReplySanitizer;
use App\Services\Ai\AgentToolRegistry;
use App\Services\Ai\Tools\GetHelpTool;
use App\Services\Ai\Tools\GetStockTool;
use App\Services\Ai\Tools\GuideTourTool;
use App\Services\Ai\Tools\OpenPageTool;
use App\Services\Ai\Tour\AgentPageCatalog;
use App\Services\Ai\WmsAgentService;
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

$expectedTools = [
    'search_docs',
    'search_product',
    'get_stock',
    'search_customer',
    'get_sales_summary',
    'get_help',
    'manage_sale',
    'manage_record',
    'guide_tour',
    'open_page',
];

echo "\n=== 1. Tool registry + allowed_tools ===\n";

$allowed = (array) config('agent.allowed_tools', []);
foreach ($expectedTools as $name) {
    check($name.' ada di AGENT_ALLOWED_TOOLS', in_array($name, $allowed, true));
}

$examplePath = file_exists(dirname(__DIR__).'/.env.example')
    ? dirname(__DIR__).'/.env.example'
    : dirname(__DIR__).'/.env-development-example';
$example = is_file($examplePath) ? (string) file_get_contents($examplePath) : '';
check('env example memuat manage_record dan open_page', str_contains($example, 'manage_record') && str_contains($example, 'open_page'));

$user = new User;
$context = new AgentContext(
    user: $user,
    branchId: '00000000-0000-0000-0000-000000000001',
    companyId: '00000000-0000-0000-0000-000000000002',
    branchName: 'Demo',
    permissions: [
        'Product' => ['is_read' => 1],
        'Customer' => ['is_read' => 1],
        'Sales Summary' => ['is_read' => 1],
        'POS' => ['is_create' => 1],
        'Category' => ['is_read' => 1, 'is_create' => 1],
        'Employee' => ['is_read' => 1, 'is_create' => 1],
        'Items' => ['is_read' => 1, 'is_create' => 1],
        'Purchase Order' => ['is_read' => 1, 'is_create' => 1],
        'Stock' => ['is_read' => 1, 'is_create' => 1],
        'Division' => ['is_read' => 1, 'is_delete' => 1],
        'Partner Agent' => ['is_read' => 1, 'is_create' => 1],
        'Replenishment' => ['is_read' => 1, 'is_create' => 1],
    ],
    channel: 'web',
    conversationId: '11111111-1111-1111-1111-111111111111',
);

$registry = app(AgentToolRegistry::class);
$toolNames = array_values(array_filter(array_map(
    static fn (array $tool) => data_get($tool, 'function.name'),
    $registry->openAiToolsForContext($context),
)));

foreach ($expectedTools as $name) {
    check($name.' terdaftar di registry', in_array($name, $toolNames, true));
}

echo "\n=== 2. manage_record missing fields ===\n";

/** @var AgentRecordActionService $records */
$records = app(AgentRecordActionService::class);

$category = $records->handle([
    'operation' => 'create',
    'entity' => 'category',
    'name' => '',
    'fields_json' => '{}',
], $context);
check('kategori tanpa nama → missing name', in_array('name', $category['missing'] ?? [], true));
check('pesan kategori tidak menyuruh klik Tambah', is_string($category['message'] ?? null) && ! str_contains(mb_strtolower((string) $category['message']), 'klik'));

$mapper = new EmployeeChatFieldMapper;
$employeeMissing = $mapper->map(['operation' => 'create', 'entity' => 'employee'], '2026-08-16');
check('karyawan tanpa nama → missing fullname', in_array('fullname', $employeeMissing['missing'], true));

/** @var ProductChatService $products */
$products = app(ProductChatService::class);
$productMissing = $products->create(['operation' => 'create', 'entity' => 'product'], $context);
check('produk tanpa nama/sale → missing name', in_array('name', $productMissing['missing'] ?? [], true));
check('produk tanpa sale flag → missing is_sale_item', in_array('is_sale_item', $productMissing['missing'] ?? [], true));

/** @var PurchaseOrderChatService $pos */
$pos = app(PurchaseOrderChatService::class);
$poMissing = $pos->createDraft(['operation' => 'create', 'entity' => 'purchase_order'], $context, false);
check('PO tanpa supplier → missing supplier', in_array('supplier', $poMissing['missing'] ?? [], true));

$boolSale = ChatFields::parseBool('dijual');
check('flag dijual → true', $boolSale === true);

echo "\n=== 3. open_page + guide_tour stop ===\n";

Config::set('cache.default', 'array');
Cache::flush();

/** @var AgentPageCatalog $pages */
$pages = app(AgentPageCatalog::class);
$categoryPage = $pages->resolve('kategori', $context);
check('open_page kategori → /product/category', ($categoryPage['url'] ?? '') === '/product/category' || str_contains((string) ($categoryPage['url'] ?? ''), '/product/category'));

/** @var OpenPageTool $openPage */
$openPage = app(OpenPageTool::class);
$opened = $openPage->execute(['query' => 'kategori'], $context);
check('open_page tool sukses untuk kategori', ($opened['success'] ?? false) === true);

$stockOpened = $openPage->execute(['query' => 'stok', 'category' => 'Minuman'], $context);
$stockOpenedUrl = (string) ($stockOpened['url'] ?? '');
check('open_page stok + filter append query string', str_starts_with($stockOpenedUrl, '/product/stock?') && (
    str_contains($stockOpenedUrl, 'category_id=') || str_contains($stockOpenedUrl, 'variant_search=')
));
check('open_page stok + filter new_tab', ($stockOpened['new_tab'] ?? false) === true);

$stockPageSrc = (string) file_get_contents(dirname(__DIR__).'/app/Http/Controllers/Admin/ProductStockController.php');
check('stok page honor variant_search GET', str_contains($stockPageSrc, "filled('variant_search')") && str_contains($stockPageSrc, 'ilike'));

/** @var GuideTourTool $tour */
$tour = app(GuideTourTool::class);
$stopped = $tour->execute(['operation' => 'stop'], $context);
check('guide_tour stop → operation stop', ($stopped['operation'] ?? '') === 'stop');
check('guide_tour stop → active false', ($stopped['active'] ?? true) === false);

echo "\n=== 4. Confirmation token + sanitizer ===\n";

/** @var AgentPendingActionStore $pending */
$pending = app(AgentPendingActionStore::class);
$card = $pending->propose(
    (string) $context->conversationId,
    'user-eval',
    [
        'kind' => 'delete',
        'title' => 'Hapus divisi?',
        'body' => 'Divisi Management akan dihapus.',
        'confirm_label' => 'Hapus',
    ],
    ['operation' => 'delete', 'entity' => 'division', 'name' => 'Management'],
);
check('needs_confirmation true', ($card['needs_confirmation'] ?? false) === true);
check('confirmation_token 40 karakter', is_string($card['confirmation_token'] ?? null) && strlen((string) $card['confirmation_token']) === 40);
check('action_card title terisi', ($card['title'] ?? '') === 'Hapus divisi?');

$caps = $records->capabilities();
$writableKeys = array_column($caps['writable'] ?? [], 'entity');
check('stock writable di capabilities', in_array('stock', $writableKeys, true));
check('purchase_order writable di capabilities', in_array('purchase_order', $writableKeys, true));
check('journal writable di capabilities', in_array('journal', $writableKeys, true));

$cleaned = AgentReplySanitizer::stripSourceCitations('TITANIE WMS (sumber: ARCHITECTURE.md) untuk gudang.');
check('sanitizer menghapus (sumber: …)', ! str_contains($cleaned, 'sumber') && str_contains($cleaned, 'TITANIE'));

$stockSrc = (string) file_get_contents(dirname(__DIR__).'/app/Services/Ai/Actions/StockChatService.php');
check('stok chat memakai StockMutationService', str_contains($stockSrc, 'StockMutationService::inbound') && str_contains($stockSrc, 'StockMutationService::outbound'));
check('stok chat tidak assign quantity langsung', ! preg_match('/->quantity\s*=/', $stockSrc));

$wmsSrc = (string) file_get_contents(dirname(__DIR__).'/app/Services/Ai/WmsAgentService.php');
check('LLM di-retry sekali', str_contains($wmsSrc, 'chatWithRetry'));
check('pesan error membedakan jaringan', str_contains($wmsSrc, 'Koneksi ke asisten terputus'));

$addMode = StockChatService::resolveMode('set', 'tambahkan stocknya 10 pcs');
$addDelta = StockChatService::quantityDelta($addMode, 10, 100);
$addBody = StockChatService::confirmationBody($addDelta, 'Plastik (Bahan Baku)', 100);
check('tambah 10 tidak jadi mode set', $addMode === 'in');
check('tambah 10 vs stok 100 = delta +10 bukan -90', $addDelta === 10.0);
check('kartu konfirmasi Tambah 10', str_starts_with($addBody, 'Tambah 10 untuk Plastik (Bahan Baku)'));
check('kartu konfirmasi bukan Kurangi 90', ! str_contains($addBody, 'Kurangi 90'));
check('judul kartu Tambah 10?', StockChatService::confirmationTitle($addDelta) === 'Tambah 10?');
check('judul kartu Kurangi 10?', StockChatService::confirmationTitle(-10.0) === 'Kurangi 10?');

$setMode = StockChatService::resolveMode('set', 'jadikan stok menjadi 90');
$setDelta = StockChatService::quantityDelta($setMode, 90, 100);
check('jadikan 90 tetap mode set', $setMode === 'set');
check('set 90 vs stok 100 = kurangi 10', $setDelta === -10.0);

$outMode = StockChatService::resolveMode(null, 'kurangi 10 pcs');
check('kurangi 10 → mode out', $outMode === 'out');
check('out 10 vs stok 100 = delta -10', StockChatService::quantityDelta($outMode, 10, 100) === -10.0);

$defaultAdd = StockChatService::resolveMode(null, null);
check('mode kosong default increment', $defaultAdd === 'in');
check('prompt stok mode=in untuk tambah', str_contains($wmsSrc, 'mode=in') && str_contains($wmsSrc, 'JANGAN mode=set'));

echo "\n=== 4c. alur stok: PO vs opname ===\n";

check(
    'tambahkan produk plastik 10 pcs bukan opname',
    StockChatService::isExplicitAdjustmentIntent('tambahkan produk plastik 10 pcs') === false
);
check(
    'sesuaikan stok diizinkan',
    StockChatService::isExplicitAdjustmentIntent('Sesuaikan stok Kopi jadi 10') === true
);
check(
    'opname jelas diizinkan',
    StockChatService::isExplicitAdjustmentIntent('opname stok plastik jadi 10') === true
);
check(
    'koreksi selisih diizinkan',
    StockChatService::isExplicitAdjustmentIntent('koreksi selisih stok +10') === true
);
check(
    'halaman stock-adjustment mengizinkan tambah 10',
    StockChatService::isExplicitAdjustmentIntent('tambahkan 10 pcs', '/product/stock-adjustment') === true
);

$merchRefuse = StockChatService::refuseIfBypassingFlow('in', 'tambahkan produk plastik 10 pcs');
$merchMessage = mb_strtolower((string) ($merchRefuse['message'] ?? ''));
check('inbound merch ditolak', is_array($merchRefuse) && ($merchRefuse['success'] ?? true) === false);
check('inbound merch blocked_flow purchase_order', ($merchRefuse['blocked_flow'] ?? '') === 'purchase_order');
check('inbound merch tidak needs_confirmation', ($merchRefuse['needs_confirmation'] ?? false) === false);
check('inbound merch menyebut purchase order', str_contains($merchMessage, 'purchase order'));
check('inbound merch tanpa title kartu Tambah', ! isset($merchRefuse['title']));

$opnameOk = StockChatService::refuseIfBypassingFlow('set', 'opname stok plastik jadi 10');
check('opname tidak ditolak', $opnameOk === null);

$setWithoutNotes = StockChatService::refuseIfBypassingFlow('set', null);
check('mode set tanpa catatan = opname, tidak ditolak', $setWithoutNotes === null);

$outRefuse = StockChatService::refuseIfBypassingFlow('out', 'kurangi 10 pcs');
check('kurangi tanpa opname ditolak', is_array($outRefuse) && ($outRefuse['blocked_flow'] ?? '') === 'sales_or_replenishment');

$prodRefuse = StockChatService::refuseIfBypassingFlow('in', 'tambah hasil produksi 10');
check('hasil produksi blocked_flow production_order', ($prodRefuse['blocked_flow'] ?? '') === 'production_order');

$merchHandle = $records->handle([
    'operation' => 'create',
    'entity' => 'stock',
    'fields_json' => json_encode([
        'sku' => 'plastik',
        'quantity' => 10,
        'mode' => 'in',
        'notes' => 'tambahkan produk plastik 10 pcs',
    ], JSON_UNESCAPED_UNICODE),
], $context);
check('manage_record merch tidak needs_confirmation', ($merchHandle['needs_confirmation'] ?? false) === false);
check('manage_record merch blocked_flow PO', ($merchHandle['blocked_flow'] ?? '') === 'purchase_order');
check('manage_record merch tidak applied', ($merchHandle['applied'] ?? false) === false);

$productQty = $products->create([
    'operation' => 'create',
    'entity' => 'product',
    'name' => 'plastik 10 pcs',
    'fields_json' => json_encode(['is_sale_item' => true, 'quantity' => 10], JSON_UNESCAPED_UNICODE),
], $context);
check('produk + qty blocked_flow PO', ($productQty['blocked_flow'] ?? '') === 'purchase_order');
check('produk + qty tidak applied', ($productQty['applied'] ?? false) === false);
check('nama plastik 10 pcs terdeteksi inbound', ProductChatService::nameHasInboundQuantity('plastik 10 pcs') === true);
check('nama Kopi Arabica bukan inbound qty', ProductChatService::nameHasInboundQuantity('Kopi Arabica') === false);

check('prompt larang kartu Tambah tanpa opname', str_contains($wmsSrc, 'JANGAN kartu Tambah'));
check('prompt blocked_flow purchase_order', str_contains($wmsSrc, 'blocked_flow=purchase_order'));
check('prompt alur Purchase Order', str_contains($wmsSrc, 'Purchase Order') && str_contains($wmsSrc, 'opname'));

$poPage = $pages->resolve('purchase order', $context);
check('katalog halaman purchase order', str_contains((string) ($poPage['url'] ?? ''), 'purchase-order'));

$controllerSrc = (string) file_get_contents(dirname(__DIR__).'/app/Http/Controllers/Ai/ChatController.php');
check('confirmAction memakai AgentConfirmActionService', str_contains($controllerSrc, 'AgentConfirmActionService'));

echo "\n=== 4d. create/update mengikuti alur modul ===\n";

$categoryCard = $records->handle([
    'operation' => 'create',
    'entity' => 'category',
    'name' => 'Kategori Eval Chat',
    'fields_json' => '{}',
], $context);
check('create kategori → kartu konfirmasi', ($categoryCard['needs_confirmation'] ?? false) === true);
check('create kategori belum applied', ($categoryCard['applied'] ?? true) === false);
check('create kategori ada token', filled($categoryCard['confirmation_token'] ?? null));

$poUpdate = $records->handle([
    'operation' => 'update',
    'entity' => 'purchase_order',
    'query' => 'PO-EVAL',
    'fields_json' => json_encode(['status' => 'received'], JSON_UNESCAPED_UNICODE),
], $context);
check('update PO ditolak', ($poUpdate['success'] ?? true) === false);
check('update PO blocked_flow purchase_order', ($poUpdate['blocked_flow'] ?? '') === 'purchase_order');
check('update PO tidak needs_confirmation', ($poUpdate['needs_confirmation'] ?? false) === false);

$userAccount = $records->handle([
    'operation' => 'create',
    'entity' => 'user_account',
    'name' => 'login.eval',
    'fields_json' => '{}',
], $context);
check('create user_account ditolak', ($userAccount['success'] ?? true) === false);
check('create user_account blocked_flow employee', ($userAccount['blocked_flow'] ?? '') === 'employee');

$promptSrc = (string) file_get_contents(dirname(__DIR__).'/app/Services/Ai/WmsAgentService.php');
check('prompt larang user_account', str_contains($promptSrc, 'user_account'));
check('prompt larang update PO dari chat', str_contains($promptSrc, 'JANGAN update PO'));

echo "\n=== 4b. action_card attachment + orphan CTA ===\n";

$orphan = <<<'TXT'
Mohon maaf, sepertinya ada kendala teknis. Bisa Anda konfirmasi:
Saya akan menambahkan 10 pcs plastik (SKU FRD-PLASTIK-STD) ke stok cabang Bandung. Apakah Anda ingin melanjutkan? ✅
Silakan tekan tombol konfirmasi di chat jika sudah setuju, ya.
TXT;
check('orphan CTA terdeteksi', AgentReplySanitizer::asksToPressConfirmationButton($orphan));
$stripped = AgentReplySanitizer::withoutOrphanConfirmationCta($orphan);
check('orphan CTA tidak minta tekan tombol', ! AgentReplySanitizer::asksToPressConfirmationButton($stripped));
check('orphan CTA tetap menyebut SKU', str_contains($stripped, 'FRD-PLASTIK-STD'));

$instruct = 'Draf siap. User harus menekan tombol konfirmasi di chat sebelum transaksi dibuat.';
check(
    'pesan tool jadi user-facing',
    AgentReplySanitizer::userFacingConfirmationMessage($instruct) === 'Draf siap. Tekan Konfirmasi di kartu di bawah.'
);

/** @var WmsAgentService $agent */
$agent = app(WmsAgentService::class);
$reflect = new ReflectionClass($agent);
$buildCard = $reflect->getMethod('buildActionAttachment');
$buildCard->setAccessible(true);
$normalize = $reflect->getMethod('normalizeConfirmationResult');
$normalize->setAccessible(true);

$noToken = $buildCard->invoke($agent, [
    'needs_confirmation' => true,
    'title' => 'Tambah 10?',
    'confirm_label' => 'Konfirmasi',
]);
check('needs_confirmation tanpa token → tidak ada kartu', $noToken === null);

$dropped = $normalize->invoke($agent, [
    'success' => true,
    'needs_confirmation' => true,
    'title' => 'Tambah 10?',
    'message' => 'Konfirmasi dulu di kartu.',
]);
check('konfirmasi tanpa token dinormalisasi gagal', ($dropped['success'] ?? true) === false);
check('konfirmasi tanpa token tidak needs_confirmation', ($dropped['needs_confirmation'] ?? false) === false);

$withToken = $buildCard->invoke($agent, [
    'needs_confirmation' => true,
    'confirmation_token' => str_repeat('a', 40),
    'action' => 'stock_adjust',
    'title' => 'Tambah 10?',
    'body' => 'Tambah 10 untuk Plastik (sekarang 100).',
    'confirm_label' => 'Konfirmasi',
    'cancel_label' => 'Batal',
]);
check('action_card type', ($withToken['type'] ?? '') === 'action_card');
check('action_card title Tambah 10?', ($withToken['title'] ?? '') === 'Tambah 10?');
check('action_card label Konfirmasi/Batal', ($withToken['confirm_label'] ?? '') === 'Konfirmasi' && ($withToken['cancel_label'] ?? '') === 'Batal');
check('action_card token 40 karakter', strlen((string) ($withToken['token'] ?? '')) === 40);

check('prompt larang tombol tanpa token', str_contains($wmsSrc, 'JANGAN minta user menekan tombol konfirmasi'));
check('prompt token wajib untuk kartu', str_contains($wmsSrc, 'confirmation_token'));
check('short-circuit setelah action_card', str_contains($wmsSrc, 'hasActionCard($attachments)'));

$chatJsSrc = (string) file_get_contents(dirname(__DIR__).'/public/assets/ai/chat.js');
check('widget terima action-card alias', str_contains($chatJsSrc, "attachment.type === 'action-card'"));
check('widget notice kartu hilang', str_contains($chatJsSrc, 'agent-chat-action-missing'));

$pendingCard = $pending->propose(
    (string) $context->conversationId,
    'user-eval',
    [
        'kind' => 'stock_adjust',
        'title' => 'Tambah 10?',
        'body' => 'Tambah 10 untuk Plastik.',
        'confirm_label' => 'Konfirmasi',
        'cancel_label' => 'Batal',
    ],
    ['operation' => 'create', 'entity' => 'stock', 'fields_json' => '{"sku":"FRD-PLASTIK-STD","quantity":10,"mode":"in"}'],
);
check('pending stok menyimpan confirm_label', ($pendingCard['confirm_label'] ?? '') === 'Konfirmasi');
$storedPending = $pending->get((string) $context->conversationId);
check('pending cache menyimpan confirm_label', ($storedPending['confirm_label'] ?? '') === 'Konfirmasi');

echo "\n=== 5. partner_agent create + replenishment agent ===\n";

check('partner_agent writable di capabilities', in_array('partner_agent', $writableKeys, true));
check('replenishment writable di capabilities', in_array('replenishment', $writableKeys, true));

$agentMissing = $records->handle([
    'operation' => 'create',
    'entity' => 'partner_agent',
    'name' => '',
    'fields_json' => '{}',
], $context);
check('agen tanpa nama → missing name', in_array('name', $agentMissing['missing'] ?? [], true));
check('pesan agen tanpa nama tidak menyuruh klik Tambah', is_string($agentMissing['message'] ?? null) && ! str_contains(mb_strtolower((string) $agentMissing['message']), 'klik'));

$agentCard = $records->handle([
    'operation' => 'create',
    'entity' => 'partner_agent',
    'name' => 'Toko Eval Konfirmasi',
    'fields_json' => json_encode(['city' => 'Cirebon'], JSON_UNESCAPED_UNICODE),
], $context);
check('agen dengan nama → needs_confirmation', ($agentCard['needs_confirmation'] ?? false) === true);
check('agen confirmation_kind partner_agent_create', ($agentCard['action'] ?? '') === 'partner_agent_create');
check('agen confirmation_token 40 karakter', is_string($agentCard['confirmation_token'] ?? null) && strlen((string) $agentCard['confirmation_token']) === 40);
check('agen belum applied sebelum konfirmasi', ($agentCard['applied'] ?? true) === false);
check('agen title kartu terisi', ($agentCard['title'] ?? '') === 'Buat agen baru?');

$replenishMissing = $records->handle([
    'operation' => 'create',
    'entity' => 'replenishment',
    'name' => '',
    'fields_json' => '{}',
], $context);
check('replenishment tanpa agen → missing agent', in_array('agent', $replenishMissing['missing'] ?? [], true));

$partnerSrc = (string) file_get_contents(dirname(__DIR__).'/app/Services/Ai/Actions/PartnerAgentChatService.php');
check('create agen memakai Convert Agent', str_contains($partnerSrc, 'convertAgent') && str_contains($partnerSrc, 'createFromAttributes'));
check('prompt tidak menolak create agen', str_contains($wmsSrc, 'entity=partner_agent') && str_contains($wmsSrc, 'jangan menawarkan open_page'));

echo "\n=== 6. get_stock overview tanpa keyword ===\n";

$stockToolSrc = (string) file_get_contents(dirname(__DIR__).'/app/Services/Ai/Tools/GetStockTool.php');
$helpSrc = (string) file_get_contents(dirname(__DIR__).'/app/Services/Ai/Tools/GetHelpTool.php');
check('get_stock tidak menolak query kosong', ! str_contains($stockToolSrc, 'Query stok wajib diisi'));
check('get_stock preview max 10', str_contains($stockToolSrc, 'PREVIEW_LIMIT = 10'));
check('prompt stok query kosong', str_contains($wmsSrc, 'query kosong') && str_contains($wmsSrc, 'tampilkan stok'));
check('prompt seluruh stok', str_contains($wmsSrc, 'seluruh stok'));
check('prompt larang menolak list stok', str_contains($wmsSrc, 'JANGAN bilang tool tidak bisa menampilkan semua produk'));
check('prompt larang alihkan ke halaman Stok', str_contains($wmsSrc, 'JANGAN mengalihkan user ke halaman Stok'));
check('prompt tampilkan stok bukan open_page', str_contains($wmsSrc, 'BUKAN buka') && str_contains($wmsSrc, 'get_stock dengan query kosong'));
check('prompt open_page dengan filter', str_contains($wmsSrc, 'buka stok minuman') && str_contains($wmsSrc, 'category atau search'));

$keywordOnlyNeedles = ['wajib keyword', 'harus ada keyword', 'keyword-only', 'Query stok wajib'];
$promptAndHelp = $wmsSrc."\n".$helpSrc."\n".$stockToolSrc;
foreach ($keywordOnlyNeedles as $needle) {
    check('teks tidak klaim '.$needle, ! str_contains($promptAndHelp, $needle));
}

$help = app(GetHelpTool::class)->execute([], $context);
$helpExamples = $help['examples'] ?? [];
$helpText = json_encode($help, JSON_UNESCAPED_UNICODE);
check('get_help contoh Tampilkan stok', in_array('Tampilkan stok', $helpExamples, true));
check('get_help contoh Stok semua', in_array('Stok semua', $helpExamples, true));
check('get_help contoh Seluruh stok', in_array('Seluruh stok', $helpExamples, true));
check('get_help contoh Opname stok kopi jadi 100', in_array('Opname stok kopi jadi 100', $helpExamples, true));
check('get_help bukan jadikan semua stok', is_string($helpText) && ! str_contains($helpText, 'Jadikan semua stok'));
check('get_help sebut barang beli PO', is_string($helpText) && str_contains($helpText, 'Purchase Order'));
check('get_help limit 10 SKU', is_string($helpText) && str_contains($helpText, '10 SKU'));
check('get_help bukan 20 SKU', is_string($helpText) && ! str_contains($helpText, '20 SKU'));

/** @var GetStockTool $stockTool */
$stockTool = app(GetStockTool::class);
$queryType = $stockTool->parameters()['properties']['query']['type'] ?? null;
check('schema query boleh string atau null', $queryType === ['string', 'null']);

$emptyStock = $stockTool->execute(['query' => ''], $context);
$emptyMessage = mb_strtolower((string) ($emptyStock['message'] ?? ''));
$emptyItems = $emptyStock['items'] ?? null;
check('stok query kosong sukses', ($emptyStock['success'] ?? false) === true);
check('stok query kosong bukan error missing', ! str_contains($emptyMessage, 'wajib'));
check('stok query kosong punya summary atau items', isset($emptyStock['summary']) || is_array($emptyItems));
check('stok query kosong overview true', ($emptyStock['overview'] ?? false) === true);
check('stok query kosong items ≤10', is_array($emptyItems) && count($emptyItems) <= 10);
check('stok query kosong shown ≤10', (int) ($emptyStock['shown'] ?? 99) <= 10);

$nullQueryStock = $stockTool->execute(['query' => null], $context);
check('stok query null sukses', ($nullQueryStock['success'] ?? false) === true);
check('stok query null overview true', ($nullQueryStock['overview'] ?? false) === true);

$missingQueryStock = $stockTool->execute([], $context);
check('stok tanpa argumen query sukses', ($missingQueryStock['success'] ?? false) === true);
check('stok tanpa argumen bukan error missing', ! str_contains(mb_strtolower((string) ($missingQueryStock['message'] ?? '')), 'wajib'));

$keywordNeedle = '__no_such_sku_eval__';
$filteredStock = $stockTool->execute(['query' => $keywordNeedle], $context);
check('stok keyword tetap filter', ($filteredStock['query'] ?? '') === $keywordNeedle);
check('stok keyword sukses', ($filteredStock['success'] ?? false) === true);
check('stok keyword overview false', ($filteredStock['overview'] ?? true) === false);
check(
    'stok keyword tidak ketemu atau terfilter',
    ($filteredStock['count'] ?? 1) === 0 || ($filteredStock['items'] ?? ['x']) === []
);

echo "\n=== 7. Conversation history ===\n";

$convSrc = (string) file_get_contents(dirname(__DIR__).'/app/Services/Ai/AgentConversationService.php');
check('listSummariesForUser ada', str_contains($convSrc, 'function listSummariesForUser'));
check('widgetMessages ada', str_contains($convSrc, 'function widgetMessages'));
check('listForUser membatasi max 50 thread', str_contains($convSrc, 'min($limit, 50)'));
check('widgetMessages membatasi max 100 pesan', str_contains($convSrc, 'min($limit, 100)'));
check('list/load tetap filter user_id', str_contains($convSrc, "->where('user_id', \$user->id)"));

$convCtrlSrc = (string) file_get_contents(dirname(__DIR__).'/app/Http/Controllers/Ai/ConversationController.php');
check('index memakai listSummariesForUser', str_contains($convCtrlSrc, 'listSummariesForUser($user)'));
check('messages memakai widgetMessages', str_contains($convCtrlSrc, 'widgetMessages($conversation)'));
check('messages menolak UUID tidak valid', str_contains($convCtrlSrc, 'Str::isUuid($conversationId)'));

$webSrc = (string) file_get_contents(dirname(__DIR__).'/routes/web.php');
check('GET /agent/conversations terdaftar', str_contains($webSrc, "name('agent.conversations')"));
check('GET messages terdaftar', str_contains($webSrc, "name('agent.conversations.messages')"));

$widgetSrc = (string) file_get_contents(dirname(__DIR__).'/resources/views/components/ai/chat-widget.blade.php');
check('widget punya data-list-url', str_contains($widgetSrc, 'data-list-url'));
check('widget punya tombol riwayat', str_contains($widgetSrc, 'agent-chat-history-btn'));

$conversationService = app(AgentConversationService::class);
$historyUser = new User;
$historyUser->id = '00000000-0000-0000-0000-000000000099';
$summaries = $conversationService->listSummariesForUser($historyUser, 5);
check('listSummariesForUser mengembalikan array', is_array($summaries));

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
