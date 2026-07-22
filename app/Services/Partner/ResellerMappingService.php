<?php

namespace App\Services\Partner;

use App\Models\Partner\Agent;
use App\Models\Partner\AgentResellerAssignment;
use App\Models\Partner\Reseller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ResellerMappingService
{
    /**
     * Assign or unassign a single reseller.
     *
     * @param  list<string>|null  $resellerIds  used by bulk wrapper
     */
    public function assign(?string $agentId, array $resellerIds, User $actor): int
    {
        $resellerIds = array_values(array_unique(array_filter($resellerIds)));
        if ($resellerIds === []) {
            throw new InvalidArgumentException('Pilih minimal satu Reseller.');
        }

        $actorAgent = $actor->partnerAgent;
        $isPartnerAgent = $actorAgent !== null;

        if ($isPartnerAgent) {
            if (! $agentId || $agentId !== $actorAgent->id) {
                throw new InvalidArgumentException('Agent hanya boleh memetakan Reseller ke dirinya sendiri.');
            }
        }

        if ($agentId) {
            $agent = Agent::query()->whereNull('deleted_at')->find($agentId);
            if (! $agent) {
                throw new InvalidArgumentException('Agent tidak ditemukan.');
            }
            if ($agent->status !== 'active' || $agent->approval_status !== 'approved') {
                throw new InvalidArgumentException('Agent harus active dan approved.');
            }
        }

        $userId = $actor->id;

        return (int) DB::connection((new Reseller)->getConnectionName())->transaction(function () use (
            $agentId,
            $resellerIds,
            $actorAgent,
            $isPartnerAgent,
            $userId
        ) {
            $updated = 0;

            $resellers = Reseller::query()
                ->whereNull('deleted_at')
                ->whereIn('id', $resellerIds)
                ->lockForUpdate()
                ->get();

            if ($resellers->count() !== count($resellerIds)) {
                throw new InvalidArgumentException('Beberapa Reseller tidak ditemukan.');
            }

            foreach ($resellers as $reseller) {
                if ($isPartnerAgent) {
                    if ($reseller->agent_id && $reseller->agent_id !== $actorAgent->id) {
                        throw new InvalidArgumentException(
                            "Reseller {$reseller->code} sudah terikat Agent lain."
                        );
                    }
                }

                if ($reseller->agent_id === $agentId) {
                    // Ensure active assignment exists when already linked.
                    if ($agentId) {
                        $this->ensureActiveAssignment($reseller->id, $agentId, $userId);
                    }

                    continue;
                }

                AgentResellerAssignment::where('reseller_id', $reseller->id)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'effective_to' => now()->toDateString(),
                        'updated_by' => $userId,
                    ]);

                $reseller->update([
                    'agent_id' => $agentId,
                    'updated_by' => $userId,
                ]);

                if ($agentId) {
                    AgentResellerAssignment::create([
                        'agent_id' => $agentId,
                        'reseller_id' => $reseller->id,
                        'effective_from' => now()->toDateString(),
                        'is_active' => true,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }

                $updated++;
            }

            return $updated;
        });
    }

    public function unassign(array $resellerIds, User $actor): int
    {
        if ($actor->partnerAgent) {
            throw new InvalidArgumentException('Agent tidak boleh melepas mapping Reseller.');
        }

        return $this->assign(null, $resellerIds, $actor);
    }

    private function ensureActiveAssignment(string $resellerId, string $agentId, ?string $userId): void
    {
        $active = AgentResellerAssignment::query()
            ->where('reseller_id', $resellerId)
            ->where('agent_id', $agentId)
            ->where('is_active', true)
            ->exists();

        if ($active) {
            return;
        }

        AgentResellerAssignment::where('reseller_id', $resellerId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'effective_to' => now()->toDateString(),
                'updated_by' => $userId,
            ]);

        AgentResellerAssignment::create([
            'agent_id' => $agentId,
            'reseller_id' => $resellerId,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
