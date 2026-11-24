# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Penonton (pengguna utama):** penonton umum di Indonesia yang memesan tiket lewat ponsel (mobile-first), sering mendadak dan terburu-buru. Pekerjaannya: menemukan film dan jadwal, menahan kursi, membayar, lalu menunjukkan e-ticket QR di bioskop.
- **Admin cabang:** memakai desktop di loket. Pekerjaannya: mengelola film, bioskop, studio, dan jadwal; memverifikasi QR tiket dan menandainya terpakai.

## Product Purpose

CINEM4 adalah situs pemesanan tiket untuk satu jaringan bioskop dengan beberapa cabang. Penonton memesan sendiri; admin cabang mengelola jadwal dan memverifikasi tiket. Keberhasilan: penonton menyelesaikan pemesanan dan pembayaran tanpa bantuan, dan petugas memverifikasi tiket di pintu tanpa kesalahan.

## Positioning

Pemesanan kursi dengan penguncian 10 menit, pembayaran Midtrans, dan satu e-ticket QR per booking yang hanya dapat diverifikasi admin terautentikasi dan dipakai sekali. Katalog film berasal dari TMDB.

## Operating Context

- Alur: katalog/jadwal (`/showtimes`) → pilih kursi → reservasi ditahan 10 menit (diperpanjang 20 menit saat pembayaran dimulai) → Snap redirect Midtrans (hosted checkout) → status disinkronkan lewat webhook atau Get Status API → e-ticket QR.
- Status dari URL redirect browser bukan bukti pembayaran.
- Midtrans masih mode Sandbox; belum produksi.

## Capabilities and Constraints

- Stack yang sudah ada: Laravel 13, Vue 3, Inertia 3, SQLite lokal, TiDB Cloud direncanakan.
- Satu QR per booking (mencakup semua kursi); model ini tetap dipakai untuk tahap ini.
- Bahasa antarmuka belum dikonfirmasi sebagai keputusan mengikat; dokumentasi proyek berbahasa Indonesia.
- Tampilan Vue saat ini hanya antarmuka fungsional untuk pengujian, bukan desain akhir.
- Belum diputuskan: e-ticket per kursi, workflow admin untuk payment review/refund.

## Brand Commitments

- Nama merek: **CINEM4**. Logo final belum ada.
- Referensi visual yang dinyatakan mengikat sebagai arah desain: Behance "Astral Cinema" (https://www.behance.net/gallery/79555113/Astral-Cinema). Hanya dicatat di sini; penerjemahannya ke dunia visual ditentukan pada tahap desain, bukan di init.

## Evidence on Hand

- Poster dan metadata film hanya dari TMDB.
- Tidak ada testimoni, pelanggan, harga, penghargaan, atau klaim bukti nyata lain; jangan dikarang.

## Product Principles

1. Pembayaran dan keamanan tiket di atas segalanya: tampilan tidak boleh menyiratkan status lunas sebelum backend mengonfirmasi.
2. Mobile-first untuk penonton yang terburu-buru; desktop untuk loket admin.
3. Setiap layar menunjukkan batas waktu dan status reservasi secara jelas selama kursi ditahan.
4. Tidak ada konten, klaim, atau bukti yang dikarang.
