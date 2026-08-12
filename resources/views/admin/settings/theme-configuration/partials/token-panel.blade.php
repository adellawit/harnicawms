@php
    $labels = $themeView['token_labels'] ?? [];
    $groups = $themeView['token_groups'] ?? [];
    $light = $tokensLight ?? ($themeView['tokens_light'] ?? []);
@endphp
<aside class="theme-token-panel">
    <div class="theme-token-panel-header">
        <h5 class="mb-1">Theme Colors</h5>
        <p class="text-muted small mb-0">Edit tokens for the preview. Changes apply globally after you save.</p>
        <div class="theme-token-actions">
            <button type="button" class="btn btn-sm btn-outline-primary" id="themeAiBtn">
                <i class="ti ti-sparkles me-1"></i>Generate with AI
            </button>
        </div>
        <div class="theme-ai-status mt-2" id="themeAiStatus" aria-live="polite"></div>
    </div>

    <div class="theme-token-tabs" role="tablist">
        <button type="button" class="active" data-token-group="base">Base Colors</button>
        <button type="button" data-token-group="navbar">Navbar</button>
        <button type="button" data-token-group="sidebar">Sidebar</button>
        <button type="button" data-token-group="charts">Charts</button>
    </div>

    <div class="theme-token-list">
        @foreach ($groups as $groupKey => $keys)
            <div class="theme-token-group" data-token-group-panel="{{ $groupKey }}" @if ($groupKey !== 'base') hidden @endif>
                @foreach ($keys as $key)
                    @php $value = $light[$key] ?? '#000000'; @endphp
                    <div class="theme-token-row" data-token-key="{{ $key }}">
                        <label for="token-input-{{ $key }}">{{ $labels[$key] ?? $key }}</label>
                        <input type="color" class="form-control form-control-color token-swatch"
                               id="token-swatch-{{ $key }}" value="{{ $value }}" data-token-key="{{ $key }}">
                        <input type="text" class="form-control form-control-sm token-hex"
                               id="token-input-{{ $key }}" value="{{ $value }}" maxlength="7"
                               pattern="#[0-9A-Fa-f]{6}" data-token-key="{{ $key }}">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-reset-token"
                                data-token-key="{{ $key }}" title="Reset token">
                            <i class="ti ti-refresh"></i>
                        </button>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="theme-token-effects">
        <div class="mb-2">
            <label class="form-label small mb-1">Sumber Warna</label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="color_mode" id="color_mode_logo" value="logo_extract"
                    @checked(old('color_mode', $theme->color_mode) === 'logo_extract')>
                <label class="form-check-label small" for="color_mode_logo">Otomatis dari logo</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="color_mode" id="color_mode_custom" value="custom"
                    @checked(old('color_mode', $theme->color_mode) === 'custom')>
                <label class="form-check-label small" for="color_mode_custom">Warna kustom (studio)</label>
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label small mb-1">Font</label>
            @php
                $font = $themeView['font'] ?? ['source' => 'preset', 'preset' => 'dm-sans', 'has_upload' => false];
                $fontPresets = $themeView['font_presets'] ?? [];
                $fontSource = old('font_source', $font['source'] ?? 'preset');
                $fontPreset = old('font_preset', $font['preset'] ?? 'dm-sans');
            @endphp
            <div class="form-check">
                <input class="form-check-input" type="radio" name="font_source" id="font_source_preset" value="preset"
                    @checked($fontSource === 'preset')>
                <label class="form-check-label small" for="font_source_preset">Preset</label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="font_source" id="font_source_upload" value="upload"
                    @checked($fontSource === 'upload')>
                <label class="form-check-label small" for="font_source_upload">Upload custom</label>
            </div>
            <div class="theme-font-preset-field mb-2" data-font-source-panel="preset">
                <select name="font_preset" id="font_preset" class="form-select form-select-sm theme-font-select"
                        data-placeholder="Cari font…">
                    @foreach ($fontPresets as $key => $meta)
                        <option value="{{ $key }}" @selected($fontPreset === $key)
                            data-family="{{ $meta['family'] }}"
                            data-google="{{ $meta['google'] ?? '' }}">
                            {{ $meta['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="theme-font-upload-field mb-2" data-font-source-panel="upload" @if ($fontSource !== 'upload') hidden @endif>
                <input type="file" class="form-control form-control-sm" name="font_file" id="theme_font_file"
                       accept=".ttf,.otf,.woff,.woff2,font/ttf,font/otf,font/woff,font/woff2">
                <small class="text-muted">TTF / OTF / WOFF / WOFF2 · maks 2MB
                    @if (! empty($font['has_upload']))
                        · file tersimpan
                    @endif
                </small>
            </div>
            <div class="theme-font-sample small border rounded px-2 py-1 bg-white" id="themeFontSample">
                The quick brown fox — Aa Bb Cc 123
            </div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6">
                <label class="form-label small" for="theme_logo">Logo</label>
                <input type="file" class="form-control form-control-sm" name="logo" id="theme_logo"
                       accept="image/png,image/jpeg,image/webp,image/svg+xml">
            </div>
            <div class="col-6">
                <label class="form-label small" for="theme_favicon">Favicon</label>
                <input type="file" class="form-control form-control-sm" name="favicon" id="theme_favicon"
                       accept="image/png,image/x-icon,image/jpeg">
            </div>
        </div>
        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" name="glass_enabled" id="glass_enabled" value="1"
                @checked(old('glass_enabled', $theme->glass_enabled))>
            <label class="form-check-label small" for="glass_enabled">Glassmorphism</label>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="motion_enabled" id="motion_enabled" value="1"
                @checked(old('motion_enabled', $theme->motion_enabled))>
            <label class="form-check-label small" for="motion_enabled">Animasi transisi</label>
        </div>
    </div>

    <div class="theme-token-footer">
        <button type="submit" class="btn btn-primary w-100">
            <i class="ti ti-device-floppy me-1"></i>Save Changes
        </button>
        <button type="button" class="btn btn-outline-secondary w-100" id="themeResetDefaultsBtn">
            Reset to Defaults
        </button>
    </div>
</aside>
