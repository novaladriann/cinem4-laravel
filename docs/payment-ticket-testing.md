# CINEM4 — Pembayaran Sandbox dan E-ticket

## Alur

1. Pelanggan memilih kursi dan membuat reservasi pending.
2. Pelanggan membuka Snap Sandbox. Permintaan Snap menyertakan callback finish ke halaman reservasi yang sama.
3. Ketika kembali, Laravel menanyakan status langsung ke Midtrans, bukan mempercayai parameter status dari browser.
4. Setelah Midtrans mengonfirmasi settlement/capture serta semua kursi masih terkunci, Laravel menandai booking paid dan membuat **satu e-ticket untuk satu booking** dengan token acak.
5. QR pada halaman detail booking adalah URL verifikasi yang hanya dapat dibuka admin terverifikasi. Petugas admin dapat menandai tiket digunakan **sekali**.
6. Perintah `php artisan payments:reconcile` mengecek kembali pembayaran pending. Jalankan `php artisan schedule:work` pada terminal terpisah ketika menguji scheduler lokal.

## Penting

- Satu QR mencakup semua kursi dalam satu booking. Ini **bukan** kode tiket per kursi; jika perlu pemindaian per orang, ubah desain sebelum implementasi produksi.
- Jangan membagikan QR tiket secara publik. Kode acak adalah kredensial masuk studio yang rahasia.
- Pengunjung tidak boleh membuka QR milik booking orang lain. Admin wajib login untuk mengakses URL yang disimpan di QR.
- Tidak ada pembayaran produksi. Status dari URL redirect **tidak** menjadi bukti pembayaran.
- Jika status dari Midtrans belum tersedia, booking tetap pending, tidak dilepas untuk pengguna lain.
- Untuk pengujian localhost, browser dapat kembali ke `http://127.0.0.1:8000` ketika Snap memakai callback finish. Midtrans webhook tetap memerlukan alamat HTTPS publik.
- Rekonsiliasi perlu dimonitor (error logs dan payment_reviews) sebelum produksi, termasuk proses refund/chargeback.
- Kesesuaian transaksi paralel dengan TiDB serta perilaku receipt dan notifikasi pembayaran sungguhan belum diverifikasi.
