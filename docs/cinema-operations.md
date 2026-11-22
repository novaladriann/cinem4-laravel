# Operasional bioskop dan jadwal CINEM4

Fitur admin untuk membuat bioskop, membuat studio, serta menyusun jadwal tayang.

- **Bioskop:** nama, kota, alamat, dan status aktif.
- **Studio:** milik satu bioskop, nama unik per bioskop, kapasitas 1–300, dan jeda pembersihan 5–90 menit.
- **Jadwal:** film aktif dengan status Now Showing dan durasi positif, bioskop serta studio aktif, waktu mendatang menurut zona Asia/Jakarta, harga Rp1.000–1.000.000.
- **Pencegahan bentrok:** saat menyimpan jadwal, Laravel memeriksa rentang mulai sampai selesai film **ditambah jeda** terhadap jadwal aktif lain dalam studio yang sama, termasuk jadwal yang hanya memiliki nama studio lama.
- **Pembatalan:** menyimpan record sebagai cancelled dan nonaktif; **belum dilengkapi pemeriksaan tiket/refund**, sehingga pembatalan jadwal yang sudah memiliki booking harus ditangani sebelum fitur booking dirilis.
- **Kompatibilitas:** migrasi hanya menambahkan tabel studios dan kolom schedules.id_studio yang nullable. Jadwal lama tetap bisa dibaca berdasarkan studio_name.

## Menjalankan

Gunakan database development SQLite yang sudah dipakai CINEM4; jangan melakukan migrate:fresh. Jalankan:

```bash
php artisan migrate
php artisan test
npm run check
composer run dev
```

Login sebagai admin terverifikasi, lalu buka /admin/cinemas untuk menambah lokasi dan studio, dan /admin/schedules untuk membuat jadwal. Semua tampilan jam tayang menggunakan WIB (Asia/Jakarta).

**Batasan:** Diagram kursi per baris, pemesanan, transaksi, dan izin pembatalan setelah ada booking belum dibuat. Sistem booking harus menggunakan kapasitas yang disimpan di setiap jadwal dan membuat aturan perubahan kapasitas yang aman. Pengujian waktu/bentrok telah dibuat di SQLite; kesesuaian transaksi dan penguncian konkurensi terhadap TiDB harus divalidasi terpisah sebelum deployment.
