<?php

use App\Models\Accounting\AccountMapping;
use App\Models\Accounting\BeginningBalance;
use App\Models\Accounting\CashFlowCategory;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\FiscalCalendar;
use App\Models\Accounting\Journal;
use App\Models\AttributeDefinition;
use App\Models\BillOfMaterial;
use App\Models\BusinessUnit;
use App\Models\Configuration\AppThemeSetting;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Division;
use App\Models\Employees;
use App\Models\Marketing\Asset as MarketingAsset;
use App\Models\Marketing\Category as MarketingCategory;
use App\Models\MembershipPointConfiguration;
use App\Models\Menu;
use App\Models\MethodPayment;
use App\Models\Notification;
use App\Models\Parameter;
use App\Models\ParameterDetail;
use App\Models\Partner\Agent;
use App\Models\Partner\CuttingPriceConfig;
use App\Models\Partner\PartnerApplication;
use App\Models\Partner\Reseller;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductCollection;
use App\Models\ProductionOrder;
use App\Models\ProductNature;
use App\Models\ProductPriceList;
use App\Models\ProductPurchaseOrder;
use App\Models\ProductTag;
use App\Models\ProductUnit;
use App\Models\ProductVariantStock;
use App\Models\Promotion;
use App\Models\PurchaseKontrabon;
use App\Models\ReplenishmentOrder;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\ShippingRate;
use App\Models\Supplier;
use App\Models\Training\Category as TrainingCategory;
use App\Models\Training\Course;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Katalog entitas yang bisa diolah chatbot.
 *
 * writable: list/get/create/update/delete (stok/PO/jurnal/produksi/replenishment
 * lewat service + konfirmasi; posting jurnal hanya jika seimbang)
 * read_only: list/get saja (transaksi penjualan, invoice, saldo awal)
 */
