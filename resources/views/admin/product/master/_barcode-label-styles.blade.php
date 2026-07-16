.preview-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5mm 2mm;
}

.preview-group {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
    background: #f8f9fa;
}

.preview-group__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 2px solid #dee2e6;
}

.preview-group__title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
}

.preview-group__badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.preview-group__info {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    font-size: 13px;
}

.preview-group__qty {
    font-weight: 600;
    color: #0d6efd;
}

.preview-group__serial {
    font-family: ui-monospace, monospace;
    font-size: 12px;
    color: #6c757d;
}

.preview-group__labels {
    background: #fff;
    padding: 12px;
    border-radius: 6px;
}

.preview-tree {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.preview-tree__node {
    position: relative;
}

.preview-tree__node--level-1,
.preview-tree__node--level-2-group,
.preview-tree__node--level-3 {
    border-radius: 8px;
    padding: 10px;
}

.preview-tree__node--level-1 {
    border: 2px dashed #0d6efd;
    background: #f0f6ff;
    width: fit-content;
    max-width: 100%;
}

.preview-tree__node--level-2-group {
    border: 1.5px dashed #6ea8fe;
    background: #f0f6ff;
}

.preview-tree__node--level-3 {
    border: 1.5px dashed #6c757d;
    background: transparent;
}

.preview-tree__cut-line {
    font-size: 11px;
    color: #6c757d;
    text-align: center;
    border-bottom: 1px dashed #ccc;
    margin: -10px -10px 8px;
    padding: 5px 8px;
    background: #e9ecef;
    border-radius: 8px 8px 0 0;
    line-height: 1.2;
}

.preview-tree__box-grid {
    display: grid;
    grid-template-columns: repeat(5, 55mm);
    gap: 1.5mm 2mm;
    justify-content: start;
    background: transparent;
    padding: 2mm;
    border-radius: 4px;
}

.preview-tree__box-groups-page .preview-tree__node--level-3 {
    margin-bottom: 10px;
}

.preview-tree__box-groups-page .preview-tree__node--level-3:last-child {
    margin-bottom: 0;
}

.preview-tree__page {
    margin-bottom: 20px;
}

.preview-tree__page + .preview-tree__page {
    border-top: 2px dashed #adb5bd;
    padding-top: 16px;
    margin-top: 8px;
}

.preview-tree__page-label {
    font-size: 10px;
    font-weight: 700;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 10px;
}

.preview-tree__page--level-1-2 {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
}

.preview-tree__pack-grid {
    display: grid;
    grid-template-columns: repeat(6, 40mm);
    gap: 1.5mm;
    justify-content: start;
}

.preview-tree__pack-grid-item-title {
    font-size: 10px;
    font-weight: 700;
    text-align: center;
    color: #444;
    margin-bottom: 3px;
    line-height: 1;
}

.preview-tree__labels {
    display: block;
}

.preview-tree__more {
    margin: 6px 0 0;
    font-size: 12px;
    color: #6c757d;
    font-style: italic;
}

.label-item {
    overflow: hidden;
}

.label-item--box {
    width: 55mm;
    height: 9mm;
    border: 0.35mm solid #000;
    background: transparent;
    box-sizing: border-box;
    overflow: hidden;
    padding: 0.5mm;
}

.label-item--box .label-item__table {
    width: 100%;
    height: 7mm;
    border-collapse: collapse;
    table-layout: fixed;
}

.label-item--box .label-item__qr {
    width: 7mm;
    height: 7mm;
    vertical-align: middle;
    text-align: center;
    padding: 0.5mm;
    box-sizing: border-box;
}

.label-item--box .label-item__qr img {
    width: 6mm;
    height: 6mm;
    display: block;
    margin: 0 auto;
}

.label-item--box .label-item__content {
    height: 7mm;
    vertical-align: middle;
    text-align: left;
    padding: 0 0.6mm 0 0.9mm;
}

.label-item--box .label-item__distributed-by {
    font-size: 4.5pt;
    font-weight: 400;
    color: #000;
    line-height: 1.1;
    white-space: nowrap;
    margin: 0;
    padding: 0;
}

.label-item--box .label-item__distributor-name {
    font-size: 6pt;
    font-weight: 700;
    color: #000;
    line-height: 1.1;
    text-transform: uppercase;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0;
    padding: 0;
}

.label-item--karton {
    width: 100mm;
    height: 80mm;
    padding: 1mm;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    border: 1px solid #999;
    background: #fff;
}

.label-item--karton .label-item__top {
    width: 100%;
    flex-shrink: 0;
}

.label-item--karton .label-item__bottom {
    width: 100%;
    margin-top: auto;
    flex-shrink: 0;
    text-align: center;
}

.label-item--pack {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    text-align: center;
    width: 40mm;
    height: 30mm;
    padding: 9% 1mm 10%;
    box-sizing: border-box;
    border: 1px solid #999;
    background: #fff;
}

.label-item--pack .label-item__top {
    width: 100%;
    flex-shrink: 0;
    line-height: 1.15;
}

.label-item--pack .label-item__bottom {
    text-align: center;
    width: 100%;
    flex-shrink: 0;
    line-height: 1.15;
    padding-top: 0;
}

.label-item--karton .label-item__logo {
    line-height: 0;
    margin-bottom: 0.3mm;
}

.label-item--karton .label-item__qr {
    width: auto;
    height: auto;
    display: block;
    margin: 0 auto;
}

.label-item--karton .label-item__logo img {
    max-width: 96mm;
    max-height: 15mm;
    object-fit: contain;
}

.label-item--karton .label-item__product {
    font-size: 17px;
    font-weight: 700;
    text-transform: uppercase;
    line-height: 1.15;
    margin-top: 0.4mm;
}

.label-item--karton .label-item__unit-qty {
    font-size: 11px;
    font-weight: 700;
    line-height: 1.15;
    margin-top: 0.4mm;
}

.label-item--karton .label-item__summary {
    font-size: 9px;
    line-height: 1.15;
    margin-top: 0.4mm;
}

.label-item--karton .label-item__qr img {
    display: block;
    margin: 0 auto;
    width: 35mm;
    height: 35mm;
}

.label-item--karton .label-item__serial {
    font-size: 10px;
    font-weight: 700;
    font-family: ui-monospace, monospace;
    line-height: 1.15;
    margin-top: 0.4mm;
}

.label-item--pack .label-item__qr {
    width: auto;
    height: auto;
    display: block;
    margin: 0 auto;
    line-height: 0;
}

.label-item--pack .label-item__product {
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    line-height: 1.15;
    margin-top: 0;
}

.label-item--pack .label-item__summary {
    font-size: 7px;
    line-height: 1.15;
    margin-top: 0.3mm;
    margin-bottom: 0;
}

.label-item--pack .label-item__qr img {
    width: 14mm;
    height: 14mm;
}

.label-item--pack .label-item__serial {
    font-size: 7px;
    font-family: ui-monospace, monospace;
    line-height: 1.1;
    margin-top: 0.4mm;
}
