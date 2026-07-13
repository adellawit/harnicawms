{{-- Injected server-side theme tokens (custom mode) + body flags --}}
@php
    $t = $appTheme ?? [];
@endphp
<style id="app-theme-vars">
:root {
    --brand-primary: {{ $t['primary'] ?? '#5C9E84' }};
    --brand-primary-rgb: {{ $t['primary_rgb'] ?? '92, 158, 132' }};
    --brand-primary-600: {{ $t['primary_600'] ?? '#4A8770' }};
    --brand-primary-700: {{ $t['primary_700'] ?? '#3D7260' }};
    --brand-primary-soft: {{ $t['primary_soft'] ?? '#E8F3EE' }};
    --brand-secondary: {{ $t['secondary'] ?? '#7BB5A0' }};
    --brand-secondary-rgb: {{ $t['secondary_rgb'] ?? '123, 181, 160' }};
    --brand-secondary-600: {{ $t['secondary_600'] ?? '#6AA894' }};
    --brand-secondary-soft: {{ $t['secondary_soft'] ?? '#E8F3EE' }};
    --brand-ink: #2f3a44;
    --brand-ink-soft: #5a6672;
    --brand-surface: rgba(255, 255, 255, 0.72);
    --brand-surface-soft: rgba(255, 255, 255, 0.45);
    --brand-radius-lg: 24px;
    --brand-radius-md: 16px;
    --brand-radius-sm: 12px;
    --glass-bg: rgba(255, 255, 255, 0.72);
    --glass-border: rgba(255, 255, 255, 0.75);
    --glass-blur: 20px;
    --glass-shadow: 0 20px 50px -28px rgba(31, 41, 55, 0.28);
    --bs-primary: var(--brand-primary);
    --bs-primary-rgb: var(--brand-primary-rgb);
}
</style>
