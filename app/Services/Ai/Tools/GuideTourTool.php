<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Actions\AgentTourStore;
use App\Services\Ai\AgentContext;
use App\Services\Ai\Tour\AgentTourCatalog;
use App\Services\Ai\Tour\AgentTourSequence;

class GuideTourTool extends AbstractAgentTool
{
    public function __construct(
        protected AgentTourCatalog $catalog,
        protected AgentTourSequence $sequence,
        protected AgentTourStore $store,
    ) {}

    public function name(): string
    {
        return 'guide_tour';
    }

    public function description(): string
    {
        return 'In-app product tour of the admin UI. Use here when the user asks what this page is '
            .'(apa sih ini, ini halaman apa, jelaskan fitur ini, jelasin halaman ini) — that is a DEEP tour '
            .'of the CURRENT page (parent menu, submenus, then 3-6 UI spots), NOT the 11-module overview. '
            .'Use start when they ask for a feature tour (turin dong, turin fiturnya) — module rooms, '
            .'at most 2 page spots per room. Use next when they say lanjut/next. Use prev when they say kembali. '
            .'Use stop when they say cukup/stop/lewati/selesai or press Selesai on the last step — that is NOT next. '
            .'Returns a CSS selector so the widget can spotlight the '
            .'real sidebar or page UI with an overlay tooltip. Keep chat narration to 1-2 sentences. '
            .'This tool does not contain product facts — also call search_docs with the returned docs_query.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['operation'],
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'description' => 'here = deep tour of current page; start = module overview; next = next step; prev = previous step; stop = end tour.',
                    'enum' => ['here', 'start', 'next', 'prev', 'stop'],
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
        $operation = strtolower(trim((string) ($arguments['operation'] ?? 'here')));

        return match ($operation) {
            'here' => $this->here($context),
            'start' => $this->start($context),
            'next' => $this->next($context),
            'prev' => $this->prev($context),
            'stop' => $this->stop($context),
            default => [
                'success' => false,
                'message' => 'Operasi tur tidak dikenali. Pakai here, start, next, prev, atau stop.',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function here(AgentContext $context): array
    {
        $room = $this->currentRoom($context);
        $steps = $this->sequence->pageDeep($room, $context->pagePath);

        if ($steps === []) {
            return $this->idle('Tidak ada langkah tur untuk halaman ini.');
        }

        if ($context->conversationId !== null) {
            $this->store->put(
                $context->conversationId,
                $this->store->start($this->catalog->tourKeys(), 0, 'page_deep', $steps),
            );
        }

        return $this->successFromStep(
            operation: 'here',
            step: $steps[0],
            steps: $steps,
            index: 0,
            context: $context,
            active: true,
            message: 'Page-deep: sorot menu induk, submenu, lalu 3-6 bagian UI halaman ini. Jangan pindah modul. Chat 1-2 kalimat. Fakta dari search_docs.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function start(AgentContext $context): array
    {
        $steps = $this->sequence->overview();

        if ($steps === []) {
            return [
                'success' => false,
                'message' => 'Daftar tur belum dikonfigurasi.',
            ];
        }

        if ($context->conversationId !== null) {
            $this->store->put(
                $context->conversationId,
                $this->store->start($this->catalog->tourKeys(), 0, 'overview', $steps),
            );
        }

        return $this->successFromStep(
            operation: 'start',
            step: $steps[0],
            steps: $steps,
            index: 0,
            context: $context,
            active: true,
            message: 'Tur modul dari ruang 1. Setiap modul: sidebar (menu terbuka) lalu maksimal 2 spot halaman. Ajak user tekan Lanjut.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function next(AgentContext $context): array
    {
        $walk = $this->walk($context, 1);

        if ($walk === null) {
            return $this->idle('Tidak ada tur yang sedang berjalan. Jangan menyorot overlay. Kalau user minta tur, panggil operation=start dari ruang 1.');
        }

        return $walk;
    }

    /**
     * @return array<string, mixed>
     */
    protected function prev(AgentContext $context): array
    {
        $walk = $this->walk($context, -1);

        if ($walk === null) {
            return $this->idle('Tidak ada tur yang sedang berjalan. Jangan menyorot overlay. Kalau user minta tur, panggil operation=start dari ruang 1.');
        }

        return $walk;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function walk(AgentContext $context, int $delta): ?array
    {
        $conversationId = $context->conversationId;
        $state = $conversationId !== null ? $this->store->get($conversationId) : null;

        if (! is_array($state) || ! ($state['active'] ?? false)) {
            return null;
        }

        $steps = $this->stepsFromState($state, $context);

        if ($steps === []) {
            return $this->idle('Daftar tur kosong. Jangan menyorot overlay.');
        }

        $index = (int) ($state['index'] ?? 0) + $delta;

        if ($delta > 0 && $index >= count($steps)) {
            if ($conversationId !== null) {
                $this->store->forget($conversationId);
            }

            $last = $steps[count($steps) - 1];

            return $this->successFromStep(
                operation: 'stop',
                step: $last,
                steps: $steps,
                index: count($steps) - 1,
                context: $context,
                active: false,
                message: 'Tur selesai di langkah terakhir. User menekan Selesai, bukan Lanjut. Ucapkan terima kasih singkat. Jangan bilang mereka masih menekan Lanjut.',
            );
        }

        $index = max(0, min($index, count($steps) - 1));
        $mode = (string) ($state['mode'] ?? 'overview');

        if ($conversationId !== null) {
            $this->store->put($conversationId, [
                'active' => true,
                'index' => $index,
                'keys' => $state['keys'] ?? $this->catalog->tourKeys(),
                'mode' => $mode,
                'steps' => $steps,
            ]);
        }

        $operation = $delta < 0 ? 'prev' : 'next';
        $step = $steps[$index];
        $label = (string) ($step['label'] ?? 'langkah berikutnya');

        return $this->successFromStep(
            operation: $operation,
            step: $step,
            steps: $steps,
            index: $index,
            context: $context,
            active: true,
            message: $delta < 0
                ? 'Kembali ke '.$label.'. Overlay menyorot UI. Narasi chat 1-2 kalimat saja.'
                : 'Pindah ke '.$label.'. Overlay menyorot UI. Narasi chat 1-2 kalimat saja.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function stop(AgentContext $context): array
    {
        if ($context->conversationId !== null) {
            $this->store->forget($context->conversationId);
        }

        $room = $this->currentRoom($context);
        $steps = $this->sequence->pageDeep($room, $context->pagePath);
        $step = $steps[0] ?? $this->fallbackStep($room);

        return $this->successFromStep(
            operation: 'stop',
            step: $step,
            steps: $steps === [] ? [$step] : $steps,
            index: 0,
            context: $context,
            active: false,
            message: 'Tur dihentikan. User tetap di halaman sekarang.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function idle(string $message): array
    {
        return [
            'success' => true,
            'operation' => 'idle',
            'message' => $message,
            'active' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentRoom(AgentContext $context): array
    {
        return $this->catalog->match($context->pagePath, $context->pageTitle, $context->pageMenu);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<array<string, mixed>>
     */
    protected function stepsFromState(array $state, AgentContext $context): array
    {
        $stored = $state['steps'] ?? null;

        if (is_array($stored) && $stored !== [] && isset($stored[0]) && is_array($stored[0])) {
            return array_values($stored);
        }

        $mode = (string) ($state['mode'] ?? 'overview');

        if ($mode === 'page_deep') {
            return $this->sequence->pageDeep($this->currentRoom($context), $context->pagePath);
        }

        return $this->sequence->overview();
    }

    /**
     * @param  array<string, mixed>  $room
     * @return array<string, mixed>
     */
    protected function fallbackStep(array $room): array
    {
        return [
            'id' => ($room['key'] ?? 'unknown').'.sidebar',
            'kind' => 'sidebar',
            'mode' => 'page_deep',
            'room_key' => $room['key'] ?? 'unknown',
            'label' => $room['label'] ?? 'Halaman ini',
            'blurb' => $room['blurb'] ?? '',
            'voice' => $room['voice'] ?? '',
            'selector' => $room['selector'] ?? '',
            'heading_selector' => $this->catalog->headingSelector(),
            'menu_names' => $room['menu_names'] ?? [],
            'url' => $room['url'] ?? '',
            'navigate' => false,
            'spot_key' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  list<array<string, mixed>>  $steps
     * @return array<string, mixed>
     */
    protected function successFromStep(
        string $operation,
        array $step,
        array $steps,
        int $index,
        AgentContext $context,
        bool $active,
        string $message,
    ): array {
        $roomKey = (string) ($step['room_key'] ?? 'unknown');
        $room = $this->catalog->room($roomKey) ?? $this->currentRoom($context);
        $url = (string) ($step['url'] ?? '');
        $shouldNavigate = $active
            && (bool) ($step['navigate'] ?? false)
            && $url !== ''
            && ! $this->catalog->sameLocation((string) $context->pagePath, $url);

        $total = count($steps);
        $stepNumber = $index + 1;
        $next = $steps[$index + 1] ?? null;
        $nextLabel = is_array($next) ? (string) ($next['label'] ?? '') : '';

        return [
            'success' => true,
            'operation' => $operation,
            'mode' => (string) ($step['mode'] ?? 'overview'),
            'message' => $message,
            'room' => [
                'key' => $roomKey,
                'label' => (string) ($room['label'] ?? $step['label'] ?? 'Halaman ini'),
                'path' => $room['path'] ?? $context->pagePath,
            ],
            'highlight' => [
                'selector' => (string) ($step['selector'] ?? ''),
                'heading_selector' => (string) ($step['heading_selector'] ?? $this->catalog->headingSelector()),
                'label' => (string) ($step['label'] ?? 'Halaman ini'),
                'blurb' => (string) ($step['blurb'] ?? ''),
                'voice' => (string) ($step['voice'] ?? ''),
                'menu_names' => array_values(array_filter(
                    (array) ($step['menu_names'] ?? []),
                    fn ($name) => is_string($name) && $name !== '',
                )),
                'navigate_url' => $shouldNavigate ? $url : null,
                'kind' => (string) ($step['kind'] ?? 'sidebar'),
                'spot_key' => $step['spot_key'] ?? null,
            ],
            'step' => $stepNumber,
            'total' => $total,
            'active' => $active,
            'has_prev' => $active && $stepNumber > 1,
            'is_last' => $active && $total > 0 && $stepNumber >= $total,
            'docs_query' => (string) ($room['docs_query'] ?? ''),
            'next_hint' => $nextLabel !== ''
                ? 'Tekan Lanjut di overlay untuk ke '.$nextLabel.'.'
                : ($active ? 'Ini langkah terakhir. Tekan Selesai untuk menutup tur.' : null),
            'narration_hint' => 'Balasan chat SANGAT SINGKAT (1-2 kalimat). Tur visual sudah menyorot UI. Jangan dump dokumen. Fakta hanya dari search_docs.',
        ];
    }
}
