<?php

namespace App\Services\Ai;

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
        public ?string $pagePath = null,
        public ?string $pageTitle = null,
        public ?string $pageMenu = null,
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

    public function withPage(?string $path, ?string $title = null, ?string $menu = null): self
    {
        return new self(
            user: $this->user,
            branchId: $this->branchId,
            companyId: $this->companyId,
            branchName: $this->branchName,
            permissions: $this->permissions,
            channel: $this->channel,
            conversationId: $this->conversationId,
            pagePath: $path,
            pageTitle: $title,
            pageMenu: $menu,
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
