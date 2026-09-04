<x-app-layout>
    @section('title', 'Bill of Material | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = session('permissions.Bill of Material.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Bill of Material.is_delete', false) == 1;

            $branches = \App\Models\BusinessUnit::where('is_active', true)
                ->where('type_code', 'BRANCH')
                ->orderBy('name')
                ->get(['id', 'name']);
            $defaultBranch = auth('web')->user()->current_business_unit_id;
            $filterBranchId = request('branch_id', $defaultBranch);
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Bill of Material', 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        <div id="alertArea"></div>

        <div class="card">
            <div class="card-datatable">
                <table class="table table-bordered" id="table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">No</th>
                            <th>SKU</th>
                            <th>Product</th>
                            <th>Variant</th>
                            <th>Product Type</th>
                            <th class="text-center">Recipe</th>
                            <th class="text-end">HPP Existing</th>
                            <th class="text-end">HPP New (BOM)</th>
                            @if($hasUpdatePermission)<th class="text-center">Action</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($variants as $i => $variant)
                            @php
                                $product = $variant->product;
                                $info = $hppData[$variant->id] ?? [];
                                $hppExisting = $info['hpp_existing'] ?? null;
                                $hppNew = $info['hpp_new'] ?? null;
                                $hasBom = $info['has_bom'] ?? false;
                                $bomId = $info['bom_id'] ?? null;
                                $unitLabel = $product->defaultUnit ? ($product->defaultUnit->symbol ?: $product->defaultUnit->name) : '-';

                                $variantAttrs = $variant->variantAttributes->map(function ($va) {
                                    return $va->attributeValue?->value ?? '';
                                })->filter()->implode(' / ');

                                $diff = null;
                                if ($hppExisting !== null && $hppNew !== null && $hppExisting > 0) {
                                    $diff = $hppNew - $hppExisting;
                                }
                            @endphp
                            <tr data-variant-id="{{ $variant->id }}">
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $variant->sku ?: '-' }}</td>
                                <td>{{ $product->name ?? '-' }}</td>
                                <td>
                                    @if($hasUpdatePermission)
                                        <a href="{{ route('product.bom.manage.view', $variant->id) }}">{{ $variantAttrs ?: $variant->sku ?: 'Default' }}</a>
                                    @else
                                        {{ $variantAttrs ?: $variant->sku ?: 'Default' }}
                                    @endif
                                </td>
                                <td>{{ $product->nature->name ?? '-' }}</td>
                                <td class="text-center">
                                    @if($hasBom)
                                        <x-badge color="success">Yes</x-badge>
                                    @else
                                        <x-badge color="secondary">No</x-badge>
                                    @endif
                                </td>
                                <td class="text-end hpp-existing">
                                    @if($hppExisting !== null)
                                        {{ format_number($hppExisting, 2, true) }}
                                        <small class="text-muted">/ {{ $unitLabel }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($hppNew !== null)
                                        {{ format_number($hppNew, 2, true) }}
                                        <small class="text-muted">/ {{ $unitLabel }}</small>
                                        @if($diff !== null && $diff != 0)
                                            <br>
                                            <small class="{{ $diff > 0 ? 'text-danger' : 'text-success' }}">
                                                {{ $diff > 0 ? '+' : '' }}{{ format_number($diff, 2, true) }}
                                            </small>
                                        @endif
                                    @elseif(!$hasBom)
                                        <x-badge color="secondary">No Recipe</x-badge>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                @if($hasUpdatePermission)
                                <td class="text-center text-nowrap">
                                    @if($hasBom && $hppNew !== null && $hppNew > 0)
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-warning btn-refresh-hpp" data-variant-id="{{ $variant->id }}" title="Update HPP Existing ke HPP New">
                                            <i class="ti ti-refresh"></i>
                                        </button>
                                    @endif
                                    <a href="{{ route('product.bom.manage.view', $variant->id) }}" class="btn btn-sm btn-icon btn-outline-info" title="Manage Recipe">
                                        <i class="ti ti-clipboard-list"></i>
                                    </a>
                                    @if($hasDeletePermission && $hasBom && $bomId)
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="{{ $bomId }}" data-name="{{ $variant->display_name }}" title="Delete Recipe">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    @endif
                                </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-modal id="filterModal" title="Filter">
        <div class="mb-3">
            <label class="form-label">Branch</label>
            <select id="selectBranch" class="select2-modal form-select" data-allow-clear="true">
                <option value="">All Branch</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @if($filterBranchId === $b->id) selected @endif>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">SKU</label>
            <select id="selectSku" class="select2-modal form-select" data-allow-clear="true">
                <option value="">Semua</option>
                @foreach($variants->whereNotNull('sku')->unique('sku')->sortBy('sku') as $v)
                    <option value="{{ $v->sku }}" @if($filterSku == $v->sku) selected @endif>{{ $v->sku }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Product</label>
            <select id="selectProduct" class="select2-modal form-select" data-allow-clear="true">
                <option value="">Semua</option>
                @foreach($allProducts as $product)
                    <option value="{{ $product->id }}" @if($filterProduct == $product->id) selected @endif>{{ $product->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Product Type</label>
            <select id="selectNature" class="select2-modal form-select" data-allow-clear="true">
                <option value="">Semua</option>
                @foreach($natures as $nat)
                    <option value="{{ $nat->id }}" @if($filterNature == $nat->id) selected @endif>{{ $nat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-0">
            <label class="form-label">Recipe Status</label>
            <select id="selectBomStatus" class="form-select">
                <option value="">All</option>
                <option value="yes" @if($bomStatus=='yes') selected @endif>Has Recipe</option>
                <option value="no" @if($bomStatus=='no') selected @endif>No Recipe</option>
            </select>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn-label-dark" id="btnResetFilter">Reset</button>
            <button type="button" class="btn btn-primary" id="btnFilter" data-bs-dismiss="modal">Filter</button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-modal id="deleteModal" title="Delete Recipe" :action="route('product.bom.delete.data')" confirmText="Delete" confirmClass="btn-danger">
        <p>Are you sure you want to delete recipe for <strong id="bom-name-deleted"></strong>? All recipe items will also be removed.</p>
        <input type="hidden" id="bom-id-deleted" name="bom_id_deleted" />
    </x-confirm-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons/datatables-buttons.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(document).ready(function() {
                $('#table').DataTable({
                    paging: true,
                     
                    ordering: false,
                    info: true,
                    columnDefs: [{ orderable: false, targets: '_all' }],
                    buttons: [
                        { text: '<i class="ti ti-filter me-sm-1"></i> Filter', className: "btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }}", action: function() { $("#filterModal").modal("show"); } }
                    ]
                });
                $("div.head-label").html('<h4 class="card-title mb-0">Bill of Material</h4>');

                $('#filterModal').on('shown.bs.modal', function() {
                    $('#selectBranch, #selectSku, #selectProduct, #selectNature').each(function () {
                        if (!$(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2({
                                dropdownParent: $('#filterModal'),
                                placeholder: 'Semua',
                                allowClear: true,
                                width: '100%'
                            });
                        }
                    });
                });

                $("#btnFilter").click(function() {
                    var params = [];
                    var branchId = $('#selectBranch').val();
                    var sku = $('#selectSku').val();
                    var productId = $('#selectProduct').val();
                    var natureId = $('#selectNature').val();
                    var status = $('#selectBomStatus').val();
                    if (branchId) params.push('branch_id=' + encodeURIComponent(branchId));
                    if (sku) params.push('sku=' + encodeURIComponent(sku));
                    if (productId) params.push('product_id=' + encodeURIComponent(productId));
                    if (natureId) params.push('nature_id=' + encodeURIComponent(natureId));
                    if (status) params.push('bom_status=' + encodeURIComponent(status));
                    var url = '{{ route("product.bom.index.view") }}';
                    if (params.length > 0) url += '?' + params.join('&');
                    window.location = url;
                });
                $("#btnResetFilter").click(function() {
                    window.location = '{{ route("product.bom.index.view") }}';
                });

                $('#deleteModal').on('show.bs.modal', function(e) {
                    var b = $(e.relatedTarget);
                    $('#bom-id-deleted').val(b.data('id'));
                    $('#bom-name-deleted').text(b.data('name'));
                });

                function showAlert(type, msg) {
                    var html = '<div class="alert alert-' + type + ' alert-dismissible">' + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    $('#alertArea').html(html);
                    setTimeout(function() { $('#alertArea .alert').alert('close'); }, 4000);
                }

                $(document).on('click', '.btn-refresh-hpp', function() {
                    var btn = $(this);
                    var variantId = btn.data('variant-id');
                    btn.prop('disabled', true).html('<i class="ti ti-loader ti-spin"></i>');

                    $.ajax({
                        url: "{{ route('product.bom.refresh-hpp') }}",
                        type: 'POST',
                        data: { _token: "{{ csrf_token() }}", variant_id: variantId },
                        success: function(res) {
                            if (res.success) {
                                showAlert('success', res.message);
                                setTimeout(function() { location.reload(); }, 1000);
                            } else {
                                showAlert('danger', res.message || 'Failed.');
                                btn.prop('disabled', false).html('<i class="ti ti-refresh"></i>');
                            }
                        },
                        error: function(xhr) {
                            var msg = 'Failed to update HPP.';
                            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                            showAlert('danger', msg);
                            btn.prop('disabled', false).html('<i class="ti ti-refresh"></i>');
                        }
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
