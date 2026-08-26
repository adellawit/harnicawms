<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ai\Actions\AgentConfirmActionService;
use App\Services\Ai\AgentConversationService;
use App\Services\Ai\WmsAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        protected WmsAgentService $agent,
        protected AgentConfirmActionService $confirms,
        protected AgentConversationService $conversations,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:'.(int) config('agent.max_message_length', 2000)],
            'conversation_id' => ['nullable', 'uuid'],
            'page_path' => ['nullable', 'string', 'max:300'],
            'page_title' => ['nullable', 'string', 'max:200'],
            'page_menu' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = auth('web')->user();

        $result = $this->agent->handle(
            $user,
            $validated['message'],
            $validated['conversation_id'] ?? null,
            [
                'path' => $this->sanitizePagePath($validated['page_path'] ?? null),
                'title' => $this->sanitizePageText($validated['page_title'] ?? null, 200),
                'menu' => $this->sanitizePageText($validated['page_menu'] ?? null, 120),
            ],
        );

        $status = ($result['success'] ?? false) ? 200 : 422;

        return response()->json($result, $status);
    }

    public function stopTour(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'uuid'],
            'reason' => ['nullable', 'in:finish,skip'],
            'page_path' => ['nullable', 'string', 'max:300'],
            'page_title' => ['nullable', 'string', 'max:200'],
            'page_menu' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = auth('web')->user();

        $result = $this->agent->stopTour(
            $user,
            $validated['conversation_id'] ?? null,
            $validated['reason'] ?? 'skip',
            [
                'path' => $this->sanitizePagePath($validated['page_path'] ?? null),
                'title' => $this->sanitizePageText($validated['page_title'] ?? null, 200),
                'menu' => $this->sanitizePageText($validated['page_menu'] ?? null, 120),
            ],
        );

        return response()->json($result);
    }

    public function confirmAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'uuid'],
            'token' => ['required', 'string', 'max:80'],
            'decision' => ['required', 'in:confirm,cancel'],
        ]);

        /** @var User $user */
        $user = auth('web')->user();

        $conversation = $this->conversations->findForUser($validated['conversation_id'], $user);

        if ($conversation === null) {
            return response()->json([
                'success' => false,
                'message' => 'Percakapan tidak ditemukan.',
            ], 404);
        }

        if ($validated['decision'] === 'cancel') {
            $result = $this->confirms->cancel($user, $conversation->id, $validated['token']);
        } else {
            $result = $this->confirms->confirm($user, $conversation->id, $validated['token']);
        }

        if ($result['success'] ?? false) {
            $this->conversations->appendMessage(
                $conversation,
                'assistant',
                (string) ($result['message'] ?? 'Selesai.'),
            );
        }

        $status = ($result['success'] ?? false) ? 200 : 422;

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'conversation_id' => $conversation->id,
            'reply' => [
                'content' => (string) ($result['message'] ?? 'Gagal memproses aksi.'),
                'format' => 'markdown',
                'attachments' => [],
            ],
            'message' => $result['message'] ?? null,
            'sales_number' => $result['sales_number'] ?? null,
        ], $status);
    }

    protected function sanitizePagePath(?string $path): ?string
    {
        $value = trim((string) $path);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, '://')) {
            $parsed = parse_url($value, PHP_URL_PATH);
            $value = is_string($parsed) ? $parsed : '';
        }

        $value = explode('?', $value, 2)[0];
        $value = '/'.ltrim($value, '/');

        if ($value !== '/') {
            $value = rtrim($value, '/');
        }

        return $value === '' ? null : $value;
    }

    protected function sanitizePageText(?string $text, int $max): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $text) ?? '');

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
