<style>
    .fin-report {
        --fin-accent: #696cff;
        --fin-accent-soft: rgba(105, 108, 255, 0.08);
        --fin-ok: #28c76f;
        --fin-warn: #ff9f43;
        --fin-danger: #ea5455;
        --fin-muted: #a1acb8;
    }

    .fin-toolbar {
        border: 0;
        background: linear-gradient(135deg, rgba(105, 108, 255, 0.08) 0%, rgba(40, 199, 111, 0.05) 100%);
    }

    .fin-toolbar .fin-company {
        font-size: 1.05rem;
        font-weight: 600;
        letter-spacing: -0.01em;
    }

    .fin-kpi .card {
        border: 0;
        box-shadow: 0 0.125rem 0.5rem rgba(38, 43, 67, 0.06);
        height: 100%;
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .fin-kpi .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.35rem 1rem rgba(38, 43, 67, 0.1);
    }

    .fin-kpi .fin-kpi-label {
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--fin-muted);
        font-weight: 600;
        margin-bottom: .35rem;
    }

    .fin-kpi .fin-kpi-value {
        font-size: 1.35rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
        line-height: 1.2;
    }

    .fin-kpi .fin-kpi-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: .75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .fin-section {
        border: 0;
        box-shadow: 0 0.125rem 0.5rem rgba(38, 43, 67, 0.06);
        overflow: hidden;
    }

    .fin-section .fin-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(38, 43, 67, 0.06);
        border-left: 4px solid var(--fin-accent);
        background: #fff;
    }

    .fin-section.accent-primary .fin-section-head { border-left-color: #696cff; }
    .fin-section.accent-info .fin-section-head { border-left-color: #00cfe8; }
    .fin-section.accent-success .fin-section-head { border-left-color: #28c76f; }
    .fin-section.accent-warning .fin-section-head { border-left-color: #ff9f43; }
    .fin-section.accent-danger .fin-section-head { border-left-color: #ea5455; }
    .fin-section.accent-secondary .fin-section-head { border-left-color: #8592a3; }

    .fin-section .fin-section-title {
        margin: 0;
        font-size: .95rem;
        font-weight: 700;
        letter-spacing: .02em;
        text-transform: uppercase;
    }

    .fin-section .fin-section-sub {
        font-size: .8rem;
        color: var(--fin-muted);
    }

    .fin-table {
        margin: 0;
        --bs-table-bg: transparent;
    }

    .fin-table thead th {
        font-size: .7rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--fin-muted);
        font-weight: 700;
        border-bottom-width: 1px;
        background: rgba(38, 43, 67, 0.02) !important;
        padding-top: .75rem;
        padding-bottom: .75rem;
    }

    .fin-table tbody td {
        vertical-align: middle;
        border-color: rgba(38, 43, 67, 0.05);
        padding-top: .65rem;
        padding-bottom: .65rem;
        font-variant-numeric: tabular-nums;
    }

    .fin-table tbody tr.fin-row-header {
        background: rgba(105, 108, 255, 0.04);
    }

    .fin-table tbody tr.fin-row-header td {
        font-weight: 600;
    }

    .fin-table tbody tr.fin-row-zero td {
        color: var(--fin-muted);
    }

    .fin-table tbody tr.fin-row-total {
        background: rgba(38, 43, 67, 0.03);
    }

    .fin-table tbody tr.fin-row-total td,
    .fin-table tfoot td {
        font-weight: 700;
        border-top: 1px solid rgba(38, 43, 67, 0.12);
        font-variant-numeric: tabular-nums;
    }

    .fin-table tbody tr.fin-row-grand {
        background: linear-gradient(90deg, rgba(105, 108, 255, 0.1), rgba(40, 199, 111, 0.08));
    }

    .fin-table tbody tr.fin-row-grand td {
        font-weight: 800;
        font-size: 1.02rem;
        border-top: 2px solid rgba(105, 108, 255, 0.25);
    }

    .fin-table tbody tr.fin-row-synth td {
        font-style: italic;
        background: rgba(255, 159, 67, 0.06);
    }

    .fin-account-code {
        display: inline-block;
        min-width: 3.25rem;
        font-size: .75rem;
        font-weight: 600;
        color: #696cff;
        background: rgba(105, 108, 255, 0.08);
        border-radius: .35rem;
        padding: .1rem .4rem;
        margin-right: .4rem;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    .fin-tree {
        position: relative;
        display: inline-block;
    }

    .fin-tree::before {
        content: '';
        position: absolute;
        left: -.65rem;
        top: 50%;
        width: .45rem;
        height: 1px;
        background: rgba(38, 43, 67, 0.18);
    }

    .fin-amount-wrap {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: .25rem;
        min-width: 8rem;
    }

    .fin-amount {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        letter-spacing: -0.01em;
    }

    .fin-bar {
        height: 4px;
        width: 7rem;
        max-width: 100%;
        border-radius: 99px;
        background: rgba(38, 43, 67, 0.06);
        overflow: hidden;
    }

    .fin-bar > span {
        display: block;
        height: 100%;
        border-radius: 99px;
        background: linear-gradient(90deg, #696cff, #00cfe8);
    }

    .fin-bar.is-neg > span {
        background: linear-gradient(90deg, #ea5455, #ff9f43);
    }

    .fin-status-pill {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .4rem .75rem;
        border-radius: 99px;
        font-size: .8rem;
        font-weight: 600;
    }

    .fin-gl-card .fin-gl-head {
        border-left: 4px solid #696cff;
        background: linear-gradient(90deg, rgba(105, 108, 255, 0.07), transparent);
    }

    .fin-gl-card.is-idle .fin-gl-head {
        border-left-color: #8592a3;
        background: linear-gradient(90deg, rgba(133, 146, 163, 0.08), transparent);
    }

    .fin-gl-card.is-active .fin-gl-head {
        border-left-color: #28c76f;
        background: linear-gradient(90deg, rgba(40, 199, 111, 0.08), transparent);
    }

    .fin-gl-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
    }

    .fin-gl-chip {
        font-size: .75rem;
        padding: .25rem .55rem;
        border-radius: .5rem;
        background: rgba(38, 43, 67, 0.05);
        font-variant-numeric: tabular-nums;
    }

    .fin-gl-chip strong {
        font-weight: 700;
        margin-left: .15rem;
    }

    .fin-empty {
        border: 1px dashed rgba(38, 43, 67, 0.15);
        border-radius: .75rem;
        background: rgba(38, 43, 67, 0.02);
    }

    @media print {
        .layout-menu, .layout-navbar, .btn, .modal, .content-footer, #filterModal, .d-print-none { display: none !important; }
        .layout-page, .content-wrapper, .container-xxl { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; }
        .card, .fin-section, .fin-kpi .card { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; }
        .fin-kpi .card:hover { transform: none; }
        .fin-bar { display: none !important; }
    }
</style>
