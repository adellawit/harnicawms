<?php

namespace App\Services\Telegram;

use App\Models\MethodPayment;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Support\Facades\Log;

class TelegramConversationService
{
    public function __construct(
        protected TelegramBotService $bot,
        protected TelegramAuthService $auth,
        protected TelegramSessionService $sessions,
        protected TelegramDraftResolver $draftResolver,
        protected TelegramCheckoutService $checkout,
        protected TelegramProductResolver $products,
        protected XenditService $xendit,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     */
    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if ($message === null) {
            return;
        }

        $chatId = (int) data_get($message, 'chat.id');
        $telegramUserId = (int) data_get($message, 'from.id');
        $text = trim((string) data_get($message, 'text', ''));

        if ($text === '') {
            $this->bot->sendMessage($chatId, 'Kirim pesan teks. Contoh: <code>/transaksi 3 kopi untuk Budi tunai</code>');

            return;
        }

        if (str_starts_with($text, '/')) {
            $this->handleCommand($chatId, $telegramUserId, $text, $message);

            return;
        }

        $account = $this->auth->findActiveAccount($telegramUserId);

        if ($account === null) {
            $this->bot->sendMessage($chatId, 'Akun belum terhubung. Hubungkan dulu dengan <code>/link KODE</code>.');

            return;
        }

        $this->processTransactionInput($chatId, $telegramUserId, $text, $account->user);
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     */
    protected function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackId = (string) data_get($callbackQuery, 'id');
        $data = (string) data_get($callbackQuery, 'data', '');
        $chatId = (int) data_get($callbackQuery, 'message.chat.id');
        $telegramUserId = (int) data_get($callbackQuery, 'from.id');

        $account = $this->auth->findActiveAccount($telegramUserId);

        if ($account === null) {
            $this->bot->answerCallbackQuery($callbackId, 'Akun belum terhubung.');

            return;
        }

        try {
            if ($data === 'cfm') {
                $this->handleConfirm($chatId, $telegramUserId, $account->user);
                $this->bot->answerCallbackQuery($callbackId, 'Memproses...');
            } elseif ($data === 'cnl') {
                $this->sessions->clear($telegramUserId);
                $this->bot->sendMessage($chatId, 'Transaksi dibatalkan.');
                $this->bot->answerCallbackQuery($callbackId);
            } elseif (str_starts_with($data, 'xc:')) {
                $this->handleXenditChannelPick($chatId, $telegramUserId, substr($data, 3), $account->user);
                $this->bot->answerCallbackQuery($callbackId);
            } elseif (str_starts_with($data, 'pm:')) {
                $index = (int) substr($data, 3);
                $this->handlePaymentPick($chatId, $telegramUserId, $index, $account->user);
                $this->bot->answerCallbackQuery($callbackId);
            } elseif (str_starts_with($data, 'cu:')) {
                $this->handleCustomerPickByCallback($chatId, $telegramUserId, substr($data, 3), $account->user);
                $this->bot->answerCallbackQuery($callbackId);
            } elseif (str_starts_with($data, 'vc:')) {
                [$itemIndex, $optionIndex] = array_map('intval', explode(':', substr($data, 3)));
                $this->handleVariantPick($chatId, $telegramUserId, $itemIndex, $optionIndex, $account->user);
                $this->bot->answerCallbackQuery($callbackId);
            } else {
                $this->bot->answerCallbackQuery($callbackId, 'Aksi tidak dikenali.');
            }
        } catch (\Throwable $e) {
            Log::error('Telegram callback error', ['error' => $e->getMessage(), 'data' => $data]);
            $this->bot->answerCallbackQuery($callbackId, 'Terjadi kesalahan.');
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function handleCommand(int $chatId, int $telegramUserId, string $text, array $message): void
    {
        $parts = preg_split('/\s+/', $text, 2) ?: [$text];
        $command = strtolower(explode('@', $parts[0], 2)[0]);
        $argument = trim($parts[1] ?? '');

        match ($command) {
            '/start', '/help' => $this->sendHelp($chatId),
            '/link' => $this->handleLink($chatId, $telegramUserId, $argument, $message),
            '/menu', '/produk' => $this->handleMenuCommand($chatId, $telegramUserId, $argument),
            '/transaksi' => $this->handleTransaksiCommand($chatId, $telegramUserId, $argument),
            '/batal' => $this->handleCancelCommand($chatId, $telegramUserId),
            '/status' => $this->handleStatusCommand($chatId, $telegramUserId),
            default => $this->bot->sendMessage($chatId, 'Perintah tidak dikenali. Ketik /help'),
        };
    }

    protected function sendHelp(int $chatId): void
    {
        $aiStatus = config('telegram.ai_enabled') && config('deepseek.enabled') ? 'aktif' : 'nonaktif';

        $this->bot->sendMessage($chatId, implode("\n", [
            '<b>Telegram POS WMS</b>',
            '',
            '<b>Perintah:</b>',
            '/link KODE — hubungkan akun kasir',
            '/menu [cari] — lihat daftar produk cabang',
            '/transaksi [pesan] — buat transaksi',
            '/status — lihat draft aktif',
            '/batal — batalkan draft',
            '/help — bantuan',
            '',
            '<b>Contoh transaksi (AI '.$aiStatus.'):</b>',
            '<code>/transaksi 3 kopi arabica 2 susu uht untuk Budi tunai</code>',
            '',
            'Atau setelah /transaksi, kirim pesan natural language.',
        ]));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function handleLink(int $chatId, int $telegramUserId, string $code, array $message): void
    {
        if ($code === '') {
            $this->bot->sendMessage($chatId, 'Format: <code>/link KODE</code>. Minta kode dari admin/backoffice.');

            return;
        }

        $username = data_get($message, 'from.username');
        $result = $this->auth->linkAccount(
            $code,
            $telegramUserId,
            is_string($username) ? $username : null,
            $chatId
        );

        $this->bot->sendMessage($chatId, $result['message']);
    }

    protected function handleMenuCommand(int $chatId, int $telegramUserId, string $keyword): void
    {
        $account = $this->auth->findActiveAccount($telegramUserId);

        if ($account === null) {
            $this->bot->sendMessage($chatId, 'Akun belum terhubung. Gunakan <code>/link KODE</code>.');

            return;
        }

        $user = $account->user;
        $branchId = $user->getBranchIdForTransaction();

        if ($branchId === null) {
            $this->bot->sendMessage($chatId, 'Cabang aktif tidak ditemukan. Set branch di akun kasir.');

            return;
        }

        $companyId = $user->getCompanyIdForProduct();
        $branchName = $user->businessUnit?->name ?? 'Cabang';
        $priceListName = $this->products->resolveDefaultPriceListName($branchId, $companyId);
        $items = $this->products->branchMenuItems($branchId, $companyId, $keyword !== '' ? $keyword : null);

        if ($items->isEmpty()) {
            $hint = $keyword !== ''
                ? "Produk \"{$keyword}\" tidak ditemukan di cabang {$branchName}."
                : "Belum ada produk jual aktif di cabang {$branchName}.";

            $this->bot->sendMessage($chatId, $hint."\n\nCoba: <code>/menu black</code>");

            return;
        }

        $searchLabel = $keyword !== '' ? ' (filter: '.e($keyword).')' : '';
        $header = implode("\n", [
            '<b>Menu Produk — '.e($branchName).'</b>'.$searchLabel,
            'Price list: '.e($priceListName),
            'Total: '.$items->count().' item',
            '',
        ]);

        $lines = $items
            ->map(fn (array $item) => $this->products->formatMenuLine($item['label'], $item['unit_price']))
            ->all();

        $footer = "\n\n<i>Contoh:</i> <code>/transaksi 3 black hot untuk Budi tunai</code>";
        $this->sendChunkedMessages($chatId, $header, $lines, $footer);
    }

    /**
     * @param  array<int, string>  $lines
     */
    protected function sendChunkedMessages(int $chatId, string $header, array $lines, string $footer = ''): void
    {
        $maxLength = 3800;
        $chunks = [];
        $current = $header;

        foreach ($lines as $line) {
            $next = $current === '' ? $line : $current."\n".$line;

            if (strlen($next) > $maxLength) {
                $chunks[] = $current;
                $current = $line;
            } else {
                $current = $next;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        if ($chunks === []) {
            $chunks[] = $header;
        }

        $lastIndex = count($chunks) - 1;

        foreach ($chunks as $index => $chunk) {
            if ($index === $lastIndex && $footer !== '') {
                $chunk .= $footer;
            }

            $this->bot->sendMessage($chatId, $chunk);
        }
    }

    protected function handleTransaksiCommand(int $chatId, int $telegramUserId, string $argument): void
    {
        $account = $this->auth->findActiveAccount($telegramUserId);

        if ($account === null) {
            $this->bot->sendMessage($chatId, 'Akun belum terhubung. Gunakan <code>/link KODE</code>.');

            return;
        }

        if ($argument === '') {
            $this->sessions->setState($telegramUserId, 'await_transaction_input', []);
            $this->bot->sendMessage($chatId, 'Kirim detail transaksi. Contoh:'."\n".'<code>3 kopi arabica untuk Budi bayar tunai</code>');

            return;
        }

        $this->processTransactionInput($chatId, $telegramUserId, $argument, $account->user);
    }

    protected function handleCancelCommand(int $chatId, int $telegramUserId): void
    {
        $this->sessions->clear($telegramUserId);
        $this->bot->sendMessage($chatId, 'Draft transaksi dibatalkan.');
    }

    protected function handleStatusCommand(int $chatId, int $telegramUserId): void
    {
        $session = $this->sessions->get($telegramUserId);
        $draft = $session->payload['draft'] ?? null;

        if ($draft === null) {
            $this->bot->sendMessage($chatId, 'Tidak ada draft transaksi aktif.');

            return;
        }

        $this->bot->sendMessage($chatId, $this->formatDraftPreview($draft), $this->confirmKeyboard());
    }

    protected function processTransactionInput(int $chatId, int $telegramUserId, string $message, User $user): void
    {
        if (! config('telegram.ai_enabled') || ! config('deepseek.api_key')) {
            $this->bot->sendMessage($chatId, 'AI parser belum aktif. Set DEEPSEEK_API_KEY dan TELEGRAM_AI_ENABLED=true.');

            return;
        }

        $this->bot->sendMessage($chatId, '⏳ Memproses pesan...');

        $result = $this->draftResolver->buildFromNaturalLanguage($message, $user);

        if (($result['variant_choices'] ?? []) !== []) {
            $this->handleVariantChoices($chatId, $telegramUserId, $result);

            return;
        }

        if (($result['customer_choices'] ?? []) !== []) {
            $this->handleCustomerChoices($chatId, $telegramUserId, $result);

            return;
        }

        if (! ($result['success'] ?? false)) {
            $this->bot->sendMessage($chatId, '❌ '.($result['message'] ?? 'Gagal memproses transaksi.'));

            return;
        }

        $draft = $result['draft'] ?? [];

        if ($result['needs_payment'] ?? false) {
            $this->promptPaymentSelection($chatId, $telegramUserId, $draft, $user);

            return;
        }

        $methodPayment = MethodPayment::query()->find($draft['payment_method_id'] ?? null);

        if ($methodPayment !== null) {
            $this->continueAfterPaymentMethodPick($chatId, $telegramUserId, $draft, $methodPayment);

            return;
        }

        $this->presentDraftForConfirm($chatId, $telegramUserId, $draft);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function handleVariantChoices(int $chatId, int $telegramUserId, array $result): void
    {
        $partial = $result['partial_draft'] ?? [];
        $choices = $result['variant_choices'] ?? [];
        $first = $choices[0] ?? null;

        if ($first === null) {
            $this->bot->sendMessage($chatId, '❌ Produk ambigu tetapi pilihan tidak tersedia.');

            return;
        }

        $this->sessions->setState($telegramUserId, 'await_variant_pick', [
            'partial_draft' => $partial,
            'variant_choices' => $choices,
            'current_choice_index' => 0,
        ]);

        $this->sendVariantChoicePrompt($chatId, $first);
    }

    /**
     * @param  array<string, mixed>  $choice
     */
    protected function sendVariantChoicePrompt(int $chatId, array $choice): void
    {
        $rows = [];
        $itemIndex = (int) ($choice['index'] ?? 0);

        foreach ($choice['options'] ?? [] as $optionIndex => $option) {
            $label = mb_substr((string) ($option['label'] ?? 'Produk'), 0, 40);
            $price = number_format((float) ($option['unit_price'] ?? 0), 0, ',', '.');
            $rows[] = [$this->bot->button("{$label} (Rp {$price})", "vc:{$itemIndex}:{$optionIndex}")];
        }

        $query = (string) ($choice['query'] ?? 'produk');
        $this->bot->sendMessage(
            $chatId,
            "Pilih variant untuk <b>{$query}</b>:",
            $this->bot->inlineKeyboard($rows)
        );
    }

    protected function handleVariantPick(int $chatId, int $telegramUserId, int $itemIndex, int $optionIndex, User $user): void
    {
        $session = $this->sessions->get($telegramUserId);
        $partial = $session->payload['partial_draft'] ?? [];
        $choices = $session->payload['variant_choices'] ?? [];
        $current = collect($choices)->firstWhere('index', $itemIndex);

        if ($current === null) {
            $this->bot->sendMessage($chatId, 'Pilihan produk tidak valid.');

            return;
        }

        $option = $current['options'][$optionIndex] ?? null;

        if ($option === null) {
            $this->bot->sendMessage($chatId, 'Pilihan produk tidak valid.');

            return;
        }

        $quantity = (float) ($current['quantity'] ?? 1);
        $resolvedItems = $partial['resolved_items'] ?? [];
        $resolvedItems[] = $this->draftResolver->buildResolvedItem($option, $quantity);

        $remaining = collect($choices)->filter(fn ($c) => (int) $c['index'] !== $itemIndex)->values()->all();

        if ($remaining !== []) {
            $partial['resolved_items'] = $resolvedItems;
            $this->sessions->setState($telegramUserId, 'await_variant_pick', [
                'partial_draft' => $partial,
                'variant_choices' => $remaining,
            ]);
            $this->sendVariantChoicePrompt($chatId, $remaining[0]);

            return;
        }

        $draft = [
            'branch_id' => $partial['branch_id'],
            'company_id' => $partial['company_id'],
            'price_list_id' => $partial['price_list_id'],
            'customer_id' => null,
            'customer_name' => $partial['customer_name'] ?? null,
            'customer_mode' => filled($partial['customer_name'] ?? null) ? 'new_or_existing' : 'walk_in',
            'items' => $resolvedItems,
            'payment_method_id' => null,
            'payment_hint' => $partial['payment_hint'] ?? null,
        ];

        $paymentMethod = $this->draftResolver->resolvePaymentMethod(
            (string) $draft['branch_id'],
            $draft['payment_hint']
        );

        if ($paymentMethod === null) {
            $this->promptPaymentSelection($chatId, $telegramUserId, $draft, $user);

            return;
        }

        $draft['payment_method_id'] = $paymentMethod->id;
        $draft['payment_method_name'] = $paymentMethod->name;

        $this->continueAfterPaymentMethodPick($chatId, $telegramUserId, $draft, $paymentMethod);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function handleCustomerChoices(int $chatId, int $telegramUserId, array $result): void
    {
        $partial = $result['partial_draft'] ?? [];
        $choices = $result['customer_choices'] ?? [];

        $rows = [];

        foreach ($choices as $index => $choice) {
            $label = mb_substr($choice['name'].' ('.$choice['code'].')', 0, 45);
            $rows[] = [$this->bot->button($label, "cu:{$index}")];
        }

        $rows[] = [$this->bot->button('➕ Customer baru', 'cu:new')];

        $this->sessions->setState($telegramUserId, 'await_customer_pick', [
            'partial_draft' => $partial,
            'customer_choices' => $choices,
        ]);

        $this->bot->sendMessage(
            $chatId,
            'Pilih customer:',
            $this->bot->inlineKeyboard($rows)
        );
    }

    protected function handleCustomerPickByCallback(int $chatId, int $telegramUserId, string $suffix, User $user): void
    {
        $session = $this->sessions->get($telegramUserId);
        $partial = $session->payload['partial_draft'] ?? [];
        $choices = $session->payload['customer_choices'] ?? [];

        $draft = [
            'branch_id' => $partial['branch_id'],
            'company_id' => $partial['company_id'],
            'price_list_id' => $partial['price_list_id'],
            'items' => $partial['items'] ?? [],
            'payment_hint' => $partial['payment_hint'] ?? null,
        ];

        if ($suffix === 'new') {
            $draft['customer_id'] = null;
            $draft['customer_name'] = $partial['customer_name'] ?? null;
        } else {
            $index = (int) $suffix;
            $choice = $choices[$index] ?? null;

            if ($choice === null) {
                $this->bot->sendMessage($chatId, 'Customer tidak valid.');

                return;
            }

            $draft['customer_id'] = $choice['id'];
            $draft['customer_name'] = $choice['name'];
        }

        $paymentMethod = $this->draftResolver->resolvePaymentMethod(
            (string) $draft['branch_id'],
            $draft['payment_hint']
        );

        if ($paymentMethod === null) {
            $this->promptPaymentSelection($chatId, $telegramUserId, $draft, $user);

            return;
        }

        $draft['payment_method_id'] = $paymentMethod->id;
        $draft['payment_method_name'] = $paymentMethod->name;

        $this->continueAfterPaymentMethodPick($chatId, $telegramUserId, $draft, $paymentMethod);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function promptPaymentSelection(int $chatId, int $telegramUserId, array $draft, User $user): void
    {
        $branchId = (string) ($draft['branch_id'] ?? $user->getBranchIdForTransaction());
        $methods = $this->draftResolver->availablePaymentMethods($branchId)
            ->filter(function (MethodPayment $method) {
                if ($this->xendit->usesXenditForMethod($method->code, $method)) {
                    return $this->xendit->isConfigured();
                }

                return true;
            })
            ->values();

        if ($methods->isEmpty()) {
            $this->bot->sendMessage($chatId, '❌ Metode pembayaran tidak tersedia untuk cabang ini.');

            return;
        }

        if ($methods->count() === 1) {
            $method = $methods->first();
            $draft['payment_method_id'] = $method->id;
            $draft['payment_method_name'] = $method->name;
            $this->continueAfterPaymentMethodPick($chatId, $telegramUserId, $draft, $method);

            return;
        }

        $rows = [];

        foreach ($methods->values() as $index => $method) {
            $rows[] = [$this->bot->button($method->name, "pm:{$index}")];
        }

        $this->sessions->setState($telegramUserId, 'await_payment', [
            'draft' => $draft,
            'payment_methods' => $methods->map(fn (MethodPayment $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'code' => $m->code,
                'uses_payment_gateway' => $m->uses_payment_gateway,
                'gateway_channel_code' => $m->gateway_channel_code,
                'payment_group_code' => $m->payment_group_code,
            ])->values()->all(),
        ]);

        $this->bot->sendMessage(
            $chatId,
            'Pilih metode pembayaran:',
            $this->bot->inlineKeyboard($rows)
        );
    }

    protected function handlePaymentPick(int $chatId, int $telegramUserId, int $index, User $user): void
    {
        $session = $this->sessions->get($telegramUserId);
        $methods = $session->payload['payment_methods'] ?? [];
        $draft = $session->payload['draft'] ?? [];
        $method = $methods[$index] ?? null;

        if ($method === null) {
            $this->bot->sendMessage($chatId, 'Metode pembayaran tidak valid.');

            return;
        }

        $methodPayment = MethodPayment::query()
            ->where('id', $method['id'])
            ->whereNull('deleted_at')
            ->first();

        if ($methodPayment === null) {
            $this->bot->sendMessage($chatId, 'Metode pembayaran tidak valid.');

            return;
        }

        $draft['payment_method_id'] = $methodPayment->id;
        $draft['payment_method_name'] = $methodPayment->name;

        $this->continueAfterPaymentMethodPick($chatId, $telegramUserId, $draft, $methodPayment);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function continueAfterPaymentMethodPick(
        int $chatId,
        int $telegramUserId,
        array $draft,
        MethodPayment $methodPayment,
    ): void {
        if (! $this->xendit->usesXenditForMethod($methodPayment->code, $methodPayment)) {
            $this->presentDraftForConfirm($chatId, $telegramUserId, $draft);

            return;
        }

        if (! $this->xendit->isConfigured()) {
            $this->bot->sendMessage($chatId, '❌ Payment gateway Xendit belum dikonfigurasi.');

            return;
        }

        if ($methodPayment->isPgChannel()) {
            $draft['xendit_channel'] = $methodPayment->gateway_channel_code;
            $this->presentDraftForConfirm($chatId, $telegramUserId, $draft);

            return;
        }

        $channels = $this->xenditChannelsForMethod($methodPayment);

        if ($channels === []) {
            $this->presentDraftForConfirm($chatId, $telegramUserId, $draft);

            return;
        }

        if (count($channels) === 1) {
            $draft['xendit_channel'] = $channels[0]['code'];
            $this->presentDraftForConfirm($chatId, $telegramUserId, $draft);

            return;
        }

        $this->promptXenditChannelSelection($chatId, $telegramUserId, $draft, $methodPayment, $channels);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<int, array{code: string, label: string}>  $channels
     */
    protected function promptXenditChannelSelection(
        int $chatId,
        int $telegramUserId,
        array $draft,
        MethodPayment $methodPayment,
        array $channels,
    ): void {
        $rows = [];

        foreach (array_chunk($channels, 2) as $chunk) {
            $row = [];
            foreach ($chunk as $channel) {
                $label = mb_substr((string) ($channel['label'] ?? $channel['code']), 0, 32);
                $row[] = $this->bot->button($label, 'xc:'.strtoupper((string) $channel['code']));
            }
            $rows[] = $row;
        }

        $this->sessions->setState($telegramUserId, 'await_xendit_channel', [
            'draft' => $draft,
            'payment_method' => [
                'id' => $methodPayment->id,
                'name' => $methodPayment->name,
                'code' => $methodPayment->code,
            ],
            'channels' => $channels,
        ]);

        $this->bot->sendMessage(
            $chatId,
            'Pilih channel <b>'.e($methodPayment->name).'</b>:',
            $this->bot->inlineKeyboard($rows)
        );
    }

    protected function handleXenditChannelPick(int $chatId, int $telegramUserId, string $channelCode, User $user): void
    {
        $session = $this->sessions->get($telegramUserId);
        $draft = $session->payload['draft'] ?? [];
        $channels = collect($session->payload['channels'] ?? []);
        $normalized = strtoupper(trim($channelCode));

        if ($normalized === '' || ! $channels->contains(fn (array $ch) => strtoupper((string) $ch['code']) === $normalized)) {
            $this->bot->sendMessage($chatId, 'Channel pembayaran tidak valid.');

            return;
        }

        $draft['xendit_channel'] = $normalized;
        $this->presentDraftForConfirm($chatId, $telegramUserId, $draft);
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    protected function xenditChannelsForMethod(MethodPayment $methodPayment): array
    {
        $groups = $this->xendit->buildPaymentChannelGroups([$methodPayment]);
        $methodCode = strtoupper((string) $methodPayment->code);

        foreach ($groups as $group) {
            if (strtoupper((string) ($group['method_code'] ?? '')) === $methodCode) {
                return array_map(fn (array $channel) => [
                    'code' => (string) $channel['code'],
                    'label' => (string) $channel['label'],
                ], $group['channels'] ?? []);
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function presentDraftForConfirm(int $chatId, int $telegramUserId, array $draft): void
    {
        $this->sessions->setState($telegramUserId, 'await_confirm', [
            'draft' => $draft,
        ]);

        $this->bot->sendMessage(
            $chatId,
            $this->formatDraftPreview($draft),
            $this->confirmKeyboard()
        );
    }

    protected function handleConfirm(int $chatId, int $telegramUserId, User $user): void
    {
        $session = $this->sessions->get($telegramUserId);
        $draft = $session->payload['draft'] ?? null;

        if ($draft === null) {
            $this->bot->sendMessage($chatId, 'Draft transaksi tidak ditemukan. Mulai lagi dengan /transaksi');

            return;
        }

        $result = $this->checkout->submit($draft, $user);
        $this->sessions->clear($telegramUserId);

        if (! ($result['success'] ?? false)) {
            $this->bot->sendMessage($chatId, '❌ '.($result['message'] ?? 'Checkout gagal.'));

            return;
        }

        $data = $result['data'] ?? [];
        $isPendingXendit = ($data['payment_status'] ?? '') === 'pending' && filled($data['invoice_url'] ?? null);

        if ($isPendingXendit) {
            $lines = [
                '⏳ <b>Order dibuat — menunggu pembayaran</b>',
                '',
                'No: <code>'.($data['sales_number'] ?? '-').'</code>',
                'Customer: '.htmlspecialchars((string) ($data['customer_name'] ?? '-')),
                'Total: Rp '.number_format((float) ($data['total'] ?? 0), 0, ',', '.'),
                'Payment: '.htmlspecialchars((string) ($data['payment_method'] ?? '-')),
                '',
                '🔗 <a href="'.e((string) $data['invoice_url']).'">Bayar via Xendit</a>',
                '',
                'Order akan selesai otomatis setelah pembayaran terkonfirmasi.',
            ];
        } else {
            $lines = [
                '✅ <b>Transaksi berhasil</b>',
                '',
                'No: <code>'.($data['sales_number'] ?? '-').'</code>',
                'Customer: '.htmlspecialchars((string) ($data['customer_name'] ?? '-')),
                'Total: Rp '.number_format((float) ($data['total'] ?? 0), 0, ',', '.'),
                'Payment: '.htmlspecialchars((string) ($data['payment_method'] ?? '-')),
            ];
        }

        if ($data['customer_created'] ?? false) {
            $lines[] = '';
            $lines[] = 'ℹ️ Customer baru otomatis dibuat.';
        }

        $this->bot->sendMessage($chatId, implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function formatDraftPreview(array $draft): string
    {
        $lines = [
            '📋 <b>RINGKASAN TRANSAKSI</b>',
            '',
        ];

        $customerName = $draft['customer_name'] ?? 'Walk-in Customer';

        if (($draft['customer_id'] ?? null) === null && filled($draft['customer_name'] ?? null)) {
            $customerName .= ' (baru jika belum ada)';
        }

        $lines[] = 'Customer: '.htmlspecialchars((string) $customerName);
        $lines[] = 'Items:';

        foreach ($draft['items'] ?? [] as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $label = htmlspecialchars((string) ($item['label'] ?? 'Item'));
            $lineTotal = number_format((float) ($item['line_total'] ?? 0), 0, ',', '.');
            $lines[] = "• {$label} x{$qty} = Rp {$lineTotal}";
        }

        $total = $this->draftResolver->calculateDraftTotal($draft);
        $lines[] = '';
        $lines[] = 'Total: Rp '.number_format($total, 0, ',', '.');
        $paymentLabel = (string) ($draft['payment_method_name'] ?? '-');
        if (filled($draft['xendit_channel'] ?? null)) {
            $paymentLabel .= ' ('.strtoupper((string) $draft['xendit_channel']).')';
        }
        $lines[] = 'Payment: '.htmlspecialchars($paymentLabel);

        return implode("\n", $lines);
    }

    protected function confirmKeyboard(): array
    {
        return $this->bot->inlineKeyboard([
            [
                $this->bot->button('✅ Konfirmasi', 'cfm'),
                $this->bot->button('❌ Batal', 'cnl'),
            ],
        ]);
    }
}
