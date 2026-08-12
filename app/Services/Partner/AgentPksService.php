<?php

namespace App\Services\Partner;

use App\Models\IamHasAccess;
use App\Models\Menu;
use App\Models\Partner\Agent;
use App\Models\Partner\AgentPks;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AgentPksService
{
    public const REMINDER_DAYS = 30;

    public function hasCompletedFirstPurchase(Agent $agent): bool
    {
        if (! filled($agent->customer_id)) {
            return false;
        }

        return SalesOrder::query()
            ->where('customer_id', $agent->customer_id)
            ->where('payment_status', 'paid')
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * @return 'missing'|'active'|'expiring'|'expired'|'none'
     */
    public function pksBadge(Agent $agent): string
    {
        $active = $agent->relationLoaded('activePks')
            ? $agent->activePks
            : $agent->activePks()->first();

        if ($active) {
            if ($active->isExpiredByDate()) {
                return 'expired';
            }

            return $active->isExpiringSoon(self::REMINDER_DAYS) ? 'expiring' : 'active';
        }

        // Any historical PKS that ended
        $latest = $agent->relationLoaded('pksDocuments')
            ? $agent->pksDocuments->first()
            : $agent->pksDocuments()->first();

        if ($latest && in_array($latest->status, [AgentPks::STATUS_EXPIRED, AgentPks::STATUS_SUPERSEDED], true)) {
            if ($latest->status === AgentPks::STATUS_EXPIRED || $latest->isExpiredByDate()) {
                return 'expired';
            }
        }

        if ($this->hasCompletedFirstPurchase($agent)) {
            return 'missing';
        }

        return 'none';
    }

    public function store(Agent $agent, UploadedFile $file, Carbon $startDate, Carbon $endDate, ?string $notes, string $userId): AgentPks
    {
        if (! $this->hasCompletedFirstPurchase($agent)) {
            throw ValidationException::withMessages([
                'file' => 'Upload PKS hanya setelah transaksi pertama agent selesai (pembayaran lunas).',
            ]);
        }

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'Tanggal akhir PKS harus sama atau setelah tanggal mulai.',
            ]);
        }

        return DB::transaction(function () use ($agent, $file, $startDate, $endDate, $notes, $userId) {
            AgentPks::query()
                ->where('agent_id', $agent->id)
                ->where('status', AgentPks::STATUS_ACTIVE)
                ->update([
                    'status' => AgentPks::STATUS_SUPERSEDED,
                    'updated_by' => $userId,
                ]);

            $filename = now()->format('YmdHis') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs('partner/agents/' . $agent->id . '/pks', $filename, 'public');

            return AgentPks::create([
                'agent_id' => $agent->id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_mime' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => AgentPks::STATUS_ACTIVE,
                'notes' => $notes,
                'uploaded_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });
    }

    public function markExpiredPastEndDate(): int
    {
        return AgentPks::query()
            ->where('status', AgentPks::STATUS_ACTIVE)
            ->whereDate('end_date', '<', Carbon::today())
            ->update(['status' => AgentPks::STATUS_EXPIRED]);
    }

    /**
     * @return array{notified: int, candidates: int}
     */
    public function sendExpiringReminders(): array
    {
        $this->markExpiredPastEndDate();

        $candidates = AgentPks::query()
            ->with('agent')
            ->expiringWithinDays(self::REMINDER_DAYS)
            ->where(function ($q) {
                $q->whereNull('reminded_at')
                    ->orWhereDate('reminded_at', '<', Carbon::today());
            })
            ->get();

        $recipients = $this->reminderRecipients();
        $notified = 0;

        foreach ($candidates as $pks) {
            if (! $pks->agent || $recipients->isEmpty()) {
                continue;
            }

            $days = $pks->daysUntilEnd();
            $title = 'PKS Agent segera berakhir';
            $message = sprintf(
                'PKS %s (%s) berakhir %s (H-%d). Segera perpanjang.',
                $pks->agent->name,
                $pks->agent->code,
                $pks->end_date?->format('d/m/Y') ?? '-',
                max(0, (int) $days)
            );

            NotificationService::createForUsers(
                $recipients,
                $title,
                $message,
                'warning',
                [
                    'module' => 'partner-agent-pks',
                    'url' => route('partner.agents.show', $pks->agent_id),
                    'related' => $pks,
                    'created_by' => null,
                ]
            );

            $pks->forceFill(['reminded_at' => now()])->save();
            $notified++;
        }

        return [
            'notified' => $notified,
            'candidates' => $candidates->count(),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function reminderRecipients(): Collection
    {
        $menu = Menu::query()
            ->where(function ($q) {
                $q->where('code', 'partner-agent')
                    ->orWhere('name', 'Partner Agent');
            })
            ->first();

        $roleIds = collect([Role::SUPER_ADMIN_ID]);

        if ($menu) {
            $accessRoleIds = IamHasAccess::query()
                ->where('sidebar_menu_id', $menu->id)
                ->where('is_read', true)
                ->whereNull('deleted_at')
                ->with('iamAccess')
                ->get()
                ->map(fn (IamHasAccess $row) => $row->iamAccess?->role_id)
                ->filter()
                ->values();

            $roleIds = $roleIds->merge($accessRoleIds)->unique()->values();
        }

        return User::query()
            ->whereIn('role_id', $roleIds->all())
            ->whereNull('deleted_at')
            ->get();
    }

    /**
     * @return array{expiring: int, missing: int, expired: int}
     */
    public function dashboardStats(?string $companyId = null): array
    {
        $agentsQuery = Agent::query()->whereNull('deleted_at');
        if ($companyId) {
            $agentsQuery->where('company_id', $companyId);
        }

        $agents = $agentsQuery->with(['activePks', 'pksDocuments'])->get();

        $expiring = 0;
        $missing = 0;
        $expired = 0;

        foreach ($agents as $agent) {
            $badge = $this->pksBadge($agent);
            if ($badge === 'expiring') {
                $expiring++;
            } elseif ($badge === 'missing') {
                $missing++;
            } elseif ($badge === 'expired') {
                $expired++;
            }
        }

        return compact('expiring', 'missing', 'expired');
    }

    public function absoluteFilePath(AgentPks $pks): string
    {
        return Storage::disk('public')->path($pks->file_path);
    }

    public function fileExists(AgentPks $pks): bool
    {
        return Storage::disk('public')->exists($pks->file_path);
    }
}
