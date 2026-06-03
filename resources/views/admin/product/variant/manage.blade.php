<x-app-layout>
    @section('title', 'Product Variants | ')
    @push('vendor-css')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => route('product.index.view')],
                ['label' => $product->name, 'url' => route('product.edit.view', $product->id)],
                ['label' => 'Variants', 'active' => true],
            ]"
        />

        <div class="card">
            <h5 class="card-header fw-bold d-flex justify-content-between align-items-center">
                Product Variants: {{ $product->name }}
                <a href="{{ route('product.edit.view', $product->id) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Back to Product
                </a>
            </h5>
            <div class="card-body">
                @php $variants = $product->variants ?? collect(); @endphp
                @if ($variants->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>SKU</th>
                                    <th>Attributes</th>
                                    <th class="text-end">Price Adj</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($variants as $v)
                                    <tr>
                                        <td>{{ $v->sku ?: '-' }}</td>
                                        <td>
                                            @foreach ($v->variantAttributes ?? [] as $va)
                                                <span class="badge bg-label-secondary me-1">{{ $va->attributeDefinition?->name ?? '-' }}: {{ $va->attributeValue?->value ?? '-' }}</span>
                                            @endforeach
                                            @if (($v->variantAttributes ?? collect())->isEmpty())
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ format_number($v->price_adjustment ?? 0, 2, true) }}</td>
                                        <td>
                                            @if ($v->is_active)
                                                <span class="badge bg-label-success">Active</span>
                                            @else
                                                <span class="badge bg-label-secondary">Inactive</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No variants defined. Define attributes first (Attribute > is_variant_attribute), then add variants via API or future UI.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
