<?php

namespace App\Services\Ai\Actions;

use App\Models\User;
use App\Services\Ai\AgentContext;

/**
 * Generalisasi ChatController::confirmAction di luar penjualan.
 *
 * Pending record actions first, then AgentSaleActionService.
 */
class AgentConfirmActionService
{
    public function __construct(
        protected AgentPendingActionStore $pending,
        protected AgentSaleActionService $sales,
        protected AgentRecordActionService $records,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function confirm(User $user, string $conversationId, string $token): array
    {
        $pending = $this->pending->get($conversationId);

        if ($pending !== null && $this->pending->tokenMatches($pending, $token)) {
            if ((string) ($pending['user_id'] ?? '') !== (string) $user->id) {
                return [
                    'success' => false,
                    'message' => 'Konfirmasi tidak valid untuk user ini.',
                ];
            }

            $arguments = $pending['arguments'] ?? [];
            if (! is_array($arguments)) {
                $arguments = [];
            }
            $arguments['_confirmed'] = true;

            $this->pending->forget($conversationId);

            $context = AgentContext::fromUser($user, 'web', $conversationId);

            return $this->records->handle($arguments, $context);
        }

        return $this->sales->confirm($user, $conversationId, $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(User $user, string $conversationId, string $token): array
    {
        $pending = $this->pending->get($conversationId);

        if ($pending !== null && $this->pending->tokenMatches($pending, $token)) {
            if ((string) ($pending['user_id'] ?? '') !== (string) $user->id) {
                return [
                    'success' => false,
                    'message' => 'Pembatalan tidak valid.',
                ];
            }

            $this->pending->forget($conversationId);

            return [
                'success' => true,
                'message' => 'Aksi dibatalkan. Tidak ada data yang diubah.',
            ];
        }

        return $this->sales->cancel($conversationId, $token);
    }
}
