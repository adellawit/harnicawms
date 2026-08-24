<x-app-layout>
    @section('title', $promo->code.' | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'CRM'],
                ['label' => 'Promotions', 'url' => route('promotions.index')],
                ['label' => $promo->code, 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-0">{{ $promo->name }}</h5>
                    <small class="text-muted">{{ $promo->code }}</small>
                </div>
                <div class="d-flex gap-2">
                    @if (($promo->promotion_type ?? 'product') === 'marketing')
                        <span class="badge bg-label-info">Marketing</span>
                    @else
                        <span class="badge bg-label-primary">Product</span>
                    @endif
                    @if($promo->is_active)
                        <span class="badge bg-label-success">Active</span>
                    @else
                        <span class="badge bg-label-secondary">Inactive</span>
                    @endif
                    <a href="{{ route('promotions.edit', $promo->id) }}" class="btn btn-sm btn-primary">Edit</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @if (($promo->promotion_type ?? 'product') === 'marketing')
                        @php
                            $targetLabel = $promo->target_type === 'agent' ? 'Agen' : ($promo->target_type === 'reseller' ? 'Reseller' : 'Agen & Reseller');
                            $agentNames = $promo->targetAgents->map(fn ($a) => trim(($a->code ? $a->code.' — ' : '').$a->name))->filter()->values();
                            $resellerNames = $promo->targetResellers->map(fn ($r) => trim(($r->code ? $r->code.' — ' : '').$r->name))->filter()->values();
                            $syarat = $promo->min_purchase_type === 'qty'
                                ? 'min '.rtrim(rtrim(number_format((float) $promo->min_purchase_value, 4, '.', ''), '0'), '.').' item'
                                : 'min Rp '.number_format((float) $promo->min_purchase_value, 0, ',', '.');
                            $diskon = $promo->discount_type === 'percent'
                                ? rtrim(rtrim(number_format((float) $promo->discount_value, 4, '.', ''), '0'), '.').'%'
                                : 'Rp '.number_format((float) $promo->discount_value, 0, ',', '.');
                        @endphp
                        <div class="col-md-4">
                            <div class="text-muted small">Target</div>
                            <div class="fw-semibold">{{ $targetLabel }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Agent</div>
                            <div class="fw-semibold">
                                {{ $agentNames->isEmpty() ? 'Semua agen' : $agentNames->implode(', ') }}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Reseller</div>
                            <div class="fw-semibold">
                                {{ $resellerNames->isEmpty() ? 'Semua reseller' : $resellerNames->implode(', ') }}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Syarat</div>
                            <div class="fw-semibold">{{ $syarat }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Diskon</div>
                            <div class="fw-semibold">{{ $diskon }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Reaktivasi reseller</div>
                            <div class="fw-semibold">{{ $promo->reactivates_reseller ? 'Ya' : 'Tidak' }}</div>
                        </div>
                    @else
                        <div class="col-md-4">
                            <div class="text-muted small">Buy</div>
                            <div class="fw-semibold">
                                Qty ≥ {{ rtrim(rtrim(number_format((float) $promo->buy_min_qty, 4, '.', ''), '0'), '.') }}
                            </div>
                            <div>{{ $promo->buyVariant?->display_name ?? $promo->buyProduct?->name ?? '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Get</div>
                            <div class="fw-semibold">
                                {{ rtrim(rtrim(number_format((float) $promo->get_qty, 4, '.', ''), '0'), '.') }}
                                ({{ $promo->get_product_mode }})
                            </div>
                            <div>
                                @if($promo->get_product_mode === 'same')
                                    Same as buy item
                                @else
                                    {{ $promo->getVariant?->display_name ?? $promo->getProduct?->name ?? '—' }}
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Free warehouse</div>
                            <div class="fw-semibold">{{ $promo->free_warehouse_type }}</div>
                        </div>
                    @endif
                    <div class="col-md-6">
                        <div class="text-muted small">Period</div>
                        <div>
                            {{ optional($promo->starts_at)->format('d/m/Y') ?? '—' }}
                            →
                            {{ optional($promo->ends_at)->format('d/m/Y') ?? '—' }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Description</div>
                        <div>{{ $promo->description ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('promotions.destroy', $promo->id) }}" onsubmit="return confirm('Delete this promotion?')">
            @csrf
            @method('DELETE')
            <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">Back</a>
            <button type="submit" class="btn btn-outline-danger">Delete</button>
        </form>
    </div>
</x-app-layout>
