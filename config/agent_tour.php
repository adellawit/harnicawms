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
    | Urutan tur utama mengikuti urutan sidebar menu di DB.
    */
    'tour' => [
        'dashboard',
        'product',
        'accounting',
        'customer',
        'marketing',
        'training',
        'pos',
        'business',
        'hr',
        'reporting',
        'settings',
        'crm',
    ],

    'heading_selector' => '.content-wrapper .head-label h4, .content-wrapper h4.card-title, .content-wrapper .card-header, .content-wrapper h4, .content-wrapper h5, .pos-top-bar .pos-title, .pos-top-bar, #layout-navbar',

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
            'voice' => 'Aku REDDIE. Ini Dashboard, pintu masuk harian kamu. Di sini ada ringkasan angka dan aktivitas cabang.',
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
            'blurb' => 'Data pelanggan dan jaringan partner. Ada daftar pelanggan, grup, dan manajemen agen serta reseller.',
            'voice' => 'Ini ruang Customer. Ada daftar pelanggan, grup, dan jaringan partner agen serta reseller.',
            'menu_names' => ['Customer', 'Group', 'List', 'Network', 'Partner Application', 'Partner Agent', 'Partner Reseller'],
            'prefixes' => ['/customer', '/partner-network'],
            'url' => '/customer/list',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Customer"], #layout-menu a.menu-link[data-menu="Customer"]',
            'docs_query' => 'modul customer grup pelanggan jaringan partner agen reseller',
            'children' => [
                [
                    'label' => 'List (Customer)',
                    'blurb' => 'Daftar pelanggan untuk penjualan dan CRM.',
                    'voice' => 'Ini daftar pelanggan. Cari, tambah, atau ubah data customer dari sini.',
                    'menu_names' => ['List', 'Customer List'],
                    'url' => '/customer/list',
                    'selector' => '#layout-menu a.menu-link[href*="/customer/list"], #layout-menu a.menu-link[data-menu="List"]',
                ],
                [
                    'label' => 'Group',
                    'blurb' => 'Grup pelanggan, dipakai untuk segmentasi harga dan diskon.',
                    'voice' => 'Ini Group. Pengelompokan pelanggan ada di sini.',
                    'menu_names' => ['Group'],
                    'url' => '/customer/group',
                    'selector' => '#layout-menu a.menu-link[href*="/customer/group"], #layout-menu a.menu-link[data-menu="Group"]',
                ],
                [
                    'label' => 'Network (Partner)',
                    'blurb' => 'Jaringan partner: pengajuan, agen, reseller, dan peta jaringan distribusi.',
                    'voice' => 'Ini Network Partner. Agen, reseller, dan pengajuan partner ada di sini.',
                    'menu_names' => ['Network', 'Partner Application', 'Partner Agent', 'Partner Reseller'],
                    'url' => '/partner-network',
                    'selector' => '#layout-menu a.menu-link[href*="/partner-network"], #layout-menu a.menu-link[data-menu="Network"]',
                ],
            ],
        ],

        'product' => [
            'label' => 'Product',
            'blurb' => 'Semua yang berkaitan dengan barang: master item, inventory, pembelian, produksi, harga, dan distribusi.',
            'voice' => 'Ini ruang Product. Di sini ada master item, stok, pembelian, produksi, dan harga.',
            'menu_names' => ['Product', 'Items', 'Inventory', 'Purchasing', 'Production', 'Master', 'Pricing', 'Distribution'],
            'prefixes' => ['/product', '/bom', '/production', '/marketing-allocation', '/master-data/supplier'],
            'url' => '/product/items',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Product"], #layout-menu a.menu-link[data-menu="Product"]',
            'docs_query' => 'modul produk master item kategori satuan daftar harga stok pembelian produksi',
            'children' => [
                [
                    'label' => 'Items',
                    'blurb' => 'Master item: SKU, nama, varian, dan atribut produk.',
                    'voice' => 'Ini Items. Katalog barang utama ada di sini.',
                    'menu_names' => ['Items', 'Product Item'],
                    'url' => '/product/items',
                    'selector' => '#layout-menu a.menu-link[href*="/product/items"], #layout-menu a.menu-link[data-menu="Items"]',
                ],
                [
                    'label' => 'Inventory',
                    'blurb' => 'Stok on-hand, stock opname, dan stock adjustment.',
                    'voice' => 'Ini Inventory. Stok gudang, opname, dan penyesuaian ada di sini.',
                    'menu_names' => ['Inventory', 'Stock', 'Stock Opname', 'Stock Adjustment'],
                    'url' => '/product/stock',
                    'selector' => '#layout-menu a.menu-link[href*="/product/stock"], #layout-menu a.menu-link[data-menu="Inventory"]',
                ],
                [
                    'label' => 'Purchasing',
                    'blurb' => 'Purchase Order ke supplier, penerimaan barang, dan invoice pembelian.',
                    'voice' => 'Ini Purchasing. Order ke supplier dan penerimaan barang dicatat di sini.',
                    'menu_names' => ['Purchasing', 'Purchase Order', 'Supplier', 'Invoice'],
                    'url' => '/product/purchase-order',
                    'selector' => '#layout-menu a.menu-link[href*="/purchase-order"], #layout-menu a.menu-link[data-menu="Purchasing"]',
                ],
                [
                    'label' => 'Production',
                    'blurb' => 'Production Order dan Bill of Materials untuk barang jadi dari bahan baku.',
                    'voice' => 'Ini Production. Produksi barang dari bahan baku diatur di sini.',
                    'menu_names' => ['Production', 'Production Order', 'Bill of Materials'],
                    'url' => '/production',
                    'selector' => '#layout-menu a.menu-link[href*="/production"], #layout-menu a.menu-link[data-menu="Production"]',
                ],
                [
                    'label' => 'Master (Produk)',
                    'blurb' => 'Data master produk: kategori, satuan, price list, atribut, tag, dan koleksi.',
                    'voice' => 'Ini Master Produk. Kategori, satuan, dan daftar harga diatur di sini.',
                    'menu_names' => ['Master', 'Category', 'Unit', 'Price Lists'],
                    'url' => '/product/category',
                    'selector' => '#layout-menu a.menu-link[href*="/product/category"], #layout-menu a.menu-link[data-menu="Master"]',
                ],
                [
                    'label' => 'Pricing',
                    'blurb' => 'Harga jual per item atau grup pelanggan.',
                    'voice' => 'Ini Pricing. Harga jual diatur dari sini.',
                    'menu_names' => ['Pricing', 'Product Price', 'Price Lists'],
                    'url' => '/product/price',
                    'selector' => '#layout-menu a.menu-link[href*="/product/price"], #layout-menu a.menu-link[data-menu="Pricing"]',
                ],
            ],
        ],

        'pos' => [
            'label' => 'Point of Sales',
            'blurb' => 'Kasir penjualan tunai di cabang. Menu ini berisi layar POS, metode pembayaran, dan riwayat transaksi.',
            'voice' => 'Ini ruang Point of Sales, kasir penjualan tunai di cabang. Transaksi di tempat dicatat dari sini.',
            'menu_names' => ['Point of Sales', 'POS', 'Transaction', 'Method Payment'],
            'prefixes' => ['/transaction'],
            'url' => '/transaction/pos',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Point of Sales"], #layout-menu a.menu-link[href*="/transaction"]',
            'docs_query' => 'modul POS penjualan tunai transaksi metode pembayaran',
            'page_spots' => [
                'index' => [
                    [
                        'key' => 'title',
                        'label' => 'Info transaksi POS',
                        'blurb' => 'Header kasir: nama cabang, tanggal, jam, dan nomor transaksi aktif.',
                        'voice' => 'Ini header kasir. Ada info cabang, tanggal, dan nomor transaksi di sini.',
                        'selector' => '.pos-top-bar, .pos-top-bar .pos-title, #posWrapper .pos-top-bar',
                    ],
                    [
                        'key' => 'add',
                        'label' => 'Cari & tambah produk',
                        'blurb' => 'Cari produk dengan mengetik nama atau scan barcode, lalu tekan Tambah untuk memasukkannya ke keranjang.',
                        'voice' => 'Cari produk di sini. Ketik nama atau scan barcode, lalu tekan Tambah.',
                        'selector' => '#productSearch, #barcodeInput, .pos-top-bar ~ * input[placeholder], #posWrapper input[type="text"]',
                    ],
                    [
                        'key' => 'cart',
                        'label' => 'Keranjang & total',
                        'blurb' => 'Panel kanan: keranjang belanja, sub total, diskon, dan tombol Bayar untuk menyelesaikan transaksi.',
                        'voice' => 'Keranjang ada di kanan. Tambahkan produk, lalu tekan Bayar untuk selesai.',
                        'selector' => '#cartSection, #cartItems, .pay-btn, #posWrapper .btn-primary, #posWrapper [id*="cart"]',
                    ],
                    [
                        'key' => 'filter',
                        'label' => 'Filter kategori produk',
                        'blurb' => 'Tab kategori di atas grid produk: Bahan Baku, Jasa, Non Stock, Produk Jadi, dll.',
                        'voice' => 'Ini filter kategori. Pilih tab untuk menyaring jenis produk.',
                        'selector' => '#posWrapper .nav-pills, #posWrapper .filter-tabs, #posWrapper [data-category], #productFilter',
                    ],
                ],
            ],
            'children' => [
                [
                    'label' => 'POS',
                    'blurb' => 'Layar kasir: cari produk, tambah ke keranjang, pilih pelanggan, lalu bayar.',
                    'voice' => 'Ini kasir POS. Pilih barang, masukkan ke keranjang, lalu tekan Bayar.',
                    'menu_names' => ['POS'],
                    'url' => '/transaction/pos',
                    'selector' => '#layout-menu a.menu-link[href*="/transaction/pos"], #layout-menu a.menu-link[data-menu="POS"]',
                ],
                [
                    'label' => 'Method Payment',
                    'blurb' => 'Metode pembayaran yang tersedia di kasir: tunai, transfer, QRIS, e-wallet.',
                    'voice' => 'Ini Method Payment. Cara bayar yang tersedia di kasir diatur di sini.',
                    'menu_names' => ['Method Payment'],
                    'url' => '/transaction/method-payment',
                    'selector' => '#layout-menu a.menu-link[href*="/transaction/method-payment"], #layout-menu a.menu-link[data-menu="Method Payment"]',
                ],
                [
                    'label' => 'Transaction',
                    'blurb' => 'Riwayat semua transaksi kasir yang sudah selesai.',
                    'voice' => 'Ini riwayat transaksi. Semua penjualan kasir tercatat di sini.',
                    'menu_names' => ['Transaction'],
                    'url' => '/transaction',
                    'selector' => '#layout-menu a.menu-link[href="/transaction"], #layout-menu a.menu-link[data-menu="Transaction"]',
                ],
            ],
        ],

        'marketing' => [
            'label' => 'Marketing Center',
            'blurb' => 'Aset dan materi pemasaran untuk partner dan reseller.',
            'voice' => 'Ini Marketing Center. Aset dan materi promosi untuk partner ada di sini.',
            'menu_names' => ['Marketing Center', 'Marketing Assets', 'Marketing Category'],
            'prefixes' => ['/marketing'],
            'url' => '/marketing/assets',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Marketing Center"], #layout-menu a.menu-link[data-menu="Marketing Center"]',
            'docs_query' => 'Marketing Center aset materi pemasaran promosi partner',
            'children' => [
                [
                    'label' => 'Marketing Assets',
                    'blurb' => 'Daftar aset pemasaran yang bisa digunakan partner.',
                    'voice' => 'Ini Marketing Assets. Bahan promosi untuk partner ada di sini.',
                    'menu_names' => ['Marketing Assets'],
                    'url' => '/marketing/assets',
                    'selector' => '#layout-menu a.menu-link[href*="/marketing/assets"], #layout-menu a.menu-link[data-menu="Marketing Assets"]',
                ],
                [
                    'label' => 'Marketing Category',
                    'blurb' => 'Kategori aset pemasaran.',
                    'voice' => 'Ini Marketing Category. Pengelompokan aset promosi ada di sini.',
                    'menu_names' => ['Marketing Category'],
                    'url' => '/marketing/categories',
                    'selector' => '#layout-menu a.menu-link[href*="/marketing/categories"], #layout-menu a.menu-link[data-menu="Marketing Category"]',
                ],
            ],
        ],

        'training' => [
            'label' => 'Training Academy',
            'blurb' => 'Akademi pelatihan untuk partner dan reseller: kursus, materi, dan laporan.',
            'voice' => 'Ini Training Academy. Materi kursus untuk partner ada di sini.',
            'menu_names' => ['Training Academy', 'Course', 'Academy', 'Pengaturan Academy', 'Course Category'],
            'prefixes' => ['/training', '/academy'],
            'url' => '/training/courses',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Training Academy"], #layout-menu a.menu-link[href*="/training"]',
            'docs_query' => 'Training Academy kursus modul materi partner reseller',
            'children' => [
                [
                    'label' => 'Course',
                    'blurb' => 'Daftar kursus yang tersedia untuk partner.',
                    'voice' => 'Ini Course. Daftar kursus untuk partner ada di sini.',
                    'menu_names' => ['Course'],
                    'url' => '/training/courses',
                    'selector' => '#layout-menu a.menu-link[href*="/training/courses"], #layout-menu a.menu-link[data-menu="Course"]',
                ],
                [
                    'label' => 'Academy (Portal Partner)',
                    'blurb' => 'Portal akademi untuk partner mengakses materi kursus.',
                    'voice' => 'Ini Academy. Portal kursus untuk partner bisa diakses dari sini.',
                    'menu_names' => ['Academy'],
                    'url' => '/academy',
                    'selector' => '#layout-menu a.menu-link[href*="/academy"], #layout-menu a.menu-link[data-menu="Academy"]',
                ],
            ],
        ],

        'accounting' => [
            'label' => 'Accounting',
            'blurb' => 'Akuntansi lengkap: COA, jurnal, kas & bank, laporan keuangan.',
            'voice' => 'Ini ruang Accounting. COA, jurnal, kas dan bank, sampai laporan keuangan ada di sini.',
            'menu_names' => ['Accounting', 'Chart of Accounts', 'Journal Entry', 'Cash & Bank', 'General Ledger', 'Balance Sheet', 'Income Statement', 'Cash Flow'],
            'prefixes' => ['/finance'],
            'url' => '/finance/chart-of-accounts',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Accounting"], #layout-menu a.menu-link[data-menu="Accounting"]',
            'docs_query' => 'modul akuntansi COA jurnal kas bank laporan keuangan',
            'children' => [
                [
                    'label' => 'Chart of Accounts',
                    'blurb' => 'Bagan akun (COA) — dasar seluruh pencatatan keuangan.',
                    'voice' => 'Ini Chart of Accounts. Bagan akun diatur di sini.',
                    'menu_names' => ['Chart of Accounts'],
                    'url' => '/finance/chart-of-accounts',
                    'selector' => '#layout-menu a.menu-link[href*="/chart-of-accounts"], #layout-menu a.menu-link[data-menu="Chart of Accounts"]',
                ],
                [
                    'label' => 'Journal Entry',
                    'blurb' => 'Jurnal transaksi akuntansi manual.',
                    'voice' => 'Ini Journal Entry. Pencatatan jurnal manual ada di sini.',
                    'menu_names' => ['Journal Entry', 'Jurnal Umum'],
                    'url' => '/finance/journal-entry',
                    'selector' => '#layout-menu a.menu-link[href*="/journal-entry"], #layout-menu a.menu-link[data-menu="Journal Entry"]',
                ],
                [
                    'label' => 'Cash & Bank',
                    'blurb' => 'Mutasi kas dan rekening bank cabang.',
                    'voice' => 'Ini Cash dan Bank. Mutasi kas dan rekening dikelola di sini.',
                    'menu_names' => ['Cash & Bank'],
                    'url' => '/finance/cash-bank',
                    'selector' => '#layout-menu a.menu-link[href*="/cash-bank"], #layout-menu a.menu-link[data-menu="Cash & Bank"]',
                ],
                [
                    'label' => 'Laporan Keuangan',
                    'blurb' => 'General Ledger, Balance Sheet, Income Statement, dan Cash Flow.',
                    'voice' => 'Ini laporan keuangan. Neraca, laba rugi, dan arus kas ada di sini.',
                    'menu_names' => ['General Ledger', 'Balance Sheet', 'Income Statement', 'Cash Flow'],
                    'url' => '/finance/general-ledger',
                    'selector' => '#layout-menu a.menu-link[href*="/general-ledger"], #layout-menu a.menu-link[data-menu="General Ledger"]',
                ],
            ],
        ],

        'reporting' => [
            'label' => 'Reporting',
            'blurb' => 'Laporan komprehensif: penjualan, stok, pembelian, dan performa bisnis.',
            'voice' => 'Ini Reporting. Laporan penjualan, stok, dan pembelian lengkap ada di sini.',
            'menu_names' => ['Reporting', 'Sales & Transaction', 'Inventory & Warehouse', 'Executive & Overview'],
            'prefixes' => ['/reporting'],
            'url' => '/reporting/sales-summary',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Reporting"], #layout-menu a.menu-link[href*="/reporting"]',
            'docs_query' => 'modul reporting penjualan stok pembelian KPI laporan',
            'children' => [
                [
                    'label' => 'Sales & Transaction',
                    'blurb' => 'Laporan penjualan: ringkasan, detail transaksi, per kasir, per outlet.',
                    'voice' => 'Ini laporan penjualan. Transaksi, kasir, outlet, semuanya ada di sini.',
                    'menu_names' => ['Sales & Transaction', 'Sales Summary'],
                    'url' => '/reporting/sales-summary',
                    'selector' => '#layout-menu a.menu-link[href*="/reporting/sales"], #layout-menu a.menu-link[data-menu="Sales & Transaction"]',
                ],
                [
                    'label' => 'Inventory & Warehouse',
                    'blurb' => 'Laporan stok: on-hand, adjustment, slow stock, valuation.',
                    'voice' => 'Ini laporan stok. Stok gudang dan pergerakannya ada di sini.',
                    'menu_names' => ['Inventory & Warehouse', 'Stock On Hand'],
                    'url' => '/reporting/stock-on-hand',
                    'selector' => '#layout-menu a.menu-link[href*="/reporting/stock"], #layout-menu a.menu-link[data-menu="Inventory & Warehouse"]',
                ],
                [
                    'label' => 'Executive & Overview',
                    'blurb' => 'Dashboard eksekutif, KPI, dan perbandingan performa outlet.',
                    'voice' => 'Ini laporan eksekutif. KPI dan performa bisnis ada di sini.',
                    'menu_names' => ['Executive & Overview', 'KPI Dashboard'],
                    'url' => '/reporting/executive-dashboard',
                    'selector' => '#layout-menu a.menu-link[href*="/reporting/executive"], #layout-menu a.menu-link[data-menu="Executive & Overview"]',
                ],
            ],
        ],

        'crm' => [
            'label' => 'CRM',
            'blurb' => 'Membership, poin loyalitas, dan promosi untuk pelanggan akhir.',
            'voice' => 'Ini CRM. Membership, poin loyalitas, dan promosi untuk pelanggan ada di sini.',
            'menu_names' => ['CRM', 'Membership Configuration', 'Promotions'],
            'prefixes' => ['/crm', '/promotions'],
            'url' => '/crm/membership-configuration',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="CRM"], #layout-menu a.menu-link[href*="/crm"]',
            'docs_query' => 'CRM membership poin loyalitas pelanggan B2C promosi',
        ],

        'settings' => [
            'label' => 'Settings',
            'blurb' => 'Pengaturan sistem: role, menu, parameter, tema, payment gateway, dan konfigurasi AI.',
            'voice' => 'Ini Settings. Role, menu, tema, payment gateway, dan konfigurasi AI ada di sini.',
            'menu_names' => ['Settings', 'Role', 'Menu', 'Parameter', 'Appearance & Theme', 'AI Chat Configuration', 'Payment Gateway Configuration', 'Notification Config', 'Configuration'],
            'prefixes' => ['/settings', '/access-management', '/master-data'],
            'url' => '/settings/theme-configuration',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Settings"], #layout-menu a.menu-link[data-menu="Settings"]',
            'docs_query' => 'settings role menu tema konfigurasi AI payment gateway permission',
            'children' => [
                [
                    'label' => 'Role',
                    'blurb' => 'Role dan izin menu per pengguna.',
                    'voice' => 'Ini Role. Izin akses per peran diatur dari sini.',
                    'menu_names' => ['Role'],
                    'url' => '/access-management/roles',
                    'selector' => '#layout-menu a.menu-link[href*="/access-management/roles"], #layout-menu a.menu-link[data-menu="Role"]',
                ],
                [
                    'label' => 'AI Chat Configuration',
                    'blurb' => 'Nyala-mati widget chatbot dan pilihan provider AI.',
                    'voice' => 'Ini konfigurasi AI Chat. Widget asisten dan provider diatur di sini.',
                    'menu_names' => ['AI Chat Configuration'],
                    'url' => '/settings/ai-configuration',
                    'selector' => '#layout-menu a.menu-link[href*="/settings/ai-configuration"], #layout-menu a.menu-link[data-menu="AI Chat Configuration"]',
                ],
                [
                    'label' => 'Appearance & Theme',
                    'blurb' => 'Tema dan tampilan admin: warna, logo, dan gaya UI.',
                    'voice' => 'Ini Appearance dan Theme. Tampilan admin diatur di sini.',
                    'menu_names' => ['Appearance & Theme'],
                    'url' => '/settings/theme-configuration',
                    'selector' => '#layout-menu a.menu-link[href*="/settings/theme-configuration"], #layout-menu a.menu-link[data-menu="Appearance & Theme"]',
                ],
                [
                    'label' => 'Payment Gateway Configuration',
                    'blurb' => 'Konfigurasi Xendit dan metode pembayaran online.',
                    'voice' => 'Ini konfigurasi Payment Gateway. Xendit dan pembayaran online diatur di sini.',
                    'menu_names' => ['Payment Gateway Configuration'],
                    'url' => '/settings/payment-gateway-configuration',
                    'selector' => '#layout-menu a.menu-link[href*="/settings/payment-gateway"], #layout-menu a.menu-link[data-menu="Payment Gateway Configuration"]',
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
