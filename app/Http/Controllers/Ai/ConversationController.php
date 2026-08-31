<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ai\AgentConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function __construct(
        protected AgentConversationService $conversations,
    ) {}

    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = auth('web')->user();

        return response()->json([
            'success' => true,
            'conversations' => $this->conversations->listSummariesForUser($user),
        ]);
    }

    public function messages(string $conversationId): JsonResponse
    {
        /** @var User $user */
        $user = auth('web')->user();

        if (! Str::isUuid($conversationId)) {
            return response()->json([
                'success' => false,
                'message' => 'Percakapan tidak ditemukan.',
            ], 404);
        }

        $conversation = $this->conversations->findForUser($conversationId, $user);

        if ($conversation === null) {
            return response()->json([
                'success' => false,
                'message' => 'Percakapan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'messages' => $this->conversations->widgetMessages($conversation),
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
