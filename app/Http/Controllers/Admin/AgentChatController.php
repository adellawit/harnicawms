<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Agent\AgentConversationService;
use App\Services\Agent\WmsAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentChatController extends Controller
{
    public function __construct(
        protected WmsAgentService $agent,
        protected AgentConversationService $conversations,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:'.(int) config('agent.max_message_length', 2000)],
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        /** @var User $user */
        $user = auth('web')->user();

        $result = $this->agent->handle(
            $user,
            $validated['message'],
            $validated['conversation_id'] ?? null,
        );

        $status = ($result['success'] ?? false) ? 200 : 422;

        return response()->json($result, $status);
    }

    public function conversations(): JsonResponse
    {
        /** @var User $user */
        $user = auth('web')->user();

        $items = $this->conversations->listForUser($user)->map(fn ($row) => [
            'id' => $row->id,
            'title' => $row->title ?? 'Percakapan',
            'updated_at' => $row->updated_at?->diffForHumans(),
        ]);

        return response()->json([
            'success' => true,
            'conversations' => $items,
        ]);
    }

    public function messages(string $conversationId): JsonResponse
    {
        /** @var User $user */
        $user = auth('web')->user();

        $conversation = $this->conversations->findForUser($conversationId, $user);

        if ($conversation === null) {
            return response()->json([
                'success' => false,
                'message' => 'Percakapan tidak ditemukan.',
            ], 404);
        }

        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->get(['id', 'role', 'content', 'created_at'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'role' => $row->role,
                'content' => $row->content ?? '',
                'created_at' => $row->created_at?->format('H:i'),
            ]);

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'messages' => $messages,
        ]);
    }

    public function newConversation(): JsonResponse
    {
        /** @var User $user */
        $user = auth('web')->user();

        $conversation = $this->conversations->create(
            $user,
            $user->getBranchIdForTransaction(),
        );

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
        ]);
    }

    public function destroy(string $conversationId): JsonResponse
    {
        /** @var User $user */
        $user = auth('web')->user();

        $deleted = $this->conversations->deleteForUser($conversationId, $user);

        return response()->json([
            'success' => $deleted,
        ], $deleted ? 200 : 404);
    }
}
