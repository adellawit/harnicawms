<?php

namespace App\Services\Ai\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Draf konfirmasi non-penjualan (hapus, Super Admin, stok/PO/jurnal).
 *
 * Called from AgentRecordActionService and AgentConfirmActionService.
 * AgentDraftStore remains sale-only (agent:sale-draft:{id}).
 *
 * Cache value example: token "a1b2…", kind "delete", user_id uuid,
 * arguments {operation, entity, id}, title/body strings. TTL minutes from config.
 */
class AgentPendingActionStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $conversationId): ?array
    {
        $pending = Cache::get($this->key($conversationId));

        return is_array($pending) ? $pending : null;
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    public function put(string $conversationId, array $pending): void
    {
        $ttl = (int) config('agent.draft_ttl_minutes', 60);

        Cache::put($this->key($conversationId), $pending, now()->addMinutes($ttl));
    }

    public function forget(string $conversationId): void
    {
        Cache::forget($this->key($conversationId));
    }

    public function tokenMatches(?array $pending, string $token): bool
    {
        $stored = (string) ($pending['token'] ?? '');

        return $stored !== '' && hash_equals($stored, $token);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function propose(string $conversationId, string $userId, array $meta, array $arguments): array
    {
        $token = Str::random(40);
        $kind = (string) ($meta['kind'] ?? 'confirm_record');
        $replay = $arguments;
        unset($replay['_confirmed']);

        $this->put($conversationId, [
            'token' => $token,
            'kind' => $kind,
            'user_id' => $userId,
            'arguments' => $replay,
            'title' => (string) ($meta['title'] ?? 'Konfirmasi'),
            'body' => (string) ($meta['body'] ?? ''),
        ]);

        return [
            'success' => true,
            'applied' => false,
            'needs_confirmation' => true,
            'confirmation_token' => $token,
            'action' => $kind,
            'title' => (string) ($meta['title'] ?? 'Konfirmasi'),
            'body' => (string) ($meta['body'] ?? ''),
            'confirm_label' => (string) ($meta['confirm_label'] ?? 'Konfirmasi'),
            'cancel_label' => (string) ($meta['cancel_label'] ?? 'Batal'),
            'message' => (string) ($meta['message'] ?? 'Konfirmasi dulu di kartu di bawah. Belum ada data yang diubah.'),
        ];
    }

    protected function key(string $conversationId): string
    {
        return 'agent:pending-action:'.$conversationId;
    }
}
