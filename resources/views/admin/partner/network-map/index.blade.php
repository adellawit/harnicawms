<x-app-layout>
    @section('title', 'Peta Jaringan Partner | ')

    @push('vendor-css')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    @endpush

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/css/partner-network-map.css') }}?v={{ filemtime(public_path('assets/css/partner-network-map.css')) }}" />
    @endpush

    @push('vendor-js')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endpush

    @php
        $nodes = collect($mapData['nodes'] ?? []);
        $agents = $nodes->where('type', 'agent')->values();
        $resellers = $nodes->where('type', 'reseller')->values();
        $links = collect($mapData['links'] ?? []);
        $linksByAgent = $links->groupBy('agentId');
        $nodesById = $nodes->keyBy('id');
        $resellerMinZoom = $mapData['resellerMinZoom'] ?? 8;
        $assignedCount = $links->where('mode', 'assigned')->count();
        $nearestCount = $links->where('mode', 'nearest')->count();
    @endphp

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Customer'],
            ['label' => 'Network', 'url' => route('partner.reports.index')],
            ['label' => 'Peta Jaringan', 'active' => true],
        ]" />

        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="card-title mb-1">Peta Jaringan Agen &amp; Reseller</h5>
                    <p class="text-muted small mb-0">
                        Tampilan awal: <strong>Agent + area coverage</strong>.
                        Zoom ≥ {{ $resellerMinZoom }} untuk menampilkan <strong>Reseller</strong> dan garis ke agen.
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-label-primary" id="pnm-zoom-level">Zoom —</span>
                    <span class="badge bg-label-success">{{ $agents->count() }} Agent</span>
                    <span class="badge pnm-badge-reseller">{{ $resellers->count() }} Reseller</span>
                    <span class="badge bg-label-secondary">{{ $links->count() }} garis</span>
                </div>
            </div>
            <div class="card-body">
                <div class="pnm-legend mb-3">
                    <span class="pnm-legend__item"><span class="pnm-legend__dot pnm-legend__dot--agent"></span> Agen (hijau)</span>
                    <span class="pnm-legend__item"><span class="pnm-legend__dot pnm-legend__dot--reseller"></span> Reseller biru (muncul saat zoom)</span>
                    <span class="pnm-legend__item"><span class="pnm-legend__area"></span> Coverage area agen</span>
                    <span class="pnm-legend__item"><i class="ti ti-minus text-success"></i> Garis solid = assigned ({{ $assignedCount }})</span>
                    <span class="pnm-legend__item"><i class="ti ti-line-dashed text-muted"></i> Garis putus = nearest agent ({{ $nearestCount }})</span>
                    <span class="pnm-legend__item"><i class="ti ti-satellite text-muted"></i> Default: Satellite</span>
                </div>

                <div id="pnm-zoom-hint" class="alert alert-primary py-2 px-3 small mb-3" role="status">
                    <i class="ti ti-zoom-in me-1"></i>
                    Zoom in (scroll / +) hingga level {{ $resellerMinZoom }}+ untuk melihat reseller dan garis hubung ke agen.
                </div>

                <div class="pnm-layout">
                    <div id="partner-network-map"></div>

                    <aside class="pnm-sidebar">
                        <div class="pnm-sidebar__list">
                            @forelse ($agents->sortBy('code')->values() as $agent)
                                @php
                                    $agentLinks = $linksByAgent->get($agent['id'], collect());
                                    $resellerCount = $agentLinks->count();
                                @endphp
                                <div class="pnm-group" data-pnm-agent-card="{{ $agent['id'] }}">
                                    <button type="button" class="pnm-group__head" data-pnm-toggle aria-expanded="false">
                                        <span class="pnm-group__title">
                                            <i class="ti ti-briefcase"></i>
                                            <span class="pnm-group__name">{{ $agent['label'] }}</span>
                                        </span>
                                        <span class="pnm-group__meta">
                                            <span class="pnm-group__code">{{ $agent['code'] }}</span>
                                            @if (!empty($agent['city']))
                                                <span class="pnm-group__sep">·</span>
                                                <span>{{ $agent['city'] }}</span>
                                            @endif
                                            <span class="pnm-group__sep">·</span>
                                            <span>{{ $resellerCount }} reseller</span>
                                        </span>
                                        <span class="pnm-coords" id="pnm-coords-{{ $agent['id'] }}">
                                            {{ number_format((float) $agent['lat'], 5, '.', '') }}, {{ number_format((float) $agent['lng'], 5, '.', '') }}
                                        </span>
                                        @if ($resellerCount > 0)
                                            <span class="pnm-group__chevron" aria-hidden="true"><i class="ti ti-chevron-down"></i></span>
                                        @endif
                                    </button>

                                    @if ($resellerCount > 0)
                                        <ul class="pnm-group__list" hidden>
                                            @foreach ($agentLinks as $link)
                                                @php $reseller = $nodesById->get($link['resellerId']); @endphp
                                                @if ($reseller)
                                                    <li>
                                                        <div class="pnm-reseller">
                                                            <span class="pnm-reseller__name">{{ $reseller['label'] }}</span>
                                                            <span class="pnm-reseller__meta">
                                                                {{ $reseller['code'] }}
                                                                @if (($link['mode'] ?? '') === 'nearest')
                                                                    · nearest{{ isset($link['distanceKm']) ? ' '.$link['distanceKm'].' km' : '' }}
                                                                @elseif (isset($link['distanceKm']))
                                                                    · {{ $link['distanceKm'] }} km
                                                                @endif
                                                            </span>
                                                        </div>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @empty
                                <p class="text-muted small mb-0">Belum ada agent dengan koordinat (lat/long).</p>
                            @endforelse
                        </div>

                        <p class="pnm-slice-note mb-0">
                            <i class="ti ti-info-circle me-1"></i>
                            Klik kartu agent untuk melihat daftar reseller.
                            Garis putus = nearest (masih Unassigned / HQ).
                        </p>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="partner-map-data">@json($mapData)</script>

    @push('page-js')
        <script src="{{ asset('assets/js/partner-network-map.js') }}?v={{ filemtime(public_path('assets/js/partner-network-map.js')) }}"></script>
    @endpush
</x-app-layout>
