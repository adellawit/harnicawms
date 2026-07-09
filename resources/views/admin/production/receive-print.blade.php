<x-app-layout>
    @section('title', 'Cetak Barcode Hasil Produksi | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Produksi'],
                ['label' => 'Production Order', 'url' => route('production.index')],
                ['label' => $order->order_number, 'url' => route('production.show', $order->id)],
                ['label' => 'Cetak Barcode', 'active' => true],
            ]"
        />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><small class="text-muted">Produk Jadi</small><div class="fw-medium">{{ $order->variant?->display_name ?? $order->product?->name }}</div></div>
                    <div class="col-md-4"><small class="text-muted">Qty Diterima</small><div class="fw-medium">{{ $quantity }} {{ $unit->symbol ?: $unit->name }}</div></div>
                </div>

                @if ($showSmallestUnitToggle)
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="includeSmallestUnit">
                        <label class="form-check-label" for="includeSmallestUnit">
                            Cetak barcode sampai ke satuan terkecil ({{ $smallestUnit->symbol ?: $smallestUnit->name }})
                        </label>
                        <div class="form-text">
                            Secara default, satuan terkecil ({{ $smallestUnit->symbol ?: $smallestUnit->name }}) tidak dicetak barcode-nya sendiri — hanya kemasan di atasnya.
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card mb-4" id="previewCard">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-0">Preview Label</h5>
                    <small class="text-muted" id="previewMeta">Memuat preview...</small>
                </div>
                <span class="badge bg-label-primary" id="previewRange"></span>
            </div>
            <div class="card-body">
                <div id="previewLoading" class="text-center text-muted py-5">
                    <span class="spinner-border spinner-border-sm me-1"></span> Memuat preview barcode...
                </div>
                <div class="preview-grid" id="previewGrid"></div>
                <p class="text-muted small mb-0 mt-3 d-none" id="previewMore"></p>
            </div>
        </div>

        <form id="pdfForm">
            @csrf
            <input type="hidden" name="quantity" value="{{ $quantity }}">
            <input type="hidden" name="unit_id" value="{{ $unit->id }}">
            <input type="hidden" name="variant_id" value="{{ $order->product_variant_id }}">
            <input type="hidden" name="print_mode" value="hierarchy">
            <input type="hidden" name="include_smallest_unit" id="includeSmallestUnitField" value="0">
            <input type="hidden" name="batch_id" id="batch_id" value="">

            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary" id="btnPdf" disabled>
                    <i class="ti ti-file-type-pdf me-1"></i> Save to PDF (A3)
                </button>
                <a href="{{ route('production.show', $order->id) }}" class="btn btn-label-secondary">Kembali ke Production Order</a>
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

                @include('admin.product.master._barcode-tree-renderer')

                function includeSmallestUnit() {
                    return $('#includeSmallestUnit').is(':checked') ? 1 : 0;
                }

                function loadPreview() {
                    $('#includeSmallestUnitField').val(includeSmallestUnit());
                    $('#batch_id').val('');
                    $('#btnPdf').prop('disabled', true);
                    $('#previewLoading').removeClass('d-none');
                    $('#previewGrid').empty();
                    $('#previewMore').addClass('d-none').text('');

                    $.ajax({
                        url: previewUrl,
                        method: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
                            quantity: {{ $quantity }},
                            unit_id: @json($unit->id),
                            variant_id: @json($order->product_variant_id),
                            print_mode: 'hierarchy',
                            include_smallest_unit: includeSmallestUnit()
                        },
                        success: function (res) {
                            $('#previewLoading').addClass('d-none');
                            $('#batch_id').val(res.batch_id);
                            $('#btnPdf').prop('disabled', false);

                            var breakdownArr = Object.values(res.breakdown || {});
                            var breakdownText = breakdownArr.map(function (row) {
                                return row.label + ': ' + row.qty;
                            }).join(' · ');

                            $('#previewMeta').text(breakdownText + ' | Menampilkan ' + res.displayed + ' dari ' + res.total + ' label');
                            $('#previewRange').text('Total ' + res.total + ' label');

                            renderHierarchyTreePreview(res.tree || []);

                            if (res.hidden && res.hidden > 0) {
                                $('#previewMore').removeClass('d-none').text(
                                    '... dan ' + res.hidden + ' label lainnya akan disertakan di PDF (urutan Karton → Pack → Box sesuai nomor seri).'
                                );
                            }
                        },
                        error: function (xhr) {
                            $('#previewLoading').addClass('d-none');
                            var msg = xhr.responseJSON?.message || 'Gagal memuat preview barcode.';
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
                    }, 3000);
                });

                $('#includeSmallestUnit').on('change', loadPreview);

                loadPreview();
            });
        </script>
    @endpush
</x-app-layout>
