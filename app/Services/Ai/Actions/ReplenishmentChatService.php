<?php

namespace App\Services\Ai\Actions;

use App\Models\Partner\Agent;
use App\Models\ReplenishmentOrder;
use App\Services\Ai\AgentContext;
use App\Services\Distribution\ReplenishmentOrderService;
use App\Support\ReplenishmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

/**
 * Draf replenishment dari chat. Tidak memakai ReplenishmentOrderService::create
 * karena method itu langsung SUBMITTED.
 *
 * Called from AgentRecordActionService for entity=replenishment create.
 * Nomor order tetap dari ReplenishmentOrderService::generateNumber().
 */
class ReplenishmentChatService
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function createDraft(array $arguments, AgentContext $context, bool $commit = true): array
    {
        $agentName = ChatFields::string(
            $arguments,
            ['agent', 'agen', 'agent_name'],
            $arguments['name'] ?? $arguments['query'] ?? null,
        );
        $agentId = ChatFields::string($arguments, ['agent_id']);
        $notes = ChatFields::string($arguments, ['notes', 'catatan'], $arguments['description'] ?? null);

        if ($agentName === null && $agentId === null) {
            return ChatFields::missing(['agent'], 'Draf replenishment untuk agen mana?');
        }

        $agent = $this->resolveAgent($agentId, $agentName, $context);
        if ($agent === null) {
            $label = (string) ($agentName ?? $agentId);
            $candidates = $agentName !== null ? $this->candidates($agentName, $context) : [];

            return ChatFields::missing(
                ['agent'],
                'Agen "'.$label.'" tidak ditemukan. '.($candidates === []
                    ? 'Sebut nama agen yang benar, atau minta buat agen baru dulu dari chat.'
                    : 'Yang mirip: '.implode(', ', $candidates).'. Mana yang dimaksud?'),
            );
        }

        $summary = 'Draf replenishment untuk '.$agent->name.'. Status draft, tanpa item — lanjutkan di halaman Replenishment.';

        if (! $commit) {
            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => 'replenishment_draft',
                'title' => 'Buat draf replenishment?',
                'body' => $summary,
                'confirm_label' => 'Buat draf',
                'cancel_label' => 'Batal',
                'message' => $summary.' Konfirmasi dulu di kartu.',
            ];
        }

        try {
            $order = ReplenishmentOrder::query()->create([
                'order_number' => ReplenishmentOrderService::generateNumber(),
                'order_date' => now()->toDateString(),
                'distributor_id' => $context->companyId,
                'agent_id' => $agent->id,
                'payment_status' => ReplenishmentStatus::PAYMENT_UNPAID,
                'status' => ReplenishmentStatus::DRAFT,
                'notes' => $notes,
                'subtotal' => 0,
                'total' => 0,
                'created_by' => $context->user->id,
                'updated_by' => $context->user->id,
            ]);
        } catch (QueryException $e) {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan draf replenishment. Coba lagi dari chat.',
            ];
        }

        $item = [
            'id' => $order->id,
            'name' => $order->order_number,
            'label' => $order->order_number,
            'code' => $order->order_number,
            'status' => $order->status,
            'agent' => $agent->name,
        ];

        return [
            'success' => true,
            'applied' => true,
            'entity' => 'replenishment',
            'item' => $item,
            'items' => [$item],
            'message' => 'Draf replenishment '.$order->order_number.' untuk '.$agent->name.' tersimpan. Ajukan/approve tetap di modul Replenishment.',
        ];
    }

    protected function resolveAgent(?string $id, ?string $name, AgentContext $context): ?Agent
    {
        if ($id !== null) {
            return $this->agentQuery($context)->find($id);
        }

        if ($name === null) {
            return null;
        }

        $exact = $this->agentQuery($context)
            ->where(function ($q) use ($name) {
                $q->where('name', 'ilike', $name)->orWhere('code', 'ilike', $name);
            })
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        // Nama sebagian ("Makmur Jaya") hanya diterima kalau tidak ambigu,
        // supaya draf tidak nyangkut ke agen yang salah.
        $partial = $this->partialMatches($name, $context);

        return $partial->count() === 1 ? $partial->first() : null;
    }

    /**
     * @return list<string>
     */
    protected function candidates(string $name, AgentContext $context): array
    {
        return $this->partialMatches($name, $context)
            ->map(fn (Agent $agent) => $agent->name.' ('.$agent->code.')')
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Agent>
     */
    protected function partialMatches(string $name, AgentContext $context)
    {
        return $this->agentQuery($context)
            ->where(function ($q) use ($name) {
                $q->where('name', 'ilike', '%'.$name.'%')->orWhere('code', 'ilike', '%'.$name.'%');
            })
            ->orderBy('name')
            ->limit(5)
            ->get();
    }

    /**
     * @return Builder<Agent>
     */
    protected function agentQuery(AgentContext $context)
    {
        $query = Agent::query()->whereNull('deleted_at');

        if ($context->companyId) {
            $query->where('company_id', $context->companyId);
        }

        return $query;
    }
}