return [

    'aliases' => [
        'divisi' => 'division',
        'jabatan' => 'position',
        'karyawan' => 'employee',
        'pegawai' => 'employee',
        'user' => 'employee',
        'pelanggan' => 'customer',
        'grup' => 'customer_group',
        'group' => 'customer_group',
        'pemasok' => 'supplier',
        'satuan' => 'unit',
        'kategori' => 'category',
        'tipe_produk' => 'product_type',
        'produk' => 'product',
        'item' => 'product',
        'barang' => 'product',
        'harga' => 'price_list',
        'pricelist' => 'price_list',
        'gudang' => 'warehouse',
        'holding' => 'holding',
        'perusahaan' => 'company',
        'cabang' => 'branch',
        'pembayaran' => 'method_payment',
        'ongkir' => 'shipping_rate',
        'coa' => 'chart_of_account',
        'akun' => 'chart_of_account',
        'jurnal' => 'journal',
        'stok' => 'stock',
        'penjualan' => 'sales_order',
        'transaksi' => 'sales_order',
        'po' => 'purchase_order',
        'invoice' => 'purchase_invoice',
        'kontrabon' => 'purchase_invoice',
        'bom' => 'bill_of_materials',
        'agen' => 'partner_agent',
        'reseller' => 'partner_reseller',
        'promosi' => 'promotion',
        'role' => 'role',
        'menu' => 'menu',
        'parameter' => 'parameter',
        'notifikasi' => 'notification',
        'tema' => 'theme',
        'kursus' => 'course',
        'akademi' => 'training_category',
    ],

    'entities' => [

        'division' => ['label' => 'divisi', 'menu' => 'Division', 'model' => Division::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true],
        'position' => ['label' => 'jabatan', 'menu' => 'Position', 'model' => Position::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true],
        'employee' => ['label' => 'karyawan', 'menu' => 'Employee', 'model' => Employees::class, 'search' => ['fullname', 'employee_code', 'email', 'nickname'], 'name' => 'fullname', 'writable' => true],
        'user_account' => ['label' => 'akun login', 'menu' => 'Employee', 'model' => User::class, 'search' => ['username', 'email', 'first_name', 'last_name'], 'name' => 'username', 'writable' => true, 'hidden' => ['password']],

        'customer_group' => ['label' => 'grup pelanggan', 'menu' => 'Group', 'model' => CustomerGroup::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'scope_branch' => true],
        'customer' => ['label' => 'pelanggan', 'menu' => 'List', 'model' => Customer::class, 'search' => ['name', 'code', 'phone', 'email', 'mobile'], 'name' => 'name', 'writable' => true, 'hidden' => ['password']],

        'partner_application' => ['label' => 'pengajuan partner', 'menu' => 'Partner Application', 'model' => PartnerApplication::class, 'search' => ['name', 'application_number', 'email', 'phone'], 'name' => 'name', 'writable' => true],
        'partner_agent' => ['label' => 'agen', 'menu' => 'Partner Agent', 'model' => Agent::class, 'search' => ['name', 'code', 'email', 'phone'], 'name' => 'name', 'writable' => true],
        'partner_reseller' => ['label' => 'reseller', 'menu' => 'Partner Reseller', 'model' => Reseller::class, 'search' => ['name', 'code', 'email', 'phone'], 'name' => 'name', 'writable' => true],
        'cutting_price_config' => ['label' => 'konfigurasi cutting price', 'menu' => 'Cutting Price Config', 'model' => CuttingPriceConfig::class, 'search' => ['unit_code'], 'name' => 'unit_code', 'writable' => true],

        'product_type' => ['label' => 'tipe produk', 'menu' => 'Product Type', 'model' => ProductNature::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'unit' => ['label' => 'satuan', 'menu' => 'Unit', 'model' => ProductUnit::class, 'search' => ['name', 'code', 'symbol'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'category' => ['label' => 'kategori produk', 'menu' => 'Category', 'model' => ProductCategory::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'product' => ['label' => 'produk', 'menu' => 'Items', 'model' => Product::class, 'search' => ['name', 'code', 'sku'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'price_list' => ['label' => 'daftar harga', 'menu' => 'Price Lists', 'model' => ProductPriceList::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'attribute' => ['label' => 'atribut produk', 'menu' => 'Attribute', 'model' => AttributeDefinition::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'tag' => ['label' => 'tag produk', 'menu' => 'Tag', 'model' => ProductTag::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'collection' => ['label' => 'koleksi produk', 'menu' => 'Collection', 'model' => ProductCollection::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'scope_company' => true],

        'stock' => ['label' => 'stok', 'menu' => 'Stock', 'model' => ProductVariantStock::class, 'search' => [], 'name' => 'quantity', 'writable' => true],

        'supplier' => ['label' => 'supplier', 'menu' => 'Supplier', 'model' => Supplier::class, 'search' => ['name', 'code', 'phone', 'email'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'purchase_order' => ['label' => 'purchase order', 'menu' => 'Purchase Order', 'model' => ProductPurchaseOrder::class, 'search' => ['purchase_number', 'supplier_name', 'status'], 'name' => 'purchase_number', 'writable' => true],
        'purchase_invoice' => ['label' => 'invoice / kontrabon pembelian', 'menu' => 'Invoice', 'model' => PurchaseKontrabon::class, 'search' => ['kontrabon_number', 'supplier_name', 'status'], 'name' => 'kontrabon_number', 'writable' => false],

        'bill_of_materials' => ['label' => 'bill of materials', 'menu' => 'Bill of Materials', 'model' => BillOfMaterial::class, 'search' => ['name', 'version'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'production_order' => ['label' => 'production order', 'menu' => 'Production Order', 'model' => ProductionOrder::class, 'search' => ['order_number', 'status'], 'name' => 'order_number', 'writable' => true],
        'replenishment' => ['label' => 'replenishment', 'menu' => 'Replenishment', 'model' => ReplenishmentOrder::class, 'search' => ['order_number', 'invoice_number', 'status'], 'name' => 'order_number', 'writable' => true],

        'membership' => ['label' => 'konfigurasi membership', 'menu' => 'Membership Configuration', 'model' => MembershipPointConfiguration::class, 'search' => ['name'], 'name' => 'name', 'writable' => true, 'scope_branch' => true],
        'promotion' => ['label' => 'promosi', 'menu' => 'Promotions', 'model' => Promotion::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'scope_company' => true],

        'method_payment' => ['label' => 'metode pembayaran', 'menu' => 'Method Payment', 'model' => MethodPayment::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'scope_branch' => true],
        'sales_order' => ['label' => 'transaksi penjualan', 'menu' => 'Transaction', 'model' => SalesOrder::class, 'search' => ['sales_number', 'customer_name', 'status'], 'name' => 'sales_number', 'writable' => false, 'scope_branch' => true],

        'holding' => ['label' => 'holding', 'menu' => 'Holding', 'model' => BusinessUnit::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'type_code' => 'HOLDING'],
        'company' => ['label' => 'company', 'menu' => 'Company', 'model' => BusinessUnit::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'type_code' => 'COMPANY'],
        'branch' => ['label' => 'cabang', 'menu' => 'Branch', 'model' => BusinessUnit::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'type_code' => 'BRANCH'],
        'warehouse' => ['label' => 'gudang', 'menu' => 'Warehouse', 'model' => Warehouse::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true, 'scope_company' => true],

        'role' => ['label' => 'role', 'menu' => 'Role', 'model' => Role::class, 'search' => ['name'], 'name' => 'name', 'writable' => true],
        'menu' => ['label' => 'menu', 'menu' => 'Menu', 'model' => Menu::class, 'search' => ['name', 'code', 'text_sidebar'], 'name' => 'name', 'writable' => true],
        'parameter' => ['label' => 'parameter', 'menu' => 'Parameter', 'model' => Parameter::class, 'search' => ['name', 'code'], 'name' => 'name', 'writable' => true],
        'parameter_detail' => ['label' => 'detail parameter', 'menu' => 'Parameter', 'model' => ParameterDetail::class, 'search' => ['key', 'value'], 'name' => 'value', 'writable' => true],
        'shipping_rate' => ['label' => 'ongkir', 'menu' => 'Master Ongkir', 'model' => ShippingRate::class, 'search' => ['courier_code', 'service_name', 'service_code'], 'name' => 'service_name', 'writable' => true],
        'notification' => ['label' => 'notifikasi', 'menu' => 'Notification Config', 'model' => Notification::class, 'search' => ['title', 'message', 'module'], 'name' => 'title', 'writable' => true],
        'theme' => ['label' => 'tampilan tema', 'menu' => 'Appearance & Theme', 'model' => AppThemeSetting::class, 'search' => ['primary_color'], 'name' => 'primary_color', 'writable' => true],

        'chart_of_account' => ['label' => 'akun COA', 'menu' => 'Chart of Accounts', 'model' => ChartOfAccount::class, 'search' => ['code', 'name'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'account_mapping' => ['label' => 'account mapping', 'menu' => 'Account Mapping', 'model' => AccountMapping::class, 'search' => ['mapping_key', 'description'], 'name' => 'mapping_key', 'writable' => true, 'scope_company' => true],
        'cash_flow_category' => ['label' => 'kategori arus kas', 'menu' => 'Cash Flow Category', 'model' => CashFlowCategory::class, 'search' => ['code', 'name'], 'name' => 'name', 'writable' => true],
        'fiscal_calendar' => ['label' => 'kalender fiskal', 'menu' => 'Fiscal Calendar', 'model' => FiscalCalendar::class, 'search' => ['name', 'fiscal_year'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'beginning_balance' => ['label' => 'saldo awal', 'menu' => 'Beginning Balance', 'model' => BeginningBalance::class, 'search' => ['status', 'notes'], 'name' => 'status', 'writable' => false],
        'journal' => ['label' => 'jurnal', 'menu' => 'Journal Entry', 'model' => Journal::class, 'search' => ['journal_no', 'description', 'status'], 'name' => 'journal_no', 'writable' => true],

        'training_category' => ['label' => 'kategori academy', 'menu' => 'Course', 'model' => TrainingCategory::class, 'search' => ['name'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'course' => ['label' => 'kursus', 'menu' => 'Course', 'model' => Course::class, 'search' => ['title', 'status'], 'name' => 'title', 'writable' => true, 'scope_company' => true],
        'marketing_category' => ['label' => 'kategori marketing', 'menu' => 'Marketing Category', 'model' => MarketingCategory::class, 'search' => ['name'], 'name' => 'name', 'writable' => true, 'scope_company' => true],
        'marketing_asset' => ['label' => 'aset marketing', 'menu' => 'Marketing Assets', 'model' => MarketingAsset::class, 'search' => ['title', 'type', 'status'], 'name' => 'title', 'writable' => true, 'scope_company' => true],
    ],
];
