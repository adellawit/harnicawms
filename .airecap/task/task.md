# POS Admin Ongkir

## Checklist
- [x] PosCheckoutService: shipping di total + persist meta
- [x] Endpoint `/transaction/pos/shipping-options`
- [x] UI kota + kurir + input Rp di footer cart
- [x] Agent POS tidak double-add shipping
- [x] Verifikasi syntax + route
- [x] `/transaction` history: kolom Ongkir + kurir/layanan/ETD
- [x] Detail: baris Ongkir + meta kurir
- [x] Invoice & surat jalan: ongkir + kurir

## Review
- Footer `/transaction/pos`: kota tujuan (typeahead) → kurir Master Ongkir → input Rp (bisa override)
- Total = subtotal + pajak − diskon − redeem + ongkir
- Kota asal dari `city_id` gudang sumber penjualan (`cityRef`)
- Agent POS tetap kirim `shipping_amount`; total tidak di-add dua kali
- History/detail/print menampilkan `shipping_amount` + `shippingMetaLabel()` (kurir · layanan · ETD)
