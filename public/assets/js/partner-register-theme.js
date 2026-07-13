/**
 * Partner Register — Brand Theme Engine
 * ------------------------------------------------------------------
 * Auto-extracts the dominant brand palette from the project logo and
 * applies it to the page via CSS custom properties. No user picker:
 * the theme is derived directly from the logo the app already uses.
 *
 * Falls back to the baked-in Harnica palette (see partner-register.css)
 * if extraction is not possible (e.g. logo missing / canvas blocked).
 */
(function () {
    'use strict';

    var root = document.documentElement;

    // ---- color helpers -------------------------------------------------
    function clamp(v) { return Math.max(0, Math.min(255, Math.round(v))); }

    function toHex(r, g, b) {
        return '#' + [r, g, b].map(function (c) {
            var h = clamp(c).toString(16);
            return h.length === 1 ? '0' + h : h;
        }).join('');
    }

    function rgbToHsl(r, g, b) {
        r /= 255; g /= 255; b /= 255;
        var max = Math.max(r, g, b), min = Math.min(r, g, b);
        var h = 0, s = 0, l = (max + min) / 2;
        if (max !== min) {
            var d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            switch (max) {
                case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                case g: h = (b - r) / d + 2; break;
                default: h = (r - g) / d + 4; break;
            }
            h /= 6;
        }
        return { h: h * 360, s: s, l: l };
    }

    function mix(rgb, target, amount) {
        return {
            r: rgb.r + (target - rgb.r) * amount,
            g: rgb.g + (target - rgb.g) * amount,
            b: rgb.b + (target - rgb.b) * amount,
        };
    }

    function shade(rgb, amount) { return mix(rgb, 0, amount); }   // darker
    function tint(rgb, amount) { return mix(rgb, 255, amount); }  // lighter

    // ---- CSS variable application -------------------------------------
    function applyColor(prefix, rgb) {
        var r = clamp(rgb.r), g = clamp(rgb.g), b = clamp(rgb.b);
        root.style.setProperty(prefix, toHex(r, g, b));
        root.style.setProperty(prefix + '-rgb', r + ', ' + g + ', ' + b);
        var dark = shade(rgb, 0.16);
        var darker = shade(rgb, 0.32);
        var soft = tint(rgb, 0.86);
        root.style.setProperty(prefix + '-600', toHex(dark.r, dark.g, dark.b));
        root.style.setProperty(prefix + '-700', toHex(darker.r, darker.g, darker.b));
        root.style.setProperty(prefix + '-soft', toHex(soft.r, soft.g, soft.b));
    }

    function applyPalette(primary, secondary) {
        applyColor('--brand-primary', primary);
        applyColor('--brand-secondary', secondary);
        // Bridge into Bootstrap so themed components follow the brand.
        root.style.setProperty('--bs-primary', toHex(primary.r, primary.g, primary.b));
        root.style.setProperty('--bs-primary-rgb', clamp(primary.r) + ', ' + clamp(primary.g) + ', ' + clamp(primary.b));
        root.setAttribute('data-brand-ready', 'true');
    }

    // ---- dominant color extraction ------------------------------------
    function extract(img) {
        var size = 96;
        var canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        var ctx = canvas.getContext('2d');
        if (!ctx) return null;

        try {
            ctx.drawImage(img, 0, 0, size, size);
        } catch (e) {
            return null; // tainted canvas / cross-origin
        }

        var data;
        try {
            data = ctx.getImageData(0, 0, size, size).data;
        } catch (e) {
            return null;
        }

        var buckets = {};
        for (var i = 0; i < data.length; i += 4) {
            var r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
            if (a < 200) continue; // skip transparent

            var hsl = rgbToHsl(r, g, b);
            // Keep only vivid, mid-tone colors (drop white/black/grey text).
            if (hsl.s < 0.32) continue;
            if (hsl.l < 0.18 || hsl.l > 0.86) continue;

            var hueKey = Math.round(hsl.h / 24); // ~15 hue buckets
            var key = hueKey;
            if (!buckets[key]) {
                buckets[key] = { count: 0, r: 0, g: 0, b: 0, s: 0 };
            }
            var bkt = buckets[key];
            bkt.count++;
            bkt.r += r; bkt.g += g; bkt.b += b; bkt.s += hsl.s;
        }

        var clusters = Object.keys(buckets).map(function (k) {
            var bkt = buckets[k];
            return {
                hue: Number(k),
                count: bkt.count,
                sat: bkt.s / bkt.count,
                rgb: { r: bkt.r / bkt.count, g: bkt.g / bkt.count, b: bkt.b / bkt.count },
                // Score favours colors that are both frequent and vivid.
                score: bkt.count * (0.5 + bkt.s / bkt.count),
            };
        });

        if (!clusters.length) return null;

        clusters.sort(function (a, b) { return b.score - a.score; });

        var primary = clusters[0];
        var secondary = null;
        for (var j = 1; j < clusters.length; j++) {
            if (Math.abs(clusters[j].hue - primary.hue) >= 2) { // distinct hue
                secondary = clusters[j];
                break;
            }
        }
        if (!secondary) secondary = clusters[Math.min(1, clusters.length - 1)];

        return {
            primary: primary.rgb,
            secondary: secondary ? secondary.rgb : primary.rgb,
        };
    }

    function run() {
        var logo = document.querySelector('[data-brand-logo]');
        var src = (logo && (logo.getAttribute('data-brand-logo') || logo.getAttribute('src')));
        if (!src) return;

        var img = new Image();
        img.onload = function () {
            var palette = extract(img);
            if (palette) {
                applyPalette(palette.primary, palette.secondary);
            }
        };
        img.onerror = function () { /* keep baked-in fallback */ };
        img.src = src;
        if (img.complete && img.naturalWidth) { img.onload(); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
