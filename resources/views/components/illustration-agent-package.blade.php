{{-- Shared flat-style illustration (agent + package + checklist) for the agent portal.
     Used by both the agent-order login hero and the agent-order dashboard hero, so the
     visual identity carries through the whole agent journey. Brand palette only
     (#5C9E84 / #7BB5A0 / #E8F3EE / #1e3a5f) — no external assets. --}}
<div {{ $attributes->merge(['aria-hidden' => 'true']) }}>
    <svg viewBox="0 0 420 220" xmlns="http://www.w3.org/2000/svg" role="img">
        <circle cx="210" cy="112" r="98" fill="#E8F3EE" />

        <g opacity="0.55">
            <circle cx="70" cy="42" r="6" fill="#7BB5A0" />
            <circle cx="352" cy="168" r="5" fill="#5C9E84" />
            <circle cx="336" cy="34" r="4" fill="#9ECAB8" />
        </g>

        <!-- open box -->
        <path d="M140,95 L120,58 L200,95 Z" fill="#9ECAB8" />
        <path d="M260,95 L280,58 L200,95 Z" fill="#9ECAB8" />
        <rect x="140" y="95" width="120" height="85" rx="6" fill="#7BB5A0" />
        <rect x="140" y="123" width="120" height="57" rx="6" fill="#5C9E84" />
        <rect x="194" y="95" width="12" height="85" fill="#3D7260" opacity="0.35" />

        <!-- agent figure -->
        <path d="M76,180 Q76,112 106,106 Q136,112 136,180 Z" fill="#5C9E84" />
        <circle cx="106" cy="72" r="19" fill="#F2C6A0" />
        <path d="M89,66 a17,17 0 0 1 34,0 q-2,-10 -17,-10 q-15,0 -17,10 Z" fill="#3D7260" />
        <path d="M118,118 q16,4 20,14 l-10,8 q-8,-8 -16,-10 Z" fill="#4A8770" />

        <!-- clipboard -->
        <rect x="122" y="124" width="30" height="38" rx="3" fill="#ffffff" stroke="#4A8770" stroke-width="2" />
        <rect x="130" y="120" width="14" height="6" rx="2" fill="#4A8770" />
        <line x1="128" y1="136" x2="146" y2="136" stroke="#C7DED5" stroke-width="2" />
        <line x1="128" y1="144" x2="146" y2="144" stroke="#C7DED5" stroke-width="2" />
        <path d="M128,153 l4,4 l8,-8" fill="none" stroke="#5C9E84" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

        <!-- floating checklist card -->
        <g>
            <rect x="272" y="36" width="80" height="56" rx="10" fill="#ffffff" stroke="#E3F5E7" stroke-width="2" />
            <circle cx="286" cy="52" r="6" fill="#E8F3EE" />
            <path d="M283,52 l2,2 l4,-4" fill="none" stroke="#5C9E84" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <line x1="298" y1="50" x2="340" y2="50" stroke="#DCEEE5" stroke-width="3" stroke-linecap="round" />
            <circle cx="286" cy="68" r="6" fill="#E8F3EE" />
            <path d="M283,68 l2,2 l4,-4" fill="none" stroke="#5C9E84" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <line x1="298" y1="66" x2="332" y2="66" stroke="#DCEEE5" stroke-width="3" stroke-linecap="round" />
        </g>

        <!-- floating delivery badge -->
        <g>
            <circle cx="316" cy="150" r="26" fill="#5C9E84" />
            <path d="M304,152 h16 v-8 h6 l6,6 v6 h-2" fill="none" stroke="#ffffff" stroke-width="2" stroke-linejoin="round" />
            <rect x="304" y="144" width="16" height="8" rx="1.5" fill="#ffffff" />
            <circle cx="309" cy="156" r="2.6" fill="#ffffff" />
            <circle cx="320" cy="156" r="2.6" fill="#ffffff" />
        </g>
    </svg>
</div>
