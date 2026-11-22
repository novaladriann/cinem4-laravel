# Audit Awal Database CINEM4 (22 November 2026)

Sumber: dump MySQL 8.4.3 yang diperiksa secara lokal. Dokumen ini tidak memuat data pribadi, hash password, token pembayaran, atau payload transaksi. Ini adalah audit statis, bukan hasil menjalankan query ke server TiDB.

## Inventaris

- 9 pelanggan, 3 admin.
- 17 film dan 1 bioskop.
- 22 jadwal, seluruhnya bertanggal hingga 25 Mei 2026.
- 41 booking dan 23 baris kursi yang masih tersimpan.
- 5 promosi dan 134 log pembayaran.

Pemeriksaan konsistensi statis pada dump tidak menemukan foreign key yatim, ketidaksesuaian jadwal antara booking dan booking_seats, kursi ganda pada kombinasi (id_schedule, seat_code) yang masih tersimpan, atau total pembayaran yang tidak memenuhi subtotal dikurangi diskon.

## Temuan

1. Autentikasi: users lama memakai id_user, first_name, last_name, wa, is_verified, dan tidak memiliki updated_at. Jangan menimpa users starter kit Laravel tanpa pemetaan data dan uji kompatibilitas hash bcrypt.
2. Booking: terdapat 14 booking berstatus paid; 10 di antaranya tetap memiliki booking_status=active. Satu booking pending sudah melewati tanggal kedaluwarsa. Jangan ubah histori tanpa verifikasi alur pembayaran.
3. Kursi: 26 booking memiliki total_seats yang berbeda dari jumlah booking_seats saat ini. Aplikasi lama menghapus alokasi kursi setelah expiry/cancel; sistem baru perlu menyimpan histori tiket terpisah dari lock kursi aktif.
4. Penghapusan: relasi lama memakai ON DELETE CASCADE sampai ke booking dan payment_logs. Rancangan baru harus mempertahankan histori transaksi dan membatasi hard delete.
5. Promosi: kelima promosi masih is_active=1 walaupun tanggal berakhir telah lewat. Validasi rentang waktu harus berjalan di server.
6. Studio: tidak ada tabel studios; studio_name masih berupa string. Pengecekan start time yang sama saja belum mencegah bentrokan rentang tayang. Dua pasangan jadwal historis berpotensi bertumpang tindih jika studio_name diperlakukan sebagai studio fisik yang sama, namun kedua pasangan memiliki setidaknya satu jadwal nonaktif.
7. Keamanan: dump berisi email, nomor telepon, password hash, token Snap, dan payload callback Midtrans. Jangan commit dump ke GitHub atau memakainya sebagai fixture test.

## Pekerjaan tahap ini

- Tambah migration untuk tabel kosong movies, cinemas, dan schedules pada database Laravel baru, bukan pada database existing.
- Pertahankan nama primary key historis id_movie, id_cinema, dan id_schedule.
- Tambah tmdb_id nullable dan unique; film tetap diaktifkan dan dijadwalkan oleh admin.
- Foreign key schedules menggunakan RESTRICT agar data induk tidak hilang karena penghapusan tidak sengaja.
- Tambah unique constraint pada jadwal dengan studio dan waktu mulai sama.
- Tambah model Eloquent dan feature tests katalog.

## Batasan dan langkah berikutnya

Belum ada impor data pengguna, film, booking, promosi, atau pembayaran; belum ada koneksi TiDB atau TMDB. Migrasi belum diuji terhadap versi TiDB yang akan dipakai. Jalankan php artisan migrate hanya pada database baru yang memang boleh dimigrasikan, dan jangan menjalankan migrate:fresh pada database existing.

Tahap berikutnya: uji skema di SQLite dan TiDB development terisolasi, rancang impor data katalog, lalu strategi migrasi autentikasi dan booking yang menjaga riwayat transaksi.
