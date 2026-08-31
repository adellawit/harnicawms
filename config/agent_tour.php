<?php

/**
 * Peta "ruangan" admin untuk tool guide_tour.
 *
 * Selector dan langkah tur — bukan sumber fakta produk.
 * Narasi fitur tetap lewat search_docs (docs_query).
 * `voice` = 1–2 kalimat Bahasa Indonesia, in-character Titanie, untuk Web Speech API
 * di overlay (opsional, default mati). Jangan markdown/emoji.
 *
 * `tour` = urutan overview (sidebar level-1).
 * `rooms` = semua ruangan yang bisa dikenali `here`, termasuk yang tidak ada di overview.
 */

$child = static function (
    string $label,
    string $url,
    string $blurb,
    string $voice,
    array $menuNames = [],
    ?string $selector = null,
): array {
    $names = array_values(array_unique(array_filter(array_merge([$label], $menuNames))));

    return [
        'label' => $label,
        'blurb' => $blurb,
        'voice' => $voice,
        'menu_names' => $names,
        'url' => $url,
        'selector' => $selector ?? (
            ($url !== '' ? '#layout-menu a.menu-link[href*="'.$url.'"], ' : '')
            .'#layout-menu a.menu-link[data-menu="'.$label.'"]'
        ),
    ];
};

$report = static function (string $label, string $url) use ($child): array {
    return $child(
        $label,
        $url,
        'Laporan '.$label.'. Atur filter periode, lalu baca tabel hasil.',
        'Ini laporan '.$label.'. Pakai filter dulu, lalu baca hasilnya di tabel.',
    );
};

