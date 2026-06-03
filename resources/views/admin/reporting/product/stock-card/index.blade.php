<x-app-layout>
    @section('title', 'Stock Card | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Reporting'],
                ['label' => 'Stock Card', 'active' => true]
            ]"
        />

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Filter</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('reporting.stock-card.index') }}">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" id="selectBranch" class="form-select select2">
                                <option value="">All Branch</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @selected($branchId === $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Product <span class="text-danger">*</span></label>
                            <select name="product_id" id="selectProduct" class="form-select select2" required>
                                <option value="">Select Product</option>
                                @foreach($allProducts as $product)
                                    <option value="{{ $product->id }}" @if(request('product_id') == $product->id) selected @endif>
                                        {{ $product->sku ? $product->sku . ' - ' : '' }}{{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Variant</label>
                            <select name="variant_id" id="selectVariant" class="form-select">
                                <option value="">All Variants</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date From <span class="text-danger">*</span></label>
                            <input type="text" name="date_from" class="form-control flatpickr-date" placeholder="DD/MM/YYYY" value="{{ format_date_id($dateFrom) }}" required />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date To <span class="text-danger">*</span></label>
                            <input type="text" name="date_to" class="form-control flatpickr-date" placeholder="DD/MM/YYYY" value="{{ format_date_id($dateTo) }}" required />
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary"><i class="ti ti-search me-1"></i>Show</button>
                            <a href="{{ route('reporting.stock-card.index') }}" class="btn btn-label-dark"><i class="ti ti-x me-1"></i>Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if($selectedProduct)
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border">
                    <div class="card-body text-center">
                        <div class="text-muted mb-1">Opening Balance</div>
                        <h4 class="mb-0">{{ format_number($openingBalance, 2, true) }}</h4>
                        <small class="text-muted">{{ $unitLabel }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border border-success">
                    <div class="card-body text-center">
                        <div class="text-muted mb-1">Total In</div>
                        <h4 class="mb-0 text-success">{{ format_number($totalIn, 2, true) }}</h4>
                        <small class="text-muted">{{ $unitLabel }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border border-danger">
                    <div class="card-body text-center">
                        <div class="text-muted mb-1">Total Out</div>
                        <h4 class="mb-0 text-danger">{{ format_number($totalOut, 2, true) }}</h4>
                        <small class="text-muted">{{ $unitLabel }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border border-primary">
                    <div class="card-body text-center">
                        <div class="text-muted mb-1">Closing Balance</div>
                        <h4 class="mb-0 text-primary">{{ format_number($closingBalance, 2, true) }}</h4>
                        <small class="text-muted">{{ $unitLabel }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-1">
                    Stock Card: {{ $selectedProduct->name }}
                    @if($selectedVariant)
                        <small class="text-muted">- {{ $selectedVariant->sku }}</small>
                    @elseif($selectedProduct->sku)
                        <small class="text-muted">({{ $selectedProduct->sku }})</small>
                    @endif
                </h5>
                <p class="text-muted mb-0">Unit: {{ $unitLabel }}</p>
            </div>
            <div class="card-datatable text-nowrap table-responsive">
                <table class="table table-bordered table-hover" id="table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">No</th>
                            <th style="width:160px">Date</th>
                            <th>Variant</th>
                            <th>Mutation Type</th>
                            <th>Notes</th>
                            <th class="text-end" style="width:130px">In</th>
                            <th class="text-end" style="width:130px">Out</th>
                            <th class="text-end" style="width:140px">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $runningBalance = $openingBalance; @endphp
                        <tr class="table-secondary">
                            <td></td>
                            <td colspan="4"><strong>Opening Balance</strong></td>
                            <td></td>
                            <td></td>
                            <td class="text-end"><strong>{{ format_number($openingBalance, 2, true) }}</strong></td>
                        </tr>
                        @forelse($movements as $i => $mv)
                            @php
                                $qty = abs((float) $mv->quantity);
                                $isIn = $mv->stockMutationType
                                    ? $mv->stockMutationType->direction === 'in'
                                    : $mv->type === 'in';
                                $isOut = !$isIn;
                                $runningBalance = (float) $mv->quantity_after;

                                $variantLabel = '-';
                                if ($mv->variant) {
                                    $attrs = $mv->variant->variantAttributes?->map(fn ($va) => $va->attributeValue?->value ?? '')->filter()->implode(' / ');
                                    $variantLabel = $attrs ?: $mv->variant->sku;
                                }
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $mv->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <small class="text-muted">{{ $variantLabel }}</small>
                                </td>
                                <td>
                                    @if($mv->stockMutationType)
                                        <span class="badge bg-label-{{ $isIn ? 'success' : 'danger' }}">{{ $mv->stockMutationType->name }}</span>
                                    @else
                                        <span class="badge bg-label-{{ $mv->type === 'in' ? 'success' : 'danger' }}">{{ ucfirst($mv->type) }}</span>
                                    @endif
                                </td>
                                <td>{{ $mv->notes ?: '-' }}</td>
                                <td class="text-end text-success">{{ $isIn ? format_number($qty, 2, true) : '' }}</td>
                                <td class="text-end text-danger">{{ $isOut ? format_number($qty, 2, true) : '' }}</td>
                                <td class="text-end"><strong>{{ format_number($runningBalance, 2, true) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No stock movements found.</td></tr>
                        @endforelse
                    </tbody>
                    @if($movements->isNotEmpty())
                    <tfoot class="table-dark">
                        <tr>
                            <td colspan="5" class="text-end"><strong>Total</strong></td>
                            <td class="text-end"><strong>{{ format_number($totalIn, 2, true) }}</strong></td>
                            <td class="text-end"><strong>{{ format_number($totalOut, 2, true) }}</strong></td>
                            <td class="text-end"><strong>{{ format_number($closingBalance, 2, true) }}</strong></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
        @endif
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function() {
                var productVariants = @json($productVariantsJson);

                $('.select2').select2({ placeholder: 'Select Product', allowClear: true, width: '100%' });
                $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y', allowInput: true });

                function populateVariants(productId) {
                    var $select = $('#selectVariant');
                    $select.html('<option value="">All Variants</option>');
                    if (productId && productVariants[productId]) {
                        var variants = productVariants[productId];
                        if (variants.length > 1) {
                            variants.forEach(function(v) {
                                var selected = '{{ request("variant_id") }}' === v.id ? ' selected' : '';
                                $select.append('<option value="' + v.id + '"' + selected + '>' + v.label + ' (' + v.sku + ')</option>');
                            });
                        }
                    }
                }

                populateVariants($('#selectProduct').val());

                $('#selectProduct').on('change', function() {
                    populateVariants($(this).val());
                });
            });
        </script>
    @endpush
</x-app-layout>
