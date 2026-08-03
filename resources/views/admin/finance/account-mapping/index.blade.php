<x-app-layout>
    @section('title', 'Account Mapping | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        @include('admin.finance.reports._styles')
        <style>
            .am-layout {
                display: grid;
                grid-template-columns: 280px minmax(0, 1fr);
                gap: 1rem;
                align-items: start;
            }
            @media (max-width: 991.98px) {
                .am-layout { grid-template-columns: 1fr; }
            }
            .am-sidebar { position: sticky; top: 1rem; }
            .am-sidebar .list-group-item {
                border-left: 3px solid transparent;
                border-radius: 0;
                padding: 0.85rem 1rem;
            }
            .am-sidebar .list-group-item.active {
                border-left-color: #696cff;
                background: rgba(105, 108, 255, 0.08);
                color: inherit;
                font-weight: 600;
            }
            .am-sidebar .list-group-item:hover:not(.active) {
                background: rgba(105, 108, 255, 0.04);
            }
            .am-sidebar .am-module-title {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-bottom: 0.15rem;
            }
            .am-sidebar .am-module-desc {
                font-size: 0.75rem;
                color: var(--fin-muted, #a1acb8);
                line-height: 1.35;
                margin: 0;
            }
            .am-field-key {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 0.75rem;
            }
            .am-map-card {
                border: 1px solid rgba(38, 43, 67, 0.08);
                border-radius: .75rem;
                padding: 1rem;
                height: 100%;
                background: rgba(38, 43, 67, 0.015);
            }
            .am-map-card.is-filled {
                border-color: rgba(40, 199, 111, 0.35);
                background: rgba(40, 199, 111, 0.04);
            }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report" style="padding-bottom: 70px !important;">
        @php
            $hasUpdatePermission = session('permissions.Account Mapping.is_update', false) == 1
                || session('permissions.Account Mapping.is_create', false) == 1;
            $activeMeta = $groupedKeys[$activeModule]['meta'] ?? ['label' => $activeModule, 'description' => '', 'icon' => 'ti ti-folder'];
            $activeKeys = $groupedKeys[$activeModule]['keys'] ?? [];
            $accountTypeLabels = [
                'asset' => 'Asset',
                'liability' => 'Liability',
                'equity' => 'Equity',
                'revenue' => 'Revenue',
                'expense' => 'Expense',
            ];
            $selectedCompany = $companies->firstWhere('id', $companyId);
            $moduleFilled = 0;
            $moduleTotal = count($activeKeys);
            foreach ($activeKeys as $k => $_) {
                if (! empty($mappings[$k])) {
                    $moduleFilled++;
                }
            }
            $overallFilled = 0;
            $overallTotal = 0;
            foreach ($groupedKeys as $group) {
                foreach ($group['keys'] as $k => $_) {
                    $overallTotal++;
                    if (! empty($mappings[$k])) {
                        $overallFilled++;
                    }
                }
            }
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Account Mapping', 'active' => true],
        ]" />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card fin-toolbar mb-3">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="fin-kpi-icon bg-label-primary text-primary"><i class="ti ti-link"></i></div>
                    <div>
                        <div class="text-muted small mb-0">Map GL accounts per business module</div>
                        @if($selectedCompany)
                            <div class="fin-company">{{ $selectedCompany->name }}</div>
                        @endif
                    </div>
                </div>
                <form method="GET" action="{{ route('finance.account-mapping.index.view') }}" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="module" value="{{ $activeModule }}">
                    <select name="company_id" class="form-select select2" style="min-width: 220px;" onchange="this.form.submit()">
                        @forelse($companies as $c)
                            <option value="{{ $c->id }}" @selected($companyId == $c->id)>{{ $c->name }}</option>
                        @empty
                            <option value="">No company available</option>
                        @endforelse
                    </select>
                </form>
            </div>
        </div>

        @if($companyId)
            <div class="row g-3 mb-3 fin-kpi">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-kpi-label">Overall mapped</div>
                            <div class="fin-kpi-value text-primary">{{ $overallFilled }}/{{ $overallTotal }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-kpi-label">Active module</div>
                            <div class="fin-kpi-value">{{ $moduleFilled }}/{{ $moduleTotal }}</div>
                            <div class="small text-muted mt-1">{{ $activeMeta['label'] ?? $activeModule }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-kpi-label">Modules</div>
                            <div class="fin-kpi-value">{{ count($groupedKeys) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="am-layout">
                <aside class="am-sidebar">
                    <div class="card fin-section accent-secondary mb-0">
                        <div class="fin-section-head py-3">
                            <div>
                                <h5 class="fin-section-title mb-0">Modules</h5>
                            </div>
                        </div>
                        <div class="list-group list-group-flush">
                            @foreach($groupedKeys as $moduleKey => $group)
                                @php
                                    $filled = 0;
                                    foreach ($group['keys'] as $k => $_) {
                                        if (! empty($mappings[$k])) {
                                            $filled++;
                                        }
                                    }
                                    $total = count($group['keys']);
                                    $isActive = $activeModule === $moduleKey;
                                @endphp
                                <a href="{{ route('finance.account-mapping.index.view', ['company_id' => $companyId, 'module' => $moduleKey]) }}"
                                   class="list-group-item list-group-item-action {{ $isActive ? 'active' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="am-module-title">
                                            <i class="{{ $group['meta']['icon'] ?? 'ti ti-folder' }}"></i>
                                            <span>{{ $group['meta']['label'] }}</span>
                                        </div>
                                        <span class="badge {{ $filled === $total && $total > 0 ? 'bg-label-success' : ($isActive ? 'bg-primary' : 'bg-label-secondary') }}">
                                            {{ $filled }}/{{ $total }}
                                        </span>
                                    </div>
                                    @if(! empty($group['meta']['description']))
                                        <p class="am-module-desc mt-1">{{ $group['meta']['description'] }}</p>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </aside>

                <section>
                    <form method="POST" action="{{ route('finance.account-mapping.save') }}">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $companyId }}">
                        <input type="hidden" name="module" value="{{ $activeModule }}">

                        <div class="card fin-section accent-primary mb-0">
                            <div class="fin-section-head">
                                <div>
                                    <h5 class="fin-section-title">
                                        <i class="{{ $activeMeta['icon'] ?? 'ti ti-folder' }} me-1"></i>
                                        {{ $activeMeta['label'] ?? $activeModule }}
                                    </h5>
                                    @if(! empty($activeMeta['description']))
                                        <div class="fin-section-sub">{{ $activeMeta['description'] }}</div>
                                    @endif
                                </div>
                                @if($hasUpdatePermission)
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="ti ti-device-floppy me-1"></i> Save Module
                                    </button>
                                @endif
                            </div>
                            <div class="card-body">
                                @if(count($activeKeys) === 0)
                                    <div class="text-muted text-center py-4">No mapping keys for this module.</div>
                                @else
                                    <div class="row g-3">
                                        @foreach($activeKeys as $key => $meta)
                                            @php
                                                $selected = old('mappings.'.$key, $mappings[$key] ?? '');
                                                $expected = $accountTypeLabels[$meta['expected_type']] ?? ($accountTypes[$meta['expected_type']] ?? $meta['expected_type']);
                                                $isFilled = ! empty($selected);
                                            @endphp
                                            <div class="col-md-6">
                                                <div class="am-map-card {{ $isFilled ? 'is-filled' : '' }}">
                                                    <label class="form-label" for="map_{{ $key }}">
                                                        {{ $meta['label'] }}
                                                        @if(! empty($meta['required']))
                                                            <span class="text-danger">*</span>
                                                        @endif
                                                        <span class="badge bg-label-secondary ms-1">{{ $expected }}</span>
                                                    </label>
                                                    <select name="mappings[{{ $key }}]" id="map_{{ $key }}"
                                                        class="form-select select2" data-allow-clear="true"
                                                        @disabled(! $hasUpdatePermission)>
                                                        <option value="">— Not set —</option>
                                                        @foreach($accounts->where('account_type', $meta['expected_type']) as $acc)
                                                            <option value="{{ $acc->id }}" @selected($selected == $acc->id)>
                                                                {{ $acc->displayLabel() }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <div class="form-text am-field-key">{{ $key }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            @if($hasUpdatePermission && count($activeKeys) > 0)
                                <div class="card-footer d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-device-floppy me-1"></i> Save Module
                                    </button>
                                </div>
                            @endif
                        </div>
                    </form>
                </section>
            </div>
        @endif
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
    @endpush
</x-app-layout>
