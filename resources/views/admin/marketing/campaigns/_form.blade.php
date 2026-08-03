@php
    $isEdit = isset($campaign);
    $c = $campaign ?? null;
    $codeDisplay = $isEdit ? $c->code : ($previewCode ?? 'CMP-YYYYMM-XXXX');
@endphp

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ $isEdit ? 'Edit Campaign' : 'Campaign Baru' }}</h5>
        <p class="text-muted small mb-0 mt-1">Program promosi bertanggal; mekanik diskon mengikuti Promotion yang ditautkan.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Kode</label>
                <input type="text" class="form-control" value="{{ $codeDisplay }}" readonly>
            </div>
            <div class="col-md-5">
                <label class="form-label">Nama <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $c?->name) }}" required maxlength="255">
            </div>
            <div class="col-md-2">
                <label class="form-label">Prioritas</label>
                <input type="number" name="priority" class="form-control" value="{{ old('priority', $c?->priority ?? 0) }}" min="0">
            </div>
            <div class="col-md-2">
                <label class="form-label d-block">Aktif</label>
                <div class="form-check form-switch mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                           @checked(old('is_active', $c?->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Enabled</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Deskripsi</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description', $c?->description) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Promotion (opsional)</label>
                <select name="promotion_id" class="form-select">
                    <option value="">— Tanpa promotion —</option>
                    @foreach ($promotions as $promotion)
                        <option value="{{ $promotion->id }}" @selected(old('promotion_id', $c?->promotion_id) === $promotion->id)>
                            {{ $promotion->code }} · {{ $promotion->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mulai</label>
                <input type="text" name="starts_at" id="starts_at" class="form-control flatpickr-date"
                       value="{{ old('starts_at', optional($c?->starts_at)->format('d/m/Y')) }}"
                       placeholder="dd/mm/yyyy" autocomplete="off">
            </div>
            <div class="col-md-3">
                <label class="form-label">Selesai</label>
                <input type="text" name="ends_at" id="ends_at" class="form-control flatpickr-date"
                       value="{{ old('ends_at', optional($c?->ends_at)->format('d/m/Y')) }}"
                       placeholder="dd/mm/yyyy" autocomplete="off">
            </div>
            <div class="col-md-6">
                <label class="form-label d-block">Reaktivasi reseller</label>
                <div class="form-check form-switch mt-2">
                    <input type="hidden" name="reactivates_reseller" value="0">
                    <input class="form-check-input" type="checkbox" name="reactivates_reseller" value="1" id="reactivates_reseller"
                           @checked(old('reactivates_reseller', $c?->reactivates_reseller ?? false))>
                    <label class="form-check-label" for="reactivates_reseller">Reaktivasi reseller yang ikut campaign</label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Banner</label>
                <input type="file" name="banner" class="form-control" accept="image/*">
                @if (!empty($c?->banner_url))
                    <div class="mt-2">
                        <img src="{{ $c->banner_url }}" alt="Banner" class="rounded" style="max-height:120px">
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('page-js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof flatpickr !== 'undefined') {
            flatpickr('.flatpickr-date', { dateFormat: 'd/m/Y', allowInput: true });
        }
    });
</script>
@endpush
