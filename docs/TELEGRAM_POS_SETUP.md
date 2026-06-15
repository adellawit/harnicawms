# Telegram POS + DeepSeek — Panduan Setup & Deploy

> Domain production: **https://pos.argoes.site**

Dokumen ini menjelaskan langkah deploy aplikasi WMS 3.0 dan mengaktifkan **Telegram Bot POS** dengan parser **DeepSeek AI**.

---

## Bot Production

| Item | Nilai |
|------|-------|
| Bot Telegram | [@pos_www_bot](https://t.me/pos_www_bot) |
| Webhook URL | **https://pos.argoes.site/webhooks/telegram** |

---

| Fungsi | URL |
|--------|-----|
| Aplikasi WMS | https://pos.argoes.site |
| Login | https://pos.argoes.site/login |
| Health check Laravel | https://pos.argoes.site/up |
| **Webhook Telegram** | **https://pos.argoes.site/webhooks/telegram** |
| Webhook Xendit | https://pos.argoes.site/webhooks/xendit |
| POS Web (admin) | https://pos.argoes.site/transaction/pos |

---

## Prasyarat

- Server dengan **PHP 8.2+**, **Composer**, **PostgreSQL**
- **Nginx** (atau Apache) + **SSL HTTPS** aktif di `pos.argoes.site`
- Bot Telegram dari **@BotFather**
- API key **DeepSeek** dari [platform.deepseek.com](https://platform.deepseek.com)
- Akses SSH ke server production

---

## Step 1 — Deploy Laravel ke Server

### 1.1 Clone / upload project

```bash
cd /var/www
git clone <repo-url> wms-3.0
cd wms-3.0
```

### 1.2 Install dependency

```bash
composer install --no-dev --optimize-autoloader
```

### 1.3 Setup environment

```bash
cp .env-production .env
nano .env
```

Isi minimal yang wajib:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pos.argoes.site

DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

TELEGRAM_ENABLED=true
TELEGRAM_BOT_TOKEN=          # dari @BotFather
TELEGRAM_WEBHOOK_SECRET=     # string random (min 16 karakter)
TELEGRAM_MOCK=false
TELEGRAM_AI_ENABLED=true

DEEPSEEK_ENABLED=true
DEEPSEEK_API_KEY=            # dari dashboard DeepSeek
```

Generate application key:

```bash
php artisan key:generate
```

### 1.4 Database migrate

```bash
php artisan migrate:all --force
```

Opsional — seed data awal (user, produk, payment):

```bash
php artisan db:seed --force
php artisan db:seed --class=TelegramAccountSeeder --force
```

### 1.5 Cache & permission

```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 1.6 Nginx — document root ke `public/`

```nginx
server {
    listen 443 ssl http2;
    server_name pos.argoes.site;

    root /var/www/wms-3.0/public;
    index index.php;

    ssl_certificate     /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

```bash
sudo nginx -t
sudo systemctl reload nginx
```

### 1.7 Verifikasi deploy

```bash
curl -I https://pos.argoes.site/up
```

Browser: buka https://pos.argoes.site/login — harus tampil halaman login WMS, **bukan** halaman default nginx.

---

## Step 2 — Buat Bot Telegram

1. Buka Telegram, cari **@BotFather**
2. Kirim `/newbot`
3. Ikuti instruksi (nama bot + username, harus diakhiri `bot`)
4. Copy **HTTP API Token**
5. Paste ke `.env`:

```env
TELEGRAM_BOT_TOKEN=123456789:AAFxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

```bash
php artisan config:cache
```

---

## Step 3 — Daftarkan Webhook Telegram

Webhook adalah cara Telegram **mengirim pesan user** ke aplikasi Laravel Anda.

**Endpoint webhook aplikasi:**

```
https://pos.argoes.site/webhooks/telegram
```

### 3.1 Set secret token

Di `.env`, buat string random:

```env
TELEGRAM_WEBHOOK_SECRET=MyRandomSecret2026Prod
```

```bash
php artisan config:cache
```

### 3.2 Register webhook (di server)

```bash
php artisan telegram:set-webhook https://pos.argoes.site/webhooks/telegram
```

Output sukses:

```
Webhook registered: https://pos.argoes.site/webhooks/telegram
Pending updates: 0
```

### 3.3 Verifikasi webhook

```bash
curl "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/getWebhookInfo"
```

Pastikan JSON response:

```json
{
  "ok": true,
  "result": {
    "url": "https://pos.argoes.site/webhooks/telegram",
    "has_custom_certificate": false,
    "pending_update_count": 0,
    "last_error_message": ""
  }
}
```

### 3.4 Alternatif manual (curl)

```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://pos.argoes.site/webhooks/telegram",
    "secret_token": "MyRandomSecret2026Prod",
    "allowed_updates": ["message", "callback_query", "edited_message"]
  }'
```

> `secret_token` **harus sama** dengan `TELEGRAM_WEBHOOK_SECRET` di `.env`.

### 3.5 Hapus webhook (jika perlu reset)

```bash
php artisan telegram:set-webhook --delete
```

---

## Step 4 — Setup DeepSeek AI

1. Daftar/login di [platform.deepseek.com](https://platform.deepseek.com)
2. Buat **API Key**
3. Isi `.env`:

```env
DEEPSEEK_ENABLED=true
DEEPSEEK_API_KEY=sk-xxxxxxxx
DEEPSEEK_MODEL=deepseek-chat
DEEPSEEK_USE_STRICT_TOOLS=true
```

4. Test di server:

```bash
php artisan config:cache
php artisan deepseek:parse "3 kopi untuk agus tunai"
```

Harus keluar JSON dengan `"intent": "create_transaction"`.

---

## Step 5 — Hubungkan Akun Kasir

Kasir harus link akun WMS ke Telegram sebelum bisa input transaksi.

### 5.1 Lihat daftar user

```bash
php artisan telegram:generate-link-code --list
```

### 5.2 Generate kode link

```bash
php artisan telegram:generate-link-code user01@wit.id
```

Output contoh:

```
Code: X7K2M9PQ
Expires : Tidak expired (sampai dipakai)
Kasir kirim ke bot: /link X7K2M9PQ
```

**Masa berlaku kode** (`.env`):

```env
TELEGRAM_LINK_CODE_TTL=0    # tidak expired (valid sampai dipakai sekali)
TELEGRAM_LINK_CODE_TTL=60   # expired setelah 60 menit (default config)
```

> Kode tetap **one-time**: setelah `/link` sukses, generate kode baru jika perlu link ulang.

### 5.3 Di Telegram (HP kasir)

1. Buka bot Anda di Telegram
2. Kirim:

```
/start
/link X7K2M9PQ
```

3. Bot membalas: *Akun berhasil dihubungkan...*

> Pastikan user punya **branch aktif** (`current_business_unit_id`) dan cabang tersebut punya produk + metode **CASH**.

---

## Step 6 — Test Input Transaksi

### 6.1 Via Telegram (production)

```
/transaksi 3 kopi arabica 2 susu uht untuk Budi tunai
```

Flow:

1. DeepSeek parse pesan
2. Sistem cari produk & customer di DB
3. Bot tampilkan **ringkasan + tombol Konfirmasi**
4. Tap **Konfirmasi** → transaksi tersimpan (nomor **TGM-**)

Perintah bot lain:

| Perintah | Fungsi |
|----------|--------|
| `/help` | Bantuan |
| `/menu [cari]` | Daftar produk cabang (contoh: `/menu black`) |
| `/produk [cari]` | Alias `/menu` |
| `/status` | Lihat draft aktif |
| `/batal` | Batalkan draft |
| `/transaksi ...` | Buat transaksi |

**Metode pembayaran:**

- Kosongkan kata bayar di pesan → bot tampilkan pilihan (Cash, Transfer, QRIS, E-Wallet)
- Sebut di pesan: `tunai`, `transfer`, `qris`, `ewallet`
- Transfer/E-Wallet → pilih channel (BCA, OVO, dll.) jika ada beberapa
- Non-cash via **Xendit** → bot kirim link invoice; order selesai setelah webhook Xendit

Env:

```env
TELEGRAM_ALLOWED_PAYMENT_CODES=CASH,TRANSFER,QRIS,EWALLET
XENDIT_ENABLED=true
XENDIT_SECRET_KEY=...
XENDIT_WEBHOOK_TOKEN=...
```

### 6.2 Via CLI (debug di server)

```bash
# Parse AI saja
php artisan deepseek:parse "3 kopi untuk agus tunai"

# Simulasi full flow (dry-run)
php artisan telegram:simulate user01@wit.id "3 latte untuk agus tunai"

# Simulasi + simpan ke DB
php artisan telegram:simulate user01@wit.id "3 latte untuk agus tunai" --submit
```

---

## Step 7 — Verifikasi Transaksi di WMS

1. Login https://pos.argoes.site/login
2. Menu **Transaction** → list transaksi
3. Cari order dengan prefix **TGM-** dan `order_type` = `telegram`

---

## Troubleshooting

| Gejala | Penyebab | Solusi |
|--------|----------|--------|
| Halaman nginx default | Nginx belum point ke Laravel `public/` | Fix nginx config, reload |
| Bot tidak merespons | Webhook belum terdaftar / error | `getWebhookInfo`, cek log |
| Webhook 401 | Secret tidak cocok | Samakan `TELEGRAM_WEBHOOK_SECRET` & `secret_token` |
| `TELEGRAM_BOT_TOKEN belum diset` | Token kosong | Isi `.env` + `config:cache` |
| AI tidak jalan | DeepSeek key invalid | `php artisan deepseek:parse "..."` |
| Produk tidak ditemukan | Nama tidak match DB cabang | Cek produk di POS web cabang yang sama |
| Akun belum terhubung | Belum `/link` | Generate link code ulang |
| Strict tool error DeepSeek | Schema strict gagal | Set `DEEPSEEK_USE_STRICT_TOOLS=false` |

Log error:

```bash
tail -f storage/logs/laravel.log
```

---

## Checklist Deploy Cepat

- [ ] https://pos.argoes.site/login bisa dibuka
- [ ] https://pos.argoes.site/up return 200
- [ ] `.env` production (`APP_DEBUG=false`, `TELEGRAM_MOCK=false`)
- [ ] `php artisan migrate:all --force`
- [ ] `TELEGRAM_BOT_TOKEN` terisi
- [ ] `DEEPSEEK_API_KEY` terisi
- [ ] `php artisan telegram:set-webhook https://pos.argoes.site/webhooks/telegram`
- [ ] `getWebhookInfo` → url benar, no error
- [ ] `php artisan telegram:generate-link-code user01@wit.id`
- [ ] `/link KODE` di Telegram sukses
- [ ] `/transaksi ...` → konfirmasi → order **TGM-** tercreate

---

## Referensi File di Project

| File | Fungsi |
|------|--------|
| `routes/web.php` | Route `POST /webhooks/telegram` |
| `app/Http/Controllers/TelegramWebhookController.php` | Handler webhook |
| `app/Services/Telegram/TelegramConversationService.php` | Logic bot |
| `app/Services/DeepSeek/DeepSeekTransactionParser.php` | Parser AI |
| `config/telegram.php` | Config Telegram |
| `config/deepseek.php` | Config DeepSeek |
| `.env-production` | Template env production |
