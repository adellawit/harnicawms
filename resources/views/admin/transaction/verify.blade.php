<x-app-layout>
    @section('title', 'Verify Order | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Transaction', 'url' => route('transaction.index')],
                ['label' => 'Verify', 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>
        @endif

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ $order->sales_number }}</h5>
                <a href="{{ route('transaction.detail', $order->id) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-arrow-left me-1"></i>Back
                </a>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0" style="max-width: 480px;">
                    <tr><td class="text-muted" style="width:140px;">Customer</td><td>{{ $order->customer_name ?: '-' }}</td></tr>
                    <tr><td class="text-muted">Branch</td><td>{{ $order->branch?->name ?? '-' }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="ti ti-barcode me-1"></i>Scan Barcode</h6>
            </div>
            <div class="card-body">
                <form id="verifyScanForm" class="d-flex gap-2">
                    <input type="text" id="verifyScanInput" class="form-control font-monospace" maxlength="50" autocomplete="off" placeholder="Scan atau ketik nomor serial...">
                    <button type="submit" class="btn btn-primary flex-shrink-0">Tambah</button>
                </form>
                <div id="verifyScanError" class="text-danger small mt-2 d-none"></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Item</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th class="text-center">Qty</th>
                            <th>Status</th>
                            <th>Serial ter-scan</th>
                        </tr>
                    </thead>
                    <tbody id="verifyItemsBody">
                        @foreach ($order->items as $orderItem)
                            @php $row = collect($items)->firstWhere('id', $orderItem->id); @endphp
                            <tr data-item-id="{{ $orderItem->id }}">
                                <td>{{ product_print_name($orderItem->product?->name) }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format((float) $orderItem->quantity, 2, ',', '.'), '0'), ',') }} {{ $orderItem->unit?->symbol ?: $orderItem->unit?->name }}</td>
                                <td class="verify-item-status">
                                    @if (! $row['trackable'])
                                        <span class="badge bg-label-secondary">Otomatis lolos</span>
                                    @elseif ($row['complete'])
                                        <span class="badge bg-label-success">{{ $row['scanned'] }}/{{ $row['expected'] }} lengkap</span>
                                    @else
                                        <span class="badge bg-label-warning">{{ $row['scanned'] }}/{{ $row['expected'] }} discan</span>
                                    @endif
                                </td>
                                <td class="verify-item-serials">
                                    @foreach ($row['serials'] as $serial)
                                        <span class="badge bg-label-primary me-1 mb-1 d-inline-flex align-items-center gap-1">
                                            {{ $serial['serial_number'] }}
                                            <button type="button" class="btn-close btn-close-sm verify-remove-serial" style="font-size:0.6rem;" data-assignment-id="{{ $serial['assignment_id'] }}" aria-label="Hapus"></button>
                                        </span>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <form id="verifySubmitForm" method="POST" action="{{ route('transaction.verify.submit', $order->id) }}">
            @csrf
            <button type="submit" id="verifySubmitBtn" class="btn btn-success" {{ collect($items)->contains(fn ($i) => ! $i['complete']) ? 'disabled' : '' }}>
                <i class="ti ti-check me-1"></i>Submit Verifikasi
            </button>
        </form>
    </div>

    @push('page-js')
        <script>
            (function () {
                var orderId = @json($order->id);
                var scanUrl = @json(route('transaction.verify.scan', $order->id));
                var removeUrlBase = @json(url('transaction/'.$order->id.'/verify/scan'));

                function renderItems(items) {
                    items.forEach(function (item) {
                        var $row = $('tr[data-item-id="' + item.id + '"]');
                        var statusHtml;
                        if (!item.trackable) {
                            statusHtml = '<span class="badge bg-label-secondary">Otomatis lolos</span>';
                        } else if (item.complete) {
                            statusHtml = '<span class="badge bg-label-success">' + item.scanned + '/' + item.expected + ' lengkap</span>';
                        } else {
                            statusHtml = '<span class="badge bg-label-warning">' + item.scanned + '/' + item.expected + ' discan</span>';
                        }
                        $row.find('.verify-item-status').html(statusHtml);

                        var serialsHtml = item.serials.map(function (s) {
                            return '<span class="badge bg-label-primary me-1 mb-1 d-inline-flex align-items-center gap-1">'
                                + s.serial_number
                                + '<button type="button" class="btn-close btn-close-sm verify-remove-serial" style="font-size:0.6rem;" data-assignment-id="' + s.assignment_id + '" aria-label="Hapus"></button>'
                                + '</span>';
                        }).join('');
                        $row.find('.verify-item-serials').html(serialsHtml);
                    });

                    var allComplete = items.every(function (item) { return item.complete; });
                    $('#verifySubmitBtn').prop('disabled', !allComplete);
                }

                function showError(message) {
                    $('#verifyScanError').text(message).removeClass('d-none');
                }

                function clearError() {
                    $('#verifyScanError').addClass('d-none').text('');
                }

                $('#verifyScanForm').on('submit', function (e) {
                    e.preventDefault();
                    clearError();
                    var serial = $('#verifyScanInput').val().trim();
                    if (!serial) {
                        return;
                    }

                    $.ajax({
                        url: scanUrl,
                        method: 'POST',
                        data: { serial_number: serial, _token: @json(csrf_token()) },
                    }).done(function (res) {
                        $('#verifyScanInput').val('').focus();
                        renderItems(res.items);
                    }).fail(function (xhr) {
                        var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Gagal memproses barcode.';
                        showError(message);
                        $('#verifyScanInput').val('').focus();
                    });
                });

                $(document).on('click', '.verify-remove-serial', function () {
                    clearError();
                    var assignmentId = $(this).data('assignment-id');

                    $.ajax({
                        url: removeUrlBase + '/' + assignmentId,
                        method: 'DELETE',
                        data: { _token: @json(csrf_token()) },
                    }).done(function (res) {
                        renderItems(res.items);
                    }).fail(function (xhr) {
                        var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menghapus scan.';
                        showError(message);
                    });
                });

                $('#verifyScanInput').trigger('focus');
            })();
        </script>
    @endpush
</x-app-layout>
