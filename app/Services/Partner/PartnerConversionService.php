<?php

namespace App\Services\Partner;

use App\Models\Partner\Agent;
use App\Models\Partner\AgentResellerAssignment;
use App\Models\Partner\PartnerApplication;
use App\Models\Partner\Reseller;
use App\Models\ProductVariant;
use App\Models\ReplenishmentOrder;
use App\Models\ReplenishmentOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class PartnerConversionService
{
    public function convertAgent(PartnerApplication $application, array $payload, ?string $userId): Agent
    {
        if ($application->partner_type !== PartnerApplication::TYPE_AGENT) {
            throw new \InvalidArgumentException('Application is not an Agent application.');
        }

        return DB::transaction(function () use ($application, $payload, $userId) {
            $agent = Agent::create([
                'company_id' => $application->company_id,
                'customer_id' => $application->customer_id,
                'code' => $payload['code'] ?? $this->generateAgentCode($application->company_id),
                'name' => $application->name,
                'email' => $application->email,
                'phone' => $application->phone,
                'address' => $application->address,
                'city' => $application->city,
                'province' => $application->province,
                'postal_code' => $application->postal_code,
                'status' => 'active',
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $userId,
                'notes' => $payload['notes'] ?? $application->notes,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $warehouse = $this->createAgentWarehouse($agent, $userId);
            $loginUser = $this->createAgentUser($agent, $userId);
            $agent->update([
                'default_warehouse_id' => $warehouse->id,
                'user_id' => $loginUser?->id,
            ]);

            $application->update([
                'converted_agent_id' => $agent->id,
                'status' => 'converted',
                'converted_at' => now(),
                'updated_by' => $userId,
            ]);

            if (! empty($payload['initial_purchase']) && is_array($payload['initial_purchase'])) {
                $this->createInitialReplenishment($agent, $payload['initial_purchase'], $userId);
            }

            return $agent->fresh(['defaultWarehouse', 'customer']);
        });
    }

    public function convertReseller(PartnerApplication $application, string $agentId, array $payload, ?string $userId): Reseller
    {
        if ($application->partner_type !== PartnerApplication::TYPE_RESELLER) {
            throw new \InvalidArgumentException('Application is not a Reseller application.');
        }

        return DB::transaction(function () use ($application, $agentId, $payload, $userId) {
            $agent = Agent::where('company_id', $application->company_id)->findOrFail($agentId);

            $reseller = Reseller::create([
                'company_id' => $application->company_id,
                'agent_id' => $agent->id,
                'customer_id' => $application->customer_id,
                'code' => $payload['code'] ?? $this->generateResellerCode($application->company_id),
                'name' => $application->name,
                'email' => $application->email,
                'phone' => $application->phone,
                'address' => $application->address,
                'city' => $application->city,
                'province' => $application->province,
                'postal_code' => $application->postal_code,
                'status' => 'active',
                'notes' => $payload['notes'] ?? $application->notes,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            AgentResellerAssignment::where('reseller_id', $reseller->id)->update(['is_active' => false]);
            AgentResellerAssignment::create([
                'agent_id' => $agent->id,
                'reseller_id' => $reseller->id,
                'effective_from' => now()->toDateString(),
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $application->update([
                'assigned_agent_id' => $agent->id,
                'converted_reseller_id' => $reseller->id,
                'status' => 'converted',
                'assigned_at' => $application->assigned_at ?: now(),
                'converted_at' => now(),
                'updated_by' => $userId,
            ]);

            return $reseller->fresh(['agent', 'customer', 'activeAssignment']);
        });
    }

    private function createAgentWarehouse(Agent $agent, ?string $userId): Warehouse
    {
        return Warehouse::firstOrCreate(
            [
                'company_id' => $agent->company_id,
                'owner_type' => 'AGENT',
                'owner_id' => $agent->id,
                'is_default' => true,
            ],
            [
                'branch_id' => null,
                'warehouse_type_code' => 'GENERAL',
                'code' => 'AG-' . $agent->code . '-WH',
                'name' => 'Gudang ' . $agent->name,
                'short_name' => $agent->code,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'address' => $agent->address,
                'city' => $agent->city,
                'province' => $agent->province,
                'postal_code' => $agent->postal_code,
                'country' => 'Indonesia',
                'is_inventory_active' => true,
                'is_active' => true,
                'notes' => 'Auto-created for partner Agent.',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    private function createAgentUser(Agent $agent, ?string $createdBy): ?User
    {
        $roleId = $this->agentRoleId();
        if (! $roleId) {
            return null;
        }

        $username = $agent->email ?: strtolower($agent->code) . '@agent.local';
        $parts = preg_split('/\s+/', trim($agent->name), 2, PREG_SPLIT_NO_EMPTY) ?: [$agent->name];

        return User::updateOrCreate(
            ['username' => $username],
            [
                'role_id' => $roleId,
                'current_business_unit_id' => $agent->company_id,
                'first_name' => $parts[0],
                'last_name' => $parts[1] ?? $parts[0],
                'email' => $agent->email ?: $username,
                'phone' => $agent->phone,
                'password' => 'agent12345',
                'need_update_password' => true,
                'url_image' => config('app.url') . '/assets/img/ars/avatar/user-default.jpg',
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
            ]
        );
    }

    private function agentRoleId(): ?string
    {
        return \App\Models\Role::updateOrCreate(
            ['id' => '2ac6f6a1-7b8c-4d9e-9f10-111213141516'],
            ['name' => 'Agent']
        )->id;
    }

    private function createInitialReplenishment(Agent $agent, array $payload, ?string $userId): ?ReplenishmentOrder
    {
        $lines = array_filter($payload['lines'] ?? [], fn ($line) => ! empty($line['variant_id']) && (float) ($line['qty'] ?? 0) > 0);
        if ($lines === []) {
            return null;
        }

        $order = ReplenishmentOrder::create([
            'order_number' => $this->generateReplenishmentNumber(),
            'order_date' => $payload['order_date'] ?? now()->toDateString(),
            'distributor_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'status' => 'submitted',
            'invoice_number' => $payload['invoice_number'] ?? null,
            'payment_reference' => $payload['payment_reference'] ?? null,
            'payment_status' => $payload['payment_status'] ?? 'unpaid',
            'paid_at' => ($payload['payment_status'] ?? null) === 'paid' ? now() : null,
            'notes' => $payload['notes'] ?? 'Initial purchase from Agent conversion.',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $subtotal = 0;
        foreach ($lines as $line) {
            $variant = ProductVariant::with('product')->find($line['variant_id']);
            if (! $variant || ! $variant->product?->default_unit_id) {
                continue;
            }

            $qty = (float) $line['qty'];
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $lineSubtotal = $qty * $unitPrice;
            $subtotal += $lineSubtotal;

            ReplenishmentOrderItem::create([
                'order_id' => $order->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'unit_id' => $variant->product->default_unit_id,
                'qty_ordered' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $lineSubtotal,
            ]);
        }

        $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);

        return $order->fresh('items');
    }

    private function generateAgentCode(string $companyId): string
    {
        $prefix = 'AG-' . date('ym') . '-';
        $last = Agent::withTrashed()
            ->where('company_id', $companyId)
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('code')
            ->value('code');
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1) + 1) : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function generateResellerCode(string $companyId): string
    {
        $prefix = 'RS-' . date('ym') . '-';
        $last = Reseller::withTrashed()
            ->where('company_id', $companyId)
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('code')
            ->value('code');
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1) + 1) : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function generateReplenishmentNumber(): string
    {
        $prefix = 'RPO-' . date('Ym') . '-';
        $last = ReplenishmentOrder::withTrashed()
            ->where('order_number', 'like', $prefix . '%')
            ->orderByDesc('order_number')
            ->value('order_number');
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1) + 1) : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
