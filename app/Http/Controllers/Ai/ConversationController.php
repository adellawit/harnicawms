<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ai\AgentConversationService;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    public function __construct(
        protected AgentConversationService $conversations,
    ) {}

    public function index(): JsonResponse
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

    public function store(): JsonResponse
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
