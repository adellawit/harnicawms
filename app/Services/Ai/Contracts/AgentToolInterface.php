<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AgentContext;

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
