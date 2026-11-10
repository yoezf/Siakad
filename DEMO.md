# Naskah demo investor: Digital Student Portfolio

Sistem rapor dan manajemen sekolah untuk **banyak sekolah dalam satu platform**. Nama produk masih sementara.
Demo ini berjalan lokal dengan data contoh (bukan data asli): dua sekolah berbeda jenjang, SD dan SMP, dengan akun untuk setiap peran.

## Pesan inti (tiga kalimat)
1. **Satu platform, banyak sekolah.** Tim pengembang mendaftarkan sekolah, dan data tiap sekolah terpisah penuh.
2. **Nilai sampai rapor tanpa kertas dan tanpa salah hitung.** Guru mengisi nilai, sistem menghitung, kepala sekolah mengesahkan, rapor terkunci dan berversi, orang tua melihatnya di portal.
3. **Dibangun bertahap dan diuji.** Aturan penting (hitungan nilai, kunci rapor, hak akses, pemisahan sekolah) dijaga ratusan uji otomatis. Fitur tambahan seperti analisis AI bisa dinyalakan per sekolah.

---

## A. Persiapan

### H-1 atau pagi hari
```bash
php artisan demo:isi --ya        # kosongkan database lalu isi data demo (sekitar 70 detik)
php artisan demo:periksa --ai    # periksa kesiapan, termasuk uji nyata ke Gemini
```
`demo:isi` **menghapus semua data di database lokal**, termasuk akun uji dan riwayat analisis AI Anda. `demo:periksa` hanya membaca dan menampilkan OK, PERLU, atau GAGAL.
Bila ada GAGAL, perbaiki sebelum demo. Jika `demo:periksa --ai` gagal, bagian AI bisa dilewati (lihat bagian D).

### 15 menit sebelum
1. Pastikan Laragon (MySQL) hidup.
2. Klik dua kali **`jalankan-lokal.bat`**. Dua jendela terbuka: server dan **Antrean AI**. Biarkan keduanya terbuka.
3. Buka `http://127.0.0.1:8000`. Layar proyektor atau monitor: gunakan tema terang (menu pengguna, pojok kanan atas).
4. Siapkan **dua jendela peramban**: satu memakai alamat `127.0.0.1:8000` dan satu memakai `localhost:8000`, supaya dua peran bisa login bersamaan tanpa saling keluar.
5. Siapkan **foto telapak tangan** (milik Anda sendiri, di galeri atau HP) untuk segmen AI.
6. Tutup aplikasi lain dan matikan notifikasi.
7. Untuk demo di HP: jalankan **`jalankan-hp.bat`** dan buka alamat yang ditampilkan di HP (jaringan Wi-Fi yang sama). Jangan pakai jaringan umum.

### Alamat dan akun
Kata sandi semua akun demo ada di `database/seeders/DemoSeeder.php` (konstanta `SANDI`) dan dicetak oleh `demo:isi`.

| Tujuan | Alamat |
|---|---|
| Super Admin | `http://127.0.0.1:8000/admin` |
| Panel SD | `http://127.0.0.1:8000/sekolah/sd-harapan-bangsa` |
| Panel SMP | `http://127.0.0.1:8000/sekolah/smp-cendekia-nusantara` |
| Portal orang tua dan siswa | `http://localhost:8000/portal/sd-harapan-bangsa` |

| Peran | Email |
|---|---|
| Super Admin (tim platform) | `super@demo.test` |
| Admin SD | `admin@harapanbangsa.demo` |
| Kepala sekolah SD | `kepala@harapanbangsa.demo` |
| Wali kelas 4A / 5A / 6A | `wali4a@`, `wali5a@`, `wali6a@harapanbangsa.demo` |
| Guru Matematika | `gurumtk@harapanbangsa.demo` |
| Staf TU | `tu@harapanbangsa.demo` |
| Orang tua (anak di 6A dan 4A) | `ortu@harapanbangsa.demo` |
| Siswa kelas 6A | `siswa@harapanbangsa.demo` |
| Admin, kepala, orang tua SMP | `admin@`, `kepala@`, `ortu@cendekia.demo` |

---

## B. Alur demo (sekitar 15 menit, AI opsional +3 menit)

| # | Waktu | Peran | Intinya |
|---|---|---|---|
| 1 | 1,5 mnt | Super Admin | Satu platform, banyak sekolah |
| 2 | 2 mnt | Admin SD | Pengelola sekolah: dasbor, data, jadwal |
| 3 | 3 mnt | Guru dan wali kelas | Nilai dan kelengkapan rapor |
| 4 | 3 mnt | Kepala sekolah | Pengesahan, kunci, revisi berversi, PDF |
| 5 | 2 mnt | Orang tua dan siswa | Portal |
| 6 | 1,5 mnt | Admin SMP | Keterpisahan data |
| 7 | +3 mnt | Admin SD | Analisis AI (opsional) |

