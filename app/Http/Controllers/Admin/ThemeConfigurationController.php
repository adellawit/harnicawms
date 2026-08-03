<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuration\AppThemeSetting;
use App\Services\Theme\AppThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemeConfigurationController extends Controller
{
    public function __construct(
        protected AppThemeService $theme,
    ) {}

    public function indexView(): View
    {
        return view('admin.settings.theme-configuration.index', [
            'theme' => $this->theme->current(),
            'themeView' => $this->theme->viewData(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'color_mode' => ['required', 'in:'.AppThemeSetting::MODE_LOGO_EXTRACT.','.AppThemeSetting::MODE_CUSTOM],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'navbar_color' => [
                $request->boolean('override_navbar') ? 'required' : 'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'sidebar_color' => [
                $request->boolean('override_sidebar') ? 'required' : 'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'background_color' => [
                $request->boolean('override_background') ? 'required' : 'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'glass_enabled' => ['nullable', 'boolean'],
            'motion_enabled' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,jpg,jpeg', 'max:512'],
        ]);

        $this->theme->update([
            'color_mode' => $validated['color_mode'],
            'primary_color' => strtoupper($validated['primary_color']),
            'secondary_color' => strtoupper($validated['secondary_color']),
            'navbar_color' => $this->surfaceColor($request, 'override_navbar', $validated['navbar_color'] ?? null),
            'sidebar_color' => $this->surfaceColor($request, 'override_sidebar', $validated['sidebar_color'] ?? null),
            'background_color' => $this->surfaceColor($request, 'override_background', $validated['background_color'] ?? null),
            'glass_enabled' => $request->boolean('glass_enabled'),
            'motion_enabled' => $request->boolean('motion_enabled'),
        ], $request->file('logo'), $request->file('favicon'), auth()->id());

        return redirect()
            ->route('settings.theme-configuration.index.view')
            ->with('success', 'Pengaturan tampilan & tema berhasil disimpan.');
    }

    private function surfaceColor(Request $request, string $overrideKey, ?string $color): ?string
    {
        if (! $request->boolean($overrideKey)) {
            return null;
        }

        return strtoupper((string) $color);
    }
}
