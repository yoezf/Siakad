# Hormone Growth

Sistem manajemen sekolah dan rapor digital untuk banyak sekolah dalam satu platform (Laravel 13, Filament 5, MySQL). Nama proyek masih sementara.

## Mencoba di komputer sendiri

Langkah lengkap ada di **[PANDUAN-INSTALASI.pdf](PANDUAN-INSTALASI.pdf)**, dengan ringkasan satu gambar di [RINGKASAN-INSTALASI.jpg](RINGKASAN-INSTALASI.jpg). Singkatnya (butuh Git, PHP 8.3+, Composer, MySQL):

```bash
git clone https://github.com/yoezf/Siakad.git
cd Siakad
composer install
copy .env.example .env          # macOS/Linux: cp
php artisan key:generate
# buat database sekolah_app, lalu isi DB_* di .env dan setel QUEUE_CONNECTION=sync
php artisan demo:isi --ya       # MENGHAPUS isi database lalu mengisi data contoh
php artisan serve
```

Buka `http://127.0.0.1:8000/sekolah/login`. Daftar akun dan kata sandi demo dicetak oleh `demo:isi` dan ada di [DEMO.md](DEMO.md).

## Dokumen lain

- [DEMO.md](DEMO.md): naskah demo dan akun contoh
- [PANDUAN-AI.md](PANDUAN-AI.md): analisis foto dengan AI (kunci Gemini)
- [PANDUAN-WAJAH.md](PANDUAN-WAJAH.md): absensi wajah dan membuka dari HP

## Pengujian

```bash
php artisan test
```

Uji memakai database MySQL terpisah (`sekolah_app_test`, diatur lewat `.env.testing`, yang tidak ikut repo).
