/**
 * Partner Network Map
 * - Overview: agents + coverage areas only
 * - Zoom in: resellers + dashed lines to assigned agents
 * - Default basemap: satellite imagery
 */
(function () {
    'use strict';

    var RESELLER_MIN_ZOOM = 8;

    function init() {
        var el = document.getElementById('partner-network-map');
        var raw = document.getElementById('partner-map-data');
        if (!el || !raw || typeof L === 'undefined') return;

        var data;
        try {
            data = JSON.parse(raw.textContent);
        } catch (e) {
            return;
        }

        RESELLER_MIN_ZOOM = data.resellerMinZoom || 8;

        var nodesById = {};
        data.nodes.forEach(function (n) {
            nodesById[n.id] = n;
        });

        var agents = data.nodes.filter(function (n) {
            return n.type === 'agent';
        });
        var resellers = data.nodes.filter(function (n) {
            return n.type === 'reseller';
        });

        var map = L.map(el, {
            scrollWheelZoom: true,
            zoomControl: true,
        }).setView([data.center.lat, data.center.lng], data.zoom || 5);

        var satellite = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                attribution:
                    'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community',
                maxZoom: 19,
            }
        );

        var streets = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        });

        var labels = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
            {
                attribution: 'Labels &copy; Esri',
                maxZoom: 19,
                opacity: 0.9,
            }
        );

        var satelliteGroup = L.layerGroup([satellite, labels]);
        satelliteGroup.addTo(map);

        L.control
            .layers(
                {
                    Satellite: satelliteGroup,
                    Streets: streets,
                },
                null,
                { position: 'topright', collapsed: true }
            )
            .addTo(map);

        var markers = {};
        var coverageLayers = [];
        var polylines = [];
        var resellerVisible = false;

        function brandCssVar(name, fallback) {
            var value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            return value || fallback;
        }

        function makeIcon(node) {
            var isAgent = node.type === 'agent';
            var short = (node.code || node.label || '').replace(/^(AG|RS)-/i, '');
            if (short.length > 6) {
                short = (node.label || short).split(/\s+/)[0].slice(0, 6);
            }
            var html =
                '<div class="pnm-marker pnm-marker--' +
                node.type +
                '" title="' +
                escapeHtml(node.label) +
                '">' +
                escapeHtml(short) +
                '</div>';
            return L.divIcon({
                className: 'pnm-marker-wrap',
                html: html,
                iconSize: isAgent ? [36, 36] : [28, 28],
                iconAnchor: isAgent ? [18, 18] : [14, 14],
            });
        }

        function escapeHtml(str) {
            return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function updateCoordsPanel(node) {
            var panel = document.getElementById('pnm-coords-' + node.id);
            if (panel) {
                panel.textContent = node.lat.toFixed(5) + ', ' + node.lng.toFixed(5);
            }
        }

        function haversineKm(a, b) {
            var R = 6371;
            var dLat = ((b.lat - a.lat) * Math.PI) / 180;
            var dLng = ((b.lng - a.lng) * Math.PI) / 180;
            var lat1 = (a.lat * Math.PI) / 180;
            var lat2 = (b.lat * Math.PI) / 180;
            var h =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
            return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
        }

        function coverageRadiusMeters(agent) {
            var paddingKm = data.coveragePaddingKm || 15;
            var defaultKm = data.defaultCoverageKm || 40;
            var maxKm = 0;

            (data.links || []).forEach(function (link) {
                if (link.agentId !== agent.id) return;
                var reseller = nodesById[link.resellerId];
                if (!reseller) return;
                maxKm = Math.max(maxKm, haversineKm(agent, reseller));
            });

            var km = maxKm > 0 ? maxKm + paddingKm : defaultKm;
            return Math.min(Math.max(km, 20), 250) * 1000;
        }

        function drawCoverage() {
            coverageLayers.forEach(function (layer) {
                map.removeLayer(layer);
            });
            coverageLayers.length = 0;

            var fill = brandCssVar('--brand-primary', '#5C9E84');

            agents.forEach(function (agent) {
                var circle = L.circle([agent.lat, agent.lng], {
                    radius: coverageRadiusMeters(agent),
                    color: fill,
                    weight: 1.5,
                    opacity: 0.55,
                    fillColor: fill,
                    fillOpacity: 0.12,
                    interactive: false,
                    className: 'pnm-coverage',
                }).addTo(map);

                coverageLayers.push(circle);
            });
        }

        function clearLines() {
            polylines.forEach(function (line) {
                map.removeLayer(line);
            });
            polylines.length = 0;
        }

        function redrawLines() {
            clearLines();
            if (!resellerVisible) return;

            (data.links || []).forEach(function (link) {
                var agent = nodesById[link.agentId];
                var reseller = nodesById[link.resellerId];
                if (!agent || !reseller) return;
                if (!markers[link.resellerId] || !map.hasLayer(markers[link.resellerId])) return;

                var isAssigned = link.mode === 'assigned';
                var line = L.polyline(
                    [
                        [agent.lat, agent.lng],
                        [reseller.lat, reseller.lng],
                    ],
                    {
                        // Assigned: agent green; nearest: reseller blue (dashed)
                        color: isAssigned
                            ? brandCssVar('--brand-primary', '#5C9E84')
                            : '#3b82f6',
                        weight: isAssigned ? 2.5 : 2,
                        opacity: isAssigned ? 0.85 : 0.7,
                        dashArray: isAssigned ? null : '5 7',
                        lineCap: 'round',
                    }
                );

                var distLabel =
                    link.distanceKm != null ? ' · ' + link.distanceKm + ' km' : '';
                var modeLabel = isAssigned ? 'Assigned' : 'Nearest agent';
                line.bindTooltip(
                    escapeHtml(reseller.label) +
                        ' → ' +
                        escapeHtml(agent.label) +
                        '<br><small>' +
                        modeLabel +
                        distLabel +
                        '</small>',
                    {
                        sticky: true,
                        className: 'pnm-marker-tooltip',
                        opacity: 1,
                    }
                );

                line.addTo(map);
                polylines.push(line);
            });
        }

        function setResellerVisibility(show) {
            resellerVisible = show;
            resellers.forEach(function (node) {
                var marker = markers[node.id];
                if (!marker) return;
                if (show) {
                    if (!map.hasLayer(marker)) marker.addTo(map);
                } else if (map.hasLayer(marker)) {
                    map.removeLayer(marker);
                }
            });

            var hint = document.getElementById('pnm-zoom-hint');
            if (hint) {
                hint.classList.toggle('d-none', show);
            }

            redrawLines();
            updateZoomBadge();
        }

        function updateZoomBadge() {
            var badge = document.getElementById('pnm-zoom-level');
            if (badge) {
                badge.textContent = 'Zoom ' + map.getZoom();
            }
        }

        function onZoom() {
            setResellerVisibility(map.getZoom() >= RESELLER_MIN_ZOOM);
            updateZoomBadge();
        }

        agents.forEach(function (node) {
            var marker = L.marker([node.lat, node.lng], {
                icon: makeIcon(node),
                draggable: false,
                riseOnHover: true,
            }).addTo(map);

            marker.bindTooltip(
                '<strong>' +
                    escapeHtml(node.label) +
                    '</strong><br><small>Agent</small><br><span class="text-muted">' +
                    escapeHtml(node.code || '') +
                    (node.city ? ' · ' + escapeHtml(node.city) : '') +
                    '</span>',
                {
                    permanent: false,
                    direction: 'top',
                    offset: [0, -14],
                    className: 'pnm-marker-tooltip',
                    opacity: 1,
                }
            );

            markers[node.id] = marker;
            updateCoordsPanel(node);
        });

        resellers.forEach(function (node) {
            var marker = L.marker([node.lat, node.lng], {
                icon: makeIcon(node),
                draggable: false,
                riseOnHover: true,
            });

            marker.bindTooltip(
                '<strong>' +
                    escapeHtml(node.label) +
                    '</strong><br><small>Reseller</small><br><span class="text-muted">' +
                    escapeHtml(node.code || '') +
                    (node.city ? ' · ' + escapeHtml(node.city) : '') +
                    '</span>' +
                    (node.agentLabel
                        ? '<br><small>Agent: ' +
                          escapeHtml(node.agentLabel) +
                          (node.linkMode === 'nearest' ? ' (nearest)' : '') +
                          (node.linkDistanceKm != null ? ' · ' + node.linkDistanceKm + ' km' : '') +
                          '</small>'
                        : '<br><small>Belum terhubung agent</small>'),
                {
                    permanent: false,
                    direction: 'top',
                    offset: [0, -12],
                    className: 'pnm-marker-tooltip',
                    opacity: 1,
                }
            );

            markers[node.id] = marker;
        });

        drawCoverage();

        if (agents.length) {
            var bounds = L.latLngBounds(
                agents.map(function (n) {
                    return [n.lat, n.lng];
                })
            );
            if (bounds.isValid()) {
                map.fitBounds(bounds.pad(0.35), { maxZoom: 7 });
            }
        }

        map.on('zoomend', onZoom);
        onZoom();

        document.querySelectorAll('[data-pnm-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var card = btn.closest('[data-pnm-agent-card]');
                if (!card) return;
                var list = card.querySelector('.pnm-group__list');
                if (!list) return;

                var open = !card.classList.contains('is-open');
                card.classList.toggle('is-open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    list.removeAttribute('hidden');
                } else {
                    list.setAttribute('hidden', '');
                }
            });
        });

        setTimeout(function () {
            map.invalidateSize();
        }, 200);

        window.addEventListener('resize', function () {
            map.invalidateSize();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
