/**
 * Partner Network Map — Slice
 * Draggable markers with polylines linking agents to their resellers.
 */
(function () {
    'use strict';

    function init() {
        const el = document.getElementById('partner-network-map');
        const raw = document.getElementById('partner-map-data');
        if (!el || !raw || typeof L === 'undefined') return;

        let data;
        try {
            data = JSON.parse(raw.textContent);
        } catch (e) {
            return;
        }

        const nodesById = {};
        data.nodes.forEach((n) => { nodesById[n.id] = n; });

        const map = L.map(el, { scrollWheelZoom: true }).setView(
            [data.center.lat, data.center.lng],
            data.zoom || 12
        );

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(map);

        const markers = {};
        const polylines = [];

        function makeIcon(node) {
            const isAgent = node.type === 'agent';
            const short = node.label.replace(/^(Agen|Reseller)\s*/i, '');
            const html = '<div class="pnm-marker pnm-marker--' + node.type + '" title="' + node.label + '">' + short + '</div>';
            return L.divIcon({
                className: 'pnm-marker-wrap',
                html: html,
                iconSize: isAgent ? [34, 34] : [28, 28],
                iconAnchor: isAgent ? [17, 17] : [14, 14],
            });
        }

        function updateCoordsPanel(node) {
            const panel = document.getElementById('pnm-coords-' + node.id);
            if (panel) {
                panel.textContent = node.lat.toFixed(5) + ', ' + node.lng.toFixed(5);
            }
        }

        function brandCssVar(name, fallback) {
            const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            return value || fallback;
        }

        function redrawLines() {
            polylines.forEach((line) => map.removeLayer(line));
            polylines.length = 0;

            (data.links || []).forEach((link) => {
                const agent = nodesById[link.agentId];
                const reseller = nodesById[link.resellerId];
                if (!agent || !reseller) return;

                const line = L.polyline(
                    [[agent.lat, agent.lng], [reseller.lat, reseller.lng]],
                    {
                        color: brandCssVar('--brand-primary', '#5C9E84'),
                        weight: 2.5,
                        opacity: 0.65,
                        dashArray: '6 8',
                        lineCap: 'round',
                    }
                ).addTo(map);

                polylines.push(line);
            });
        }

        data.nodes.forEach((node) => {
            const marker = L.marker([node.lat, node.lng], {
                icon: makeIcon(node),
                draggable: true,
                riseOnHover: true,
            }).addTo(map);

            marker.bindTooltip(node.label, {
                permanent: false,
                direction: 'top',
                offset: [0, -12],
                className: 'pnm-marker-tooltip',
            });

            marker.on('drag', function () {
                const pos = marker.getLatLng();
                node.lat = pos.lat;
                node.lng = pos.lng;
                redrawLines();
                updateCoordsPanel(node);
            });

            marker.on('dragend', function () {
                const pos = marker.getLatLng();
                node.lat = pos.lat;
                node.lng = pos.lng;
                redrawLines();
                updateCoordsPanel(node);
            });

            markers[node.id] = marker;
            updateCoordsPanel(node);
        });

        redrawLines();

        // Fit bounds with padding
        const bounds = L.latLngBounds(data.nodes.map((n) => [n.lat, n.lng]));
        if (bounds.isValid()) {
            map.fitBounds(bounds.pad(0.12));
        }

        setTimeout(() => map.invalidateSize(), 200);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
