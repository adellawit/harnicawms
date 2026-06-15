# WMS Agent Chat — Setup & Usage

> Floating AI chat widget di admin panel WMS 3.0 (read-only MVP).

## Fitur MVP

- Cari produk (nama/SKU) + harga + stok
- Cek stok produk per cabang aktif
- Cari customer
- Ringkasan penjualan per tanggal
- Bantuan / contoh pertanyaan

**Read-only** — tidak membuat transaksi atau mengubah data.

---

## Environment

Tambahkan ke `.env`:

```env
AGENT_ENABLED=true
DEEPSEEK_ENABLED=true
DEEPSEEK_API_KEY=sk-...

# Optional
AGENT_MAX_TOOL_ROUNDS=5
AGENT_MAX_MESSAGE_LENGTH=2000
AGENT_RATE_LIMIT_PER_MINUTE=30
AGENT_PERMISSION_MENU=AI Assistant
```

---

## Database

```bash
php artisan migrate
php artisan db:seed --class=AgentMenuSeeder
```

Tabel baru (schema `auth`):

- `agent_conversations`
- `agent_messages`
- `agent_tool_logs`

---

## Permission

Menu **AI Assistant** (`is_read`) mengontrol:

1. Akses API `/agent/*`
2. Visibility widget chat di layout admin

User perlu **logout & login ulang** setelah seed agar session permission ter-update.

---

## Testing CLI

```bash
php artisan agent:chat "stok kopi arabica" --user-id=<UUID_USER>
```

---

## API Endpoints

| Method | Route | Keterangan |
|--------|-------|------------|
| POST | `/agent/chat` | Kirim pesan |
| GET | `/agent/conversations` | List riwayat |
| POST | `/agent/conversations/new` | Chat baru |
| GET | `/agent/conversations/{id}/messages` | Load history |
| DELETE | `/agent/conversations/{id}` | Hapus conversation |

---

## Contoh Pertanyaan

- `Cari produk kopi arabica`
- `Stok susu UHT berapa?`
- `Customer Budi`
- `Penjualan hari ini`
- `Kamu bisa apa?`

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Widget tidak muncul | `AGENT_ENABLED=true`, user punya permission AI Assistant |
| 403 Unauthorized | Seed menu + re-login |
| DeepSeek error | Cek `DEEPSEEK_API_KEY` dan koneksi internet |
| Cabang tidak diset | User pilih cabang di Profil → Ganti Cabang |
