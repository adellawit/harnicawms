/**
 * Theme settings admin — live preview + logo extract + surface overrides
 */
(function () {
    'use strict';

    function contrastInk(hex) {
        var h = (hex || '').replace('#', '');
        if (h.length !== 6) return '#2F3A44';
        var channels = [0, 2, 4].map(function (i) {
            var c = parseInt(h.substr(i, 2), 16) / 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        });
        var luminance = 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
        return luminance > 0.179 ? '#2F3A44' : '#FFFFFF';
    }

    function init() {
        var form = document.getElementById('theme-settings-form');
        if (!form) return;

        var modeLogo = document.getElementById('color_mode_logo');
        var modeCustom = document.getElementById('color_mode_custom');
        var primaryInput = document.getElementById('primary_color');
        var secondaryInput = document.getElementById('secondary_color');
        var logoInput = document.getElementById('theme_logo');
        var extractBtn = document.getElementById('extractFromLogoBtn');
        var previewPrimary = document.getElementById('previewPrimary');
        var previewSecondary = document.getElementById('previewSecondary');
        var previewLogo = document.getElementById('previewLogo');
        var previewNavbar = document.getElementById('previewNavbar');
        var previewSidebar = document.getElementById('previewSidebar');
        var previewPageBg = document.getElementById('previewPageBg');
        var navbarInput = document.getElementById('navbar_color');
        var sidebarInput = document.getElementById('sidebar_color');
        var backgroundInput = document.getElementById('background_color');
        var overrideNavbar = document.getElementById('override_navbar');
        var overrideSidebar = document.getElementById('override_sidebar');
        var overrideBackground = document.getElementById('override_background');

        function syncPreviewColors() {
            if (!primaryInput || !secondaryInput) return;
            var p = primaryInput.value;
            var s = secondaryInput.value;
            if (previewPrimary) previewPrimary.style.background = p;
            if (previewSecondary) previewSecondary.style.background = s;
            if (window.BrandTheme) {
                window.BrandTheme.applyFromCustom(p, s);
            }
        }

        function syncSurfacePreview() {
            if (previewNavbar && navbarInput) {
                var navBg = overrideNavbar && overrideNavbar.checked ? navbarInput.value : '#FFFFFF';
                previewNavbar.style.background = navBg;
                previewNavbar.style.color = contrastInk(navBg);
            }
            if (previewSidebar && sidebarInput) {
                var sideBg = overrideSidebar && overrideSidebar.checked ? sidebarInput.value : '#FFFFFF';
                previewSidebar.style.background = sideBg;
                previewSidebar.style.color = contrastInk(sideBg);
            }
            if (previewPageBg && backgroundInput) {
                var pageBg = overrideBackground && overrideBackground.checked ? backgroundInput.value : '#F4F6F9';
                previewPageBg.style.background = pageBg;
            }
        }

        function toggleCustomFields() {
            var isCustom = modeCustom && modeCustom.checked;
            document.querySelectorAll('.theme-custom-field').forEach(function (el) {
                el.classList.toggle('d-none', !isCustom);
            });
            if (extractBtn) extractBtn.classList.toggle('d-none', isCustom);
        }

        function syncSurfaceFields() {
            document.querySelectorAll('.surface-override-toggle').forEach(function (toggle) {
                var targetId = toggle.getAttribute('data-target');
                var input = targetId ? document.getElementById(targetId) : null;
                var field = document.querySelector('.surface-color-field[data-for="' + targetId + '"]');
                var enabled = toggle.checked;
                if (input) input.disabled = !enabled;
                if (field) field.classList.toggle('opacity-50', !enabled);
            });
            syncSurfacePreview();
        }

        primaryInput?.addEventListener('input', syncPreviewColors);
        secondaryInput?.addEventListener('input', syncPreviewColors);
        modeLogo?.addEventListener('change', toggleCustomFields);
        modeCustom?.addEventListener('change', toggleCustomFields);

        [navbarInput, sidebarInput, backgroundInput].forEach(function (input) {
            input?.addEventListener('input', syncSurfacePreview);
        });
        document.querySelectorAll('.surface-override-toggle').forEach(function (toggle) {
            toggle.addEventListener('change', syncSurfaceFields);
        });

        // Disabled color inputs are omitted from POST — re-enable briefly on submit when override is on
        form.addEventListener('submit', function () {
            document.querySelectorAll('.surface-override-toggle').forEach(function (toggle) {
                var targetId = toggle.getAttribute('data-target');
                var input = targetId ? document.getElementById(targetId) : null;
                if (input && toggle.checked) {
                    input.disabled = false;
                }
            });
        });

        logoInput?.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (!file || !previewLogo) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                previewLogo.src = e.target.result;
                previewLogo.setAttribute('data-brand-logo', e.target.result);
            };
            reader.readAsDataURL(file);
        });

        extractBtn?.addEventListener('click', function () {
            var src = previewLogo?.getAttribute('data-brand-logo') || previewLogo?.src;
            if (!src || !window.BrandTheme) return;
            window.BrandTheme.applyFromLogo(src, function (palette) {
                if (!palette) return;
                var toHex = function (rgb) {
                    return '#' + [rgb.r, rgb.g, rgb.b].map(function (c) {
                        var h = Math.round(c).toString(16);
                        return h.length === 1 ? '0' + h : h;
                    }).join('');
                };
                if (primaryInput) primaryInput.value = toHex(palette.primary).toUpperCase();
                if (secondaryInput) secondaryInput.value = toHex(palette.secondary).toUpperCase();
                syncPreviewColors();
            });
        });

        toggleCustomFields();
        syncPreviewColors();
        syncSurfaceFields();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
