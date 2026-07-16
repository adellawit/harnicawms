{{-- Shared hierarchy tree label renderer. Include inside a <script> block that
     already declares: harnicaLogoUrl, productName, distributorName. Renders into #previewGrid. --}}
function formatBoxBrandLabel(distName) {
    var name = (distName || '').trim();
    if (name.toUpperCase().indexOf('HARNICA ') === 0) {
        return name;
    }
    return 'HARNICA ' + name;
}

function renderPreviewLabel(label, distName) {
    var type = label.label_type || 'box';
    var distHtml = $('<div>').text(distName).html();
    var productHtml = $('<div>').text(label.product_name || productName).html();
    var summaryHtml = $('<div>').text(label.content_summary || '').html();

    if (type === 'karton') {
        return '<div class="label-item label-item--karton">' +
            '<div class="label-item__top">' +
            '<div class="label-item__logo"><img src="' + harnicaLogoUrl + '" alt="Harnica"></div>' +
            '<div class="label-item__product">' + productHtml + '</div>' +
            '<div class="label-item__unit-qty">1 ' + label.unit_label + '</div>' +
            (label.content_summary ? '<div class="label-item__summary">' + summaryHtml + '</div>' : '') +
            '</div>' +
            '<div class="label-item__bottom">' +
            '<div class="label-item__qr"><img src="' + label.qr_data_uri + '" alt="Barcode"></div>' +
            '<div class="label-item__serial">' + label.serial + '</div>' +
            '</div></div>';
    }

    if (type === 'pack') {
        return '<div class="label-item label-item--pack">' +
            '<div class="label-item__top">' +
            '<div class="label-item__product">' + productHtml + '</div>' +
            (label.content_summary ? '<div class="label-item__summary">' + summaryHtml + '</div>' : '') +
            '</div>' +
            '<div class="label-item__bottom">' +
            '<div class="label-item__qr"><img src="' + label.qr_data_uri + '" alt="Barcode"></div>' +
            '<div class="label-item__serial">' + label.serial + '</div>' +
            '</div></div>';
    }

    var boxBrandHtml = $('<div>').text(formatBoxBrandLabel(distName)).html();

    return '<div class="label-item label-item--box" style="width:55mm;height:9mm;border:0.35mm solid #000;background:transparent;box-sizing:border-box;overflow:hidden;padding:0.5mm;">' +
        '<table class="label-item__table" cellpadding="0" cellspacing="0" style="width:100%;height:7mm;border-collapse:collapse;table-layout:fixed;">' +
        '<colgroup><col style="width:7mm"><col></colgroup>' +
        '<tr style="height:7mm;">' +
        '<td class="label-item__qr" valign="middle" align="center" style="width:7mm;height:7mm;padding:0.5mm;box-sizing:border-box;vertical-align:middle;text-align:center;">' +
        '<table cellpadding="0" cellspacing="0" style="width:100%;height:100%;border-collapse:collapse;"><tr>' +
        '<td align="center" valign="middle" style="width:100%;height:100%;padding:0;text-align:center;vertical-align:middle;">' +
        '<img src="' + label.qr_data_uri + '" alt="QR" style="width:6mm;height:6mm;display:block;margin:0 auto;">' +
        '</td></tr></table></td>' +
        '<td class="label-item__content" valign="middle" style="height:7mm;vertical-align:middle;text-align:left;padding:0 0.6mm 0 0.9mm;">' +
        '<div class="label-item__distributed-by" style="font-size:4.5pt;line-height:1.1;margin:0;padding:0;color:#000;">Distributed by :</div>' +
        '<div class="label-item__distributor-name" style="font-size:6pt;font-weight:700;line-height:1.1;margin:0;padding:0;color:#000;text-transform:uppercase;">' + boxBrandHtml + '</div>' +
        '</td></tr></table></div>';
}

function renderHierarchyTreePreview(tree) {
    function boxSerialRange(packNode) {
        var serials = (packNode.children || [])
            .map(function (child) {
                return child.serial || (child.label && child.label.serial) || null;
            })
            .filter(Boolean);
        if (!serials.length) {
            return '';
        }
        if (serials.length === 1) {
            return serials[0];
        }
        return serials[0] + '-' + serials[serials.length - 1];
    }

    function renderPackGroupSection(kartonNode) {
        var packs = kartonNode.children || [];
        if (!packs.length) {
            return '';
        }

        var unitLabel = $('<div>').text(packs[0].unit_label || 'Pack').html();
        var html = '<div class="preview-tree__node preview-tree__node--level-2-group">';
        html += '<div class="preview-tree__cut-line">&#9986; POTONG &mdash; ' + unitLabel.toUpperCase() + ' : 1-' + packs.length + '</div>';
        html += '<div class="preview-tree__pack-grid">';

        packs.forEach(function (packNode) {
            if (packNode.label) {
                html += '<div class="preview-tree__pack-grid-item">';
                html += '<div class="preview-tree__pack-grid-item-title">' + packNode.ordinal + ' ' + unitLabel.toUpperCase() + '</div>';
                html += renderPreviewLabel(packNode.label, distributorName);
                html += '</div>';
            }
        });

        html += '</div></div>';
        return html;
    }

    function renderLevel1And2Page(kartonNode) {
        var unitLabel = $('<div>').text(kartonNode.unit_label || 'Karton').html();
        var html = '<div class="preview-tree__page preview-tree__page--level-1-2">';
        html += '<div class="preview-tree__page-label">Halaman Level 1 &amp; 2 — Karton &amp; Pack</div>';

        html += '<div class="preview-tree__node preview-tree__node--level-1">';
        html += '<div class="preview-tree__cut-line">&#9986; POTONG &mdash; ' + kartonNode.ordinal + ' ' + unitLabel.toUpperCase() + '</div>';

        if (kartonNode.label) {
            html += '<div class="preview-tree__labels">';
            html += renderPreviewLabel(kartonNode.label, distributorName);
            html += '</div>';
        }

        if (kartonNode.hidden_children && kartonNode.hidden_children > 0) {
            html += '<p class="preview-tree__more">... dan ' + kartonNode.hidden_children + ' label lainnya akan disertakan di PDF.</p>';
        }

        html += '</div>';

        html += renderPackGroupSection(kartonNode);
        html += '</div>';
        return html;
    }

    function renderBoxGroupSection(packNode) {
        var unitLabel = $('<div>').text(packNode.unit_label || 'Pack').html();
        var boxLabels = (packNode.children || [])
            .map(function (child) { return child.label; })
            .filter(Boolean);

        if (!boxLabels.length) {
            return '';
        }

        var rangeLabel = boxSerialRange(packNode);
        var html = '<div class="preview-tree__node preview-tree__node--level-3">';
        html += '<div class="preview-tree__cut-line">&#9986; POTONG &mdash; ' + packNode.ordinal + ' ' + unitLabel.toUpperCase();
        if (rangeLabel) {
            html += ' : ' + rangeLabel;
        }
        html += '</div>';
        html += '<div class="preview-tree__box-grid">';
        boxLabels.forEach(function (label) {
            html += renderPreviewLabel(label, distributorName);
        });
        html += '</div>';

        if (packNode.hidden_children && packNode.hidden_children > 0) {
            html += '<p class="preview-tree__more">... dan ' + packNode.hidden_children + ' box lainnya akan disertakan di PDF.</p>';
        }

        html += '</div>';
        return html;
    }

    function renderLevel3Page(kartonNode) {
        var packs = kartonNode.children || [];
        var sections = packs.map(renderBoxGroupSection).filter(Boolean);

        if (!sections.length) {
            return '';
        }

        var html = '<div class="preview-tree__page preview-tree__page--level-3">';
        html += '<div class="preview-tree__page-label">Halaman Level 3 — Box</div>';
        html += '<div class="preview-tree__box-groups-page">';
        html += sections.join('');
        html += '</div></div>';
        return html;
    }

    function renderKartonNode(kartonNode) {
        return renderLevel1And2Page(kartonNode) + renderLevel3Page(kartonNode);
    }

    var html = '<div class="preview-tree">';
    tree.forEach(function (rootNode) {
        html += renderKartonNode(rootNode);
    });
    html += '</div>';

    $('#previewGrid').html(html);
}
