/**
 * Carton (MC) display — konversi qty PO ke breakdown satuan (Karton / Pack / Box).
 */
(function (global) {
    'use strict';

    function formatNumber(value) {
        if (value === null || value === undefined || isNaN(value)) {
            return '0';
        }
        var formatted = Number(value).toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        });
        return formatted;
    }

    function convertDirect(product, qty, fromUnitId, toUnitId) {
        var conversions = product.unit_conversions || [];
        var direct = conversions.find(function (c) {
            return c.from_unit_id === fromUnitId && c.to_unit_id === toUnitId;
        });
        if (direct) {
            return qty * parseFloat(direct.conversion_factor);
        }
        var reverse = conversions.find(function (c) {
            return c.from_unit_id === toUnitId && c.to_unit_id === fromUnitId;
        });
        if (reverse) {
            return qty / parseFloat(reverse.conversion_factor);
        }
        return null;
    }

    function convertQuantity(product, qty, fromUnitId, toUnitId) {
        if (!product || qty <= 0 || !fromUnitId || !toUnitId) {
            return null;
        }
        if (fromUnitId === toUnitId) {
            return qty;
        }

        var direct = convertDirect(product, qty, fromUnitId, toUnitId);
        if (direct !== null) {
            return Math.round(direct * 1000000) / 1000000;
        }

        var visited = {};
        var queue = [[fromUnitId, qty]];

        while (queue.length > 0) {
            var current = queue.shift();
            var unitId = current[0];
            var currentQty = current[1];

            if (unitId === toUnitId) {
                return Math.round(currentQty * 1000000) / 1000000;
            }
            if (visited[unitId]) {
                continue;
            }
            visited[unitId] = true;

            (product.unit_conversions || []).forEach(function (conv) {
                var factor = parseFloat(conv.conversion_factor);
                if (!factor || factor <= 0) {
                    return;
                }
                if (conv.from_unit_id === unitId) {
                    queue.push([conv.to_unit_id, currentQty * factor]);
                }
                if (conv.to_unit_id === unitId) {
                    queue.push([conv.from_unit_id, currentQty / factor]);
                }
            });
        }

        return null;
    }

    function getSmallestUnitId(product) {
        var conversions = (product.unit_conversions || []).slice().sort(function (a, b) {
            return (b.conversion_level || 0) - (a.conversion_level || 0);
        });
        if (conversions.length > 0) {
            return conversions[0].to_unit_id;
        }
        return product.default_unit_id;
    }

    function downstreamChain(product, defaultId, smallestId) {
        var conversions = (product.unit_conversions || []).slice().sort(function (a, b) {
            return (a.conversion_level || 0) - (b.conversion_level || 0);
        });
        var chain = [];
        var current = defaultId;

        while (current !== smallestId) {
            var conv = conversions.find(function (c) {
                return c.from_unit_id === current;
            });
            if (!conv) {
                break;
            }
            chain.push(conv.to_unit_id);
            current = conv.to_unit_id;
        }

        return chain;
    }

    function unitName(units, unitId) {
        var unit = (units || []).find(function (u) {
            return u.id === unitId;
        });
        return unit ? unit.name : 'Unit';
    }

    function formatCartonDisplay(product, quantity, unitId, units) {
        if (!product || !quantity || quantity <= 0 || !unitId) {
            return '-';
        }

        var smallestId = getSmallestUnitId(product);
        var defaultId = product.default_unit_id;
        var qtyBox = convertQuantity(product, quantity, unitId, smallestId);

        if (qtyBox === null) {
            return formatNumber(quantity) + ' ' + unitName(units, unitId);
        }

        var boxName = unitName(units, smallestId);

        if (unitId !== defaultId) {
            return formatNumber(qtyBox) + ' ' + boxName;
        }

        var chain = downstreamChain(product, defaultId, smallestId);
        if (chain.length === 0) {
            return formatNumber(qtyBox) + ' ' + boxName;
        }

        var parts = [];
        chain.forEach(function (stepUnitId) {
            var converted = convertQuantity(product, quantity, unitId, stepUnitId);
            if (converted !== null) {
                parts.push(formatNumber(converted) + ' ' + unitName(units, stepUnitId));
            }
        });

        return parts.length > 0 ? parts.join(' ') : formatNumber(qtyBox) + ' ' + boxName;
    }

    function updateRowMcDisplay($row, products, units, parseNum) {
        var productId = $row.find('.select2-product').val();
        var unitId = $row.find('.select2-unit').val();
        var qty = parseNum($row.find('.item-qty').val());
        var product = (products || []).find(function (p) {
            return p.id === productId;
        });
        var text = formatCartonDisplay(product, qty, unitId, units);
        $row.find('.item-mc-display').text(text);
    }

    global.PurchaseOrderCarton = {
        formatCartonDisplay: formatCartonDisplay,
        updateRowMcDisplay: updateRowMcDisplay,
        convertQuantity: convertQuantity,
    };
})(window);
