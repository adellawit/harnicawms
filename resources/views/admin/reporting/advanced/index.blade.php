<x-app-layout>
    @section('title', $title . ' | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :title="$title"
            :subtitle="$subtitle"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Reporting'],
                ['label' => $title, 'active' => true],
            ]"
        />

        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pb-0">
                <h6 class="mb-0 fw-bold"><i class="ti ti-filter me-1"></i> Filter</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route($routeName) }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label small text-muted">Branch</label>
                            <select name="branch_id" class="form-select select2">
                                <option value="">All Branch</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @selected($branchId === $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small text-muted">Date From</label>
                            <input type="text" name="date_from" class="form-control flatpickr-date" value="{{ format_date_id($dateFrom) }}" required>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small text-muted">Date To</label>
                            <input type="text" name="date_to" class="form-control flatpickr-date" value="{{ format_date_id($dateTo) }}" required>
                        </div>
                        <div class="col-lg-2 col-md-6 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1"><i class="ti ti-search me-1"></i>Show</button>
                            <a href="{{ route($routeName) }}" class="btn btn-label-dark"><i class="ti ti-x"></i></a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if(!empty($cards))
            <div class="row g-3 mb-4">
                @foreach($cards as $card)
                    <div class="col-xl-3 col-md-6">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body">
                                <p class="text-muted mb-1">{{ $card['label'] }}</p>
                                <h4 class="mb-0">
                                    @if(($card['type'] ?? 'text') === 'currency')
                                        Rp {{ number_format((float) ($card['value'] ?? 0), 0, ',', '.') }}
                                    @elseif(($card['type'] ?? 'text') === 'percent')
                                        {{ number_format((float) ($card['value'] ?? 0), 2, ',', '.') }}%
                                    @elseif(($card['type'] ?? 'text') === 'number')
                                        {{ format_number((float) ($card['value'] ?? 0), 0, true) }}
                                    @else
                                        {{ $card['value'] ?? '-' }}
                                    @endif
                                </h4>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0">
                <h6 class="mb-0 fw-bold">Data</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            @foreach($columns as $label)
                                <th>{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                @foreach(array_keys($columns) as $key)
                                    @php $v = $row[$key] ?? null; @endphp
                                    <td>
                                        @if($v !== null && ($key === 'period' || str_contains($key, 'date') || preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $v)))
                                            {{ format_date_id($v) }}
                                        @elseif(is_numeric($v) && (str_contains($key, 'revenue') || str_contains($key, 'purchase') || str_contains($key, 'value') || str_contains($key, 'price') || str_contains($key, 'cost') || str_contains($key, 'profit') || str_contains($key, 'total')))
                                            Rp {{ number_format((float) $v, 0, ',', '.') }}
                                        @elseif(is_numeric($v))
                                            {{ format_number((float) $v, 2, true) }}
                                        @else
                                            {{ $v ?? '-' }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) }}" class="text-center text-muted py-4">No data found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush

    @push('page-js')
        <script>
            $(function () {
                $('.select2').select2({ theme: 'bootstrap-5', allowClear: true, width: '100%' });
                $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y', allowInput: true });
            });
        </script>
    @endpush
</x-app-layout>

