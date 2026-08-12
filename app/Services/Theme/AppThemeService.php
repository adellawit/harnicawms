<?php

namespace App\Services\Theme;

use App\Models\Configuration\AppThemeSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AppThemeService
{
    private const CACHE_KEY = 'app_theme_settings';

    public const TOKEN_KEYS = [
        'background',
        'foreground',
        'card',
        'card-foreground',
        'primary',
        'primary-foreground',
        'secondary',
        'secondary-foreground',
        'muted',
        'muted-foreground',
        'accent',
        'accent-foreground',
        'destructive',
        'destructive-foreground',
        'border',
        'input',
        'ring',
        'navbar-background',
        'navbar-foreground',
        'sidebar-background',
        'sidebar-foreground',
        'sidebar-primary',
        'sidebar-accent',
        'sidebar-border',
        'chart-1',
        'chart-2',
        'chart-3',
        'chart-4',
        'chart-5',
    ];

    public const TOKEN_GROUPS = [
        'base' => [
            'background',
            'foreground',
            'card',
            'card-foreground',
            'primary',
            'primary-foreground',
            'secondary',
            'secondary-foreground',
            'muted',
            'muted-foreground',
            'accent',
            'accent-foreground',
            'destructive',
            'destructive-foreground',
            'border',
            'input',
            'ring',
        ],
        'navbar' => [
            'navbar-background',
            'navbar-foreground',
        ],
        'sidebar' => [
            'sidebar-background',
            'sidebar-foreground',
            'sidebar-primary',
            'sidebar-accent',
            'sidebar-border',
        ],
        'charts' => [
            'chart-1',
            'chart-2',
            'chart-3',
            'chart-4',
            'chart-5',
        ],
    ];

    public function current(): AppThemeSetting
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $setting = AppThemeSetting::query()->orderBy('created_at')->first();

            if (! $setting) {
                $setting = AppThemeSetting::create([
                    'primary_color' => '#5C9E84',
                    'secondary_color' => '#7BB5A0',
                    'color_mode' => AppThemeSetting::MODE_LOGO_EXTRACT,
                    'glass_enabled' => true,
                    'motion_enabled' => true,
                    'tokens_light' => $this->defaultTokens('light'),
                    'tokens_dark' => $this->defaultTokens('dark'),
                    'preview_mode' => 'light',
                ]);
            }

            return $setting;
        });
    }

    /**
     * @return list<string>
     */
    public function tokenKeys(): array
    {
        return self::TOKEN_KEYS;
    }

    /**
     * @return array<string, string>
     */
    public function defaultTokens(string $mode): array
    {
        return $mode === 'dark'
            ? $this->defaultDarkTokens()
            : $this->defaultLightTokens();
    }

    /**
     * @param  array<string, mixed>|null  $tokens
     * @return array<string, string>
     */
    public function normalizeTokens(?array $tokens, string $mode): array
    {
        $defaults = $this->defaultTokens($mode);
        $normalized = [];

        foreach (self::TOKEN_KEYS as $key) {
            $value = is_array($tokens) ? ($tokens[$key] ?? null) : null;
            $normalized[$key] = $this->normalizeHex(
                is_string($value) ? $value : null,
                $defaults[$key]
            );
        }

        return $normalized;
    }

    /**
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function resolveTokens(?AppThemeSetting $setting = null): array
    {
        $setting ??= $this->current();

        $light = $setting->tokens_light;
        $dark = $setting->tokens_dark;

        if (! is_array($light) || $light === []) {
            $light = $this->backfillLightFromLegacy($setting);
        }

        if (! is_array($dark) || $dark === []) {
            $dark = $this->defaultTokens('dark');
            $primary = $this->normalizeHex($setting->primary_color, $dark['primary']);
            $dark['primary'] = $primary;
            $dark['sidebar-primary'] = $primary;
            $dark['ring'] = $primary;
            $dark['chart-1'] = $primary;
        }

        return [
            'light' => $this->normalizeTokens($light, 'light'),
            'dark' => $this->normalizeTokens($dark, 'dark'),
        ];
    }

    /**
     * Map light tokens → legacy columns payload.
     *
     * @param  array<string, string>  $lightTokens
     * @return array<string, string>
     */
    public function syncLegacyFromTokens(array $lightTokens): array
    {
        $light = $this->normalizeTokens($lightTokens, 'light');

        return [
            'primary_color' => $light['primary'],
            'secondary_color' => $light['secondary'],
            'navbar_color' => $light['navbar-background'],
            'sidebar_color' => $light['sidebar-background'],
            'background_color' => $light['background'],
        ];
    }

    public function viewData(): array
    {
        $setting = $this->current();
        $tokens = $this->resolveTokens($setting);
        $light = $tokens['light'];

        $primary = $this->normalizeHex($setting->primary_color ?: $light['primary'], '#5C9E84');
        $secondary = $this->normalizeHex($setting->secondary_color ?: $light['secondary'], '#7BB5A0');
        $primaryRgb = $this->hexToRgbString($primary);
        $secondaryRgb = $this->hexToRgbString($secondary);

        $navbarBg = $this->optionalHex($setting->navbar_color);
        $sidebarBg = $this->optionalHex($setting->sidebar_color);
        $pageBg = $this->optionalHex($setting->background_color);

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'primary_rgb' => $primaryRgb,
            'secondary_rgb' => $secondaryRgb,
            'primary_600' => $this->shadeHex($primary, 0.16),
            'primary_700' => $this->shadeHex($primary, 0.32),
            'primary_soft' => $this->tintHex($primary, 0.86),
            'secondary_600' => $this->shadeHex($secondary, 0.16),
            'secondary_soft' => $this->tintHex($secondary, 0.86),
            'navbar_bg' => $navbarBg,
            'navbar_rgb' => $navbarBg ? $this->hexToRgbString($navbarBg) : null,
            'navbar_color' => $navbarBg ? $this->contrastInk($navbarBg) : null,
            'sidebar_bg' => $sidebarBg,
            'sidebar_rgb' => $sidebarBg ? $this->hexToRgbString($sidebarBg) : null,
            'sidebar_color' => $sidebarBg ? $this->contrastInk($sidebarBg) : null,
            'page_bg' => $pageBg,
            'page_bg_rgb' => $pageBg ? $this->hexToRgbString($pageBg) : null,
            'color_mode' => $setting->color_mode,
            'glass_enabled' => $setting->glass_enabled,
            'motion_enabled' => $setting->motion_enabled,
            'logo_url' => $this->logoUrl($setting),
            'favicon_url' => $this->faviconUrl($setting),
            'default_logo_url' => asset('assets/img/harnica/logo.png'),
            'tokens_light' => $tokens['light'],
            'tokens_dark' => $tokens['dark'],
            'preview_mode' => in_array($setting->preview_mode, ['light', 'dark'], true)
                ? $setting->preview_mode
                : 'light',
            'token_groups' => self::TOKEN_GROUPS,
            'token_labels' => $this->tokenLabels(),
            'font' => $this->fontViewData($setting),
            'font_presets' => $this->fontPresets(),
        ];
    }

    /**
     * @return array<string, array{label: string, family: string, google: ?string}>
     */
    public function fontPresets(): array
    {
        /** @var array<string, array{label?: string, family?: string, google?: ?string}> $presets */
        $presets = config('theme-fonts.presets', []);

        $normalized = [];
        foreach ($presets as $key => $meta) {
            if (! is_array($meta) || empty($meta['family'])) {
                continue;
            }
            $normalized[(string) $key] = [
                'label' => (string) ($meta['label'] ?? $key),
                'family' => (string) $meta['family'],
                'google' => isset($meta['google']) && is_string($meta['google']) && $meta['google'] !== ''
                    ? $meta['google']
                    : null,
            ];
        }

        return $normalized !== []
            ? $normalized
            : [
                'dm-sans' => [
                    'label' => 'DM Sans (default)',
                    'family' => "'DM Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
                    'google' => null,
                ],
            ];
    }

    public function defaultFontPreset(): string
    {
        $default = (string) config('theme-fonts.default', 'dm-sans');
        $presets = $this->fontPresets();

        return isset($presets[$default]) ? $default : (array_key_first($presets) ?: 'dm-sans');
    }

    /**
     * @return array{
     *     source: string,
     *     preset: string,
     *     family: string,
     *     google_url: ?string,
     *     upload_url: ?string,
     *     has_upload: bool,
     *     css_format: ?string
     * }
     */
    public function fontViewData(?AppThemeSetting $setting = null): array
    {
        $setting ??= $this->current();
        $presets = $this->fontPresets();
        $source = $setting->font_source === AppThemeSetting::FONT_SOURCE_UPLOAD
            ? AppThemeSetting::FONT_SOURCE_UPLOAD
            : AppThemeSetting::FONT_SOURCE_PRESET;
        $preset = is_string($setting->font_preset) && isset($presets[$setting->font_preset])
            ? $setting->font_preset
            : $this->defaultFontPreset();

        $uploadUrl = null;
        $cssFormat = null;
        if ($setting->font_path && Storage::disk('public')->exists($setting->font_path)) {
            $uploadUrl = Storage::disk('public')->url($setting->font_path);
            $ext = strtolower(pathinfo($setting->font_path, PATHINFO_EXTENSION));
            $cssFormat = match ($ext) {
                'woff2' => 'woff2',
                'woff' => 'woff',
                'otf' => 'opentype',
                default => 'truetype',
            };
        }

        if ($source === AppThemeSetting::FONT_SOURCE_UPLOAD && $uploadUrl) {
            return [
                'source' => $source,
                'preset' => $preset,
                'family' => "'AppThemeFont', 'DM Sans', 'Inter', sans-serif",
                'google_url' => null,
                'upload_url' => $uploadUrl,
                'has_upload' => true,
                'css_format' => $cssFormat,
            ];
        }

        $meta = $presets[$preset];

        return [
            'source' => AppThemeSetting::FONT_SOURCE_PRESET,
            'preset' => $preset,
            'family' => $meta['family'],
            'google_url' => $meta['google'],
            'upload_url' => $uploadUrl,
            'has_upload' => (bool) $uploadUrl,
            'css_format' => $cssFormat,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function tokenLabels(): array
    {
        return [
            'background' => 'Background',
            'foreground' => 'Foreground',
            'card' => 'Card',
            'card-foreground' => 'Card Foreground',
            'primary' => 'Primary',
            'primary-foreground' => 'Primary Foreground',
            'secondary' => 'Secondary',
            'secondary-foreground' => 'Secondary Foreground',
            'muted' => 'Muted',
            'muted-foreground' => 'Muted Foreground',
            'accent' => 'Accent',
            'accent-foreground' => 'Accent Foreground',
            'destructive' => 'Destructive',
            'destructive-foreground' => 'Destructive Foreground',
            'border' => 'Border',
            'input' => 'Input',
            'ring' => 'Ring',
            'navbar-background' => 'Navbar Background',
            'navbar-foreground' => 'Navbar Foreground',
            'sidebar-background' => 'Sidebar Background',
            'sidebar-foreground' => 'Sidebar Foreground',
            'sidebar-primary' => 'Sidebar Primary',
            'sidebar-accent' => 'Sidebar Accent',
            'sidebar-border' => 'Sidebar Border',
            'chart-1' => 'Chart 1',
            'chart-2' => 'Chart 2',
            'chart-3' => 'Chart 3',
            'chart-4' => 'Chart 4',
            'chart-5' => 'Chart 5',
        ];
    }

    /**
     * Pick readable ink for a surface background using WCAG contrast ratio.
     */
    public function contrastInk(string $hex): string
    {
        $hex = $this->normalizeHex($hex, '#FFFFFF');
        $dark = '#2F3A44';
        $light = '#FFFFFF';

        return $this->contrastRatio($hex, $light) >= $this->contrastRatio($hex, $dark)
            ? $light
            : $dark;
    }

    public function update(
        array $data,
        ?UploadedFile $logo = null,
        ?UploadedFile $favicon = null,
        ?string $userId = null,
        ?UploadedFile $fontFile = null,
    ): AppThemeSetting {
        $setting = $this->current();

        if ($logo) {
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $data['logo_path'] = $logo->store('theme', 'public');
        }

        if ($favicon) {
            if ($setting->favicon_path) {
                Storage::disk('public')->delete($setting->favicon_path);
            }
            $data['favicon_path'] = $favicon->store('theme', 'public');
        }

        if ($fontFile) {
            if ($setting->font_path) {
                Storage::disk('public')->delete($setting->font_path);
            }
            $data['font_path'] = $fontFile->store('theme/fonts', 'public');
            $data['font_source'] = AppThemeSetting::FONT_SOURCE_UPLOAD;
        }

        $data['updated_by'] = $userId;
        $setting->update($data);
        Cache::forget(self::CACHE_KEY);

        return $setting->fresh();
    }

    public function logoUrl(?AppThemeSetting $setting = null): string
    {
        $setting ??= $this->current();

        if ($setting->logo_path && Storage::disk('public')->exists($setting->logo_path)) {
            return Storage::disk('public')->url($setting->logo_path);
        }

        return asset('assets/img/harnica/logo.png');
    }

    public function faviconUrl(?AppThemeSetting $setting = null): string
    {
        $setting ??= $this->current();

        if ($setting->favicon_path && Storage::disk('public')->exists($setting->favicon_path)) {
            return Storage::disk('public')->url($setting->favicon_path);
        }

        return asset('assets/img/wms/favicon.ico');
    }

    /**
     * @return array<string, string>
     */
    private function defaultLightTokens(): array
    {
        // Matches live admin chrome in custom.css / design-system.css
        // (white menu + navbar, mint page bg, brand green primary)
        return [
            'background' => '#EEF7EF',
            'foreground' => '#2F3A44',
            'card' => '#FFFFFF',
            'card-foreground' => '#2F3A44',
            'primary' => '#5C9E84',
            'primary-foreground' => '#FFFFFF',
            'secondary' => '#7BB5A0',
            'secondary-foreground' => '#FFFFFF',
            'muted' => '#F0F4F2',
            'muted-foreground' => '#6C757D',
            'accent' => '#E8F3EE',
            'accent-foreground' => '#2F3A44',
            'destructive' => '#DC3545',
            'destructive-foreground' => '#FFFFFF',
            'border' => '#D8E5DE',
            'input' => '#D8E5DE',
            'ring' => '#5C9E84',
            'navbar-background' => '#FFFFFF',
            'navbar-foreground' => '#2F3A44',
            'sidebar-background' => '#FFFFFF',
            'sidebar-foreground' => '#333333',
            'sidebar-primary' => '#5C9E84',
            'sidebar-accent' => '#E8F3EE',
            'sidebar-border' => '#E4EEE8',
            'chart-1' => '#5C9E84',
            'chart-2' => '#7BB5A0',
            'chart-3' => '#E07A5F',
            'chart-4' => '#3D5A80',
            'chart-5' => '#F2CC8F',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function defaultDarkTokens(): array
    {
        return [
            'background' => '#12151A',
            'foreground' => '#E8ECF0',
            'card' => '#1C1F23',
            'card-foreground' => '#E8ECF0',
            'primary' => '#6FB598',
            'primary-foreground' => '#0F1419',
            'secondary' => '#8BC4B0',
            'secondary-foreground' => '#0F1419',
            'muted' => '#252A31',
            'muted-foreground' => '#A0AAB4',
            'accent' => '#252A31',
            'accent-foreground' => '#E8ECF0',
            'destructive' => '#EF5350',
            'destructive-foreground' => '#FFFFFF',
            'border' => '#2F353C',
            'input' => '#2F353C',
            'ring' => '#6FB598',
            'navbar-background' => '#1C1F23',
            'navbar-foreground' => '#E8ECF0',
            'sidebar-background' => '#0F1216',
            'sidebar-foreground' => '#E8ECF0',
            'sidebar-primary' => '#6FB598',
            'sidebar-accent' => '#1C1F23',
            'sidebar-border' => '#252A31',
            'chart-1' => '#6FB598',
            'chart-2' => '#8BC4B0',
            'chart-3' => '#E07A5F',
            'chart-4' => '#98C1D9',
            'chart-5' => '#F2CC8F',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function backfillLightFromLegacy(AppThemeSetting $setting): array
    {
        $tokens = $this->defaultTokens('light');
        $primary = $this->normalizeHex($setting->primary_color, $tokens['primary']);
        $secondary = $this->normalizeHex($setting->secondary_color, $tokens['secondary']);

        $tokens['primary'] = $primary;
        $tokens['secondary'] = $secondary;
        $tokens['ring'] = $primary;
        $tokens['sidebar-primary'] = $primary;
        $tokens['chart-1'] = $primary;
        $tokens['chart-2'] = $secondary;
        $tokens['accent'] = $this->tintHex($primary, 0.86);
        $tokens['primary-foreground'] = $this->contrastInk($primary);
        $tokens['secondary-foreground'] = $this->contrastInk($secondary);

        if ($navbar = $this->optionalHex($setting->navbar_color)) {
            $tokens['navbar-background'] = $navbar;
            $tokens['navbar-foreground'] = $this->contrastInk($navbar);
        }

        if ($sidebar = $this->optionalHex($setting->sidebar_color)) {
            $tokens['sidebar-background'] = $sidebar;
            $tokens['sidebar-foreground'] = $this->contrastInk($sidebar);
        }

        if ($background = $this->optionalHex($setting->background_color)) {
            $tokens['background'] = $background;
            $tokens['foreground'] = $this->contrastInk($background);
        }

        return $tokens;
    }

    private function relativeLuminance(string $hex): float
    {
        $hex = ltrim($this->normalizeHex($hex, '#FFFFFF'), '#');
        $channels = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];

        $linear = array_map(static function (float $c): float {
            return $c <= 0.03928
                ? $c / 12.92
                : (($c + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    private function contrastRatio(string $hexA, string $hexB): float
    {
        $l1 = $this->relativeLuminance($hexA);
        $l2 = $this->relativeLuminance($hexB);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function normalizeHex(?string $hex, string $fallback): string
    {
        $hex = strtoupper(trim((string) $hex));
        if (preg_match('/^#[0-9A-F]{6}$/', $hex)) {
            return $hex;
        }

        return strtoupper($fallback);
    }

    private function optionalHex(?string $hex): ?string
    {
        if ($hex === null || trim($hex) === '') {
            return null;
        }

        $normalized = strtoupper(trim($hex));
        if (preg_match('/^#[0-9A-F]{6}$/', $normalized)) {
            return $normalized;
        }

        return null;
    }

    private function hexToRgbString(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "{$r}, {$g}, {$b}";
    }

    private function shadeHex(string $hex, float $amount): string
    {
        return $this->mixHex($hex, '#000000', $amount);
    }

    private function tintHex(string $hex, float $amount): string
    {
        return $this->mixHex($hex, '#FFFFFF', $amount);
    }

    private function mixHex(string $hex, string $target, float $amount): string
    {
        $hex = ltrim($hex, '#');
        $target = ltrim($target, '#');

        $r1 = hexdec(substr($hex, 0, 2));
        $g1 = hexdec(substr($hex, 2, 2));
        $b1 = hexdec(substr($hex, 4, 2));

        $r2 = hexdec(substr($target, 0, 2));
        $g2 = hexdec(substr($target, 2, 2));
        $b2 = hexdec(substr($target, 4, 2));

        $r = (int) round($r1 + ($r2 - $r1) * $amount);
        $g = (int) round($g1 + ($g2 - $g1) * $amount);
        $b = (int) round($b1 + ($b2 - $b1) * $amount);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }
}
