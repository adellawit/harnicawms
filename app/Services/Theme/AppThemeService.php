<?php

namespace App\Services\Theme;

use App\Models\Configuration\AppThemeSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AppThemeService
{
  private const CACHE_KEY = 'app_theme_settings';

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
                ]);
            }

            return $setting;
        });
    }

    public function viewData(): array
    {
        $setting = $this->current();
        $primary = $this->normalizeHex($setting->primary_color, '#5C9E84');
        $secondary = $this->normalizeHex($setting->secondary_color, '#7BB5A0');
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

    public function update(array $data, ?UploadedFile $logo = null, ?UploadedFile $favicon = null, ?string $userId = null): AppThemeSetting
    {
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
