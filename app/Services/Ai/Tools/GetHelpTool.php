<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AgentContext;

class GetHelpTool extends AbstractAgentTool
{
    public function name(): string
    {
        return 'get_help';
    }

    public function description(): string
    {
        return 'Return assistant capabilities and example questions for the WMS admin user.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [],
            'properties' => [],
        ];
    }

    public function requiredPermission(): ?array
    {
        return null;
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        return [
            'success' => true,
            'branch' => $this->branchLabel($context),
            'capabilities' => [
                'Menjelaskan aplikasi, alur bisnis, dan cara pakai fitur berdasarkan dokumentasi di docs/',
                'Menjadi tour guide halaman yang sedang dibuka (apa sih ini / jelasin halaman ini) dan tur modul (turin fiturnya)',
                'Membuka halaman admin dari chat (buka halaman kategori; buka stok minuman sudah ter-filter)',
                'Cari produk (nama/SKU) beserta harga dan stok',
                'Cek stok cabang aktif lewat get_stock: tampilkan stok / seluruh stok / stok semua = query kosong (ringkasan + 10 SKU); atau filter nama/SKU',
                'Cari customer berdasarkan nama',
                'Ringkasan penjualan per tanggal',
                'Menyiapkan draf penjualan tunai (item, customer, pembayaran) lalu minta konfirmasi user',
                'CRUD data sesuai izin menu langsung di chat (tanpa buka form): master HR termasuk karyawan, customer, partner, produk, gudang, bisnis, pembayaran, CRM, COA, settings, training, marketing',
                'Membuat agen partner dari chat (pendaftaran partner + Convert Agent dijalankan server: kode agen, gudang agen, akun login)',
                'Draf PO/jurnal/produksi/replenishment dan penyesuaian stok lewat mutasi (perlu konfirmasi di kartu)',
                'Hapus data dan Super Admin hanya setelah konfirmasi',
            ],
            'examples' => [
                'Apa sih ini?',
                'Jelasin halaman ini',
                'Turin fiturnya dong',
                'Buka halaman kategori',
                'Buka stok minuman',
                'Buka halamannya dong',
                'Aplikasi ini sebenarnya untuk apa?',
                'Gimana alur order agen?',
                'Tambahkan divisi Management',
                'Tambah karyawan Budi Santoso, email budi@wit.id, role Staff, status aktif, join hari ini',
                'Buat customer PT Maju',
                'Tambah produk Kopi Arabica, dijual',
                'Buat agen Toko Makmur Jaya, telepon 081200000000, kota Cirebon',
                'Buat draf replenishment untuk agen Toko Makmur Jaya',
                'Buat draf PO ke supplier PT Sumber',
                'Daftar produk',
                'Tambah gudang Cirebon',
                'Tampilkan stok',
                'Seluruh stok',
                'Stok semua',
                'Daftar stok',
                'Jual 2 kopi arabica tunai',
                'Jadikan semua stok barang jual 100',
            ],
            'limitations' => [
                'Transaksi penjualan dibuat lewat manage_sale + konfirmasi di chat',
                'Penjualan dari chat hanya mendukung pembayaran tunai di cabang aktif',
                'Stok hanya lewat mutasi (StockMutationService), bukan tulis kolom quantity',
                'Overview stok di chat = ringkasan cabang + 10 SKU (stok rendah dulu). Sisanya: sebut nama/SKU. Jangan alihkan ke halaman Stok',
                'PO/produksi/replenishment dari chat = draf; posting jurnal hanya jika seimbang',
                'Hapus dan Super Admin butuh konfirmasi di kartu',
                'Pengetahuan FAQ terbatas pada folder docs/',
            ],
        ];
    }
}