### 1. Satu platform, banyak sekolah (Super Admin)
Login `super@demo.test` di `/admin`.
- **Dasbor:** ringkasan lintas sekolah (sekolah aktif, jumlah siswa, pengguna, rapor terkunci, sekolah dengan AI).
- **Daftar sekolah:** dua sekolah dengan paket dan status. Buka **Ubah** pada satu sekolah dan tunjukkan **fitur aktif** yang bisa dinyalakan per sekolah (Analisis AI menyala di SD, mati di SMP).
- Katakan: "Tim kami mendaftarkan sekolah dan mengatur paket dari sini. Sekolah tidak melihat data sekolah lain."

### 2. Pengelola sekolah (Admin SD)
Login `admin@harapanbangsa.demo`.
- **Dasbor:** siswa aktif, guru, kehadiran, rapor yang sudah disahkan, pengumuman.
- **Data master > Siswa:** 36 siswa dengan kelas dan NISN. Tunjukkan tombol **Impor dari Excel** (data sekolah nyata masuk lewat Excel).
- **Jadwal > Tampilan mingguan:** jadwal per kelas atau per guru. Katakan: sistem menolak jadwal yang bentrok.
- **Penilaian > Komponen penilaian:** bobot bebas per sekolah (SMP memakai komponen berbeda, KKM sekolah berbeda).

### 3. Guru dan wali kelas
- Login `gurumtk@harapanbangsa.demo` > **Input nilai:** hanya mata pelajaran yang ia ampu. Isi satu nilai; nilai akhir dan predikat langsung terhitung. Katakan: kolom kosong berarti belum dinilai, bukan nol.
- Login `wali5a@harapanbangsa.demo` (jendela kedua) > **Rapor siswa**, kelas 5A: tidak ada yang bisa disahkan karena nilai UAS belum ada, dan sistem menyebutkan nilai apa yang kurang.
- Login `wali6a@harapanbangsa.demo` > kelas 6A lengkap. Tulis satu **catatan wali kelas**.

### 4. Pengesahan dan revisi (Kepala sekolah)
Login `kepala@harapanbangsa.demo` > **Rapor siswa** > kelas 6A.
- Buka siswa berstatus Draf yang lengkap, klik **Sahkan rapor**. Nilai, kehadiran, dan catatan **terkunci**.
- Buka siswa yang sudah disahkan, klik **Unduh PDF**, tunjukkan kode verifikasi. Salinan rapor yang diubah di luar sistem terdeteksi.
- Tunjukkan siswa yang **dalam revisi**: alasan wajib dicatat, hasil revisi menjadi versi baru, versi lama bertanda "TIDAK BERLAKU". Katakan: riwayat tidak pernah ditimpa.
- **Rapor > Laporan akademik:** rekap nilai dan kehadiran per kelas, unduh **PDF** dan **Excel**.

### 5. Orang tua dan siswa (portal)
Jendela dengan alamat `localhost:8000`. Login `ortu@harapanbangsa.demo`.
- Satu akun, dua anak (6A dan 4A): ganti anak lewat pilihan di atas.
- Tab **Rapor** (hanya yang sudah disahkan, bisa diunduh), **Nilai**, **Kehadiran**, **Jadwal**, **Pengumuman**.
- Login `siswa@harapanbangsa.demo`: hanya melihat dirinya sendiri.
- Buka di HP bila memungkinkan: tampilannya menyesuaikan layar.

### 6. Keterpisahan data (Admin SMP)
Login `admin@cendekia.demo`: tidak ada satu pun data SD yang terlihat, dan menu **Analisis AI tidak muncul** (fitur mati untuk SMP).
Katakan: "Pemisahan dijaga di database dan di kode, dan diuji otomatis."

