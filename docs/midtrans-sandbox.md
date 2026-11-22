# Midtrans Sandbox untuk CINEM4 (belum produksi)

Integrasi menggunakan **Snap redirect hosted checkout**, sehingga aplikasi Laravel tidak menerima detail kartu pelanggan. Untuk server Laravel dibutuhkan **Sandbox Server Key** (API) dan **Sandbox Signature Secret Key** (autentikasi webhook). Client Key tidak digunakan oleh Snap Redirect. **Jangan memakai production key**.

## Konfigurasi

1. Buat/masuk akun [Midtrans Sandbox](https://dashboard.sandbox.midtrans.com/). Tidak ada pembayaran riil dalam Sandbox.
2. Buka **Settings > Access Keys** dalam mode Sandbox. Buat Server Key untuk autentikasi API lalu ambil Signature Secret Key untuk verifikasi webhook. Kedua kunci wajib berasal dari merchant Sandbox yang sama.
3. Simpan keduanya secara terpisah di `.env` lokal: `MIDTRANS_SANDBOX_SERVER_KEY=...` dan `MIDTRANS_SANDBOX_SIGNATURE_SECRET_KEY=...`. Jangan gunakan Client Key untuk kedua variabel ini.
4. Jalankan `php artisan config:clear`, `php artisan migrate`, dan `php artisan test`.
5. Jalankan `composer run dev` lalu login sebagai pelanggan.
6. Buka `/showtimes`, tahan kursi, kemudian buka detail reservasi dan klik **Bayar dengan Midtrans Sandbox**. Link menuju checkout resmi `app.sandbox.midtrans.com`.
7. Untuk menyinkronkan hasil checkout, kembali ke reservasi lalu klik **Periksa Status Pembayaran**. Status berasal dari Get Status API Midtrans, **bukan dari parameter URL browser**.

## Webhook

Endpoint: `POST /webhooks/midtrans` (tanpa CSRF, tetapi tanda tangan SHA512 diverifikasi menggunakan **Signature Secret Key** (bukan Server Key) dan notifikasi dikonfirmasi lagi memakai Get Status API). Daftarkan Notification URL tersebut pada dashboard Sandbox ketika CINEM4 memiliki alamat HTTPS publik.

Alamat `127.0.0.1` atau `localhost` **tidak dapat dijangkau langsung oleh server Midtrans**. Untuk pengujian webhook gunakan tunnel HTTPS yang dipercaya; jangan membagikan token tunnel, Server Key, Signature Secret Key, atau data pelanggan. Tanpa webhook, pelanggan dapat menekan tombol **Periksa Status Pembayaran** setelah transaksi.

## Perilaku reservasi

- Saat kursi pertama kali dipilih, reservasi ditahan 10 menit. Saat pembayaran dimulai, server memperpanjang menjadi 20 menit dan Midtrans diberi jendela bayar 15 menit.
- Setelah order Midtrans dibuat, kursi **tidak dilepas otomatis hanya berdasarkan timer lokal**; transaksi harus disinkronkan dengan Midtrans untuk menghindari penjualan ganda jika webhook terlambat.
- `order_id` disimpan sebelum permintaan Snap, sehingga percobaan ulang tidak membuat ID transaksi baru.
- Pembayaran terkonfirmasi dengan kursi masih terkunci -> `paid`. Pesan berulang tidak membuat tiket ganda.
- Status Midtrans `expire`/`cancel`/`deny`/`failure` -> lepaskan kursi dan catat status booking.
- Hasil positif yang terlambat atau tidak konsisten -> `payment_reviews`, **jangan otomatis terbitkan tiket**.
- Sesi Midtrans aktif **tidak dapat dibatalkan pelanggan**, dan jadwal tidak boleh dibatalkan admin sebelum status pembayaran terselesaikan.

## Batasan

- API Sandbox tidak membuktikan kesiapan produksi, harga/ongkos gateway akhir, refund, rekonsiliasi otomatis, atau TiDB.
- Pending Midtrans yang tidak pernah dikonfirmasi dapat menahan kursi hingga ada webhook atau pelanggan memeriksa status. Sebelum produksi diperlukan _scheduled reconciliation_ dengan kebijakan timeout/alert.
- Middleware membatasi tingkat permintaan, tetapi sistem butuh pengujian konkurensi terhadap TiDB langsung dan audit pembayaran sesungguhnya.
- Belum ada QR tiket masuk atau pembayaran produksi. Tampilan Vue saat ini merupakan antarmuka fungsional pengujian, bukan desain akhir.
