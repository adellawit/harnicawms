<?php

namespace App\Services\Agent\Contracts;

use App\Services\Agent\AgentContext;

interface AgentToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * @return array{menu: string, action: string}|null
     */
    public function requiredPermission(): ?array;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, AgentContext $context): array;

    /**
     * @return array<string, mixed>
     */
    public function toOpenAiTool(): array;
}
