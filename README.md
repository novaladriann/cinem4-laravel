# CINEM4 — Laravel + Vue

Modernisasi aplikasi pemesanan tiket bioskop CINEM4 dari PHP Native ke Laravel 13, Vue 3, Inertia.js, dan Tailwind CSS.

## Status saat ini

- Fondasi Laravel + Vue Starter Kit tanpa Teams Support.
- Autentikasi bawaan starter kit tersedia; belum disesuaikan dengan akun CINEM4 lama.
- Belum ada fitur katalog, jadwal, booking, pembayaran, maupun database CINEM4 lama yang dimigrasikan.
- Database development bawaan menggunakan SQLite. **Jangan hubungkan ke database TiDB berisi data existing lalu menjalankan migration tanpa audit.**

Source PHP Native tetap berada di [novaladriann/cinem4](https://github.com/novaladriann/cinem4).

## Menjalankan project

Persyaratan: PHP 8.3+, Composer, Node.js, dan npm.

```bash
composer install
npm ci
```

Salin `.env.example` ke `.env`, lalu jalankan:

```bash
php artisan key:generate
php artisan migrate
composer run dev
```

Jalankan `php artisan migrate` hanya setelah memastikan `DB_CONNECTION` mengarah ke **database development kosong yang memang boleh dimigrasikan**. Proses pembuatan project pertama dapat sudah menjalankan migrasi SQLite.

Untuk pemeriksaan otomatis:

```bash
npm run check
npm run types:check
php artisan test
```

## Rencana migrasi

Lihat [docs/migration-plan.md](docs/migration-plan.md) untuk pemetaan domain dan aturan keamanan data. Tahapan berikutnya adalah audit skema SQL CINEM4 PHP Native, bukan langsung mengubah tabel di TiDB.

## Secrets

Jangan commit `.env`, API key TMDB, kredensial TiDB, SMTP, atau server key Midtrans.
