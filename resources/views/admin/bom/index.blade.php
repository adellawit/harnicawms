<x-app-layout>
    @section('title', 'Bill of Materials | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production', 'url' => route('production.index')],
                ['label' => 'Bill of Materials', 'active' => true],
            ]"
        />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h5 class="card-title mb-1">Bill of Materials</h5>
                    <small class="text-muted">
                        Manage production recipes for finished goods.
                        <strong>HPP Old</strong> = BOM baseline ·
                        <strong>HPP New</strong> = current FIFO cost.
                    </small>
                </div>
                <span class="badge bg-label-primary">{{ $variants->count() }} finished goods</span>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:120px;">Code</th>
                            <th>Finished Good</th>
                            <th style="width:90px;">Unit</th>
                            <th class="text-center" style="width:110px;">BOM Status</th>
                            <th class="text-center" style="width:100px;">Components</th>
                            <th class="text-end" style="width:130px;">HPP Old</th>
                            <th class="text-end" style="width:130px;">HPP New</th>
                            <th class="text-end" style="width:120px;">Diff</th>
                            <th class="text-end" style="width:110px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($variants as $variant)
                            @php
                                $bom = $boms->get($variant->id);
                                $cost = $bomCostSummaries[$variant->id] ?? null;
                                $diff = $cost['diff'] ?? 0;
                            @endphp
                            <tr>
                                <td>
                                    <code class="small">{{ $variant->sku ?: $variant->product?->code ?: '-' }}</code>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $variant->display_name ?? $variant->product?->name }}</div>
                                    @if ($bom?->name)
                                        <small class="text-muted">{{ $bom->name }}</small>
                                    @endif
                                </td>
                                <td>{{ $variant->product?->defaultUnit?->symbol ?: ($variant->product?->defaultUnit?->name ?? '-') }}</td>
                                <td class="text-center">
                                    @if ($bom)
                                        <span class="badge bg-label-success"><i class="ti ti-check me-1"></i>Ready</span>
                                    @else
                                        <span class="badge bg-label-secondary">Not set</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $bom ? $bom->items->count() : '—' }}</td>
                                <td class="text-end text-muted">
                                    @if ($bom && $cost)
                                        Rp {{ number_format($cost['hpp_old'], 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end fw-semibold text-primary">
                                    @if ($bom && $cost)
                                        Rp {{ number_format($cost['hpp_new'], 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end {{ $diff > 0 ? 'text-danger' : ($diff < 0 ? 'text-success' : 'text-muted') }}">
                                    @if ($bom && $cost)
                                        {{ $diff >= 0 ? '+' : '' }}Rp {{ number_format($diff, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($bom)
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-label="Actions">
                                                <i class="ti ti-dots-vertical text-primary"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('bom.show', $bom->id) }}">
                                                        <i class="ti ti-eye me-2 text-primary"></i>View
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('bom.edit', $bom->id) }}">
                                                        <i class="ti ti-pencil me-2 text-warning"></i>Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="POST" action="{{ route('bom.destroy', $bom->id) }}" onsubmit="return confirm('Delete this BOM recipe?')">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="ti ti-trash me-2 text-danger"></i>Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    @else
                                        <a href="{{ route('bom.create', ['product_variant_id' => $variant->id]) }}" class="btn btn-sm btn-primary">
                                            <i class="ti ti-plus me-1"></i>Create BOM
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="ti ti-box-off d-block mb-2" style="font-size:1.75rem;opacity:.4;"></i>
                                    No finished goods found. Add a FINISHED_GOOD product first.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('page-js')
    <script>
        document.querySelectorAll('.table-responsive .dropdown-toggle[data-bs-toggle="dropdown"]').forEach(function (toggle) {
            bootstrap.Dropdown.getOrCreateInstance(toggle, {
                popperConfig: function (defaultConfig) {
                    return Object.assign({}, defaultConfig, { strategy: 'fixed' });
                },
            });
        });
    </script>
    @endpush
</x-app-layout>
