<?php

namespace App\Services\Ai\Contracts;

interface LlmProviderInterface
{
    public function name(): string;

    public function label(): string;

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function chat(array $messages, array $tools = [], ?array $toolChoice = null): array;

    public function isConfigured(): bool;

    public function usesStrictTools(): bool;
}
