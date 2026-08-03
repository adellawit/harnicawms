<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modules (UI order for Account Mapping)
    |--------------------------------------------------------------------------
    */
    'modules' => [
        'general' => [
            'label' => 'General',
            'description' => 'Shared accounts used across modules (cash, bank, tax, etc.).',
            'icon' => 'ti ti-settings',
        ],
        'pos' => [
            'label' => 'POS / Sales',
            'description' => 'Sales revenue, discounts, COGS, and payment receipts.',
            'icon' => 'ti ti-shopping-cart',
        ],
        'purchasing' => [
            'label' => 'Purchasing',
            'description' => 'Purchase order related account mapping.',
            'icon' => 'ti ti-truck',
        ],
        'receive' => [
            'label' => 'Goods Receipt',
            'description' => 'Inventory receipt, GRNI clearing, and input VAT.',
            'icon' => 'ti ti-package-import',
        ],
        'invoice' => [
            'label' => 'Purchase Invoice',
            'description' => 'Supplier invoice: payables, GRNI settlement, and withholding tax.',
            'icon' => 'ti ti-file-invoice',
        ],
        'stock_opname' => [
            'label' => 'Stock Opname',
            'description' => 'Stock count variance (over / under).',
            'icon' => 'ti ti-clipboard-check',
        ],
        'stock_adjustment' => [
            'label' => 'Stock Adjustment',
            'description' => 'Manual stock adjustment gain and loss.',
            'icon' => 'ti ti-adjustments',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account mapping keys (Phase 0 — config only, no auto-post yet)
    |--------------------------------------------------------------------------
    */
    'mapping_keys' => [
        // —— General ——
        'cash' => [
            'label' => 'Cash',
            'module' => 'general',
            'expected_type' => 'asset',
            'required' => true,
        ],
        'bank' => [
            'label' => 'Bank',
            'module' => 'general',
            'expected_type' => 'asset',
            'required' => true,
        ],
        'accounts_receivable' => [
            'label' => 'Accounts Receivable',
            'module' => 'general',
            'expected_type' => 'asset',
            'required' => true,
        ],
        'accounts_payable' => [
            'label' => 'Accounts Payable',
            'module' => 'general',
            'expected_type' => 'liability',
            'required' => true,
        ],
        'inventory' => [
            'label' => 'Inventory',
            'module' => 'general',
            'expected_type' => 'asset',
            'required' => true,
        ],
        'retained_earnings' => [
            'label' => 'Retained Earnings',
            'module' => 'general',
            'expected_type' => 'equity',
            'required' => false,
        ],
        'opening_balance_equity' => [
            'label' => 'Opening Balance Equity',
            'module' => 'general',
            'expected_type' => 'equity',
            'required' => true,
        ],
        'ppn_output' => [
            'label' => 'Output VAT (PPN Keluaran)',
            'module' => 'general',
            'expected_type' => 'liability',
            'required' => true,
        ],
        'ppn_input' => [
            'label' => 'Input VAT (PPN Masukan)',
            'module' => 'general',
            'expected_type' => 'asset',
            'required' => true,
        ],
        'ppn_payable' => [
            'label' => 'VAT Payable',
            'module' => 'general',
            'expected_type' => 'liability',
            'required' => false,
        ],
        'pph21_payable' => [
            'label' => 'Withholding Tax 21 Payable',
            'module' => 'general',
            'expected_type' => 'liability',
            'required' => false,
        ],
        'pph22_payable' => [
            'label' => 'Withholding Tax 22 Payable',
            'module' => 'general',
            'expected_type' => 'liability',
            'required' => false,
        ],
        'pph23_payable' => [
            'label' => 'Withholding Tax 23 Payable',
            'module' => 'general',
            'expected_type' => 'liability',
            'required' => false,
        ],
        'pph4_2_payable' => [
            'label' => 'Final Tax 4(2) Payable',
            'module' => 'general',
            'expected_type' => 'liability',
            'required' => false,
        ],
        'pph23_prepaid' => [
            'label' => 'Withholding Tax 23 Prepaid',
            'module' => 'general',
            'expected_type' => 'asset',
            'required' => false,
        ],

        // —— POS ——
        'sales_revenue' => [
            'label' => 'Sales Revenue',
            'module' => 'pos',
            'expected_type' => 'revenue',
            'required' => true,
        ],
        'sales_discount' => [
            'label' => 'Sales Discount',
            'module' => 'pos',
            'expected_type' => 'revenue',
            'required' => false,
        ],
        'cogs' => [
            'label' => 'Cost of Goods Sold',
            'module' => 'pos',
            'expected_type' => 'expense',
            'required' => true,
        ],
        'pos_cash' => [
            'label' => 'POS Cash Receipt',
            'module' => 'pos',
            'expected_type' => 'asset',
            'required' => false,
        ],
        'pos_bank' => [
            'label' => 'POS Bank / Payment Gateway',
            'module' => 'pos',
            'expected_type' => 'asset',
            'required' => false,
        ],

        // —— Purchasing ——
        'purchase_commitment' => [
            'label' => 'PO Commitment (optional)',
            'module' => 'purchasing',
            'expected_type' => 'liability',
            'required' => false,
        ],

        // —— Receive ——
        'purchase_clearing' => [
            'label' => 'Goods Received Not Invoiced (GRNI)',
            'module' => 'receive',
            'expected_type' => 'liability',
            'required' => true,
        ],
        'receive_inventory' => [
            'label' => 'Inventory on Receipt',
            'module' => 'receive',
            'expected_type' => 'asset',
            'required' => false,
        ],

        // —— Purchase Invoice ——
        'invoice_payable' => [
            'label' => 'Accounts Payable (Invoice)',
            'module' => 'invoice',
            'expected_type' => 'liability',
            'required' => false,
        ],
        'invoice_ppn_input' => [
            'label' => 'Input VAT (Invoice)',
            'module' => 'invoice',
            'expected_type' => 'asset',
            'required' => false,
        ],
        'invoice_pph23' => [
            'label' => 'Withholding Tax 23 (Invoice)',
            'module' => 'invoice',
            'expected_type' => 'liability',
            'required' => false,
        ],

        // —— Stock Opname ——
        'stock_opname_gain' => [
            'label' => 'Stock Opname Gain',
            'module' => 'stock_opname',
            'expected_type' => 'revenue',
            'required' => false,
        ],
        'stock_opname_loss' => [
            'label' => 'Stock Opname Loss',
            'module' => 'stock_opname',
            'expected_type' => 'expense',
            'required' => false,
        ],

        // —— Stock Adjustment ——
        'stock_adjustment_gain' => [
            'label' => 'Stock Adjustment Gain',
            'module' => 'stock_adjustment',
            'expected_type' => 'revenue',
            'required' => false,
        ],
        'stock_adjustment_loss' => [
            'label' => 'Stock Adjustment Loss',
            'module' => 'stock_adjustment',
            'expected_type' => 'expense',
            'required' => false,
        ],
    ],

    'default_mapping_codes' => [
        '1101' => 'cash',
        '1102' => 'bank',
        '1103' => 'accounts_receivable',
        '1104' => 'ppn_input',
        '1105' => 'pph23_prepaid',
        '1201' => 'inventory',
        '2101' => 'accounts_payable',
        '2102' => 'ppn_output',
        '2103' => 'ppn_payable',
        '2104' => 'pph21_payable',
        '2105' => 'pph22_payable',
        '2106' => 'pph23_payable',
        '2107' => 'pph4_2_payable',
        '2108' => 'purchase_clearing',
        '4101' => 'sales_revenue',
        '4102' => 'sales_discount',
        '4201' => 'stock_adjustment_gain',
        '5101' => 'cogs',
        '5201' => 'stock_adjustment_loss',
    ],

    'default_mapping_key_codes' => [
        'pos_cash' => '1101',
        'pos_bank' => '1102',
        'receive_inventory' => '1201',
        'invoice_payable' => '2101',
        'invoice_ppn_input' => '1104',
        'invoice_pph23' => '2106',
        'stock_opname_gain' => '4201',
        'stock_opname_loss' => '5201',
    ],
];
