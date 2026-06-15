<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ai\WmsAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        protected WmsAgentService $agent,
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
}
