# Arsitektur Backend CINEM4

## Stack dan batas tanggung jawab

- Laravel 13: route, policy/Gate admin, validasi, transaksi, model Eloquent, webhook Midtrans, command scheduler.
- Vue 3 + Inertia 3: halaman pelanggan dan admin fungsional sementara.
- SQLite: database lokal dan CI. TiDB Cloud: target berikutnya yang belum diuji.
- TMDB: hanya metadata film; jadwal, harga, dan transaksi milik CINEM4.
- Midtrans Sandbox: server membuat Snap redirect dan membaca status melalui API; callback browser tidak menentukan status paid.

## Entitas

`users → bookings → booking_seats` (riwayat nomor kursi).

`movies → schedules ← cinemas/studios` (film dan ruang tayang).

`schedules + seat_number → seat_locks → bookings` (satu pemilik kursi aktif per jadwal).

`bookings → tickets` (satu tiket per booking paid); `bookings → payment_reviews` (kasus pembayaran yang perlu ditangani).

## Alur sensitif

1. Admin mengatur studio, film, dan jadwal.
2. Pelanggan login dan terverifikasi memilih kursi. Server memeriksa ketersediaan dan membuat booking/locks atomik; primary key komposit mencegah lock duplikat.
3. Pelanggan memulai Snap Sandbox; ID transaksi disimpan sebelum memanggil provider. Pending dengan transaksi Midtrans tidak dilepas karena timeout lokal saja.
4. Browser kembali melalui callback finish, lalu Laravel memanggil Get Status API. Webhook memeriksa signature dan juga Get Status API. `paid` hanya setelah status pembayaran sah dan semua lock kursi masih dimiliki booking.
5. Setelah paid, sistem menerbitkan token tiket acak (bukan ID booking yang mudah ditebak). QR menunjuk URL khusus admin. Admin hanya dapat redeem satu kali.
6. Jika refund/chargeback atau mismatch dicatat dalam `payment_reviews`, QR customer tidak diberikan dan admin tidak dapat redeem hingga persoalan pembayaran diselesaikan dalam proses review operasional yang belum dibangun.

## Trust boundary

- Akun pelanggan hanya boleh mengakses booking dan QR mereka sendiri.
- Route admin memakai `auth`, `verified`, dan `can:manage-movies`; izin ini mencakup pengelolaan film, jadwal, dan tiket.
- Webhook tidak memakai CSRF, tetapi signature secret diperiksa dan payload diverifikasi lagi dari Midtrans.
- Kunci API berada di `.env`, tidak dalam kode atau halaman klien.
- Lakukan audit dan load/concurrency test ulang saat beralih ke TiDB.
