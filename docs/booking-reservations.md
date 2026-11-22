# Reservasi kursi CINEM4 — tahap sebelum Midtrans

## Fungsi yang sudah tersedia

- Katalog jadwal publik di `/showtimes`, peta kursi di `/showtimes/{schedule}`.
- Pengguna login dan email terverifikasi dapat menahan 1–6 kursi per reservasi selama **10 menit**.
- Server memeriksa film, bioskop, dan studio aktif, status jadwal `open`, dan batas tutup pemesanan berdasarkan WIB.
- Server mengambil harga langsung dari jadwal, menyimpan snapshot harga per kursi dan total. Harga kiriman browser tidak dipercaya.
- Tabel `seat_locks` memakai composite primary key (`id_schedule`, `seat_number`) untuk mencegah dua penahanan kursi bersamaan. Pembuatan reservasi dilakukan dalam satu transaksi.
- Kursi kedaluwarsa secara logis setelah 10 menit; setiap reservasi berikutnya akan menghapus penguncian lama dan menandai reservasi sebagai `expired`. Riwayat `booking_seats` tidak dihapus.
- Pemilik bisa melihat riwayat `/my-bookings`, detail reservasi, dan membatalkan reservasi `pending`. Reservasi yang sudah `paid` tidak bisa dibatalkan melalui jalur pelanggan.
- Belum ada endpoint yang mengubah reservasi menjadi `paid`. Pembayaran Midtrans belum diimplementasikan dan **reservasi bukan tiket yang sah untuk masuk studio**.

## Cara uji lokal

Jalankan `php artisan migrate` (bukan `migrate:fresh`), `php artisan test`, dan `composer run dev`. Tambahkan film Now Showing, studio, serta jadwal masa depan. Login sebagai pelanggan. Pilih kursi, kemudian coba kursi yang sama dari akun kedua — permintaan harus ditolak. Batalkan reservasi pertama; kursi dapat digunakan lagi.

## Batasan penting sebelum produksi

- Pada TiDB, kemampuan transaction lock, isolation, dan unique constraint harus diuji dengan dua permintaan paralel; pengujian SQLite tidak membuktikan keselamatan konkurensi pada TiDB.
- UI kursi menggunakan nomor 1 sampai `seat_capacity`, bukan seat map baris A/B/C. Pengaturan denah dan aksesibilitas ruang dapat ditambahkan saat kebutuhan studio dipastikan.
- Pembayaran membutuhkan Midtrans Snap/Core API, webhook bertanda tangan, status idempoten, rekonsiliasi payment timeout, refund, dan penanganan keterlambatan notifikasi.
- Saat Midtrans ditambahkan, proses sukses harus memeriksa hold dan waktu kedaluwarsa sebelum menandai `paid`, atau menggunakan perpanjangan hold yang selaras dengan waktu pembayaran.
- Tombol pembatalan jadwal admin sekarang belum mengecek booking; sebelum tiket berbayar dirilis, lakukan hardening agar pembatalan hanya setelah refund/penanganan transaksi.
