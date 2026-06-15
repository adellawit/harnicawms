<?php

namespace App\Services\Agent;

use App\Models\BusinessUnit;
use App\Models\User;

class AgentContext
{
    /**
     * @param  array<string, array<string, int|bool>>  $permissions
     */
    public function __construct(
        public User $user,
        public ?string $branchId,
        public ?string $companyId,
        public ?string $branchName,
        public array $permissions,
        public string $channel = 'web',
        public ?string $conversationId = null,
    ) {}

    public static function fromUser(User $user, string $channel = 'web', ?string $conversationId = null): self
    {
        $branchId = $user->getBranchIdForTransaction();
        $branchName = null;

        if ($branchId !== null) {
            $branchName = BusinessUnit::query()
                ->where('id', $branchId)
                ->value('name');
        }

        return new self(
            user: $user,
            branchId: $branchId,
            companyId: $user->getCompanyIdForProduct(),
            branchName: $branchName,
            permissions: session('permissions', []),
            channel: $channel,
            conversationId: $conversationId,
        );
    }

    public function hasPermission(?array $permission): bool
    {
        if ($permission === null) {
            return true;
        }

        if (strtolower((string) ($this->user->role?->name ?? '')) === 'super admin') {
            return true;
        }

        $menu = $permission['menu'] ?? '';
        $action = $permission['action'] ?? 'is_read';

        return isset($this->permissions[$menu][$action])
            && (int) $this->permissions[$menu][$action] === 1;
    }

    public function requireBranch(): string
    {
        if ($this->branchId === null) {
            throw new \RuntimeException('Cabang aktif tidak ditemukan. Silakan pilih cabang di profil.');
        }

        return $this->branchId;
    }
}
