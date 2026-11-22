# Pengujian CINEM4

## CI yang tersedia

GitHub Actions di branch `main` menjalankan `composer ci:check`: format JS, TypeScript, Pint, PHPStan, dan PHPUnit/feature tests dengan SQLite serta HTTP mock Midtrans.

## Jalankan lokal

```bat
cd C:\laragon\www\cinem4-laravel
git status
git pull origin main
php artisan migrate
php artisan test
npm run check
composer run dev
```

Jangan jalankan `migrate:fresh` pada database development yang harus dipertahankan.

## Regression test keamanan/payment

- Akun tamu/nonadmin tidak dapat mengelola film, bioskop, jadwal, atau verifikasi QR.
- Pelanggan lain tidak dapat membaca booking, memulai pembayaran, membatalkan reservasi, atau mengambil QR.
- Pemesanan kursi yang sama ditolak; hold kedaluwarsa tanpa Midtrans dapat dilepas. Booking dengan order Midtrans aktif tidak dilepas hanya karena timer lokal.
- Notifikasi Midtrans tanpa signature sah ditolak, status harus diverifikasi dari API, nominal harus cocok, callback browser tidak bisa memalsukan status paid.
- Notifikasi pembayaran berulang tidak membuat tiket ganda; QR hanya untuk paid.
- **Audit regression:** refund/chargeback atau payment review membuat QR tidak dapat diberikan dan admin tidak boleh redeem, walaupun status booking masih paid.
- Tiket yang sudah redeemed tidak dapat dipakai ulang.

## Manual test yang masih diperlukan

- Browser nyata: bayar Midtrans Sandbox, callback finish ke CINEM4, cek status dan QR.
- Webhook lewat endpoint HTTPS publik dengan status sukses, pending, expire, refund, duplikasi, dan out-of-order.
- Putus sambungan provider setelah membuat order lalu coba recover pembayaran tanpa membuat double charge.
- Dua user paralel memesan kursi sama pada TiDB; simultan webhook vs pembatalan dan dua petugas redeem tiket sama.
- Migrasi database TiDB isolasi, backup/restore, dan cek kolasi, foreign key, waktu/zona, serta transaksi.
- Periksa accessibility, keyboard focus, dan tampilan mobile setelah frontend final.
