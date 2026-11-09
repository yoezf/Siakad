# Naskah demo: Digital Student Portfolio

Sistem rapor dan manajemen sekolah untuk banyak sekolah dalam satu platform. Demo ini memperlihatkan dua sekolah berbeda
jenjang (SD dan SMP) yang datanya terpisah penuh, dengan akun untuk setiap peran.

## Persiapan (sekali, sebelum demo)

```bash
php artisan demo:isi --ya
```

Perintah ini mengosongkan database lokal lalu mengisi data demo (sekitar 70 detik). Jalankan ulang kapan saja untuk
mengembalikan keadaan awal setelah demo dicoret-coret. Server lokal: Laragon (MySQL) dan Herd (PHP) harus hidup.

- Alamat panel sekolah: `http://127.0.0.1:8000/sekolah/sd-harapan-bangsa` (SMP: `.../sekolah/smp-cendekia-nusantara`)
- Alamat portal orang tua dan siswa: `http://127.0.0.1:8000/portal/sd-harapan-bangsa`
- Alamat Super Admin: `http://127.0.0.1:8000/admin`
- Kata sandi semua akun demo: `demo-sekolah-2026`

Tips: bila memakai dua peran bergantian, buka peran kedua lewat alamat `localhost:8000` (bukan `127.0.0.1`) atau jendela
penyamaran, supaya sesi masuknya terpisah. Tampilan bawaan terang; ubah tema lewat menu pengguna bila perlu.

## Akun demo

| Sekolah | Peran | Email |
|---|---|---|
| Platform | Super Admin | super@demo.test |
| SD Harapan Bangsa | Admin sekolah | admin@harapanbangsa.demo |
| SD Harapan Bangsa | Kepala sekolah | kepala@harapanbangsa.demo |
| SD Harapan Bangsa | Wali kelas 4A / 5A / 6A | wali4a@, wali5a@, wali6a@harapanbangsa.demo |
| SD Harapan Bangsa | Guru Matematika | gurumtk@harapanbangsa.demo |
| SD Harapan Bangsa | Staf TU | tu@harapanbangsa.demo |
| SD Harapan Bangsa | Orang tua (anak di 6A dan 4A) | ortu@harapanbangsa.demo |
| SD Harapan Bangsa | Siswa kelas 6A | siswa@harapanbangsa.demo |
| SMP Cendekia Nusantara | Admin / Kepala sekolah | admin@, kepala@cendekia.demo |
| SMP Cendekia Nusantara | Wali kelas 7A / 8A, Orang tua | wali7a@, wali8a@, ortu@cendekia.demo |

## Alur demo (sekitar 10 menit)

### 1. Satu platform, banyak sekolah (Super Admin, 1 menit)
Masuk sebagai **super@demo.test** di `/admin`. Tunjukkan daftar sekolah, paket layanan, dan fitur yang bisa dinyalakan per
sekolah. Pesan: tim pengembang mengelola platform; sekolah didaftarkan dan diatur dari sini; data tiap sekolah terpisah.

### 2. Pengelola sekolah (Admin SD, 2 menit)
Masuk sebagai **admin@harapanbangsa.demo**.
- **Dasbor:** ringkasan siswa, guru, kehadiran, rapor yang sudah disahkan.
- **Data master > Siswa:** 36 siswa, impor dari Excel tersedia (tombol "Impor dari Excel").
- **Jadwal > Tampilan mingguan:** jadwal per kelas atau per guru; sistem menolak jadwal yang bentrok.
- **Penilaian > Komponen penilaian:** bobot bebas per sekolah (SMP memakai komponen berbeda).

### 3. Guru dan wali kelas (3 menit)
- Masuk sebagai **gurumtk@harapanbangsa.demo**: **Input nilai** hanya menampilkan mata pelajaran yang diampunya.
  Isi satu nilai; nilai akhir dan predikat langsung terhitung. Nilai kosong dianggap belum dinilai, bukan nol.
- Masuk sebagai **wali5a@harapanbangsa.demo**: **Rapor siswa**, kelas 5A. Semua rapor belum bisa disahkan karena nilai UAS
  belum ada. Sistem menyebutkan nilai apa yang kurang.
