/**
 * Theme settings admin — live preview + logo extract
 */
(function () {
    'use strict';

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

        function toggleCustomFields() {
            var isCustom = modeCustom && modeCustom.checked;
            document.querySelectorAll('.theme-custom-field').forEach(function (el) {
                el.classList.toggle('d-none', !isCustom);
            });
            if (extractBtn) extractBtn.classList.toggle('d-none', isCustom);
        }

        primaryInput?.addEventListener('input', syncPreviewColors);
        secondaryInput?.addEventListener('input', syncPreviewColors);
        modeLogo?.addEventListener('change', toggleCustomFields);
        modeCustom?.addEventListener('change', toggleCustomFields);

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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
