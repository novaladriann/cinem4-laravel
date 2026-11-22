# Rencana Migrasi Data & Fitur CINEM4

Dokumen ini merupakan **rencana**, bukan hasil migrasi atau hasil validasi skema SQL. Sumber fitur awal: [CINEM4 PHP Native](https://github.com/novaladriann/cinem4).

## Inventaris domain

| Domain              | Tabel existing yang disebut README lama | Prioritas             |
| ------------------- | --------------------------------------- | --------------------- |
| Akun pelanggan      | `users`                                 | Tinggi                |
| Admin dan hak akses | `admins`                                | Tinggi                |
| Film                | `movies`                                | Awal                  |
| Bioskop             | `cinemas`                               | Awal                  |
| Jadwal              | `schedules`                             | Awal                  |
| Pesanan dan kursi   | `bookings`, `booking_seats`             | Kritis                |
| Promosi             | `promotions`                            | Sesudah booking dasar |
| Pembayaran          | `payment_logs`                          | Kritis                |

Nama tabel di atas perlu diverifikasi terhadap file SQL lengkap dan struktur database aktual.

## Aturan keselamatan

1. Pertahankan repository PHP Native sebagai referensi. Simpan backup data terpisah sebelum migrasi.
2. Gunakan database **isolasi** untuk pengujian dan migrasi awal. Jangan jalankan `migrate:fresh`, `db:wipe`, atau seed destruktif pada database existing/shared.
3. Audit primary key lama (`id_user`, `id_booking`, dll.), foreign key, tipe data, indeks, nullable, default, enum, serta format tanggal sebelum membuat Eloquent Models.
4. Pastikan hash password lama tetap dapat diverifikasi; migrasi auth perlu diuji sebelum mengganti struktur `users`.
5. Tinjau konflik kursi pada transaksi bersamaan, tenggat hold 15 menit, promo, idempotensi callback Midtrans, validasi signature, dan nominal transaksi. Gunakan test concurrent booking pada engine database yang dipakai.
6. Simpan identitas film dari TMDB sebagai `tmdb_id` terpisah dari primary key internal. Jadwal dan harga berasal dari CINEM4, bukan TMDB.
7. Pisahkan konfigurasi development/test/production; jangan memasukkan kredensial atau backup sensitif ke Git.

## Pekerjaan berikutnya

- Peroleh dump **struktur saja** dari database CINEM4 lama (DDL `CREATE TABLE`, tanpa data pribadi).
- Catat mapping kolom dan relasi, kemudian pilih cara migrasi data yang tidak menghapus data existing.
- Buat perubahan model dan migration kecil per domain, disertai feature test.
- Migrasikan katalog film terlebih dahulu; booking/payment sesudah constraints dan state transition terdokumentasi.
