<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuration\AppThemeSetting;
use App\Services\Theme\AppThemeService;
use App\Services\Theme\ThemePaletteGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ThemeConfigurationController extends Controller
{
    public function __construct(
        protected AppThemeService $theme,
        protected ThemePaletteGenerator $generator,
    ) {}

    public function indexView(): View
    {
        return view('admin.settings.theme-configuration.index', [
            'theme' => $this->theme->current(),
            'themeView' => $this->theme->viewData(),
            'holdingName' => $this->resolveHoldingName(),
        ]);
    }

    private function resolveHoldingName(): string
    {
        $unit = auth()->user()?->businessUnit;

        if (! $unit) {
            return (string) config('app.name', 'Company');
        }

        if ($unit->type_code === 'HOLDING') {
            return (string) $unit->name;
        }

        if ($unit->type_code === 'COMPANY') {
            $holding = $unit->relationLoaded('parent')
                ? $unit->parent
                : $unit->parent()->first();

            return (string) ($holding?->name ?? $unit->name);
        }

        // BRANCH → company → holding
        $company = $unit->relationLoaded('parent')
            ? $unit->parent
            : $unit->parent()->first();

        if (! $company) {
            return (string) $unit->name;
        }

        $holding = $company->relationLoaded('parent')
            ? $company->parent
            : $company->parent()->first();

        return (string) ($holding?->name ?? $company->name);
    }

    public function update(Request $request): RedirectResponse
    {
        $hexRule = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        $tokenRules = [];
        foreach ($this->theme->tokenKeys() as $key) {
            $tokenRules["tokens_light.{$key}"] = $hexRule;
            $tokenRules["tokens_dark.{$key}"] = $hexRule;
        }

        $validated = $request->validate(array_merge([
            'color_mode' => ['required', 'in:'.AppThemeSetting::MODE_LOGO_EXTRACT.','.AppThemeSetting::MODE_CUSTOM],
            'preview_mode' => ['required', 'in:light,dark'],
            'tokens_light' => ['required', 'array'],
            'tokens_dark' => ['required', 'array'],
            'font_source' => ['required', 'in:'.AppThemeSetting::FONT_SOURCE_PRESET.','.AppThemeSetting::FONT_SOURCE_UPLOAD],
            'font_preset' => ['nullable', 'string', 'in:'.implode(',', array_keys($this->theme->fontPresets()))],
            'font_file' => ['nullable', 'file', 'max:2048'],
            'glass_enabled' => ['nullable', 'boolean'],
            'motion_enabled' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,jpg,jpeg', 'max:512'],
        ], $tokenRules));

        if ($request->hasFile('font_file')) {
            $ext = strtolower((string) $request->file('font_file')->getClientOriginalExtension());
            if (! in_array($ext, ['ttf', 'otf', 'woff', 'woff2'], true)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['font_file' => 'Font harus berformat TTF, OTF, WOFF, atau WOFF2.']);
            }
        }

        if ($validated['font_source'] === AppThemeSetting::FONT_SOURCE_UPLOAD
            && ! $request->hasFile('font_file')
            && ! $this->theme->current()->font_path) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['font_file' => 'Upload file font atau pilih preset.']);
        }

        $light = $this->theme->normalizeTokens($validated['tokens_light'], 'light');
        $dark = $this->theme->normalizeTokens($validated['tokens_dark'], 'dark');
        $legacy = $this->theme->syncLegacyFromTokens($light);

        $fontPreset = $validated['font_preset'] ?? $this->theme->defaultFontPreset();
        if (! array_key_exists($fontPreset, $this->theme->fontPresets())) {
            $fontPreset = $this->theme->defaultFontPreset();
        }

        $this->theme->update(array_merge($legacy, [
            'color_mode' => $validated['color_mode'],
            'preview_mode' => $validated['preview_mode'],
            'tokens_light' => $light,
            'tokens_dark' => $dark,
            'font_source' => $validated['font_source'],
            'font_preset' => $fontPreset,
            'glass_enabled' => $request->boolean('glass_enabled'),
            'motion_enabled' => $request->boolean('motion_enabled'),
        ]), $request->file('logo'), $request->file('favicon'), auth()->id(), $request->file('font_file'));

        return redirect()
            ->route('settings.theme-configuration.index.view')
            ->with('success', 'Pengaturan tampilan & tema berhasil disimpan.');
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $palette = $this->generator->generate($validated['prompt'] ?? null);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'light' => $palette['light'],
            'dark' => $palette['dark'],
        ]);
    }
}