### 7. Analisis AI (opsional, hanya SD)
Login `admin@harapanbangsa.demo` > grup menu **Analisis AI**.
- **Prompt Kustom:** sudah ada tiga prompt contoh. Tunjukkan bahwa pengguna bisa menulis dan menyimpan prompt sendiri.
- **Analisis dengan Prompt:** pilih **Palmistry gaya chat**, klik **Ambil foto (kamera)** atau **Pilih dari galeri**, pilih foto telapak tangan Anda, centang pernyataan dan persetujuan, lalu **Analisis foto**. Hasil muncul sekitar 4 sampai 15 detik.
- **Palmistry Analysis:** versi terstruktur: status keterbacaan per garis, area deteksi di atas foto, dan JSON. Katakan: garis yang tidak terlihat tidak dikarang.
- **Riwayat** dan **Statistik:** semua tercatat; foto dan hasil bisa dihapus kapan saja.
- Katakan dengan jelas: ini **fitur hiburan dan bukan penilaian ilmiah**. Mati bawaan, dinyalakan per sekolah, hanya admin yang bisa memakai, memerlukan persetujuan, dan jawaban yang menyimpulkan kecerdasan, penyakit, suku, atau nasib dari wajah disaring.
- Gunakan **foto Anda sendiri atau orang dewasa yang setuju**, jangan foto siswa.

---

## C. Pertanyaan yang mungkin muncul dan jawaban jujur

| Pertanyaan | Jawaban |
|---|---|
| Aplikasi Android dan mode offline? | Direncanakan sebagai tahap berikutnya, belum dikerjakan. Saat ini web yang nyaman di ponsel. |
| Siapa pemilik data? | Sekolah. Pengembang mengelola platform. Pembagian tanggung jawab akan diatur dalam kontrak. Tinjauan hukum belum dilakukan. |
| Bagaimana keamanan? | Data sekolah dipisah di database dan kode, hak akses per peran, kunci rapor berversi dengan sidik, log audit perubahan nilai. Semuanya diuji otomatis. Belum ada audit keamanan pihak ketiga. |
| Berapa biaya AI? | Terukur sekitar Rp 25 sampai Rp 50 per analisis (Gemini). Ada batas harian per sekolah. Bisa dimatikan per sekolah. |
| Apakah foto siswa dipakai? | Tidak. Modul AI ditujukan untuk dewasa yang setuju, dan hasilnya tidak boleh dipakai untuk menilai siswa atau pegawai. |
| Kurikulum? | Bebas diatur sekolah (bawaan Kurikulum Merdeka). KKM, komponen, dan bobot per sekolah. |
| Bisa diimpor dari data lama? | Ya, ada impor dari Excel (tombol di menu Siswa), lengkap dengan templat dan pemeriksaan data. |
| Berapa banyak uji? | Lebih dari 670 uji otomatis. Aturan penting juga diuji dengan "merusak kode sengaja" untuk memastikan uji benar-benar menangkap kesalahan. |

## D. Batasan yang disampaikan jujur
- Belum ada: aplikasi Android, mode offline, notifikasi, ujian dan tugas dalam aplikasi, keuangan atau SPP, lupa kata sandi lewat email.
- Belum ada: peringkat siswa, deskripsi capaian per mata pelajaran.
- Berjalan lokal untuk demo; server produksi, HTTPS, dan cadangan data terjadwal belum disiapkan.
- AI: hasil palmistri dan fisiognomi adalah tafsir tradisi untuk hiburan; kualitas bergantung pada foto dan model.

## E. Bila ada yang macet saat demo

| Gejala | Tindakan cepat |
|---|---|
| Halaman lambat (sekitar 1 detik per klik) | Server tidak dijalankan lewat `jalankan-lokal.bat`. Hentikan, lalu jalankan lewat skrip itu. |
| Analisis AI "Menunggu" lebih dari 90 detik | Jendela **Antrean AI** tertutup. Buka `jalankan-lokal.bat` lagi, lalu klik **Coba lagi**. |
| Analisis AI gagal (kuota atau jaringan) | Lewati segmen AI. Katakan: "Ini bergantung layanan eksternal." Tunjukkan Riwayat dan Statistik dari data yang ada. |
| Foto ditolak oleh Gemini | Pakai foto lain (tangan, benda), atau lewati. |
| Data demo kacau karena dicoba-coba | `php artisan demo:isi --ya` (70 detik), lalu mulai ulang dari awal. |
| Lupa kata sandi atau akun hilang | `php artisan demo:isi --ya` mengembalikan semuanya. |
| Tidak ada internet | Semua bagian jalan, kecuali analisis AI. |
| Login saling keluar antar peran | Pakai dua alamat berbeda (`127.0.0.1` dan `localhost`) atau jendela penyamaran. |

## F. Setelah demo
- Hapus foto dan hasil lewat tombol di kartu hasil atau di **Riwayat Analisis**.
- Tutup jendela server dan **Antrean AI**. Bila memakai `jalankan-hp.bat`, hentikan sebelum meninggalkan jaringan itu.
- Hapus akun uji dengan kata sandi sederhana, jika ada (`demo:periksa` mengingatkan).
