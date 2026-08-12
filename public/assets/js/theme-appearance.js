/**
 * Theme Appearance Studio — live preview scoped to #theme-preview
 */
(function () {
    'use strict';

    var HEX_RE = /^#[0-9A-Fa-f]{6}$/;

    function clone(obj) {
        return JSON.parse(JSON.stringify(obj));
    }

    function normalizeHex(value, fallback) {
        var v = String(value || '').trim().toUpperCase();
        if (HEX_RE.test(v)) return v;
        var f = String(fallback || '#000000').trim().toUpperCase();
        return HEX_RE.test(f) ? f : '#000000';
    }

    function init() {
        var studio = document.getElementById('theme-studio');
        var form = document.getElementById('theme-settings-form');
        var preview = document.getElementById('theme-preview');
        if (!studio || !form || !preview) return;

        var config = window.ThemeAppearanceConfig || {};
        var defaultsLight = config.defaultsLight || JSON.parse(studio.dataset.defaultsLight || '{}');
        var defaultsDark = config.defaultsDark || JSON.parse(studio.dataset.defaultsDark || '{}');

        var state = {
            mode: studio.dataset.previewMode === 'dark' ? 'dark' : 'light',
            light: JSON.parse(studio.dataset.tokensLight || '{}'),
            dark: JSON.parse(studio.dataset.tokensDark || '{}'),
        };

        // Ensure full key set
        Object.keys(defaultsLight).forEach(function (k) {
            state.light[k] = normalizeHex(state.light[k], defaultsLight[k]);
        });
        Object.keys(defaultsDark).forEach(function (k) {
            state.dark[k] = normalizeHex(state.dark[k], defaultsDark[k]);
        });

        var previewModeInput = document.getElementById('preview_mode');
        var aiStatus = document.getElementById('themeAiStatus');

        function currentTokens() {
            return state.mode === 'dark' ? state.dark : state.light;
        }

        function applyPreview() {
            var tokens = currentTokens();
            Object.keys(tokens).forEach(function (key) {
                preview.style.setProperty('--' + key, tokens[key]);
            });
            preview.style.setProperty('--brand-primary', tokens.primary || tokens['primary']);
            syncPanelInputs();
            if (previewModeInput) previewModeInput.value = state.mode;
        }

        function syncPanelInputs() {
            var tokens = currentTokens();
            studio.querySelectorAll('[data-token-key]').forEach(function (el) {
                var key = el.getAttribute('data-token-key');
                if (!key || tokens[key] == null) return;
                if (el.classList.contains('token-swatch') || el.classList.contains('token-hex')) {
                    el.value = tokens[key];
                }
            });
        }

        function setToken(key, value) {
            var hex = normalizeHex(value, currentTokens()[key]);
            if (state.mode === 'dark') {
                state.dark[key] = hex;
            } else {
                state.light[key] = hex;
            }
            var custom = document.getElementById('color_mode_custom');
            if (custom) custom.checked = true;
            applyPreview();
        }

        // Scene tabs
        studio.querySelectorAll('[data-scene-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var scene = btn.getAttribute('data-scene-tab');
                studio.querySelectorAll('[data-scene-tab]').forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });
                preview.querySelectorAll('[data-scene]').forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-scene') !== scene;
                });
            });
        });

        // Light / Dark
        studio.querySelectorAll('.theme-mode-toggle [data-mode]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                state.mode = btn.getAttribute('data-mode') === 'dark' ? 'dark' : 'light';
                studio.querySelectorAll('.theme-mode-toggle [data-mode]').forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });
                applyPreview();
            });
        });

        // Token group tabs
        studio.querySelectorAll('[data-token-group]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var group = btn.getAttribute('data-token-group');
                studio.querySelectorAll('[data-token-group]').forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });
                studio.querySelectorAll('[data-token-group-panel]').forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-token-group-panel') !== group;
                });
            });
        });

        // Color inputs
        studio.querySelectorAll('.token-swatch').forEach(function (input) {
            input.addEventListener('input', function () {
                setToken(input.getAttribute('data-token-key'), input.value);
            });
        });

        studio.querySelectorAll('.token-hex').forEach(function (input) {
            input.addEventListener('change', function () {
                var key = input.getAttribute('data-token-key');
                var val = input.value.trim();
                if (!HEX_RE.test(val)) {
                    input.value = currentTokens()[key];
                    return;
                }
                setToken(key, val);
            });
        });

        studio.querySelectorAll('.btn-reset-token').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-token-key');
                var def = state.mode === 'dark' ? defaultsDark[key] : defaultsLight[key];
                setToken(key, def);
            });
        });

        var resetBtn = document.getElementById('themeResetDefaultsBtn');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (!window.confirm('Reset light & dark tokens to defaults? Logo/favicon will not be removed.')) {
                    return;
                }
                state.light = clone(defaultsLight);
                state.dark = clone(defaultsDark);
                applyPreview();
            });
        }

        // Export / Import removed by product request

        function setAiStatus(message, isError) {
            if (!aiStatus) return;
            aiStatus.textContent = message || '';
            aiStatus.classList.toggle('is-error', !!isError);
            aiStatus.classList.toggle('is-ok', !!message && !isError);
        }

        // AI generate
        var aiBtn = document.getElementById('themeAiBtn');
        if (aiBtn) {
            aiBtn.addEventListener('click', function () {
                var prompt = window.prompt('Opsional: deskripsikan warna / mood brand', '');
                if (prompt === null) return;
                setAiStatus('Generating…', false);
                aiBtn.disabled = true;
                fetch(studio.dataset.generateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ prompt: prompt || '' }),
                })
                    .then(function (res) {
                        return res.json().then(function (body) {
                            return { ok: res.ok, body: body };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok || !result.body.success) {
                            throw new Error((result.body && result.body.message) || 'Generate gagal');
                        }
                        Object.keys(defaultsLight).forEach(function (k) {
                            state.light[k] = normalizeHex(result.body.light[k], defaultsLight[k]);
                        });
                        Object.keys(defaultsDark).forEach(function (k) {
                            state.dark[k] = normalizeHex(result.body.dark[k], defaultsDark[k]);
                        });
                        var custom = document.getElementById('color_mode_custom');
                        if (custom) custom.checked = true;
                        applyPreview();
                        setAiStatus('AI palette applied to preview. Save to persist.', false);
                    })
                    .catch(function (err) {
                        setAiStatus(err.message || 'Generate gagal', true);
                    })
                    .finally(function () {
                        aiBtn.disabled = false;
                    });
            });
        }

        // Logo preview
        var logoInput = document.getElementById('theme_logo');
        var previewLogo = document.getElementById('themePreviewLogo');
        if (logoInput && previewLogo) {
            logoInput.addEventListener('change', function () {
                var file = logoInput.files && logoInput.files[0];
                if (!file) return;
                var url = URL.createObjectURL(file);
                previewLogo.src = url;
            });
        }

        // Font: preset / upload preview
        var fontSample = document.getElementById('themeFontSample');
        var fontPresetSelect = document.getElementById('font_preset');
        var fontFileInput = document.getElementById('theme_font_file');
        var fontPresets = config.fontPresets || {};
        var previewFontStyle = document.getElementById('theme-preview-font-style');
        if (!previewFontStyle) {
            previewFontStyle = document.createElement('style');
            previewFontStyle.id = 'theme-preview-font-style';
            document.head.appendChild(previewFontStyle);
        }
        var previewGoogleLink = document.getElementById('theme-preview-google-font');

        function ensurePreviewGoogleLink(href) {
            if (!href) {
                if (previewGoogleLink) previewGoogleLink.remove();
                previewGoogleLink = null;
                return;
            }
            if (!previewGoogleLink) {
                previewGoogleLink = document.createElement('link');
                previewGoogleLink.id = 'theme-preview-google-font';
                previewGoogleLink.rel = 'stylesheet';
                document.head.appendChild(previewGoogleLink);
            }
            previewGoogleLink.href = href;
        }

        function applyPreviewFont(family, googleHref, faceCss) {
            ensurePreviewGoogleLink(googleHref || '');
            var css = '';
            if (faceCss) css += faceCss + '\n';
            css += '#theme-preview, #themeFontSample { font-family: ' + family + ' !important; }';
            previewFontStyle.textContent = css;
            if (fontSample) fontSample.style.fontFamily = family;
        }

        function syncFontSourcePanels() {
            var source = (studio.querySelector('input[name="font_source"]:checked') || {}).value || 'preset';
            studio.querySelectorAll('[data-font-source-panel]').forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-font-source-panel') !== source;
            });
            if (source === 'preset') {
                applySelectedPresetFont();
            } else if (config.font && config.font.upload_url) {
                applyPreviewFont(
                    "'AppThemeFontPreview', 'DM Sans', sans-serif",
                    '',
                    "@font-face{font-family:'AppThemeFontPreview';src:url('" + config.font.upload_url + "') format('" + (config.font.css_format || 'truetype') + "');font-display:swap;}"
                );
            }
        }

        function applySelectedPresetFont() {
            if (!fontPresetSelect) return;
            var opt = fontPresetSelect.options[fontPresetSelect.selectedIndex];
            if (!opt) return;
            var family = opt.getAttribute('data-family') || 'sans-serif';
            var google = opt.getAttribute('data-google') || '';
            applyPreviewFont(family, google, '');
        }

        studio.querySelectorAll('input[name="font_source"]').forEach(function (radio) {
            radio.addEventListener('change', syncFontSourcePanels);
        });
        if (fontPresetSelect) {
            if (window.jQuery && jQuery.fn.select2) {
                var $fontSelect = jQuery(fontPresetSelect);
                if ($fontSelect.hasClass('select2-hidden-accessible')) {
                    $fontSelect.select2('destroy');
                }
                $fontSelect.select2({
                    width: '100%',
                    placeholder: fontPresetSelect.getAttribute('data-placeholder') || 'Cari font…',
                    allowClear: false,
                    dropdownParent: jQuery(document.body),
                    minimumResultsForSearch: 0,
                });
                $fontSelect.on('change select2:select', function () {
                    var presetRadio = document.getElementById('font_source_preset');
                    if (presetRadio) presetRadio.checked = true;
                    syncFontSourcePanels();
                });
            } else {
                fontPresetSelect.addEventListener('change', function () {
                    var presetRadio = document.getElementById('font_source_preset');
                    if (presetRadio) presetRadio.checked = true;
                    syncFontSourcePanels();
                });
            }
        }
        if (fontFileInput) {
            fontFileInput.addEventListener('change', function () {
                var file = fontFileInput.files && fontFileInput.files[0];
                if (!file) return;
                var uploadRadio = document.getElementById('font_source_upload');
                if (uploadRadio) uploadRadio.checked = true;
                syncFontSourcePanels();
                var url = URL.createObjectURL(file);
                var ext = (file.name.split('.').pop() || 'ttf').toLowerCase();
                var format = ext === 'woff2' ? 'woff2' : (ext === 'woff' ? 'woff' : (ext === 'otf' ? 'opentype' : 'truetype'));
                applyPreviewFont(
                    "'AppThemeFontPreview', 'DM Sans', sans-serif",
                    '',
                    "@font-face{font-family:'AppThemeFontPreview';src:url('" + url + "') format('" + format + "');font-display:swap;}"
                );
            });
        }
        syncFontSourcePanels();

        // Nested token fields for Laravel array validation
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (previewModeInput) previewModeInput.value = state.mode;
            clearDynamicTokenInputs(form);
            Object.keys(state.light).forEach(function (k) {
                appendHidden(form, 'tokens_light[' + k + ']', state.light[k]);
            });
            Object.keys(state.dark).forEach(function (k) {
                appendHidden(form, 'tokens_dark[' + k + ']', state.dark[k]);
            });
            HTMLFormElement.prototype.submit.call(form);
        });

        applyPreview();
    }

    function clearDynamicTokenInputs(form) {
        form.querySelectorAll('input[data-dynamic-token="1"]').forEach(function (el) {
            el.remove();
        });
    }

    function appendHidden(form, name, value) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        input.setAttribute('data-dynamic-token', '1');
        form.appendChild(input);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
