# CINEM4 — Status Pengembangan (22 November 2026)

Dokumen ini menjadi titik awal saat melanjutkan pekerjaan. Perbarui setelah commit fitur atau perbaikan penting. Repository PHP Native lama dipertahankan sebagai referensi; **jangan membuat branch baru tanpa persetujuan pengguna**.

## Sudah diterapkan

- Laravel 13, Vue 3, Inertia 3, SQLite lokal, otentikasi dan email verification.
- TMDB: pencarian/impor film ke katalog; pengaturan unggulan dan status tayang oleh admin.
- Bioskop, studio, dan jadwal dengan deteksi bentrok berdasar durasi dan jeda persiapan.
- Booking kursi 10 menit, penguncian melalui `seat_locks` dengan primary key komposit, pembatalan dan riwayat.
- Midtrans **Sandbox**, bukan production: Snap redirect, webhook bertanda tangan terpisah, Get Status API, rekonsiliasi terjadwal dan payment review.
- E-ticket satu QR per booking dibayar, tampilan verifikasi admin, penandaan tiket terpakai.
- Audit backend awal: ticket QR dan redeem dicegah bila pembayaran masuk `payment_reviews` (refund/chargeback atau ketidaksesuaian lain).

## Pekerjaan yang belum selesai

1. **P0 — Verifikasi checkout Midtrans Sandbox pada browser nyata** termasuk callback finish, webhook via HTTPS publik, dan timeout. CI memakai mock Midtrans dan tidak membuktikan integrasi aktual.
2. **P0 — Uji konkurensi pada TiDB Cloud** terhadap dua reservasi serentak dan pembaruan status pembayaran; SQLite test saja tidak cukup.
3. **P1 — Pulihkan retry Snap jika respons pembuatan transaksi hilang**. `midtrans_order_id` tersimpan sebelum network request; bila respons pertama timeout, retry ke Snap dapat menerima duplicate-order dan URL checkout tidak tersedia. Rancang rekonsiliasi token/order berbasis respons provider; jangan membuat ID baru secara otomatis.
4. **P1 — Operasional payment review/refund**. Entri `payment_reviews` mengunci penggunaan tiket, tetapi belum ada workflow admin untuk review, refund, penutupan kasus, dan notifikasi ke pelanggan.
5. **P1 — Retensi data, monitoring, alerting, backup, recovery, dan uji deployment HTTPS**.
6. **P2 — E-ticket per orang/kursi**: saat ini satu QR mengwakili satu booking, termasuk beberapa kursi.
7. **P2 — Frontend final** ditunda sampai backend lebih stabil. Vue sekarang berfungsi untuk pengujian.

## Aturan kerja

- Periksa `git status` sebelum `git pull`; jangan menimpa perubahan lokal yang belum disimpan.
- Gunakan migration non-destruktif, jangan `migrate:fresh` untuk database berisi data.
- Jangan commit `.env`, Server Key, Signature Secret Key, atau dump transaksi/data pribadi.
- Setiap perubahan keamanan/pembayaran wajib punya regression test dan lulus CI sebelum diuji di Laragon.
- Jangan nyatakan siap produksi hanya berdasarkan CI. Butuh validasi Midtrans Sandbox aktual, HTTPS dan TiDB.
