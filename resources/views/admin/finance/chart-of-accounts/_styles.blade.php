@include('admin.finance.reports._styles')
<style>
    .coa-tree-table .coa-indent-guide {
        display: inline-block;
        width: 1.1rem;
        text-align: center;
        color: rgba(38, 43, 67, 0.28);
        font-weight: 400;
    }
    .coa-tree-table tr.coa-type-asset td:first-child {
        box-shadow: inset 3px 0 0 #696cff;
    }
    .coa-tree-table tr.coa-type-liability td:first-child {
        box-shadow: inset 3px 0 0 #ff9f43;
    }
    .coa-tree-table tr.coa-type-equity td:first-child {
        box-shadow: inset 3px 0 0 #28c76f;
    }
    .coa-tree-table tr.coa-type-revenue td:first-child {
        box-shadow: inset 3px 0 0 #00cfe8;
    }
    .coa-tree-table tr.coa-type-expense td:first-child {
        box-shadow: inset 3px 0 0 #ea5455;
    }
    .coa-tree-table tr.coa-header-row {
        background: rgba(105, 108, 255, 0.04);
    }
    .coa-tree-table tr.coa-header-row td {
        font-weight: 600;
    }
    .coa-tree-table tr.coa-inactive {
        opacity: 0.72;
    }
    .coa-type-chip {
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .02em;
        text-transform: uppercase;
        padding: .2rem .5rem;
        border-radius: .4rem;
    }
</style>
