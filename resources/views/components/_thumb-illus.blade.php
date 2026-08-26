@switch($type)
@case('pdf')
    <svg viewBox="0 0 120 96" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Dokumen PDF">
        <rect x="34" y="18" width="52" height="64" rx="6" fill="#fff" stroke="var(--thumb-line)" stroke-width="2"/>
        <path d="M70 18v14a4 4 0 0 0 4 4h12" stroke="var(--thumb-line)" stroke-width="2"/>
        <rect x="44" y="46" width="32" height="4" rx="2" fill="var(--thumb-line)"/>
        <rect x="44" y="56" width="24" height="4" rx="2" fill="var(--thumb-line)"/>
        <rect x="44" y="64" width="28" height="4" rx="2" fill="var(--thumb-line)"/>
        <rect x="40" y="70" width="24" height="12" rx="3" fill="var(--thumb-ink)"/>
        <text x="52" y="79" font-size="8" font-family="sans-serif" fill="#fff" text-anchor="middle" font-weight="700">PDF</text>
    </svg>
    @break
@case('video')
    <svg viewBox="0 0 120 96" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Video">
        <rect x="26" y="26" width="68" height="46" rx="8" fill="#fff" stroke="var(--thumb-line)" stroke-width="2"/>
        <circle cx="60" cy="49" r="14" fill="var(--thumb-ink)"/>
        <path d="M56 43l10 6-10 6z" fill="#fff"/>
    </svg>
    @break
@case('text')
    <svg viewBox="0 0 120 96" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Pesan broadcast">
        <path d="M30 30h60a6 6 0 0 1 6 6v24a6 6 0 0 1-6 6H52l-14 12v-12h-8a6 6 0 0 1-6-6V36a6 6 0 0 1 6-6z" fill="#fff" stroke="var(--thumb-line)" stroke-width="2"/>
        <rect x="38" y="42" width="44" height="4" rx="2" fill="var(--thumb-line)"/>
        <rect x="38" y="52" width="30" height="4" rx="2" fill="var(--thumb-ink)"/>
    </svg>
    @break
@case('course')
    <svg viewBox="0 0 120 96" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Pelatihan">
        <path d="M60 28l34 14-34 14-34-14z" fill="var(--thumb-ink)"/>
        <path d="M42 50v12c0 5 8 9 18 9s18-4 18-9V50" stroke="var(--thumb-line)" stroke-width="3" fill="none"/>
        <path d="M94 42v16" stroke="var(--thumb-line)" stroke-width="3" stroke-linecap="round"/>
    </svg>
    @break
@case('product')
    <svg viewBox="0 0 120 96" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Produk">
        <path d="M60 24l30 16v24L60 80 30 64V40z" fill="#fff" stroke="var(--thumb-line)" stroke-width="2"/>
        <path d="M30 40l30 16 30-16M60 56v24" stroke="var(--thumb-line)" stroke-width="2"/>
        <path d="M45 32l30 16" stroke="var(--thumb-ink)" stroke-width="2"/>
    </svg>
    @break
@default
    {{-- generic / image --}}
    <svg viewBox="0 0 120 96" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Gambar">
        <rect x="26" y="26" width="68" height="46" rx="8" fill="#fff" stroke="var(--thumb-line)" stroke-width="2"/>
        <circle cx="44" cy="42" r="6" fill="var(--thumb-ink)"/>
        <path d="M30 66l18-16 12 10 12-12 20 18" stroke="var(--thumb-line)" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
@endswitch
