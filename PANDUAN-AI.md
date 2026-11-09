# Panduan setup modul Analisis AI (Gemini)

Untuk demo atau uji lokal. Biaya perkiraan: sekitar Rp 50 per analisis (paket berbayar) atau Rp 0 (paket gratis, dengan catatan data).

## 1. Dapatkan kunci Gemini
1. Buka https://aistudio.google.com dan masuk dengan akun Google.
2. Pilih **Get API key** lalu **Create API key**. Salin kuncinya.
3. Paket gratis berlaku otomatis. Isi kiriman pada paket gratis boleh dipakai Google untuk meningkatkan produknya, jadi pakai hanya foto Anda sendiri atau tim yang setuju.
4. Untuk paket berbayar (isi kiriman tidak dipakai Google): aktifkan penagihan pada proyek Google Cloud yang dipakai kunci itu, lalu pasang **budget alert** (misalnya Rp 50 ribu).

## 2. Pasang di proyek
Buka berkas `.env` di folder `sekolah-app` (bukan `.env.example`) dan isi atau tambahkan baris berikut, tanpa tanda kutip dan tanpa spasi:

```
GEMINI_API_KEY=AIza...kunci-anda
GEMINI_MODEL=gemini-2.5-flash
AI_BATAS_HARIAN=10
```

Catatan model: Google menyatakan `gemini-2.5-flash` deprecated; mulai 1 Desember 2026 permintaannya dialihkan ke `gemini-3.5-flash-lite`
(tarif sama). Untuk memakai pengganti sekarang, ubah menjadi `GEMINI_MODEL=gemini-3.5-flash-lite` dan uji dengan foto asli.

Pilih cara memproses antrean:
- **Paling sederhana (demo lokal):** ubah `QUEUE_CONNECTION=database` menjadi `QUEUE_CONNECTION=sync`. Unggahan langsung diproses; halaman menunggu beberapa detik.
- **Seperti produksi:** biarkan `database` dan jalankan pekerja di terminal terpisah (lihat langkah 3).

## 3. Terapkan dan jalankan
```bash
php artisan config:clear
```

Bila memakai antrean `database`, jalankan dan biarkan terbuka:
```bash
php artisan queue:work
```
Setiap kali `.env` diubah: jalankan `php artisan config:clear` lalu hentikan dan mulai ulang `queue:work` (pekerja lama menyimpan konfigurasi lama).

Pastikan juga MySQL (Laragon) hidup dan server aplikasi berjalan.

## 4. Nyalakan fitur untuk sekolah
- Data demo: **SD Harapan Bangsa** sudah menyala. SMP sengaja mati.
- Sekolah lain: masuk sebagai Super Admin (`/admin`), buka **Daftar sekolah**, ubah sekolahnya, centang **Analisis AI** pada fitur aktif, lalu simpan.

## 5. Coba
1. Masuk sebagai admin sekolah (demo: `admin@harapanbangsa.demo`) lalu buka menu **Analisis AI**.
2. Pilih **Palmistry Analysis** atau **Physiognomy Analysis**.
3. Isi nama orang yang difoto dan nama pemberi persetujuan, centang pernyataan 18 tahun ke atas dan persetujuan, pilih foto, lalu klik **Analisis foto**.
4. Status berubah: Menunggu, Diproses, Selesai. Hasil muncul di bawah formulir dan di **Riwayat Analisis**.

Tips foto: telapak tangan terbuka, cahaya merata, garis tidak tertutup bayangan. Wajah menghadap lurus, tanpa kacamata atau penutup. JPG, PNG, atau WEBP, maksimal 5 MB.

## 6. Bila bermasalah
| Gejala | Penyebab dan tindakan |
|---|---|
| "Kunci API Gemini belum diatur" | `.env` belum terbaca. Periksa penulisan, jalankan `php artisan config:clear`, mulai ulang `queue:work`. |
| "Kunci API Gemini ditolak" | Kunci salah atau dicabut. Buat ulang di AI Studio. |
| "Layanan AI sedang sibuk atau terbatas kuotanya" | Kuota paket gratis habis. Tunggu atau aktifkan penagihan. |
| "Foto ditolak oleh filter keamanan" | Coba foto lain yang lebih jelas. |
| Menunggu terus | `queue:work` belum berjalan, atau ganti ke `QUEUE_CONNECTION=sync`. |
| Gagal tanpa pesan jelas | Lihat `storage/logs/laravel.log` dan jalankan `php artisan queue:failed`. |
| Menu Analisis AI tidak muncul | Fitur belum dinyalakan untuk sekolah itu, atau akun bukan admin sekolah. |

## 7. Setelah demo
- Hapus foto dan hasil lewat tombol di kartu hasil atau di **Riwayat Analisis**.
- Bila kunci pernah terlihat orang lain atau ikut tersimpan di tempat yang salah, hapus dan buat ulang di AI Studio.
- Kunci hanya boleh ada di `.env`. Berkas itu tidak ikut git.
