<x-app-layout>
    @section('title', 'Edit Produksi | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production', 'url' => route('production.index')],
                ['label' => 'Production In-House', 'url' => route('production.index')],
                ['label' => 'Edit', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <form method="POST" action="{{ route('production.update', $order->id) }}">
            @csrf
            @method('PUT')
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Perintah Produksi</h5></div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 p-3 rounded bg-label-primary mb-3">
                        <span class="avatar-initial rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
                            <i class="ti ti-box-seam fs-4 text-white"></i>
                        </span>
                        <div>
                            <div class="text-uppercase small text-muted mb-1">Produk Jadi</div>
                            <div class="fw-bold fs-5 mb-0">{{ $order->variant?->display_name ?? $order->product?->name }}</div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Qty Produksi <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="planned_qty" class="form-control" value="{{ old('planned_qty', (float) $order->planned_qty) }}" required>
                            <input type="hidden" name="planned_unit_id" value="{{ old('planned_unit_id', $order->output_unit_id) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Overhead (Rp)</label>
                            <input type="number" step="any" min="0" name="overhead_cost" class="form-control" value="{{ old('overhead_cost', (float) $order->overhead_cost) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tgl Produksi</label>
                            <input type="date" name="production_date" class="form-control" value="{{ old('production_date', optional($order->production_date)->toDateString()) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Expired Produk Jadi</label>
                            <input type="date" name="output_expiry_date" class="form-control" value="{{ old('output_expiry_date', optional($order->output_expiry_date)->toDateString()) }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="notes" class="form-control" value="{{ old('notes', $order->notes) }}">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan Perubahan</button>
            <a href="{{ route('production.show', $order->id) }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>
</x-app-layout>