return [

    /*
    | Urutan tur utama mengikuti urutan sidebar menu di DB.
    */
    'tour' => [
        'dashboard',
        'hr',
        'accounting',
        'customer',
        'product',
        'training',
        'marketing',
        'crm',
        'pos',
        'business',
        'reporting',
        'settings',
    ],

    'heading_selector' => '.content-wrapper .head-label h4, .content-wrapper h4.card-title, .content-wrapper .card-header, .content-wrapper h4, .content-wrapper h5, .pos-top-bar .pos-title, .pos-top-bar, #layout-navbar',

    /*
    | Tur keliling modul: setelah sidebar parent, paling banyak N spot UI
    | di landing page. Submenu lengkap hanya di mode page-deep ("Apa sih ini?").
    */
    'overview_page_spot_keys' => ['title', 'add'],
    'overview_page_spot_limit' => 2,
    'page_deep_spot_limit' => 6,
    'page_deep_child_limit' => 40,

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
            'voice' => 'Aku REDDIE, asisten Titanie. Ini Dashboard, pintu masuk harian kamu. Di sini ada ringkasan angka dan aktivitas cabang.',
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
                $child('Division', '/human-resources/division', 'Division: kelompok kerja. Buat divisi dulu sebelum menaruh jabatan dan karyawan.', 'Ini submenu Division. Daftar dan kelola divisi kerja dari sini.'),
                $child('Position', '/human-resources/position', 'Position: jabatan di bawah divisi.', 'Ini Position, atau jabatan. Satu tingkat di bawah divisi.'),
                $child('Employee', '/human-resources/employee', 'Employee: data karyawan cabang.', 'Ini Employee. Data karyawan cabang ada di sini.'),
            ],
        ],

        'customer' => [
            'label' => 'Customer',
            'blurb' => 'Data pelanggan dan jaringan partner. Ada daftar pelanggan, grup, dan manajemen agen serta reseller.',
            'voice' => 'Ini ruang Customer. Ada daftar pelanggan, grup, dan jaringan partner agen serta reseller.',
            'menu_names' => [
                'Customer', 'Group', 'List', 'Network',
                'Partner Application', 'Applications', 'Partner Agent', 'Agents',
                'Partner Reseller', 'Resellers', 'Cutting Price Config',
                'Network Map', 'Peta Jaringan', 'Partner Reseller Mapping', 'Reseller Mapping',
            ],
            'prefixes' => ['/customer', '/partner-network'],
            'url' => '/customer/list',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Customer"], #layout-menu a.menu-link[data-menu="Customer"]',
            'docs_query' => 'modul customer grup pelanggan jaringan partner agen reseller',
            'children' => [
                $child('Group', '/customer/group', 'Grup pelanggan, dipakai untuk segmentasi harga dan diskon.', 'Ini Group. Pengelompokan pelanggan ada di sini.'),
                $child('List', '/customer/list', 'Daftar pelanggan untuk penjualan dan CRM.', 'Ini daftar pelanggan. Cari, tambah, atau ubah data customer dari sini.', ['Customer List', 'List (Customer)']),
                $child('Network', '/partner-network', 'Jaringan partner: pengajuan, agen, reseller, dan peta jaringan distribusi.', 'Ini Network Partner. Agen, reseller, dan pengajuan partner ada di sini.'),
                $child('Partner Application', '/partner-network/applications', 'Pengajuan partner baru: agen atau reseller.', 'Ini Applications. Pengajuan partner masuk dan diproses di sini.', ['Applications']),
                $child('Partner Agent', '/partner-network/agents', 'Daftar agen yang sudah aktif.', 'Ini Agents. Data agen aktif ada di sini.', ['Agents']),
                $child('Partner Reseller', '/partner-network/resellers', 'Daftar reseller yang sudah aktif.', 'Ini Resellers. Data reseller aktif ada di sini.', ['Resellers']),
                $child('Network Map', '/partner-network/map', 'Peta jaringan distribusi partner.', 'Ini Peta Jaringan. Hubungan agen dan reseller terlihat di sini.', ['Peta Jaringan']),
                $child('Partner Reseller Mapping', '/partner-network/resellers/mapping', 'Pemetaan reseller ke agen.', 'Ini Reseller Mapping. Reseller diikat ke agen dari sini.', ['Reseller Mapping']),
                $child('Cutting Price Config', '/partner-network/cutting-price-config', 'Aturan harga potong untuk agen.', 'Ini Cutting Price Config. Harga potong agen diatur di sini.'),
            ],
        ],

        'product' => [
            'label' => 'Product',
            'blurb' => 'Semua yang berkaitan dengan barang: master item, inventory, pembelian, produksi, harga, dan distribusi.',
            'voice' => 'Ini ruang Product. Di sini ada master item, stok, pembelian, produksi, dan harga.',
            'menu_names' => [
                'Product', 'Items', 'Inventory', 'Purchasing', 'Production', 'Master',
                'Pricing', 'Distribution', 'Product Type', 'Unit', 'Category', 'Attribute',
                'Price Lists', 'Tag', 'Collection', 'Product Price', 'Supplier',
                'Purchase Order', 'Invoice', 'Bill of Materials', 'Production Order',
                'Production In-House', 'Marketing Allocation',
            ],
            'prefixes' => ['/product', '/bom', '/production', '/marketing-allocation', '/master-data/supplier'],
            'url' => '/product/items',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Product"], #layout-menu a.menu-link[data-menu="Product"]',
            'docs_query' => 'modul produk master item kategori satuan daftar harga stok pembelian produksi',
            'children' => [
                $child('Master', '/product/category', 'Data master produk: tipe, satuan, kategori, atribut, daftar harga, tag, dan koleksi.', 'Ini Master Produk. Kategori, satuan, dan daftar harga diatur di sini.'),
                $child('Product Type', '/product/nature', 'Jenis produk: barang jadi, bahan baku, jasa, dan non-stok.', 'Ini Product Type. Jenis barang diatur di sini.'),
                $child('Unit', '/product/satuan', 'Satuan hitung produk: pcs, box, kg, dan konversi.', 'Ini Unit. Satuan hitung barang ada di sini.', ['Satuan']),
                $child('Category', '/product/category', 'Kategori produk untuk filter stok, POS, dan laporan.', 'Ini Category. Pengelompokan barang ada di sini.'),
                $child('Attribute', '/product/attribute', 'Atribut varian: warna, ukuran, dan nilai pilihannya.', 'Ini Attribute. Varian produk diatur di sini.'),
                $child('Price Lists', '/product/price-list', 'Daftar harga yang bisa dipasangkan ke grup pelanggan.', 'Ini Price Lists. Daftar harga jual ada di sini.'),
                $child('Tag', '/product/tag', 'Tag produk untuk pencarian dan filter.', 'Ini Tag. Label produk diatur di sini.'),
                $child('Collection', '/product/collection', 'Koleksi produk untuk bundling tampilan.', 'Ini Collection. Koleksi barang dikelompokkan di sini.'),
                $child('Items', '/product/items', 'Master item: SKU, nama, varian, dan atribut produk.', 'Ini Items. Katalog barang utama ada di sini.', ['Product Item']),
                $child('Inventory', '/product/stock', 'Stok on-hand, stock opname, dan stock adjustment.', 'Ini Inventory. Stok gudang, opname, dan penyesuaian ada di sini.'),
                $child('Stock', '/product/stock', 'Stok on-hand per gudang dan SKU.', 'Ini Stock. Stok gudang terlihat di sini.'),
                $child('Stock Opname', '/product/stock-opname', 'Hitung fisik stok dan catat selisih.', 'Ini Stock Opname. Hitung fisik stok dari sini.'),
                $child('Stock Adjustment', '/product/stock-adjustment', 'Penyesuaian stok plus atau minus dengan alasan.', 'Ini Stock Adjustment. Penyesuaian stok dicatat di sini.'),
                $child('Pricing', '/product/price', 'Harga jual per item atau grup pelanggan.', 'Ini Pricing. Harga jual diatur dari sini.'),
                $child('Product Price', '/product/price', 'Harga jual per SKU.', 'Ini Product Price. Harga per barang diisi di sini.'),
                $child('Purchasing', '/product/purchase-order', 'Purchase Order ke supplier, penerimaan barang, dan invoice pembelian.', 'Ini Purchasing. Order ke supplier dan penerimaan barang dicatat di sini.'),
                $child('Supplier', '/master-data/supplier', 'Master pemasok untuk pembelian.', 'Ini Supplier. Data pemasok ada di sini.'),
                $child('Purchase Order', '/product/purchase-order', 'Purchase Order ke supplier dan penerimaan barang.', 'Ini Purchase Order. Pesanan beli dan penerimaan ada di sini.'),
                $child('Invoice', '/product/purchase-invoice', 'Invoice pembelian dan pembayaran ke supplier.', 'Ini Invoice pembelian. Tagihan supplier dicatat di sini.'),
                $child('Production', '/production', 'Production Order dan Bill of Materials untuk barang jadi dari bahan baku.', 'Ini Production. Produksi barang dari bahan baku diatur di sini.'),
                $child('Bill of Materials', '/bom', 'Resep bahan baku per barang jadi.', 'Ini Bill of Materials. Resep produksi ada di sini.'),
                $child('Production Order', '/production', 'Perintah produksi in-house: mulai, selesai, terima hasil.', 'Ini Production Order. Perintah produksi in-house ada di sini.', ['Production In-House']),
                $child('Distribution', '/marketing-allocation', 'Alokasi stok ke gudang marketing untuk distribusi partner.', 'Ini Distribution. Alokasi stok ke marketing ada di sini.'),
                $child('Marketing Allocation', '/marketing-allocation', 'Pindah stok gudang produk ke gudang marketing.', 'Ini Marketing Allocation. Stok untuk marketing dialokasikan di sini.'),
            ],
        ],

        'stock' => [
            'label' => 'Inventory',
            'blurb' => 'Stok gudang: on-hand, stock opname, dan stock adjustment.',
            'voice' => 'Ini ruang Inventory. Stok on-hand, opname, dan penyesuaian ada di sini.',
            'menu_names' => ['Inventory', 'Stock', 'Stock Opname', 'Stock Adjustment'],
            'prefixes' => ['/product/stock', '/product/stock-opname', '/product/stock-adjustment'],
            'url' => '/product/stock',
            'selector' => '#layout-menu a.menu-link[href*="/product/stock"], #layout-menu a.menu-link[data-menu="Inventory"], #layout-menu a.menu-link[data-menu="Stock"]',
            'docs_query' => 'stok gudang stock opname stock adjustment inventory',
            'children' => [
                $child('Stock', '/product/stock', 'Stok on-hand per gudang dan SKU.', 'Ini Stock. Stok gudang terlihat di sini.'),
                $child('Stock Opname', '/product/stock-opname', 'Hitung fisik stok dan catat selisih.', 'Ini Stock Opname. Hitung fisik stok dari sini.'),
                $child('Stock Adjustment', '/product/stock-adjustment', 'Penyesuaian stok plus atau minus dengan alasan.', 'Ini Stock Adjustment. Penyesuaian stok dicatat di sini.'),
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
                $child('Method Payment', '/transaction/method-payment', 'Metode pembayaran yang tersedia di kasir: tunai, transfer, QRIS, e-wallet.', 'Ini Method Payment. Cara bayar yang tersedia di kasir diatur di sini.'),
                $child('POS', '/transaction/pos', 'Layar kasir: cari produk, tambah ke keranjang, pilih pelanggan, lalu bayar.', 'Ini kasir POS. Pilih barang, masukkan ke keranjang, lalu tekan Bayar.'),
                $child(
                    'Transaction',
                    '/transaction',
                    'Riwayat semua transaksi kasir yang sudah selesai.',
                    'Ini riwayat transaksi. Semua penjualan kasir tercatat di sini.',
                    [],
                    '#layout-menu a.menu-link[href$="/transaction"], #layout-menu a.menu-link[href="/transaction"], #layout-menu a.menu-link[data-menu="Transaction"]',
                ),
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
                $child('Marketing Category', '/marketing/categories', 'Kategori aset pemasaran.', 'Ini Marketing Category. Pengelompokan aset promosi ada di sini.'),
                $child('Marketing Assets', '/marketing/assets', 'Daftar aset pemasaran yang bisa digunakan partner.', 'Ini Marketing Assets. Bahan promosi untuk partner ada di sini.'),
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
                $child('Course', '/training/courses', 'Daftar kursus yang tersedia untuk partner.', 'Ini Course. Daftar kursus untuk partner ada di sini.'),
                $child('Course Category', '/training/categories', 'Kategori kursus akademi.', 'Ini Course Category. Pengelompokan kursus ada di sini.'),
                $child('Academy', '/academy', 'Portal akademi untuk partner mengakses materi kursus.', 'Ini Academy. Portal kursus untuk partner bisa diakses dari sini.', ['Academy (Portal Partner)']),
                $child('Pengaturan Academy', '/training/settings', 'Pengaturan tampilan dan perilaku akademi.', 'Ini Pengaturan Academy. Konfigurasi portal kursus ada di sini.'),
            ],
        ],

        'accounting' => [
            'label' => 'Accounting',
            'blurb' => 'Akuntansi lengkap: COA, jurnal, kas & bank, laporan keuangan.',
            'voice' => 'Ini ruang Accounting. COA, jurnal, kas dan bank, sampai laporan keuangan ada di sini.',
            'menu_names' => [
                'Accounting', 'Chart of Accounts', 'Cash Flow Category', 'CF Category',
                'Account Mapping', 'Fiscal Calendar', 'Beginning Balance', 'Journal Entry',
                'Jurnal Umum', 'Cash & Bank', 'General Ledger', 'Balance Sheet',
                'Income Statement', 'Cash Flow',
            ],
            'prefixes' => ['/finance'],
            'url' => '/finance/chart-of-accounts',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Accounting"], #layout-menu a.menu-link[data-menu="Accounting"]',
            'docs_query' => 'modul akuntansi COA jurnal kas bank laporan keuangan',
            'children' => [
                $child('Chart of Accounts', '/finance/chart-of-accounts', 'Bagan akun (COA) — dasar seluruh pencatatan keuangan.', 'Ini Chart of Accounts. Bagan akun diatur di sini.'),
                $child('Cash Flow Category', '/finance/cash-flow-category', 'Kategori arus kas untuk laporan Cash Flow.', 'Ini Cash Flow Category. Pengelompokan arus kas diatur di sini.', ['CF Category']),
                $child('Account Mapping', '/finance/account-mapping', 'Pemetaan akun otomatis: penjualan, pembelian, stok, ke COA.', 'Ini Account Mapping. Akun otomatis untuk transaksi dihubungkan di sini.'),
                $child('Fiscal Calendar', '/finance/fiscal-calendar', 'Kalender fiskal dan penutupan periode.', 'Ini Fiscal Calendar. Periode buku dan tutup buku diatur di sini.'),
                $child('Beginning Balance', '/finance/beginning-balance', 'Saldo awal akun per kalender fiskal.', 'Ini Beginning Balance. Saldo awal akun diisi di sini.'),
                $child('Journal Entry', '/finance/journal-entry', 'Jurnal transaksi akuntansi manual.', 'Ini Journal Entry. Pencatatan jurnal manual ada di sini.'),
                $child('Jurnal Umum', '/finance/jurnal-umum', 'Daftar jurnal yang sudah tercatat, termasuk jurnal otomatis.', 'Ini Jurnal Umum. Semua jurnal tercatat bisa dibaca dari sini.'),
                $child('Cash & Bank', '/finance/cash-bank', 'Mutasi kas dan rekening bank cabang.', 'Ini Cash dan Bank. Mutasi kas dan rekening dikelola di sini.'),
                $child('General Ledger', '/finance/general-ledger', 'Buku besar: mutasi per akun.', 'Ini General Ledger. Mutasi tiap akun ada di sini.'),
                $child('Balance Sheet', '/finance/balance-sheet', 'Neraca: aset, liabilitas, dan ekuitas.', 'Ini Balance Sheet, atau neraca.'),
                $child('Income Statement', '/finance/income-statement', 'Laporan laba rugi.', 'Ini Income Statement, laporan laba rugi.'),
                $child('Cash Flow', '/finance/cash-flow', 'Laporan arus kas.', 'Ini Cash Flow, laporan arus kas.'),
            ],
        ],

        'reporting' => [
            'label' => 'Reporting',
            'blurb' => 'Laporan komprehensif: penjualan, stok, pembelian, dan performa bisnis.',
            'voice' => 'Ini Reporting. Laporan penjualan, stok, dan pembelian lengkap ada di sini.',
            'menu_names' => [
                'Reporting', 'Sales & Transaction', 'Inventory & Warehouse', 'Executive & Overview', 'Purchasing',
                'Executive Dashboard', 'KPI Dashboard', 'Sales Summary', 'Stock On Hand', 'PO Summary',
            ],
            'prefixes' => ['/reporting'],
            'url' => '/reporting/executive-dashboard',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Reporting"], #layout-menu a.menu-link[href*="/reporting"]',
            'docs_query' => 'modul reporting penjualan stok pembelian KPI laporan',
            'children' => [
                $child('Executive & Overview', '/reporting/executive-dashboard', 'Dashboard eksekutif, KPI, dan perbandingan performa outlet.', 'Ini laporan eksekutif. KPI dan performa bisnis ada di sini.'),
                $report('Executive Dashboard', '/reporting/executive-dashboard'),
                $report('KPI Dashboard', '/reporting/kpi-dashboard'),
                $report('Multi-Outlet Comparison', '/reporting/multi-outlet-comparison'),
                $report('Revenue Trend', '/reporting/revenue-trend'),
                $report('Profitability Overview', '/reporting/profitability-overview'),
                $report('Business Unit Performance', '/reporting/business-unit-performance'),
                $child('Purchasing', '/reporting/po-summary', 'Laporan pembelian: ringkasan PO, penerimaan, aging, dan performa supplier.', 'Ini laporan pembelian. PO, GRN, dan supplier ada di sini.', ['PO Summary']),
                $report('PO Summary', '/reporting/po-summary'),
                $report('PO Receiving / GRN', '/reporting/po-receiving-grn'),
                $report('PO Aging / Open PO', '/reporting/po-aging-open-po'),
                $report('Purchase by Supplier', '/reporting/purchase-by-supplier'),
                $report('Purchase Price History', '/reporting/purchase-price-history'),
                $report('Supplier Performance', '/reporting/supplier-performance'),
                $child('Inventory & Warehouse', '/reporting/stock-on-hand', 'Laporan stok: on-hand, adjustment, slow stock, valuation.', 'Ini laporan stok. Stok gudang dan pergerakannya ada di sini.', ['Stock On Hand']),
                $report('Stock On Hand', '/reporting/stock-on-hand'),
                $report('Stock Card / Movement', '/reporting/stock-card-movement'),
                $report('Stock Valuation', '/reporting/stock-valuation'),
                $report('Low Stock / Reorder Alert', '/reporting/low-stock-reorder-alert'),
                $report('Stock Opname Variance', '/reporting/stock-opname-variance'),
                $report('Stock Adjustment', '/reporting/stock-adjustment-report'),
                $report('Slow / Dead Stock', '/reporting/slow-dead-stock'),
                $report('Inventory Aging', '/reporting/inventory-aging'),
                $report('Negative Stock Analysis', '/reporting/negative-stock-analysis'),
                $child('Sales & Transaction', '/reporting/sales-summary', 'Laporan penjualan: ringkasan, detail transaksi, per kasir, per outlet.', 'Ini laporan penjualan. Transaksi, kasir, outlet, semuanya ada di sini.', ['Sales Summary']),
                $report('Sales Summary', '/reporting/sales-summary'),
                $report('Transaction Detail', '/reporting/transaction-detail'),
                $report('Sales by Payment Method', '/reporting/sales-by-payment-method'),
                $report('Sales by Cashier', '/reporting/sales-by-cashier'),
                $report('Sales by Outlet', '/reporting/sales-by-outlet'),
                $report('Sales by Customer', '/reporting/sales-by-customer'),
                $report('Sales Return & Refund', '/reporting/sales-return-refund'),
                $report('Top Products & Categories', '/reporting/top-products-categories'),
                $report('Hourly / Daily Trend', '/reporting/hourly-daily-trend'),
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
            'children' => [
                $child('Membership Configuration', '/crm/membership-configuration', 'Jenjang membership dan aturan poin loyalitas.', 'Ini Membership Configuration. Jenjang member dan poin diatur di sini.'),
                $child('Promotions', '/promotions', 'Aturan promo kasir, misalnya beli X dapat Y.', 'Ini Promotions. Promo kasir diatur dari sini.'),
            ],
        ],

        'settings' => [
            'label' => 'Settings',
            'blurb' => 'Pengaturan sistem: role, menu, parameter, tema, payment gateway, dan konfigurasi AI.',
            'voice' => 'Ini Settings. Role, menu, tema, payment gateway, dan konfigurasi AI ada di sini.',
            'menu_names' => [
                'Settings', 'Menu', 'Menu Management', 'Role', 'Roles Management',
                'Configuration', 'Dashboard Configuration', 'Notification Config',
                'Parameter', 'Master Ongkir', 'AI Chat Configuration',
                'Payment Gateway Configuration', 'Payment Gateway', 'Appearance & Theme',
            ],
            'prefixes' => ['/settings', '/access-management', '/master-data'],
            'url' => '/settings/theme-configuration',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Settings"], #layout-menu a.menu-link[data-menu="Settings"]',
            'docs_query' => 'settings role menu tema konfigurasi AI payment gateway permission',
            'children' => [
                $child('Menu', '/access-management/menu', 'Susunan sidebar dan izin per menu.', 'Ini Menu Management. Isi dan urutan sidebar diatur di sini.', ['Menu Management']),
                $child('Role', '/access-management/roles', 'Role dan izin menu per pengguna.', 'Ini Role. Izin akses per peran diatur dari sini.', ['Roles Management']),
                $child('Configuration', '/access-management/dashboard-configuration', 'Kartu yang tampil di Dashboard per role.', 'Ini Dashboard Configuration. Kartu dashboard per peran diatur di sini.', ['Dashboard Configuration']),
                $child('Notification Config', '/access-management/notifications/config', 'Kanal dan template notifikasi.', 'Ini Notification Config. Notifikasi sistem diatur di sini.'),
                $child('Parameter', '/master-data/parameter', 'Parameter master: status, tipe, dan opsi dropdown.', 'Ini Parameter. Opsi master data diatur di sini.'),
                $child('Master Ongkir', '/master-data/shipping-rate', 'Tarif ongkir per kota atau zona.', 'Ini Master Ongkir. Tarif kirim diatur di sini.'),
                $child('AI Chat Configuration', '/settings/ai-configuration', 'Nyala-mati widget chatbot dan pilihan provider AI.', 'Ini konfigurasi AI Chat. Widget asisten dan provider diatur di sini.'),
                $child('Payment Gateway Configuration', '/settings/payment-gateway-configuration', 'Konfigurasi Xendit dan metode pembayaran online.', 'Ini konfigurasi Payment Gateway. Xendit dan pembayaran online diatur di sini.', ['Payment Gateway']),
                $child('Appearance & Theme', '/settings/theme-configuration', 'Tema dan tampilan admin: warna, logo, dan gaya UI.', 'Ini Appearance dan Theme. Tampilan admin diatur di sini.'),
            ],
        ],

        'business' => [
            'label' => 'Business',
            'blurb' => 'Holding, company, cabang, dan gudang.',
            'voice' => 'Ini ruang Business. Holding, company, cabang, dan gudang diatur di sini.',
            'menu_names' => ['Business', 'Holding', 'Company', 'Branch', 'Warehouse', 'Gudang', 'Struktur Bisnis'],
            'prefixes' => ['/business'],
            'url' => '/business/warehouse',
            'selector' => '#layout-menu a.menu-link.menu-toggle[data-menu="Business"], #layout-menu a.menu-link[data-menu="Business"]',
            'docs_query' => 'holding company branch warehouse cabang gudang',
            'children' => [
                $child('Holding', '/business/holding', 'Holding: induk grup perusahaan.', 'Ini Holding. Induk grup perusahaan diatur di sini.'),
                $child('Company', '/business/company', 'Company di bawah holding.', 'Ini Company. Perusahaan di bawah holding ada di sini.'),
                $child('Branch', '/business/branch', 'Cabang operasional, tempat kasir dan stok berjalan.', 'Ini Branch. Cabang operasional diatur di sini.'),
                $child('Warehouse', '/business/warehouse', 'Gudang penyimpanan stok per cabang.', 'Ini Warehouse, atau Gudang. Lokasi simpan stok ada di sini.', ['Gudang']),
            ],
        ],

        'distribution' => [
            'label' => 'Distribution',
            'blurb' => 'Operasi distribusi: inbound, replenishment agen, dan laporan HPP. Halaman ini tidak selalu tampil di sidebar.',
            'voice' => 'Ini ruang distribusi. Order agen, stok masuk, dan HPP dicatat di sini.',
            'menu_names' => ['Replenishment', 'Agen Order', 'Stok Masuk', 'Inbound', 'Laporan HPP'],
            'prefixes' => ['/agen-order', '/replenishment', '/inbound', '/laporan-hpp'],
            'url' => '/agen-order',
            'selector' => '#layout-menu a.menu-link[href*="/agen-order"], #layout-menu a.menu-link[data-menu="Distribution"]',
            'docs_query' => 'replenishment agen order inbound stok masuk laporan HPP distribusi',
            'children' => [
                $child('Replenishment', '/agen-order', 'Order replenishment distributor ke agen.', 'Ini Agen Order. Permintaan stok agen diproses di sini.', ['Agen Order']),
                $child('Inbound', '/inbound', 'Stok masuk bahan baku atau barang ke gudang.', 'Ini Stok Masuk. Penerimaan inbound dicatat di sini.', ['Stok Masuk']),
                $child('Laporan HPP', '/laporan-hpp', 'Laporan harga pokok produksi.', 'Ini Laporan HPP. Harga pokok produksi ada di sini.'),
            ],
        ],

        'account' => [
            'label' => 'Profil',
            'blurb' => 'Akun pengguna: profil, ganti password, dan pindah cabang. Tidak ada di sidebar; dibuka dari navbar.',
            'voice' => 'Ini halaman profil akunmu. Nama, password, dan cabang aktif diatur di sini.',
            'menu_names' => ['Profil', 'Profile', 'Account', 'Ganti Password', 'Pindah Cabang'],
            'prefixes' => ['/account'],
            'url' => '/account',
            'selector' => '#layout-navbar a[href*="/account"], .navbar a[href*="/account"], #layout-navbar',
            'docs_query' => 'profil akun ganti password pindah cabang',
            'page_spots' => [
                'index' => [
                    [
                        'key' => 'title',
                        'label' => 'Profil',
                        'blurb' => 'Halaman akun: ubah profil, password, atau cabang aktif.',
                        'voice' => 'Ini halaman profil. Data akunmu ada di sini.',
                        'selector' => '.content-wrapper h4, .content-wrapper h5, .content-wrapper .card-header',
                    ],
                    [
                        'key' => 'add',
                        'label' => 'Form akun',
                        'blurb' => 'Isian profil, password, atau pilih cabang.',
                        'voice' => 'Isi data akun di form ini, lalu simpan.',
                        'selector' => '.content-wrapper form, #postForm, .content-wrapper .card',
                    ],
                ],
            ],
        ],
    ],
];
