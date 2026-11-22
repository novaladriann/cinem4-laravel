# Catatan Keputusan Teknis CINEM4

| Keputusan | Alasan / konsekuensi |
| --- | --- |
| Selesaikan backend sebelum redesign frontend final | Mengurangi konteks error yang bercampur antara logika pembayaran dan visual. |
| Tetap gunakan branch `main` | Pengguna melarang pembuatan branch baru tanpa persetujuan. |
| Gunakan TMDB hanya untuk metadata | Penentuan studio, status tayang, harga, dan pembayaran harus dikelola CINEM4. |
| Penguncian kursi di DB dengan unique composite key | Meminimalkan kursi ganda; tetap harus diuji secara paralel pada TiDB. |
| Midtrans Sandbox redirect hosted checkout | Server key di backend; klien tidak menangani kartu. Signature secret untuk webhook dipisah. |
| Status pembayaran hanya dari API Midtrans | Redirect atau payload browser tidak dapat dipercaya untuk menerbitkan tiket. |
| Pending yang memiliki order Midtrans tidak auto-release lewat timer lokal | Menghindari penjualan kursi ganda saat pembayaran terlambat, dengan konsekuensi memerlukan rekonsiliasi berkala. |
| Jika `payment_reviews` ada, tiket diblokir | Mencegah akses studio dari booking yang berstatus paid tetapi memiliki refund/chargeback/mismatch yang belum diselesaikan. |
| Satu QR per booking | Cukup untuk pengujian; tiket per kursi/orang diperlukan jika ada kebutuhan gate terpisah. |
| SQLite sekarang, TiDB nanti | Pengujian CI SQLite tidak membuktikan kompatibilitas TiDB; uji sandbox TiDB sebelum produksi. |
