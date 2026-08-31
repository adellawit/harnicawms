<?php

namespace App\Services\Ai\Actions;

use Illuminate\Support\Facades\Cache;

class AgentDraftStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $conversationId, string $kind = 'sale'): ?array
    {
        $draft = Cache::get($this->key($conversationId, $kind));

        return is_array($draft) ? $draft : null;
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public function put(string $conversationId, array $draft, string $kind = 'sale'): void
    {
        $ttl = (int) config('agent.draft_ttl_minutes', 60);

        Cache::put($this->key($conversationId, $kind), $draft, now()->addMinutes($ttl));
    }

    public function forget(string $conversationId, string $kind = 'sale'): void
    {
        Cache::forget($this->key($conversationId, $kind));
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyDraft(string $branchId, ?string $companyId, ?string $priceListId): array
    {
        return [
            'branch_id' => $branchId,
            'company_id' => $companyId,
            'price_list_id' => $priceListId,
            'items' => [],
            'customer_id' => null,
            'customer_name' => 'Walk-in Customer',
            'payment_method_id' => null,
            'payment_method_name' => null,
            'payment_code' => null,
            'confirmation_token' => null,
            'subtotal' => 0.0,
        ];
    }

    protected function key(string $conversationId, string $kind = 'sale'): string
    {
        return 'agent:'.$kind.'-draft:'.$conversationId;
    }
}
