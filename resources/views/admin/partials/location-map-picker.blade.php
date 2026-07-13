{{-- Draggable location picker — outputs latitude / longitude --}}
@php
    $latValue = old('latitude', $latitude ?? '');
    $lngValue = old('longitude', $longitude ?? '');
@endphp

<div class="location-map-block">
    <label class="form-label">
        Lokasi Domisili (Peta) <span class="text-danger">*</span>
        <span class="info-tip" tabindex="0" data-tip="Geser marker atau klik peta untuk menentukan titik lokasi pengiriman. Koordinat Latitude & Longitude akan terisi otomatis.">?</span>
    </label>

    <div class="location-map-toolbar">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="locationLocateBtn">
            <i class="ti ti-current-location me-1"></i> Gunakan Lokasi Saya
        </button>
        <small class="text-muted">Atau geser marker / klik pada peta</small>
    </div>

    <div id="location-map-picker" class="location-map-picker" role="application" aria-label="Peta penentuan lokasi"></div>
    <div id="location-map-status" class="location-map-status"></div>

    <div class="location-coords-grid">
        <div>
            <label class="form-label" for="locationLatitude">Latitude <span class="text-danger">*</span></label>
            <input type="text" name="latitude" id="locationLatitude" class="form-control"
                   value="{{ $latValue }}" inputmode="decimal" placeholder="-6.91750000" required readonly>
            @error('latitude')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="locationLongitude">Longitude <span class="text-danger">*</span></label>
            <input type="text" name="longitude" id="locationLongitude" class="form-control"
                   value="{{ $lngValue }}" inputmode="decimal" placeholder="107.61910000" required readonly>
            @error('longitude')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

@once
    @push('vendor-css')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    @endpush

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/css/location-map-picker.css') }}" />
    @endpush

    @push('vendor-js')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endpush

    @push('page-js')
        <script src="{{ asset('assets/js/location-map-picker.js') }}"></script>
    @endpush
@endonce
