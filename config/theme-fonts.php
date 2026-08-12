<?php

/**
 * Theme font presets for Appearance & Theme studio.
 *
 * To add a font:
 * 1. Pick a unique slug key (kebab-case)
 * 2. Set label + CSS family stack
 * 3. Set google to a Google Fonts CSS2 URL, or null for system-only stacks
 *
 * After editing: php artisan config:clear
 */

$fallback = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";

$google = static function (string $familyQuery) {
    return 'https://fonts.googleapis.com/css2?family='.$familyQuery.'&display=swap';
};

return [

    'default' => 'dm-sans',

    'presets' => [

        // —— Current product default ——
        'dm-sans' => [
            'label' => 'DM Sans (default)',
            'family' => "'DM Sans', 'Inter', {$fallback}",
            'google' => $google('DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900'),
        ],

        // —— Sans (UI / product) ——
        'inter' => [
            'label' => 'Inter',
            'family' => "'Inter', {$fallback}",
            'google' => $google('Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900'),
        ],
        'plus-jakarta' => [
            'label' => 'Plus Jakarta Sans',
            'family' => "'Plus Jakarta Sans', {$fallback}",
            'google' => $google('Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800'),
        ],
        'manrope' => [
            'label' => 'Manrope',
            'family' => "'Manrope', {$fallback}",
            'google' => $google('Manrope:wght@200..800'),
        ],
        'outfit' => [
            'label' => 'Outfit',
            'family' => "'Outfit', {$fallback}",
            'google' => $google('Outfit:wght@100..900'),
        ],
        'figtree' => [
            'label' => 'Figtree',
            'family' => "'Figtree', {$fallback}",
            'google' => $google('Figtree:ital,wght@0,300..900;1,300..900'),
        ],
        'space-grotesk' => [
            'label' => 'Space Grotesk',
            'family' => "'Space Grotesk', {$fallback}",
            'google' => $google('Space+Grotesk:wght@300..700'),
        ],
        'poppins' => [
            'label' => 'Poppins',
            'family' => "'Poppins', {$fallback}",
            'google' => $google('Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900'),
        ],
        'montserrat' => [
            'label' => 'Montserrat',
            'family' => "'Montserrat', {$fallback}",
            'google' => $google('Montserrat:ital,wght@0,100..900;1,100..900'),
        ],
        'nunito' => [
            'label' => 'Nunito Sans',
            'family' => "'Nunito Sans', {$fallback}",
            'google' => $google('Nunito+Sans:ital,opsz,wght@0,6..12,200..1000;1,6..12,200..1000'),
        ],
        'nunito-rounded' => [
            'label' => 'Nunito',
            'family' => "'Nunito', {$fallback}",
            'google' => $google('Nunito:ital,wght@0,200..1000;1,200..1000'),
        ],
        'rubik' => [
            'label' => 'Rubik',
            'family' => "'Rubik', {$fallback}",
            'google' => $google('Rubik:ital,wght@0,300..900;1,300..900'),
        ],
        'work-sans' => [
            'label' => 'Work Sans',
            'family' => "'Work Sans', {$fallback}",
            'google' => $google('Work+Sans:ital,wght@0,100..900;1,100..900'),
        ],
        'lato' => [
            'label' => 'Lato',
            'family' => "'Lato', {$fallback}",
            'google' => $google('Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900'),
        ],
        'open-sans' => [
            'label' => 'Open Sans',
            'family' => "'Open Sans', {$fallback}",
            'google' => $google('Open+Sans:ital,wght@0,300..800;1,300..800'),
        ],
        'roboto' => [
            'label' => 'Roboto',
            'family' => "'Roboto', {$fallback}",
            'google' => $google('Roboto:ital,wght@0,100..900;1,100..900'),
        ],
        'roboto-flex' => [
            'label' => 'Roboto Flex',
            'family' => "'Roboto Flex', {$fallback}",
            'google' => $google('Roboto+Flex:opsz,wght@8..144,100..1000'),
        ],
        'source-sans' => [
            'label' => 'Source Sans 3',
            'family' => "'Source Sans 3', {$fallback}",
            'google' => $google('Source+Sans+3:ital,wght@0,200..900;1,200..900'),
        ],
        'ibm-plex-sans' => [
            'label' => 'IBM Plex Sans',
            'family' => "'IBM Plex Sans', {$fallback}",
            'google' => $google('IBM+Plex+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;1,100;1,200;1,300;1,400;1,500;1,600;1,700'),
        ],
        'noto-sans' => [
            'label' => 'Noto Sans',
            'family' => "'Noto Sans', {$fallback}",
            'google' => $google('Noto+Sans:ital,wght@0,100..900;1,100..900'),
        ],
        'mulish' => [
            'label' => 'Mulish',
            'family' => "'Mulish', {$fallback}",
            'google' => $google('Mulish:ital,wght@0,200..1000;1,200..1000'),
        ],
        'karla' => [
            'label' => 'Karla',
            'family' => "'Karla', {$fallback}",
            'google' => $google('Karla:ital,wght@0,200..800;1,200..800'),
        ],
        'lexend' => [
            'label' => 'Lexend',
            'family' => "'Lexend', {$fallback}",
            'google' => $google('Lexend:wght@100..900'),
        ],
        'public-sans' => [
            'label' => 'Public Sans',
            'family' => "'Public Sans', {$fallback}",
            'google' => $google('Public+Sans:ital,wght@0,100..900;1,100..900'),
        ],
        'sora' => [
            'label' => 'Sora',
            'family' => "'Sora', {$fallback}",
            'google' => $google('Sora:wght@100..800'),
        ],
        'urbanist' => [
            'label' => 'Urbanist',
            'family' => "'Urbanist', {$fallback}",
            'google' => $google('Urbanist:ital,wght@0,100..900;1,100..900'),
        ],
        'redhat' => [
            'label' => 'Red Hat Display',
            'family' => "'Red Hat Display', {$fallback}",
            'google' => $google('Red+Hat+Display:ital,wght@0,300..900;1,300..900'),
        ],
        'cabin' => [
            'label' => 'Cabin',
            'family' => "'Cabin', {$fallback}",
            'google' => $google('Cabin:ital,wght@0,400..700;1,400..700'),
        ],
        'barlow' => [
            'label' => 'Barlow',
            'family' => "'Barlow', {$fallback}",
            'google' => $google('Barlow:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900'),
        ],
        'josefin' => [
            'label' => 'Josefin Sans',
            'family' => "'Josefin Sans', {$fallback}",
            'google' => $google('Josefin+Sans:ital,wght@0,100..700;1,100..700'),
        ],

        // —— Serif / editorial ——
        'source-serif' => [
            'label' => 'Source Serif 4',
            'family' => "'Source Serif 4', Georgia, 'Times New Roman', serif",
            'google' => $google('Source+Serif+4:ital,opsz,wght@0,8..60,200..900;1,8..60,200..900'),
        ],
        'libre-baskerville' => [
            'label' => 'Libre Baskerville',
            'family' => "'Libre Baskerville', Georgia, 'Times New Roman', serif",
            'google' => $google('Libre+Baskerville:ital,wght@0,400;0,700;1,400'),
        ],
        'lora' => [
            'label' => 'Lora',
            'family' => "'Lora', Georgia, 'Times New Roman', serif",
            'google' => $google('Lora:ital,wght@0,400..700;1,400..700'),
        ],
        'merriweather' => [
            'label' => 'Merriweather',
            'family' => "'Merriweather', Georgia, 'Times New Roman', serif",
            'google' => $google('Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900'),
        ],
        'playfair' => [
            'label' => 'Playfair Display',
            'family' => "'Playfair Display', Georgia, 'Times New Roman', serif",
            'google' => $google('Playfair+Display:ital,wght@0,400..900;1,400..900'),
        ],
        'cormorant' => [
            'label' => 'Cormorant Garamond',
            'family' => "'Cormorant Garamond', Georgia, 'Times New Roman', serif",
            'google' => $google('Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700'),
        ],

        // —— Mono ——
        'jetbrains-mono' => [
            'label' => 'JetBrains Mono',
            'family' => "'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace",
            'google' => $google('JetBrains+Mono:ital,wght@0,100..800;1,100..800'),
        ],
        'fira-code' => [
            'label' => 'Fira Code',
            'family' => "'Fira Code', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace",
            'google' => $google('Fira+Code:wght@300..700'),
        ],
        'source-code-pro' => [
            'label' => 'Source Code Pro',
            'family' => "'Source Code Pro', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace",
            'google' => $google('Source+Code+Pro:ital,wght@0,200..900;1,200..900'),
        ],
        'ibm-plex-mono' => [
            'label' => 'IBM Plex Mono',
            'family' => "'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace",
            'google' => $google('IBM+Plex+Mono:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;1,100;1,200;1,300;1,400;1,500;1,600;1,700'),
        ],

        // —— System (no network) ——
        'system' => [
            'label' => 'System UI',
            'family' => "system-ui, {$fallback}",
            'google' => null,
        ],
    ],

];