- Masuk sebagai **wali6a@harapanbangsa.demo**: kelas 6A lengkap. Tulis catatan wali kelas.

### 4. Pengesahan dan revisi (Kepala sekolah, 2 menit)
Masuk sebagai **kepala@harapanbangsa.demo** > **Rapor siswa** > kelas 6A.
- Buka siswa yang berstatus Draf lengkap, klik **Sahkan rapor**; nilai dan kehadiran terkunci.
- Buka siswa yang sudah disahkan, klik **Unduh PDF**. Tunjukkan kode verifikasi pada PDF.
- Tunjukkan siswa yang **dalam revisi**: alasan wajib dicatat, hasil revisi menjadi versi baru, versi lama bertanda
  "TIDAK BERLAKU". Riwayat tidak pernah ditimpa.
- **Laporan akademik:** rekap nilai dan rekap kehadiran per kelas, unduh PDF atau Excel.

### 5. Orang tua dan siswa (2 menit)
Buka `/portal/sd-harapan-bangsa` (jendela baru) sebagai **ortu@harapanbangsa.demo**.
- Dua anak (6A dan 4A) dalam satu akun; ganti anak lewat pilihan di atas.
- Tab **Rapor** (hanya yang sudah disahkan, bisa diunduh), **Nilai**, **Kehadiran**, **Jadwal**, **Pengumuman**.
- Masuk sebagai **siswa@harapanbangsa.demo**: hanya melihat dirinya sendiri.

### 6. Keamanan dan keterpisahan data (kapan saja, 1 menit)
- Masuk sebagai **admin@cendekia.demo** (SMP): tidak ada satu pun data SD yang terlihat.
- Orang tua tidak bisa membuka panel sekolah, dan staf tidak bisa membuka portal.
- Setiap unduhan laporan dan setiap perubahan nilai tercatat (siapa, kapan, nilai lama dan baru).

### 7. Modul Analisis AI (opsional, hanya SD demo)
Menu **Analisis AI** (Palmistry, Physiognomy, Riwayat, Statistik) hanya muncul di sekolah yang fiturnya dinyalakan Super Admin; di data demo hanya SD Harapan Bangsa.
- Tunjukkan peringatan "hanya untuk hiburan", kotak persetujuan, dan pernyataan 18 tahun ke atas yang wajib sebelum foto diunggah.
- Tanpa kunci Gemini, unggahan akan berstatus gagal dengan pesan jelas. Untuk analisis sungguhan: isi `GEMINI_API_KEY` di `.env`, lalu jalankan `php artisan queue:work` di terminal terpisah (atau set `QUEUE_CONNECTION=sync` untuk demo lokal).
- Gunakan foto dewasa yang setuju (mis. tim sendiri), jangan foto siswa. Hapus foto dan hasil lewat tombol di kartu hasil.
- Data demo menyertakan satu analisis contoh palsu (bukan hasil Gemini) bila dibuat manual; data baru dari `demo:isi` tidak memuatnya.

## Pertanyaan yang mungkin muncul

- **Android dan mode offline?** Direncanakan sebagai tahap berikutnya; saat ini berupa aplikasi web yang nyaman di ponsel.
- **Siapa pemilik data?** Sekolah. Pengembang mengelola platform; pembagian tanggung jawab diatur dalam kontrak
  (ditinjau ahli hukum sebelum peluncuran).
- **Kurikulum?** Bebas diatur sekolah (bawaan Kurikulum Merdeka); KKM, komponen, dan bobot per sekolah.
- **Pengujian?** Ratusan uji otomatis menjaga aturan penting: perhitungan nilai, kunci rapor, hak akses, pemisahan sekolah.

## Batasan yang jujur disampaikan

- Belum ada: aplikasi Android, mode offline, notifikasi, ujian dan tugas dalam aplikasi, keuangan atau SPP.
- Belum ada fitur lupa kata sandi lewat email; kata sandi diatur ulang oleh admin sekolah.
- Peringkat siswa dan deskripsi capaian per mata pelajaran belum ada.
