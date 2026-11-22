# Integrasi TMDB CINEM4

TMDB adalah sumber metadata film (judul, sinopsis, genre, durasi, gambar, trailer). CINEM4 tetap mengelola bioskop, jadwal, harga, kursi, dan pembayaran. Film diimpor sebagai **coming_soon**; admin nantinya menentukan status tayang.

## Konfigurasi development

1. Buat API Read Access Token di [TMDB](https://developer.themoviedb.org/reference/intro/getting-started).
2. Simpan **hanya** di file lokal .env sebagai TMDB_API_READ_TOKEN. Jangan commit token.
3. Jalankan php artisan config:clear dan php artisan migrate di database development kosong/aman.
4. Buat akun melalui registrasi. Pastikan email akun telah diverifikasi.
5. Promosikan **hanya akun tepercaya milik Anda** sebagai admin melalui php artisan tinker:

    $user = App\Models\User::where('email', 'email-anda@example.com')->firstOrFail();
    $user->is_admin = true;
    $user->save();

Jangan menyediakan endpoint publik untuk mengubah role. Pada production, provisioning admin memerlukan prosedur operator berotorisasi dan audit.

## Rute

- /movies — katalog film aktif publik.
- /admin/movies/import — pencarian, impor, dan feedback; memerlukan login, verifikasi email, role admin.

## Ketentuan keamanan

- Token hanya dibaca Laravel melalui config/services.php, bukan frontend.
- Pencarian cache 15 menit, detail 12 jam, timeout HTTP 10 detik, dan batas laju route.
- ID TMDB diverifikasi, adult content ditolak, dan movies.tmdb_id memiliki unique constraint.
- Poster serta backdrop menggunakan URL TMDB tervalidasi; impor gambar lokal belum diterapkan.
- Jadwal tidak diperoleh dari TMDB.
- Atribusi disertakan di halaman. Periksa kembali [ketentuan logo dan atribusi TMDB](https://developer.themoviedb.org/docs/logos-and-attribution) sebelum rilis.

## Pengujian

Jalankan php artisan test, npm run check, dan npm run types:check. Uji tampilan pada desktop dan mobile secara manual sebelum production.

Data film SQL lama, pengguna, booking, dan pembayaran belum diimpor. Database TiDB belum dipakai atau diuji pada tahap ini.
