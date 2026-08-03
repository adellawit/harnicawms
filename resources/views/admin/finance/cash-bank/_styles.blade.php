@include('admin.finance.reports._styles')
<style>
    .cb-account-card {
        border: 0;
        box-shadow: 0 0.125rem 0.5rem rgba(38, 43, 67, 0.06);
        height: 100%;
        transition: transform .15s ease, box-shadow .15s ease;
        overflow: hidden;
    }
    .cb-account-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.45rem 1.1rem rgba(38, 43, 67, 0.12);
    }
    .cb-account-card .cb-card-accent {
        height: 4px;
        background: linear-gradient(90deg, #696cff, #00cfe8);
    }
    .cb-account-card.is-neg .cb-card-accent {
        background: linear-gradient(90deg, #ea5455, #ff9f43);
    }
    .cb-balance {
        font-size: 1.45rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
    }
    .cb-actions .btn {
        min-width: 2.25rem;
    }
    .cb-share {
        height: 6px;
        border-radius: 99px;
        background: rgba(38, 43, 67, 0.06);
        overflow: hidden;
        margin-top: .75rem;
    }
    .cb-share > span {
        display: block;
        height: 100%;
        border-radius: 99px;
        background: linear-gradient(90deg, #696cff, #28c76f);
    }
    .cb-recon-metric {
        border-radius: .85rem;
        padding: 1rem;
        background: rgba(38, 43, 67, 0.03);
        height: 100%;
    }
    .cb-recon-metric.is-ok {
        background: rgba(40, 199, 111, 0.08);
    }
    .cb-recon-metric.is-warn {
        background: rgba(255, 159, 67, 0.1);
    }
    .cb-sticky-actions {
        position: sticky;
        bottom: 1rem;
        z-index: 5;
        box-shadow: 0 0.5rem 1.25rem rgba(38, 43, 67, 0.12);
    }
    .cb-line-cleared {
        background: rgba(40, 199, 111, 0.06);
    }
</style>
