<x-app-layout>
    @section('title', 'Peta Jaringan Partner | ')

    @push('vendor-css')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    @endpush

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/css/partner-network-map.css') }}" />
    @endpush

    @push('vendor-js')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endpush

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
                    <p class="text-muted small mb-0">Slice demo — tarik marker untuk mengatur posisi. Garis menunjukkan relasi agen ke reseller.</p>
                </div>
                <span class="badge bg-label-warning">Slice / Mock Data</span>
            </div>
            <div class="card-body">
                <div class="pnm-legend mb-3">
                    <span class="pnm-legend__item"><span class="pnm-legend__dot pnm-legend__dot--agent"></span> Agen</span>
                    <span class="pnm-legend__item"><span class="pnm-legend__dot pnm-legend__dot--reseller"></span> Reseller</span>
                    <span class="pnm-legend__item"><i class="ti ti-line-dashed text-muted"></i> Garis = penugasan agen</span>
                </div>

                <div class="pnm-layout">
                    <div id="partner-network-map"></div>

                    <aside class="pnm-sidebar">
                        @php
                            $linksByAgent = collect($mapData['links'])->groupBy('agentId');
                            $nodesById = collect($mapData['nodes'])->keyBy('id');
                        @endphp

                        @foreach ($linksByAgent as $agentId => $links)
                            @php $agent = $nodesById->get($agentId); @endphp
                            @if ($agent)
                                <div class="pnm-group">
                                    <div class="pnm-group__head">
                                        <i class="ti ti-briefcase me-1"></i>{{ $agent['label'] }}
                                        <div class="pnm-coords mt-1" id="pnm-coords-{{ $agent['id'] }}"></div>
                                    </div>
                                    <ul class="pnm-group__list">
                                        @foreach ($links as $link)
                                            @php $reseller = $nodesById->get($link['resellerId']); @endphp
                                            @if ($reseller)
                                                <li>
                                                    <div>
                                                        <span>{{ $reseller['label'] }}</span>
                                                        <div class="pnm-coords" id="pnm-coords-{{ $reseller['id'] }}"></div>
                                                    </div>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach

                        <p class="pnm-slice-note mb-0">
                            <i class="ti ti-info-circle me-1"></i>
                            Contoh: <strong>Agen 1</strong> terhubung ke Reseller 1, 2, 3, dan 8.
                            Slice berikutnya: simpan koordinat ke database &amp; load data real.
                        </p>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="partner-map-data">@json($mapData)</script>

    @push('page-js')
        <script src="{{ asset('assets/js/partner-network-map.js') }}"></script>
    @endpush
</x-app-layout>
