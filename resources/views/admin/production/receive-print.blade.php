<x-app-layout>
    @section('title', 'Print Production Barcode | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production Order', 'url' => route('production.index')],
                ['label' => $order->order_number, 'url' => route('production.show', $order->id)],
                ['label' => 'Print Barcode', 'active' => true],
            ]"
        />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><small class="text-muted">Finished Good</small><div class="fw-medium">{{ $order->variant?->display_name ?? $order->product?->name }}</div></div>
                    <div class="col-md-4"><small class="text-muted">Received Qty</small><div class="fw-medium">{{ $quantity }} {{ $unit->symbol ?: $unit->name }}</div></div>
                </div>
            </div>
        </div>

        <div class="card mb-4" id="previewCard">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-0">Label Preview</h5>
                    <small class="text-muted" id="previewMeta">Loading preview...</small>
                </div>
                <span class="badge bg-label-primary" id="previewRange"></span>
            </div>
            <div class="card-body">
                <div id="previewLoading" class="text-center text-muted py-5">
                    <span class="spinner-border spinner-border-sm me-1"></span> Loading barcode preview...
                </div>
                <div class="preview-grid" id="previewGrid"></div>
            </div>
        </div>

        <form id="pdfForm">
            @csrf
            <input type="hidden" name="quantity" value="{{ $quantity }}">
            <input type="hidden" name="unit_id" value="{{ $unit->id }}">
            <input type="hidden" name="variant_id" value="{{ $order->product_variant_id }}">
            <input type="hidden" name="print_mode" value="hierarchy">
            <input type="hidden" name="include_smallest_unit" value="0">
            <input type="hidden" name="source_type" value="production_order">
            <input type="hidden" name="source_id" value="{{ $order->id }}">
            <input type="hidden" name="batch_id" id="batch_id" value="">

            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary" id="btnPdf" disabled>
                    <i class="ti ti-file-type-pdf me-1"></i> Save to PDF (A3)
                </button>
                <a href="{{ route('production.show', $order->id) }}" class="btn btn-label-secondary">Back to Production Order</a>
            </div>
        </form>
    </div>

    @push('page-css')
        <style>
            @include('admin.product.master._barcode-label-styles')
        </style>
    @endpush

    @push('page-js')
        <script>
            $(function () {
                var previewUrl = @json(route('product.print-barcode.preview', $order->product_id));
                var pdfUrl = @json(route('product.print-barcode.pdf', $order->product_id));
                var distributorName = @json($distributorName);
                var productName = @json($order->product?->name);
                var harnicaLogoUrl = @json(asset('assets/img/harnica/logo.png'));
                var productionOrderId = @json($order->id);

                @include('admin.product.master._barcode-tree-renderer')

                function loadPreview() {
                    $('#batch_id').val('');
                    $('#btnPdf').prop('disabled', true);
                    $('#previewLoading').removeClass('d-none');
                    $('#previewGrid').empty();

                    $.ajax({
                        url: previewUrl,
                        method: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
                            quantity: {{ $quantity }},
                            unit_id: @json($unit->id),
                            variant_id: @json($order->product_variant_id),
                            print_mode: 'hierarchy',
                            include_smallest_unit: 0,
                            source_type: 'production_order',
                            source_id: productionOrderId
                        },
                        success: function (res) {
                            $('#previewLoading').addClass('d-none');
                            $('#batch_id').val(res.batch_id);
                            $('#btnPdf').prop('disabled', false);

                            var breakdownArr = Object.values(res.breakdown || {});
                            var breakdownText = breakdownArr.map(function (row) {
                                return row.label + ': ' + row.qty;
                            }).join(' · ');

                            var lockedNote = res.serials_locked ? ' | Nomor terkunci ke receive ini' : '';
                            $('#previewMeta').text(breakdownText + ' | Menampilkan ' + res.displayed + ' dari ' + res.total + ' label' + lockedNote);
                            $('#previewRange').text('Total ' + res.total + ' label');

                            renderHierarchyTreePreview(res.tree || []);
                        },
                        error: function (xhr) {
                            $('#previewLoading').addClass('d-none');
                            var msg = xhr.responseJSON?.message || 'Failed to load barcode preview.';
                            $('#previewMeta').text('');
                            $('#previewGrid').html('<div class="alert alert-danger mb-0">' + $('<div>').text(msg).html() + '</div>');
                        }
                    });
                }

                $('#pdfForm').on('submit', function (e) {
                    e.preventDefault();

                    if (!$('#batch_id').val()) {
                        alert('Preview belum siap, silakan tunggu atau muat ulang halaman.');
                        return;
                    }

                    var $btn = $('#btnPdf');
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Generating...');

                    var form = $('<form>', { method: 'POST', action: pdfUrl, target: '_blank' });
                    $('#pdfForm').find('input').each(function () {
                        form.append($('<input>', { type: 'hidden', name: this.name, value: $(this).val() }));
                    });
                    $('body').append(form);
                    form.trigger('submit');
                    form.remove();

                    setTimeout(function () {
                        $btn.prop('disabled', false).html('<i class="ti ti-file-type-pdf me-1"></i> Save to PDF (A3)');
                        // Refresh preview agar batch baru memakai nomor yang sama (sudah terkunci).
                        loadPreview();
                    }, 3000);
                });

                loadPreview();
            });
        </script>
    @endpush
</x-app-layout>
