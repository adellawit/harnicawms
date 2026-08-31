<x-app-layout>

    @section('title', 'POS Configuration | ')

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Settings', 'url' => 'javascript:void(0);'],
                ['label' => 'POS Configuration', 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="mb-1">Gudang POS</h5>
                <p class="text-muted mb-0">
                    Aktifkan gudang yang boleh dipakai kasir. Scan serial dan satuan jual mengikuti gudang yang dipilih di POS.
                </p>
            </div>
            <div class="card-body">
                @if ($warehouses->isEmpty())
                    <p class="text-muted mb-0">Tidak ada gudang aktif untuk cabang ini.</p>
                @else
                    <form method="POST" action="{{ route('settings.pos-configuration.update') }}">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Gudang</th>
                                        <th class="text-center" style="width:140px;">Bisa transaksi POS</th>
                                        <th class="text-center" style="width:150px;">Wajib scan serial</th>
                                        <th style="width:220px;">Satuan jual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($warehouses as $warehouse)
                                        @php
                                            $prefix = 'warehouses['.$warehouse->id.']';
                                            $active = (bool) old("warehouses.{$warehouse->id}.is_pos_active", $warehouse->is_pos_active);
                                            $requireScan = (bool) old("warehouses.{$warehouse->id}.pos_require_serial_scan", $warehouse->pos_require_serial_scan);
                                            $unitMode = old("warehouses.{$warehouse->id}.pos_unit_mode", $warehouse->pos_unit_mode ?: 'large_only');
                                        @endphp
                                        <tr class="pos-config-row" data-warehouse-id="{{ $warehouse->id }}">
                                            <td>
                                                <div class="fw-semibold">{{ $warehouse->name }}</div>
                                                <div class="text-muted small">
                                                    {{ $warehouse->code }}
                                                    · {{ $warehouse->warehouseType?->name ?? $warehouse->warehouse_type_code }}
                                                    @if ($warehouse->branch?->name)
                                                        · {{ $warehouse->branch->name }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <input type="hidden" name="{{ $prefix }}[is_pos_active]" value="0">
                                                <div class="form-check d-inline-flex justify-content-center">
                                                    <input class="form-check-input pos-config-active" type="checkbox"
                                                           name="{{ $prefix }}[is_pos_active]" value="1"
                                                           @checked($active)>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <input type="hidden" name="{{ $prefix }}[pos_require_serial_scan]" value="0">
                                                <div class="form-check d-inline-flex justify-content-center">
                                                    <input class="form-check-input pos-config-scan" type="checkbox"
                                                           name="{{ $prefix }}[pos_require_serial_scan]" value="1"
                                                           @checked($requireScan)>
                                                </div>
                                            </td>
                                            <td>
                                                <select name="{{ $prefix }}[pos_unit_mode]" class="form-select form-select-sm pos-config-unit">
                                                    @foreach ($unitModes as $value => $label)
                                                        <option value="{{ $value }}" @selected($unitMode === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> Simpan
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @push('page-js')
        <script>
            (function () {
                function syncRow(row) {
                    var active = row.querySelector('.pos-config-active').checked;
                    row.querySelectorAll('.pos-config-scan, .pos-config-unit').forEach(function (el) {
                        el.disabled = !active;
                    });
                }
                document.querySelectorAll('.pos-config-row').forEach(function (row) {
                    syncRow(row);
                    row.querySelector('.pos-config-active').addEventListener('change', function () {
                        syncRow(row);
                    });
                });
                var form = document.querySelector('form[action="{{ route('settings.pos-configuration.update') }}"]');
                if (form) {
                    form.addEventListener('submit', function () {
                        document.querySelectorAll('.pos-config-scan, .pos-config-unit').forEach(function (el) {
                            el.disabled = false;
                        });
                    });
                }
            })();
        </script>
    @endpush
</x-app-layout>
