<?php

namespace App\Services\Ai;

use App\Models\Ai\AgentConversation;
use App\Models\User;
use App\Services\Ai\Actions\AgentPendingActionStore;
use App\Services\Ai\Actions\AgentTourStore;
use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\Tour\AgentTourIntent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WmsAgentService
{
    public function __construct(
        protected LlmProviderManager $llmManager,
        protected AgentConversationService $conversations,
        protected AgentToolRegistry $toolRegistry,
        protected AgentTourStore $tourStore,
        protected AgentPendingActionStore $pending,
    ) {}

    /**
     * @param  array{path?: ?string, title?: ?string, menu?: ?string}  $page
     * @return array{
     *   success: bool,
     *   conversation_id: string,
     *   reply: array{content: string, format: string, attachments?: array<int, array<string, mixed>>},
     *   message?: string
     * }
     */
    public function handle(User $user, string $message, ?string $conversationId = null, array $page = []): array
    {
        if (! config('agent.enabled')) {
            return $this->error('Agent chat belum diaktifkan.');
        }

        $llm = $this->llmManager->current();

        if (! $llm->isConfigured()) {
            $provider = strtoupper($llm->name());

            return $this->error("{$llm->label()} belum dikonfigurasi. Set AI_PROVIDER={$provider} dan API key di .env.");
        }

        $message = trim($message);

        if ($message === '') {
            return $this->error('Pesan tidak boleh kosong.');
        }

        if (mb_strlen($message) > (int) config('agent.max_message_length', 2000)) {
            return $this->error('Pesan terlalu panjang.');
        }

        $conversation = $this->conversations->resolve($user, $conversationId, $message);
        $context = AgentContext::fromUser($user, 'web', $conversation->id)
            ->withPage(
                $page['path'] ?? null,
                $page['title'] ?? null,
                $page['menu'] ?? null,
            );

        if (! AgentTourIntent::isControl($message)) {
            $this->tourStore->forget($conversation->id);
        }

        $this->conversations->appendMessage($conversation, 'user', $message);

        $tools = $this->toolRegistry->openAiToolsForContext($context);
        $systemPrompt = $this->systemPrompt($context, $tools);
        $maxRounds = (int) config('agent.max_tool_rounds', 5);
        $attachments = [];
        $appliedNotes = [];
        $chatMessages = $this->conversations->buildChatMessages($conversation, $systemPrompt);

        try {
            for ($round = 0; $round < $maxRounds; $round++) {
                $response = $this->chatWithRetry($llm, $chatMessages, $tools);

                $assistantMessage = data_get($response, 'choices.0.message', []);
                $toolCalls = data_get($assistantMessage, 'tool_calls', []);
                $content = data_get($assistantMessage, 'content');

                if (! is_array($toolCalls) || $toolCalls === []) {
                    $finalContent = is_string($content) && trim($content) !== ''
                        ? trim($content)
                        : 'Maaf, saya tidak dapat memproses permintaan tersebut.';

                    return $this->composeAssistantReply($conversation, $finalContent, $attachments);
                }

                $this->conversations->appendMessage(
                    $conversation,
                    'assistant',
                    is_string($content) ? $content : null,
                    null,
                    ['tool_calls' => $toolCalls],
                );

                $chatMessages[] = [
                    'role' => 'assistant',
                    'content' => is_string($content) && $content !== '' ? $content : null,
                    'tool_calls' => $toolCalls,
                ];

                foreach ($toolCalls as $toolCall) {
                    $toolCallId = (string) data_get($toolCall, 'id', '');
                    $toolName = (string) data_get($toolCall, 'function.name', '');
                    $argumentsJson = (string) data_get($toolCall, 'function.arguments', '{}');
                    $arguments = json_decode($argumentsJson, true);

                    if (! is_array($arguments)) {
                        $arguments = [];
                    }

                    $toolResult = $this->executeTool($conversation, $context, $toolName, $arguments);
                    $toolResult = $this->normalizeConfirmationResult($toolResult);
                    $toolContent = json_encode($toolResult, JSON_UNESCAPED_UNICODE);

                    if (filled($toolResult['message'] ?? null)
                        && (($toolResult['applied'] ?? false) === true
                            || ($toolResult['already_exists'] ?? false) === true
                            || ($toolResult['needs_confirmation'] ?? false) === true)) {
                        $appliedNotes[] = (string) $toolResult['message'];
                    }

                    $tableAttachment = $this->buildAttachmentFromToolResult($toolName, $toolResult);

                    if ($tableAttachment !== null) {
                        $attachments[] = $tableAttachment;
                    }

                    $actionAttachment = $this->buildActionAttachment($toolResult);

                    if ($actionAttachment !== null) {
                        $attachments[] = $actionAttachment;
                    }

                    $tourAttachment = $this->buildTourAttachment($toolResult);

                    if ($tourAttachment !== null) {
                        $attachments[] = $tourAttachment;
                    }

                    $pageAttachment = $this->buildPageNavigationAttachment($toolResult);

                    if ($pageAttachment !== null) {
                        $attachments[] = $pageAttachment;
                    }

                    $this->conversations->appendMessage(
                        $conversation,
                        'tool',
                        $toolContent ?: '{}',
                        $toolName,
                        ['tool_call_id' => $toolCallId, 'arguments' => $arguments],
                    );

                    $chatMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => $toolContent ?: '{}',
                    ];
                }

                if ($this->hasActionCard($attachments)) {
                    $confirmContent = $appliedNotes !== []
                        ? implode("\n", array_unique($appliedNotes))
                        : 'Konfirmasi dulu di kartu di bawah. Belum ada data yang diubah.';

                    return $this->composeAssistantReply($conversation, $confirmContent, $attachments);
                }
            }

            $fallback = 'Permintaan membutuhkan terlalu banyak langkah. Coba pertanyaan yang lebih spesifik.';

            return $this->composeAssistantReply($conversation, $fallback, $attachments);
        } catch (\Throwable $e) {
            // Detail teknis hanya masuk log. Pesan ke user dibuat generik agar
            // kegagalan provider tidak membocorkan endpoint atau konfigurasi.
            Log::warning('TITANIE assistant error', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            if ($appliedNotes !== [] || $this->hasActionCard($attachments)) {
                $appliedContent = $appliedNotes !== []
                    ? implode("\n", array_unique($appliedNotes))
                    : 'Konfirmasi dulu di kartu di bawah. Belum ada data yang diubah.';

                return $this->composeAssistantReply($conversation, $appliedContent, $attachments);
            }

            $userMessage = $this->userFacingProviderError($e);
            $this->conversations->appendMessage($conversation, 'assistant', $userMessage);

            return $this->error($userMessage, $conversation);
        }
    }

    /**
     * Close an in-progress product tour without calling the LLM.
     *
     * Overlay Selesai / Lewati must not POST "lanjut" to /agent/chat.
     *
     * @param  array{path?: ?string, title?: ?string, menu?: ?string}  $page
     * @return array{
     *   success: bool,
     *   conversation_id?: string,
     *   reply: array{content: string, format: string, attachments?: array<int, array<string, mixed>>}
     * }
     */
    public function stopTour(User $user, ?string $conversationId = null, string $reason = 'skip', array $page = []): array
    {
        $reason = $reason === 'finish' ? 'finish' : 'skip';
        $recap = AgentTourIntent::recap($reason);

        if ($conversationId === null || $conversationId === '') {
            return [
                'success' => true,
                'reply' => [
                    'content' => $recap,
                    'format' => 'markdown',
                    'attachments' => [],
                ],
            ];
        }

        $conversation = $this->conversations->findForUser($conversationId, $user);

        if ($conversation === null) {
            return [
                'success' => true,
                'reply' => [
                    'content' => $recap,
                    'format' => 'markdown',
                    'attachments' => [],
                ],
            ];
        }

        $context = AgentContext::fromUser($user, 'web', $conversation->id)
            ->withPage(
                $page['path'] ?? null,
                $page['title'] ?? null,
                $page['menu'] ?? null,
            );

        $tool = $this->toolRegistry->get('guide_tour');
        $toolResult = $tool !== null
            ? $this->executeTool($conversation, $context, 'guide_tour', ['operation' => 'stop'])
            : ['success' => true, 'operation' => 'stop', 'active' => false];

        $this->tourStore->forget($conversation->id);
        $this->conversations->appendMessage($conversation, 'assistant', $recap);

        $attachment = $this->buildTourAttachment($toolResult);

        return [
            'success' => true,
            'conversation_id' => $conversation->id,
            'reply' => [
                'content' => $recap,
                'format' => 'markdown',
                'attachments' => $attachment !== null ? [$attachment] : [],
            ],
        ];
    }

    /**
     * Prompt ini sengaja hanya berisi ATURAN PERILAKU, bukan pengetahuan produk.
     *
     * Seluruh isi jawaban tentang aplikasi harus datang dari tool search_docs
     * yang membaca folder docs/, sehingga dokumentasi repo tetap menjadi satu-
     * satunya sumber kebenaran dan tidak ada fakta yang di-hardcode di sini.
     * Aturan alur (PO vs opname, POS vs replenishment) adalah perilaku tool.
     *
     * @param  array<int, array<string, mixed>>  $tools
     */
    protected function systemPrompt(AgentContext $context, array $tools): string
    {
        $assistant = (string) config('agent.assistant_name', 'TITANIE');
        $userName = trim($context->user->first_name.' '.$context->user->last_name);
        $branch = $context->branchName ?? 'tidak diset';
        $toolNames = collect($tools)->map(fn ($t) => data_get($t, 'function.name'))->filter()->implode(', ');
        $pagePath = $context->pagePath ?: 'tidak diketahui';
        $pageTitle = $context->pageTitle ?: 'tidak diketahui';
        $pageMenu = $context->pageMenu ?: 'tidak diketahui';

        return <<<PROMPT
Kamu {$assistant}, asisten resmi di dalam aplikasi ini. Kamu membantu user memahami
aplikasi dan menjawab pertanyaan operasional. Di UI kamu juga pemandu wisata (tour guide)
yang hidup: overlay Product Tour menyorot menu sungguhan; chat hanya narasi singkat.

Konteks user:
- Nama: {$userName}
- Cabang aktif: {$branch}
- Channel: web admin panel
- Lokasi user sekarang:
  - Path: {$pagePath}
  - Judul halaman: {$pageTitle}
  - Menu aktif: {$pageMenu}

Aturan menjawab:
1. Selalu Bahasa Indonesia, ringkas, profesional, dan ramah.
2. Pertanyaan tentang aplikasi — apa ini, alur bisnis, modul, cara pakai fitur,
   arsitektur, scope, atau rencana pengembangan — WAJIB panggil tool search_docs
   lebih dulu. Jangan menjawab dari ingatanmu sendiri.
3. Pertanyaan tentang data nyata (produk, stok, customer, penjualan) WAJIB
   memakai tool data yang sesuai. Jangan mengarang angka.
   Stok: WAJIB panggil get_stock. "tampilkan stok", "seluruh stok", "stok semua",
   "daftar stok", atau permintaan stok tanpa nama produk = query kosong/null
   (overview cabang: jumlah SKU, total qty, habis, stok rendah, plus max 10 baris).
   JANGAN minta SKU dulu. JANGAN bilang tool tidak bisa menampilkan semua produk.
   JANGAN mengalihkan user ke halaman Stok sebagai ganti overview ini.
   Setelah tool kembali: ringkas 10 baris itu. Jika has_more, sebut "ada N lainnya,
   sebut nama/SKU untuk filter". Jika user menyebut nama/SKU, isi query itu.
4. Jawab HANYA berdasarkan hasil tool. Bila tool tidak mengembalikan informasi
   yang menjawab pertanyaan, katakan terus terang kamu tidak tahu dan sebutkan
   bahwa hal itu belum ada di dokumentasi. DILARANG mengarang atau menebak.
5. Saat menjawab dari dokumentasi, sampaikan isinya saja. DILARANG menyebut
   nama file, path docs/, atau atribusi seperti "(sumber: ARCHITECTURE.md)"
   kepada user. Tetap WAJIB pakai search_docs; sitasi hanya untuk pemakaian
   internal, jangan ditampilkan.
6. Pertanyaan di luar cakupan aplikasi ini, tolak dengan sopan dan tawarkan
   bantuan seputar aplikasi.
7. Untuk membuat penjualan, WAJIB pakai tool manage_sale. Alur:
   add_item (bisa berulang) → set_customer jika disebut → propose.
   Jangan mengaku transaksi sudah tersimpan. Tool ini tidak membuat transaksi.
   Kartu Konfirmasi/Batal hanya muncul jika tool mengembalikan confirmation_token.
8. Untuk mengolah data modul mana pun, WAJIB pakai manage_record.
   Jangan bilang kamu tidak bisa. Jangan hanya memberi langkah menu.
   Create master data (karyawan, pelanggan, kategori, divisi, jabatan, gudang, …)
   HARUS selesai di chat lewat manage_record create. Isi name + fields_json
   selengkap data yang user sebut (fullname, email, role, position, division,
   join_date, status). Untuk karyawan: role nama → role_id, username dari email,
   password digenerate jika tidak disebut, join_date "hari ini" = tanggal hari ini,
   status aktif = employee_status Active, cabang dari konteks user.
   JANGAN panggil open_page hanya karena mau menambah data.
   JANGAN minta user klik tombol Tambah atau isi form di halaman.
   Create/update/hapus master WAJIB kartu konfirmasi. Jangan bilang sudah
   tersimpan sebelum user menekan Konfirmasi.
   Akun login (entity=user_account) DILARANG — pakai entity=employee.
   JANGAN update PO/jurnal/produksi/replenishment/pengajuan partner/BOM dari
   chat (receive, post, convert, komponen). Itu di halaman modul.
   Jika tool mengembalikan success=false dan missing[], tanyakan field itu
   dengan satu pertanyaan singkat di chat, lalu panggil manage_record lagi.
   Setelah sukses, laporkan nama, kode, dan role (jika ada). Boleh tawarkan
   "buka halamannya" — baru panggil open_page jika user setuju.
   Jika ragu nama entitas, panggil operation=capabilities dulu.
   Penjualan POS tetap manage_sale.
   Alur operasional — ikuti proses aplikasi, jangan shortcut mutasi:
   - Barang beli / "tambah stok N" / "tambahkan produk X N pcs" tanpa kata
     opname, koreksi, penyesuaian, atau selisih fisik: BUKAN entity=stock.
     Stok masuk supplier lewat Purchase Order lalu penerimaan barang
     (chat tidak bisa receive). JANGAN kartu Tambah N stok. Tawarkan draf PO
     (entity=purchase_order, wajib supplier) atau open_page query="purchase order".
     Jika tool blocked_flow=purchase_order, jangan ulang create stock.
   - Hasil produksi: draf entity=production_order; proses/receive di modul
     Production Order (chat tidak memotong bahan dan tidak receive).
   - Restok agen: draf entity=replenishment (sebut agen), bukan POS, bukan
     stock increment. Submit/approve/kirim tetap di modul Replenishment.
   - Jual tunai cabang: manage_sale. Order agen ke distributor = replenishment,
     bukan manage_sale.
   - Master SKU baru (nama + dijual, tanpa jumlah stok): entity=product.
     Jangan taruh quantity stok di create produk.
   - Penyesuaian / opname / koreksi selisih: entity=stock + konfirmasi.
     Hanya jika user jelas bilang opname, penyesuaian, koreksi, selisih,
     stok fisik, rusak/hilang/afkir, atau "jadikan/set stok ke N".
     Salin kalimat user ke notes. quantity = angka yang user sebut.
     opname tambah N → mode=in quantity=N. kurangi N → mode=out.
     "jadikan/set stok ke N" → mode=set quantity=N. JANGAN mode=set untuk
     "tambah N". JANGAN quantity = stok sekarang − N.
   Agen partner: manage_record create entity=partner_agent — cukup nama toko/agen
   (opsional telepon, alamat, kota). Server menjalankan pendaftaran partner +
   Convert Agent, jadi kode agen, gudang agen, dan akun login otomatis. JANGAN
   bilang agen hanya bisa dibuat lewat form registrasi atau tombol Convert Agent
   di UI, dan jangan menawarkan open_page sebagai gantinya. Perlu konfirmasi kartu.
   PO/jurnal/produksi/replenishment: create draf lewat manage_record, perlu konfirmasi.
   Jurnal post hanya jika seimbang (operation=post).
   Hapus data apa pun, create/update master, dan penetapan role Super Admin
   WAJIB konfirmasi di kartu chat. Hapus PO/jurnal/produksi yang sudah diproses
   tidak boleh dari chat.
   Jangan bilang sudah terhapus/tersimpan sebelum user menekan konfirmasi.
   JANGAN minta user menekan tombol konfirmasi/Batal kecuali hasil tool punya
   needs_confirmation=true DAN confirmation_token terisi. Jika tool gagal,
   success=false, atau ada kendala teknis: jelaskan error dan minta kirim ulang.
   DILARANG mengarang tombol atau kartu konfirmasi.
9. Jika cabang belum diset dan pertanyaannya soal data atau aksi, minta user
   memilih cabang di profil.
10. Format nominal rupiah dengan pemisah ribuan.
11. Jika user bertanya "apa sih ini?", "ini halaman apa?", "jelaskan fitur ini",
    "jelasin halaman ini", atau semacamnya: WAJIB panggil guide_tour operation=here,
    lalu search_docs memakai docs_query dari hasil tool. here = tur DALAM halaman
    yang sedang dibuka (menu induk, submenu, lalu judul/tombol/tabel/form) — BUKAN
    tur 11 modul. Overlay sudah menyorot UI — balasan chat maksimal 2 kalimat,
    jangan dump dokumen.
12. Jika user minta tur ("turin dong", "turin fiturnya", "ajak keliling"):
    WAJIB panggil guide_tour operation=start, lalu search_docs. start SELALU
    dari ruang 1, jangan lanjut dari langkah sebelumnya. Setiap modul: sorot
    sidebar (menu terbuka), lalu paling banyak 2 spot di landing page. Overlay
    Product Tour adalah pengalaman utamanya. Chat 1-2 kalimat. Tombol Lanjut ada di overlay.
13. Jika user bilang "lanjut" / "next" / "ruang berikutnya": guide_tour
    operation=next, lalu search_docs. Chat 1-2 kalimat. Jika tool bilang tidak
    ada tur yang berjalan atau tur sudah selesai, jangan menyorot — tawarkan
    mulai tur dari awal. JANGAN bilang user "masih menekan Lanjut".
14. Jika user bilang "kembali" / "sebelumnya" / "back": guide_tour
    operation=prev, lalu search_docs. Chat 1-2 kalimat.
15. Jika user bilang "stop" / "cukup" / "lewati" / "selesai" / "berhentiin turnya":
    guide_tour operation=stop. Jangan lanjut menyorot. Tombol Selesai di overlay
    langkah terakhir = stop, BUKAN next.
16. Jika user bertanya hal baru yang BUKAN kontrol tur (lanjut/next/kembali/back/
    stop/cukup/lewati/selesai): JANGAN panggil next/prev. Tur overlay sudah ditutup.
    Jawab seperti chat biasa. Kalau mereka minta tur lagi, start dari ruang 1.
17. Jika user secara eksplisit minta buka/pergi ke/tunjukkan/arahkan ke halaman —
    termasuk "buka halamannya dong", "buka halaman kategori", "pergi ke stok" —
    WAJIB panggil open_page. JANGAN bilang kamu tidak bisa navigasi atau
    user harus klik menu sendiri. Widget akan pindah ke url dari tool.
    Jika user minta halaman SUDAH ter-filter ("buka stok minuman",
    "buka stok kategori Minuman", "filter kategori X", "buka item Pocky"):
    panggil open_page dengan query=halaman DAN category atau search terisi.
    JANGAN hanya menyuruh user mengetik di kotak search/filter.
    Boleh juga panggil get_stock untuk menampilkan sampai 10 SKU di chat.
    JANGAN pakai open_page sebagai pengganti manage_record create.
    "tampilkan stok" / "seluruh stok" / "stok semua" / "daftar stok" BUKAN buka
    halaman — itu get_stock dengan query kosong, bukan open_page.
    "halamannya" / "halaman itu" / "yang tadi" = halaman tersirat topik
    percakapan terakhir (kategori makanan → category /product/category;
    item atau produk seperti Pocky → items /product/items). Kirim query
    yang spesifik. Chat 1 kalimat setelah tool sukses.

Tool tersedia: {$toolNames}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function executeTool(
        AgentConversation $conversation,
        AgentContext $context,
        string $toolName,
        array $arguments,
    ): array {
        $tool = $this->toolRegistry->get($toolName);

        if ($tool === null) {
            return [
                'success' => false,
                'message' => "Tool \"{$toolName}\" tidak dikenali.",
            ];
        }

        if (! $context->hasPermission($tool->requiredPermission())) {
            return [
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk operasi ini.',
            ];
        }

        unset($arguments['_confirmed']);

        $started = microtime(true);

        try {
            $result = $tool->execute($arguments, $context);
        } catch (\Throwable $e) {
            Log::warning('TITANIE tool error', [
                'tool' => $toolName,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
            $result = [
                'success' => false,
                'message' => $this->userFacingToolError($e),
            ];
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $this->conversations->logToolCall(
            $conversation,
            $context->user,
            $toolName,
            $arguments,
            $result,
            $durationMs,
        );

        return $result;
    }

    /**
     * @param  array<string, mixed>  $toolResult
     * @return array<string, mixed>|null
     */
    protected function buildAttachmentFromToolResult(string $toolName, array $toolResult): ?array
    {
        $items = $toolResult['items'] ?? null;

        if (! is_array($items) || $items === []) {
            return null;
        }

        return match ($toolName) {
            'search_product' => [
                'type' => 'table',
                'headers' => ['Produk', 'SKU', 'Harga', 'Stok'],
                'rows' => collect($items)->map(fn ($item) => [
                    (string) ($item['label'] ?? '-'),
                    (string) ($item['sku'] ?? '-'),
                    (string) ($item['price_formatted'] ?? $item['price'] ?? '-'),
                    (string) ($item['stock'] ?? '-'),
                ])->all(),
            ],
            'get_stock' => [
                'type' => 'table',
                'headers' => ['Produk', 'SKU', 'Stok', 'Satuan'],
                'rows' => collect($items)->map(fn ($item) => [
                    (string) ($item['label'] ?? '-'),
                    (string) ($item['sku'] ?? '-'),
                    (string) ($item['stock'] ?? '-'),
                    (string) ($item['unit'] ?? '-'),
                ])->all(),
            ],
            'search_customer' => [
                'type' => 'table',
                'headers' => ['Kode', 'Nama'],
                'rows' => collect($items)->map(fn ($item) => [
                    (string) ($item['code'] ?? '-'),
                    (string) ($item['name'] ?? '-'),
                ])->all(),
            ],
            'manage_sale' => [
                'type' => 'table',
                'headers' => ['Produk', 'SKU', 'Qty', 'Subtotal'],
                'rows' => collect($items)->map(fn ($item) => [
                    (string) ($item['label'] ?? '-'),
                    (string) ($item['sku'] ?? '-'),
                    (string) ($item['quantity'] ?? '-'),
                    (string) ($item['line_total_formatted'] ?? '-'),
                ])->all(),
            ],
            'manage_record' => [
                'type' => 'table',
                'headers' => ['Kode', 'Nama'],
                'rows' => collect($items)->map(fn ($item) => [
                    (string) ($item['code'] ?? '-'),
                    (string) ($item['name'] ?? '-'),
                ])->all(),
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $toolResult
     * @return array<string, mixed>|null
     */
    protected function buildActionAttachment(array $toolResult): ?array
    {
        if (! ($toolResult['needs_confirmation'] ?? false)) {
            return null;
        }

        $token = (string) ($toolResult['confirmation_token'] ?? '');

        if ($token === '') {
            return null;
        }

        $isSale = ($toolResult['action'] ?? '') === 'confirm_sale';
        $saleBody = ($toolResult['customer_name'] ?? 'Walk-in').' · '.($toolResult['subtotal_formatted'] ?? '');

        return [
            'type' => 'action_card',
            'action' => (string) ($toolResult['action'] ?? 'confirm_record'),
            'token' => $token,
            'title' => (string) ($toolResult['title'] ?? ($isSale ? 'Konfirmasi penjualan' : 'Konfirmasi')),
            'body' => (string) ($toolResult['body'] ?? ($isSale ? $saleBody : '')),
            'confirm_label' => (string) ($toolResult['confirm_label'] ?? ($isSale ? 'Buat transaksi' : 'Konfirmasi')),
            'cancel_label' => (string) ($toolResult['cancel_label'] ?? 'Batal'),
        ];
    }

    /**
     * Confirmation without a token cannot render buttons. Do not let the model
     * tell the user to press a card that was never attached.
     *
     * @param  array<string, mixed>  $toolResult
     * @return array<string, mixed>
     */
    protected function normalizeConfirmationResult(array $toolResult): array
    {
        if (! ($toolResult['needs_confirmation'] ?? false)) {
            return $toolResult;
        }

        if (filled($toolResult['confirmation_token'] ?? null)) {
            return $toolResult;
        }

        Log::warning('TITANIE confirmation missing token', [
            'action' => $toolResult['action'] ?? $toolResult['confirmation_kind'] ?? null,
        ]);

        return [
            'success' => false,
            'message' => 'Gagal menyiapkan kartu konfirmasi. Kirim ulang permintaan Anda.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array{
     *   success: bool,
     *   conversation_id: string,
     *   reply: array{content: string, format: string, attachments: array<int, array<string, mixed>>}
     * }
     */
    protected function composeAssistantReply(
        AgentConversation $conversation,
        string $content,
        array $attachments,
    ): array {
        $content = AgentReplySanitizer::userFacingConfirmationMessage(
            AgentReplySanitizer::stripSourceCitations($content)
        );
        $attachments = $this->ensureActionCard($conversation->id, $content, $attachments);

        if (! $this->hasActionCard($attachments)) {
            $content = AgentReplySanitizer::withoutOrphanConfirmationCta($content);
        }

        if ($content === '') {
            $content = 'Maaf, saya tidak dapat memproses permintaan tersebut.';
        }

        $this->conversations->appendMessage($conversation, 'assistant', $content);

        return [
            'success' => true,
            'conversation_id' => $conversation->id,
            'reply' => [
                'content' => $content,
                'format' => 'markdown',
                'attachments' => $attachments,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     */
    protected function hasActionCard(array $attachments): bool
    {
        foreach ($attachments as $attachment) {
            $type = (string) ($attachment['type'] ?? '');
            if ($type === 'action_card' || $type === 'action-card') {
                return true;
            }
        }

        return false;
    }

    /**
     * Re-attach a pending card when the model asks the user to press Confirm
     * but this turn did not include an action_card attachment.
     *
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array<int, array<string, mixed>>
     */
    protected function ensureActionCard(string $conversationId, string $content, array $attachments): array
    {
        if ($this->hasActionCard($attachments)) {
            return $attachments;
        }

        if (! AgentReplySanitizer::asksToPressConfirmationButton($content)) {
            return $attachments;
        }

        $pending = $this->pending->get($conversationId);
        $token = is_array($pending) ? (string) ($pending['token'] ?? '') : '';

        if ($token === '') {
            return $attachments;
        }

        $attachments[] = [
            'type' => 'action_card',
            'action' => (string) ($pending['kind'] ?? 'confirm_record'),
            'token' => $token,
            'title' => (string) ($pending['title'] ?? 'Konfirmasi'),
            'body' => (string) ($pending['body'] ?? ''),
            'confirm_label' => (string) ($pending['confirm_label'] ?? 'Konfirmasi'),
            'cancel_label' => (string) ($pending['cancel_label'] ?? 'Batal'),
        ];

        return $attachments;
    }

    /**
     * @param  array<string, mixed>  $toolResult
     * @return array<string, mixed>|null
     */
    protected function buildTourAttachment(array $toolResult): ?array
    {
        $highlight = $toolResult['highlight'] ?? null;

        if (! is_array($highlight)) {
            return null;
        }

        $selector = trim((string) ($highlight['selector'] ?? ''));

        if ($selector === '' && ! array_key_exists('active', $toolResult)) {
            return null;
        }

        $menuNames = array_values(array_filter(
            (array) ($highlight['menu_names'] ?? []),
            static fn ($name) => is_string($name) && $name !== '',
        ));

        return [
            'type' => 'tour_highlight',
            'operation' => (string) ($toolResult['operation'] ?? 'here'),
            'selector' => $selector,
            'heading_selector' => (string) ($highlight['heading_selector'] ?? ''),
            'label' => (string) ($highlight['label'] ?? ''),
            'body' => (string) ($highlight['blurb'] ?? ''),
            'voice' => (string) ($highlight['voice'] ?? ''),
            'menu_names' => $menuNames,
            'navigate_url' => $highlight['navigate_url'] ?? null,
            'kind' => (string) ($highlight['kind'] ?? 'sidebar'),
            'spot_key' => $highlight['spot_key'] ?? null,
            'step' => (int) ($toolResult['step'] ?? 0),
            'total' => (int) ($toolResult['total'] ?? 0),
            'active' => (bool) ($toolResult['active'] ?? false),
            'has_prev' => (bool) ($toolResult['has_prev'] ?? false),
            'is_last' => (bool) ($toolResult['is_last'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $toolResult
     * @return array<string, mixed>|null
     */
    protected function buildPageNavigationAttachment(array $toolResult): ?array
    {
        if (($toolResult['needs_navigation'] ?? false) !== true) {
            return null;
        }

        $url = trim((string) ($toolResult['url'] ?? ''));

        if ($url === '' || ! str_starts_with($url, '/') || str_contains($url, '://') || str_contains($url, '..')) {
            return null;
        }

        return [
            'type' => 'page_navigation',
            'url' => $url,
            'label' => (string) ($toolResult['label'] ?? ''),
            'needs_navigation' => true,
            'new_tab' => (bool) ($toolResult['new_tab'] ?? false),
        ];
    }

    /**
     * Map provider/transport failures to a short user-facing sentence.
     * Never include endpoint, status body, or provider internals.
     */
    protected function userFacingProviderError(\Throwable $e): string
    {
        $raw = $e->getMessage();
        $lower = strtolower($raw);

        if ($e instanceof ConnectionException
            || str_contains($lower, 'timed out')
            || str_contains($lower, 'timeout')
            || str_contains($lower, 'curl error')
            || str_contains($lower, 'could not resolve host')
            || str_contains($lower, 'connection')) {
            return 'Koneksi ke asisten terputus. Periksa jaringan lalu coba lagi.';
        }

        if (str_contains($raw, 'API error: 429')) {
            return 'Asisten sedang sibuk. Tunggu sebentar lalu coba lagi.';
        }

        if (str_contains($raw, 'API error: 401') || str_contains($raw, 'API error: 403')) {
            return 'Asisten belum bisa terhubung ke layanan AI. Hubungi admin untuk memeriksa konfigurasi.';
        }

        if (preg_match('/API error: 5\d\d/', $raw) === 1) {
            return 'Layanan AI sedang bermasalah. Silakan coba lagi sebentar lagi.';
        }

        if (str_contains($raw, 'API error: 400') || str_contains($raw, 'tool_calls')) {
            return 'Percakapan ini tersendat. Coba kirim ulang, atau mulai percakapan baru.';
        }

        return 'Maaf, saya sedang tidak bisa memproses permintaan itu. Silakan coba lagi sebentar lagi.';
    }

    /**
     * @return array{success: false, message: string, conversation_id?: string, reply?: array<string, mixed>}
     */
    protected function error(string $message, ?AgentConversation $conversation = null): array
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($conversation !== null) {
            $payload['conversation_id'] = $conversation->id;
        }

        return $payload;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    protected function chatWithRetry(LlmProviderInterface $llm, array $messages, array $tools): array
    {
        try {
            return $llm->chat(messages: $messages, tools: $tools);
        } catch (\Throwable $e) {
            if (! $this->isRetryableLlmFailure($e)) {
                throw $e;
            }

            Log::info('TITANIE LLM retry after transient failure', [
                'exception' => $e::class,
            ]);

            return $llm->chat(messages: $messages, tools: $tools);
        }
    }

    protected function isRetryableLlmFailure(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        $raw = $e->getMessage();
        $lower = strtolower($raw);

        if (str_contains($lower, 'timed out')
            || str_contains($lower, 'timeout')
            || str_contains($lower, 'curl error')) {
            return true;
        }

        return preg_match('/API error: 5\d\d/', $raw) === 1;
    }

    protected function userFacingToolError(\Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            $first = collect($e->errors())->flatten()->filter()->first();

            return is_string($first) && $first !== ''
                ? $first
                : 'Data tidak valid. Lengkapi isian lalu coba lagi.';
        }

        $raw = $e->getMessage();

        if (preg_match('/SQLSTATE|stack trace|\\\\app\\\\|pgsql|Connection: pgsql/i', $raw) === 1) {
            return 'Gagal menyimpan data. Periksa isian lalu coba lagi dari chat.';
        }

        if (trim($raw) !== '' && ! str_contains($raw, 'API error:')) {
            return $raw;
        }

        return 'Gagal memproses permintaan. Silakan coba lagi.';
    }
}
