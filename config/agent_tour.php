<?php

/**
 * Peta "ruangan" admin untuk tool guide_tour.
 *
 * Selector dan langkah tur — bukan sumber fakta produk.
 * Narasi fitur tetap lewat search_docs (docs_query).
 * `voice` = 1–2 kalimat Bahasa Indonesia, in-character Titanie, untuk Web Speech API
 * di overlay (opsional, default mati). Jangan markdown/emoji.
 */
return [

    /*
    | Urutan tur utama (HR → produk → stok → POS → purchasing → distribusi
    | → accounting → partner → settings, plus dashboard & customer).
    */
    'tour' => [
        'dashboard',
        'hr',
        'customer',
        'product',
        'stock',
        'purchasing',
        'pos',
        'distribution',
        'partner',
        'accounting',
        'settings',
    ],

    'heading_selector' => '.content-wrapper .head-label h4, .content-wrapper h4.card-title, .content-wrapper .card-header, .content-wrapper h4, .content-wrapper h5, #layout-navbar',

    /*
    | Tur keliling modul: setelah sidebar parent, paling banyak N spot UI
    | di landing page. Submenu lengkap hanya di mode page-deep ("Apa sih ini?").
    */
    'overview_page_spot_keys' => ['title', 'add'],
    'overview_page_spot_limit' => 2,
    'page_deep_spot_limit' => 6,
    'page_deep_child_limit' => 6,

    /*
    | Spotlight UI dalam halaman. Selector dari Blade yang hidup:
    | judul DataTables `.head-label h4`, tombol `.create-new` / ikon plus,
    | filter ikon `.ti-filter`, tabel `#table` / `.card-datatable`,
    | form `#postForm` + `#btn-submit`.
    */
    'page_spots' => [
        'index' => [
            [
                'key' => 'title',
                'label' => 'Judul halaman',
                'blurb' => 'Ini judul halaman. Kamu sedang di daftar data modul ini.',
                'voice' => 'Ini judul halaman. Kamu lagi lihat daftar datanya.',
                'selector' => '.content-wrapper .head-label h4, .content-wrapper h4.card-title, .content-wrapper h4, .content-wrapper h5.card-header, .content-wrapper h5',
            ],
            [
                'key' => 'add',
                'label' => 'Tambah data',
                'blurb' => 'Tombol tambah untuk membuat record baru di halaman ini.',
                'voice' => 'Ini tombol tambah. Dari sini kamu buat data baru.',
                'selector' => '.dt-action-buttons .create-new, .content-wrapper .create-new, .dt-action-buttons .ti-plus, .content-wrapper a.btn-primary[href*="insert"]',
            ],
            [
                'key' => 'filter',
                'label' => 'Filter dan status',
                'blurb' => 'Filter daftar, misalnya Active atau Deleted, supaya tabel tidak ramai.',
                'voice' => 'Ini filter. Pakai ini untuk saring status atau kriteria lain.',
                'selector' => '.dt-action-buttons .ti-filter, button[data-bs-target="#filterModal"], .dt-action-buttons .btn-warning',
            ],
            [
                'key' => 'table',
                'label' => 'Tabel data',
                'blurb' => 'Tabel utama: daftar record, status, dan aksi edit atau hapus.',
                'voice' => 'Ini tabel datanya. Status dan aksi biasanya di kolom kanan.',
                'selector' => '.content-wrapper #table, .content-wrapper .card-datatable, .content-wrapper table.table, .content-wrapper .card',
            ],
        ],
        'form' => [
            [
                'key' => 'title',
                'label' => 'Form',
                'blurb' => 'Ini form tambah atau ubah data.',
                'voice' => 'Ini form isian. Isi yang wajib, lalu simpan.',
                'selector' => '.content-wrapper h5.card-header, .content-wrapper .card-header, .content-wrapper h4, .content-wrapper h5',
            ],
            [
                'key' => 'fields',
                'label' => 'Isian form',
                'blurb' => 'Field penting ada di sini. Yang bertanda bintang wajib diisi.',
                'voice' => 'Isi field yang wajib, yang ada tanda bintang.',
                'selector' => '#postForm .accordion-body, #postForm, .content-wrapper form .row.g-3, .content-wrapper form',
            ],
            [
                'key' => 'save',
                'label' => 'Simpan',
                'blurb' => 'Tombol simpan di bawah. Cancel untuk batal tanpa menyimpan.',
                'voice' => 'Kalau sudah yakin, tekan Simpan. Cancel kalau mau batal.',
                'selector' => '#btn-submit, .floating-footer .btn-primary, .content-wrapper button[type="submit"].btn-primary',
            ],
        ],
    ],

    'rooms' => [

        'dashboard' => [
            'label' => 'Dashboard',
            'blurb' => 'Pintu masuk harian: ringkasan angka dan aktivitas cabang yang sedang aktif.',
            'voice' => 'Aku Titanie. Ini Dashboard, pintu masuk harian kamu. Di sini ada ringkasan angka dan aktivitas cabang.',
            'menu_names' => ['Dashboard'],
            'prefixes' => ['/dashboard'],
            'url' => '/dashboard',
            'selector' => '#layout-menu a.menu-link[href*="/dashboard"], #layout-menu a.menu-link[data-menu="Dashboard"]',
            'docs_query' => 'dashboard backoffice TITANIE ringkasan KPI',
            'page_spots' => [
                'index' => [
                    [
                        'key' => 'title',
                        'label' => 'Dashboard',
                        'blurb' => 'Judul dan ringkasan harian cabang yang sedang aktif.',
                        'voice' => 'Ini Dashboard. Lihat ringkasan angka cabang di kartu-kartu ini.',
                        'selector' => '.content-wrapper h4, .content-wrapper h5, .content-wrapper .card-header',
                    ],
                    [
                        'key' => 'cards',
                        'label' => 'Kartu ringkasan',
                        'blurb' => 'Kartu KPI dan aktivitas. Klik salah satu kalau mau masuk ke detail.',
                        'voice' => 'Kartu-kartu ini ringkasan cepat. Dari sini kamu bisa loncat ke modul terkait.',
                        'selector' => '.content-wrapper .card, .content-wrapper .row .card',
                    ],
                ],
            ],
        ],

        'hr' => [
            'label' => 'Human Resources',
            'blurb' => 'Ruang orang. Buka menu ini: di dalamnya ada Division, Position, dan Employee.',
            'voice' => 'Yuk, ini ruang Human Resources. Aku buka menunya: ada Division, Position, dan Employee.',
            'menu_names' => ['Human Resources', 'Employee', 'Division', 'Position'],
            'prefixes' => ['/human-resources'],
            'url' => '/human-resources/employee',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Human Resources"], #layout-menu a.menu-link[data-menu="Human Resources"]',
            'docs_query' => 'modul Human Resources karyawan divisi jabatan',
            'children' => [
                [
                    'label' => 'Division',
                    'blurb' => 'Division: kelompok kerja. Buat divisi dulu sebelum menaruh jabatan dan karyawan.',
                    'voice' => 'Ini submenu Division. Daftar dan kelola divisi kerja dari sini.',
                    'menu_names' => ['Division'],
                    'url' => '/human-resources/division',
                    'selector' => '#layout-menu a.menu-link[href*="/human-resources/division"], #layout-menu a.menu-link[data-menu="Division"]',
                ],
                [
                    'label' => 'Position',
                    'blurb' => 'Position: jabatan di bawah divisi.',
                    'voice' => 'Ini Position, atau jabatan. Satu tingkat di bawah divisi.',
                    'menu_names' => ['Position'],
                    'url' => '/human-resources/position',
                    'selector' => '#layout-menu a.menu-link[href*="/human-resources/position"], #layout-menu a.menu-link[data-menu="Position"]',
                ],
                [
                    'label' => 'Employee',
                    'blurb' => 'Employee: data karyawan cabang.',
                    'voice' => 'Ini Employee. Data karyawan cabang ada di sini.',
                    'menu_names' => ['Employee'],
                    'url' => '/human-resources/employee',
                    'selector' => '#layout-menu a.menu-link[href*="/human-resources/employee"], #layout-menu a.menu-link[data-menu="Employee"]',
                ],
            ],
        ],

        'customer' => [
            'label' => 'Customer',
            'blurb' => 'Data pelanggan. Buka menu ini: ada daftar pelanggan dan grupnya.',
            'voice' => 'Ini ruang Customer. Aku buka menunya: ada daftar pelanggan dan grup.',
            'menu_names' => ['Customer', 'Group', 'Customer List'],
            'prefixes' => ['/customer'],
            'url' => '/customer/list',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Customer"], #layout-menu a.menu-link[data-menu="Customer"]',
            'docs_query' => 'modul customer grup pelanggan CRM membership',
            'children' => [
                [
                    'label' => 'Customer List',
                    'blurb' => 'Daftar pelanggan untuk penjualan dan CRM.',
                    'voice' => 'Ini daftar pelanggan. Cari, tambah, atau ubah data customer dari sini.',
                    'menu_names' => ['Customer List', 'Customer'],
                    'url' => '/customer/list',
                    'selector' => '#layout-menu a.menu-link[href*="/customer/list"], #layout-menu a.menu-link[data-menu="Customer List"]',
                ],
                [
                    'label' => 'Group',
                    'blurb' => 'Grup pelanggan, dipakai untuk harga dan pengelompokan.',
                    'voice' => 'Ini Group. Pengelompokan pelanggan ada di sini.',
                    'menu_names' => ['Group'],
                    'url' => '/customer/group',
                    'selector' => '#layout-menu a.menu-link[href*="/customer/group"], #layout-menu a.menu-link[data-menu="Group"]',
                ],
            ],
        ],

        'product' => [
            'label' => 'Produk',
            'blurb' => 'Katalog barang. Buka menu ini: item, kategori, satuan, dan daftar harga.',
            'voice' => 'Ini ruang Produk. Aku buka menunya: item, kategori, satuan, dan daftar harga.',
            'menu_names' => ['Product', 'Product Item', 'Category', 'Price List'],
            'prefixes' => ['/product/items', '/product/nature', '/product/category', '/product/satuan', '/product/price-list', '/product/attribute', '/product/tag', '/product/collection', '/product/price'],
            'url' => '/product/items',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Product"], #layout-menu a.menu-link[data-menu="Product"]',
            'docs_query' => 'modul produk master item kategori satuan daftar harga',
            'children' => [
                [
                    'label' => 'Product Item',
                    'blurb' => 'Master item: SKU, nama, dan varian produk.',
                    'voice' => 'Ini Product Item. Katalog barang utama ada di sini.',
                    'menu_names' => ['Product Item', 'Product', 'Items'],
                    'url' => '/product/items',
                    'selector' => '#layout-menu a.menu-link[href*="/product/items"], #layout-menu a.menu-link[data-menu="Product Item"]',
                ],
                [
                    'label' => 'Category',
                    'blurb' => 'Kategori untuk mengelompokkan item di katalog.',
                    'voice' => 'Ini Category. Pengelompokan produk ada di sini.',
                    'menu_names' => ['Category'],
                    'url' => '/product/category',
                    'selector' => '#layout-menu a.menu-link[href*="/product/category"], #layout-menu a.menu-link[data-menu="Category"]',
                ],
                [
                    'label' => 'Satuan',
                    'blurb' => 'Satuan jual dan stok, misalnya pcs atau box.',
                    'voice' => 'Ini Satuan. Unit hitung barang diatur di sini.',
                    'menu_names' => ['Satuan', 'Unit'],
                    'url' => '/product/satuan',
                    'selector' => '#layout-menu a.menu-link[href*="/product/satuan"], #layout-menu a.menu-link[data-menu="Satuan"]',
                ],
                [
                    'label' => 'Price List',
                    'blurb' => 'Daftar harga jual per item atau grup.',
                    'voice' => 'Ini Price List. Harga jual diatur dari sini.',
                    'menu_names' => ['Price List'],
                    'url' => '/product/price-list',
                    'selector' => '#layout-menu a.menu-link[href*="/product/price-list"], #layout-menu a.menu-link[data-menu="Price List"]',
                ],
            ],
        ],

        'stock' => [
            'label' => 'Stok',
            'blurb' => 'Stok gudang. Buka menu ini: stok, opname, dan penyesuaian.',
            'voice' => 'Ini ruang Stok. Aku buka menunya: stok gudang, opname, dan penyesuaian.',
            'menu_names' => ['Stock', 'Stock Opname', 'Stock Adjustment', 'Stok'],
            'prefixes' => ['/product/stock', '/product/stock-opname', '/product/stock-adjustment', '/inbound'],
            'url' => '/product/stock',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Stock"], #layout-menu a.menu-link[data-menu="Stock"]',
            'docs_query' => 'modul stok gudang opname adjustment FIFO FEFO',
            'children' => [
                [
                    'label' => 'Stock',
                    'blurb' => 'Stok on-hand per gudang.',
                    'voice' => 'Ini daftar stok gudang. Lihat sisa barang dari sini.',
                    'menu_names' => ['Stock', 'Stok'],
                    'url' => '/product/stock',
                    'selector' => '#layout-menu a.menu-link[href*="/product/stock"]:not([href*="stock-"]), #layout-menu a.menu-link[data-menu="Stock"]',
                ],
                [
                    'label' => 'Stock Opname',
                    'blurb' => 'Opname: hitung fisik lalu selisihkan dengan sistem.',
                    'voice' => 'Ini Stock Opname. Hitung fisik barang lewat sini.',
                    'menu_names' => ['Stock Opname'],
                    'url' => '/product/stock-opname',
                    'selector' => '#layout-menu a.menu-link[href*="/product/stock-opname"], #layout-menu a.menu-link[data-menu="Stock Opname"]',
                ],
                [
                    'label' => 'Stock Adjustment',
                    'blurb' => 'Penyesuaian stok kalau ada selisih atau koreksi.',
                    'voice' => 'Ini Stock Adjustment. Koreksi stok dicatat di sini.',
                    'menu_names' => ['Stock Adjustment'],
                    'url' => '/product/stock-adjustment',
                    'selector' => '#layout-menu a.menu-link[href*="/product/stock-adjustment"], #layout-menu a.menu-link[data-menu="Stock Adjustment"]',
                ],
            ],
        ],

        'purchasing' => [
            'label' => 'Purchasing',
            'blurb' => 'Pembelian. Buka menu ini: order supplier, invoice, dan master supplier.',
            'voice' => 'Ini ruang Purchasing. Aku buka menunya: order, invoice, dan supplier.',
            'menu_names' => ['Purchase Order', 'Supplier', 'Purchase Invoice'],
            'prefixes' => ['/product/purchase-order', '/product/purchase-invoice', '/product/supplier', '/supplier'],
            'url' => '/product/purchase-order',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Purchasing"], #layout-menu a.menu-link[data-menu="Purchase Order"]',
            'docs_query' => 'modul purchasing purchase order supplier penerimaan barang',
            'children' => [
                [
                    'label' => 'Purchase Order',
                    'blurb' => 'Order ke supplier sampai penerimaan barang.',
                    'voice' => 'Ini Purchase Order. Order ke supplier dimulai dari sini.',
                    'menu_names' => ['Purchase Order'],
                    'url' => '/product/purchase-order',
                    'selector' => '#layout-menu a.menu-link[href*="/purchase-order"], #layout-menu a.menu-link[data-menu="Purchase Order"]',
                ],
                [
                    'label' => 'Supplier',
                    'blurb' => 'Master pemasok.',
                    'voice' => 'Ini Supplier. Data pemasok ada di sini.',
                    'menu_names' => ['Supplier'],
                    'url' => '/product/supplier',
                    'selector' => '#layout-menu a.menu-link[href*="/supplier"], #layout-menu a.menu-link[data-menu="Supplier"]',
                ],
                [
                    'label' => 'Purchase Invoice',
                    'blurb' => 'Tagihan pembelian dari supplier.',
                    'voice' => 'Ini Purchase Invoice. Tagihan pembelian dicatat di sini.',
                    'menu_names' => ['Purchase Invoice'],
                    'url' => '/product/purchase-invoice',
                    'selector' => '#layout-menu a.menu-link[href*="/purchase-invoice"], #layout-menu a.menu-link[data-menu="Purchase Invoice"]',
                ],
            ],
        ],

        'pos' => [
            'label' => 'POS',
            'blurb' => 'Kasir penjualan tunai di cabang. Buka menu transaksi kalau ada submenu.',
            'voice' => 'Ini ruang POS, kasir penjualan tunai di cabang. Transaksi di tempat dicatat dari sini.',
            'menu_names' => ['POS', 'Transaction', 'Method Payment'],
            'prefixes' => ['/transaction'],
            'url' => '/transaction/pos',
            'selector' => '#layout-menu a.menu-link[href*="/transaction/pos"], #layout-menu a.menu-link[data-menu="POS"]',
            'docs_query' => 'modul POS penjualan tunai transaksi metode pembayaran',
            'children' => [
                [
                    'label' => 'POS',
                    'blurb' => 'Layar kasir: pilih item, bayar tunai, selesai.',
                    'voice' => 'Ini kasir POS. Jual tunai di cabang dari sini.',
                    'menu_names' => ['POS'],
                    'url' => '/transaction/pos',
                    'selector' => '#layout-menu a.menu-link[href*="/transaction/pos"], #layout-menu a.menu-link[data-menu="POS"]',
                ],
                [
                    'label' => 'Method Payment',
                    'blurb' => 'Metode pembayaran yang tersedia di kasir.',
                    'voice' => 'Ini Method Payment. Cara bayar di kasir diatur di sini.',
                    'menu_names' => ['Method Payment'],
                    'url' => '/transaction/method-payment',
                    'selector' => '#layout-menu a.menu-link[data-menu="Method Payment"]',
                ],
            ],
        ],

        'distribution' => [
            'label' => 'Distribusi',
            'blurb' => 'Distribusi. Buka menu ini: order agen, produksi, dan BOM.',
            'voice' => 'Ini ruang Distribusi. Aku buka menunya: order agen, produksi, dan BOM.',
            'menu_names' => ['Replenishment', 'Agen Order', 'Production Order', 'Bill of Materials'],
            'prefixes' => ['/agen-order', '/replenishment', '/production', '/bom', '/promotions', '/marketing-allocation'],
            'url' => '/agen-order',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Replenishment"], #layout-menu a.menu-link[data-menu="Agen Order"], #layout-menu a.menu-link[data-menu="Replenishment"]',
            'docs_query' => 'alur replenishment distributor agen produksi BOM',
            'children' => [
                [
                    'label' => 'Agen Order',
                    'blurb' => 'Order replenishment distributor ke agen.',
                    'voice' => 'Ini Agen Order. Order stok ke agen lewat sini.',
                    'menu_names' => ['Agen Order', 'Replenishment'],
                    'url' => '/agen-order',
                    'selector' => '#layout-menu a.menu-link[href*="/agen-order"], #layout-menu a.menu-link[data-menu="Agen Order"]',
                ],
                [
                    'label' => 'Production Order',
                    'blurb' => 'Perintah produksi dari resep BOM.',
                    'voice' => 'Ini Production Order. Produksi barang jadi dicatat di sini.',
                    'menu_names' => ['Production Order'],
                    'url' => '/production',
                    'selector' => '#layout-menu a.menu-link[href*="/production"], #layout-menu a.menu-link[data-menu="Production Order"]',
                ],
                [
                    'label' => 'Bill of Materials',
                    'blurb' => 'Resep bahan menjadi barang jadi.',
                    'voice' => 'Ini Bill of Materials. Resep produksi ada di sini.',
                    'menu_names' => ['Bill of Materials'],
                    'url' => '/bom',
                    'selector' => '#layout-menu a.menu-link[href*="/bom"], #layout-menu a.menu-link[data-menu="Bill of Materials"]',
                ],
            ],
        ],

        'partner' => [
            'label' => 'Partner',
            'blurb' => 'Jaringan partner. Buka menu ini: pengajuan, agen, dan reseller.',
            'voice' => 'Ini ruang Partner. Aku buka menunya: jaringan agen dan reseller.',
            'menu_names' => ['Partner', 'Network', 'Partner Application', 'Partner Agent', 'Partner Reseller'],
            'prefixes' => ['/partner-network', '/partner'],
            'url' => '/partner-network',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Partner"], #layout-menu a.menu-link[data-menu="Partner"]',
            'docs_query' => 'jaringan partner agen reseller convert application',
            'children' => [
                [
                    'label' => 'Network',
                    'blurb' => 'Peta jaringan partner.',
                    'voice' => 'Ini Network. Jaringan agen dan reseller terlihat dari sini.',
                    'menu_names' => ['Network', 'Partner Network'],
                    'url' => '/partner-network',
                    'selector' => '#layout-menu a.menu-link[href*="/partner-network"], #layout-menu a.menu-link[data-menu="Network"]',
                ],
                [
                    'label' => 'Partner Application',
                    'blurb' => 'Pengajuan calon partner sebelum di-convert.',
                    'voice' => 'Ini Partner Application. Pengajuan calon agen atau reseller masuk ke sini.',
                    'menu_names' => ['Partner Application', 'Application'],
                    'url' => '/partner/application',
                    'selector' => '#layout-menu a.menu-link[data-menu="Partner Application"], #layout-menu a.menu-link[data-menu="Application"]',
                ],
                [
                    'label' => 'Partner Agent',
                    'blurb' => 'Daftar agen yang sudah di-convert.',
                    'voice' => 'Ini daftar agen. Yang sudah jadi partner tampil di sini.',
                    'menu_names' => ['Partner Agent', 'Agent'],
                    'url' => '/partner/agent',
                    'selector' => '#layout-menu a.menu-link[data-menu="Partner Agent"], #layout-menu a.menu-link[data-menu="Agent"]',
                ],
            ],
        ],

        'accounting' => [
            'label' => 'Accounting',
            'blurb' => 'Akuntansi. Buka menu ini: bagan akun, jurnal, kas dan bank.',
            'voice' => 'Ini ruang Accounting. Aku buka menunya: COA, jurnal, kas dan bank.',
            'menu_names' => ['Chart of Accounts', 'Journal', 'Cash Bank', 'Finance'],
            'prefixes' => ['/finance'],
            'url' => '/finance/chart-of-accounts',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Finance"], #layout-menu a.menu-link[data-menu="Finance"]',
            'docs_query' => 'modul akuntansi COA jurnal kas bank',
            'children' => [
                [
                    'label' => 'Chart of Accounts',
                    'blurb' => 'Bagan akun (COA).',
                    'voice' => 'Ini Chart of Accounts. Bagan akun diatur di sini.',
                    'menu_names' => ['Chart of Accounts'],
                    'url' => '/finance/chart-of-accounts',
                    'selector' => '#layout-menu a.menu-link[href*="/chart-of-accounts"], #layout-menu a.menu-link[data-menu="Chart of Accounts"]',
                ],
                [
                    'label' => 'Journal',
                    'blurb' => 'Jurnal transaksi akuntansi.',
                    'voice' => 'Ini Journal. Entri jurnal ada di sini.',
                    'menu_names' => ['Journal'],
                    'url' => '/finance/journal',
                    'selector' => '#layout-menu a.menu-link[href*="/journal"], #layout-menu a.menu-link[data-menu="Journal"]',
                ],
                [
                    'label' => 'Cash Bank',
                    'blurb' => 'Kas dan bank.',
                    'voice' => 'Ini Cash Bank. Kas dan rekening bank dikelola di sini.',
                    'menu_names' => ['Cash Bank'],
                    'url' => '/finance/cash-bank',
                    'selector' => '#layout-menu a.menu-link[href*="/cash-bank"], #layout-menu a.menu-link[data-menu="Cash Bank"]',
                ],
            ],
        ],

        'crm' => [
            'label' => 'CRM',
            'blurb' => 'Membership dan poin pelanggan akhir.',
            'voice' => 'Ini ruang CRM. Membership dan poin untuk pelanggan akhir.',
            'menu_names' => ['Membership Configuration', 'CRM'],
            'prefixes' => ['/crm'],
            'url' => '/crm/membership-configuration',
            'selector' => '#layout-menu a.menu-link[href*="/crm"], #layout-menu a.menu-link[data-menu="CRM"]',
            'docs_query' => 'CRM membership poin loyalitas pelanggan B2C',
        ],

        'training' => [
            'label' => 'Training Academy',
            'blurb' => 'Akademi materi untuk partner.',
            'voice' => 'Ini Training Academy. Materi kursus untuk partner ada di sini.',
            'menu_names' => ['Training Academy', 'Academy'],
            'prefixes' => ['/training', '/academy'],
            'url' => '/academy',
            'selector' => '#layout-menu a.menu-link[href*="/academy"], #layout-menu a.menu-link[data-menu="Training Academy"]',
            'docs_query' => 'Training Academy kursus modul materi partner',
        ],

        'marketing' => [
            'label' => 'Marketing',
            'blurb' => 'Aset dan materi pemasaran.',
            'voice' => 'Ini ruang Marketing. Aset dan materi pemasaran ada di sini.',
            'menu_names' => ['Marketing Center', 'Marketing'],
            'prefixes' => ['/marketing'],
            'url' => '/marketing/assets',
            'selector' => '#layout-menu a.menu-link[href*="/marketing"], #layout-menu a.menu-link[data-menu="Marketing"]',
            'docs_query' => 'Marketing Center aset materi pemasaran',
        ],

        'reporting' => [
            'label' => 'Laporan',
            'blurb' => 'Laporan penjualan, stok, dan pembelian.',
            'voice' => 'Ini ruang Laporan. Penjualan, stok, dan pembelian bisa dicek di sini.',
            'menu_names' => ['Reporting', 'Summary Sales'],
            'prefixes' => ['/reporting'],
            'url' => '/reporting/summary-sales',
            'selector' => '#layout-menu a.menu-link[href*="/reporting"], #layout-menu a.menu-link[data-menu="Reporting"]',
            'docs_query' => 'modul reporting penjualan stok purchase order',
        ],

        'settings' => [
            'label' => 'Settings',
            'blurb' => 'Pengaturan. Buka menu ini: role, menu, tema, dan konfigurasi AI.',
            'voice' => 'Ini Settings. Aku buka menunya: role, menu, tema, dan konfigurasi AI.',
            'menu_names' => ['Settings', 'Role', 'Menu', 'Parameter', 'Appearance & Theme', 'AI Chat Configuration'],
            'prefixes' => ['/settings', '/access-management', '/master-data', '/masterdatas', '/account'],
            'url' => '/settings/theme-configuration',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Settings"], #layout-menu a.menu-link[data-menu="Settings"]',
            'docs_query' => 'settings role menu tema konfigurasi AI permission',
            'children' => [
                [
                    'label' => 'Appearance & Theme',
                    'blurb' => 'Tema dan tampilan admin.',
                    'voice' => 'Ini Appearance dan Theme. Tampilan admin diatur di sini.',
                    'menu_names' => ['Appearance & Theme'],
                    'url' => '/settings/theme-configuration',
                    'selector' => '#layout-menu a.menu-link[href*="/settings/theme"], #layout-menu a.menu-link[data-menu="Appearance & Theme"]',
                ],
                [
                    'label' => 'Role',
                    'blurb' => 'Role dan izin menu.',
                    'voice' => 'Ini Role. Izin per peran diatur dari sini.',
                    'menu_names' => ['Role'],
                    'url' => '/access-management/role',
                    'selector' => '#layout-menu a.menu-link[data-menu="Role"]',
                ],
                [
                    'label' => 'AI Chat Configuration',
                    'blurb' => 'Nyala-mati chatbot dan provider AI.',
                    'voice' => 'Ini konfigurasi AI Chat. Widget asisten diatur di sini.',
                    'menu_names' => ['AI Chat Configuration'],
                    'url' => '/settings/ai-chat',
                    'selector' => '#layout-menu a.menu-link[data-menu="AI Chat Configuration"]',
                ],
            ],
        ],

        'business' => [
            'label' => 'Struktur Bisnis',
            'blurb' => 'Holding, company, cabang, dan gudang.',
            'voice' => 'Ini Struktur Bisnis. Holding, company, cabang, dan gudang diatur di sini.',
            'menu_names' => ['Holding', 'Company', 'Branch', 'Warehouse'],
            'prefixes' => ['/business'],
            'url' => '/business/warehouse',
            'selector' => '#layout-menu a.menu-link[href*="/business"], #layout-menu a.menu-link[data-menu="Warehouse"], #layout-menu a.menu-link[data-menu="Holding"]',
            'docs_query' => 'holding company branch warehouse cabang gudang',
        ],
    ],
];
