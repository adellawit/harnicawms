/**
 * Location Map Picker — draggable marker → lat/lng fields.
 * Expects hidden/text inputs: #locationLatitude, #locationLongitude
 * Optional map container: #location-map-picker
 */
(function () {
    'use strict';

    var DEFAULT_CENTER = { lat: -6.9175, lng: 107.6191 }; // Bandung
    var DEFAULT_ZOOM = 13;

    function toNumber(value, fallback) {
        var n = parseFloat(value);
        return Number.isFinite(n) ? n : fallback;
    }

    function init() {
        var mapEl = document.getElementById('location-map-picker');
        var latInput = document.getElementById('locationLatitude');
        var lngInput = document.getElementById('locationLongitude');
        var statusEl = document.getElementById('location-map-status');
        var locateBtn = document.getElementById('locationLocateBtn');

        if (!mapEl || !latInput || !lngInput || typeof L === 'undefined') {
            return;
        }

        var startLat = toNumber(latInput.value, DEFAULT_CENTER.lat);
        var startLng = toNumber(lngInput.value, DEFAULT_CENTER.lng);
        var hasValue = latInput.value !== '' && lngInput.value !== '';

        var map = L.map(mapEl, { scrollWheelZoom: true }).setView([startLat, startLng], DEFAULT_ZOOM);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19,
        }).addTo(map);

        var marker = L.marker([startLat, startLng], {
            draggable: true,
            autoPan: true,
            riseOnHover: true,
        }).addTo(map);

        marker.bindTooltip('Geser untuk atur lokasi', {
            permanent: false,
            direction: 'top',
            offset: [0, -12],
        });

        function setStatus(text, isOk) {
            if (!statusEl) return;
            statusEl.textContent = text;
            statusEl.classList.toggle('is-ok', !!isOk);
            statusEl.classList.toggle('is-warn', !isOk);
        }

        function syncInputs(latlng, announce) {
            var lat = Number(latlng.lat.toFixed(8));
            var lng = Number(latlng.lng.toFixed(8));
            latInput.value = lat;
            lngInput.value = lng;
            if (announce !== false) {
                setStatus('Lokasi terpasang: ' + lat.toFixed(6) + ', ' + lng.toFixed(6), true);
            }
            latInput.dispatchEvent(new Event('change', { bubbles: true }));
            lngInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function moveTo(lat, lng, zoom) {
            var target = L.latLng(lat, lng);
            marker.setLatLng(target);
            map.setView(target, zoom || Math.max(map.getZoom(), 15));
            syncInputs(target);
        }

        marker.on('drag', function (e) {
            syncInputs(e.target.getLatLng(), false);
        });

        marker.on('dragend', function (e) {
            syncInputs(e.target.getLatLng());
        });

        map.on('click', function (e) {
            marker.setLatLng(e.latlng);
            syncInputs(e.latlng);
        });

        if (hasValue) {
            syncInputs({ lat: startLat, lng: startLng });
        } else {
            // Seed default pin so lat/lng are always present; user can drag to refine.
            syncInputs({ lat: startLat, lng: startLng });
            setStatus('Pin default Bandung. Geser marker atau klik peta untuk menyesuaikan.', false);
        }

        if (locateBtn && navigator.geolocation) {
            locateBtn.addEventListener('click', function () {
                locateBtn.disabled = true;
                setStatus('Mencari lokasi perangkat…', false);
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        locateBtn.disabled = false;
                        moveTo(pos.coords.latitude, pos.coords.longitude, 16);
                    },
                    function () {
                        locateBtn.disabled = false;
                        setStatus('Tidak bisa mengakses GPS. Geser marker secara manual.', false);
                    },
                    { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
                );
            });
        } else if (locateBtn) {
            locateBtn.style.display = 'none';
        }

        setTimeout(function () {
            map.invalidateSize();
        }, 200);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
