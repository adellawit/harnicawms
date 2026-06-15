<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiConfigurationService;
use App\Services\Ai\LlmProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiConfigurationController extends Controller
{
    public function __construct(
        protected AiConfigurationService $configuration,
        protected LlmProviderManager $llmManager,
    ) {}

    public function indexView(): View
    {
        return view('admin.settings.ai-configuration.index', [
            'config' => $this->configuration->current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:deepseek,chatai'],
            'agent_enabled' => ['nullable', 'boolean'],
            'agent_widget_enabled' => ['nullable', 'boolean'],
            'agent_max_tool_rounds' => ['required', 'integer', 'min:1', 'max:20'],
            'agent_max_message_length' => ['required', 'integer', 'min:100', 'max:10000'],
            'agent_rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:300'],
            'agent_permission_menu' => ['required', 'string', 'max:100'],
            'deepseek_enabled' => ['nullable', 'boolean'],
            'deepseek_api_key' => ['nullable', 'string', 'max:255'],
            'deepseek_base_url' => ['required', 'url', 'max:255'],
            'deepseek_beta_url' => ['required', 'url', 'max:255'],
            'deepseek_model' => ['required', 'string', 'max:100'],
            'deepseek_timeout' => ['required', 'integer', 'min:5', 'max:120'],
            'deepseek_max_tokens' => ['required', 'integer', 'min:100', 'max:8000'],
            'deepseek_use_strict_tools' => ['nullable', 'boolean'],
            'chatai_enabled' => ['nullable', 'boolean'],
            'chatai_api_key' => ['nullable', 'string', 'max:255'],
            'chatai_base_url' => ['required', 'url', 'max:255'],
            'chatai_model' => ['required', 'string', 'max:100'],
            'chatai_timeout' => ['required', 'integer', 'min:5', 'max:120'],
            'chatai_max_tokens' => ['required', 'integer', 'min:100', 'max:8000'],
            'chatai_use_strict_tools' => ['nullable', 'boolean'],
        ]);

        $this->configuration->update($validated);

        return redirect()
            ->route('settings.ai-configuration.index.view')
            ->with('success', 'Konfigurasi AI Chat berhasil disimpan.');
    }

    public function testConnection(): RedirectResponse
    {
        $provider = $this->llmManager->current();

        if (! $provider->isConfigured()) {
            return redirect()
                ->route('settings.ai-configuration.index.view')
                ->withErrors(['error' => $provider->label().' belum dikonfigurasi. Isi API key dan aktifkan provider.']);
        }

        try {
            $provider->chat([
                ['role' => 'user', 'content' => 'Balas satu kata: ok'],
            ]);

            return redirect()
                ->route('settings.ai-configuration.index.view')
                ->with('success', 'Koneksi ke '.$provider->label().' berhasil.');
        } catch (\Throwable $e) {
            return redirect()
                ->route('settings.ai-configuration.index.view')
                ->withErrors(['error' => 'Test koneksi gagal: '.$e->getMessage()]);
        }
    }
}
