# Panduan absensi wajah (uji lokal)

Fitur ini mati bawaan. Untuk SD demo sudah dinyalakan. Untuk sekolah lain, Super Admin menyalakan fitur **Absensi siswa dengan wajah**.

## Cara kerja singkat
- **Foto tidak disimpan.** Peramban mengubah wajah menjadi 128 angka, dan hanya angka itu yang disimpan di server, dalam bentuk terenkripsi.
- **Pencocokan terjadi di peramban** perangkat yang dipakai (tablet, HP, atau laptop). Tidak ada foto yang dikirim ke server atau pihak luar, dan tidak ada biaya per pemindaian.
- Siswa diminta **berkedip** sebelum kehadiran dicatat, supaya foto di layar HP lebih sulit dipakai.
- Yang dicatat hanya **Hadir** untuk kehadiran harian. Siswa yang sudah punya status hari itu (sakit, izin, alpa) tidak diubah.

## Menjalankan di komputer ini
Cukup buka `http://127.0.0.1:8000`. Kamera boleh dipakai di alamat `localhost` / `127.0.0.1`.

## Menjalankan dari HP (jaringan lokal)
Peramban HP hanya mengizinkan kamera lewat **HTTPS**, jadi `jalankan-hp.bat` (HTTP) tidak cukup.
1. Klik dua kali **`jalankan-hp-aman.bat`**. Ia membuat sertifikat sendiri dan menampilkan alamat seperti `https://192.168.1.19:8443/sekolah/login`.
2. HP harus di **Wi-Fi yang sama**. Windows mungkin menanyakan izin jaringan untuk `node.exe`: pilih **Allow** (jaringan Private).
3. Buka alamat itu di HP. Peramban memperingatkan sertifikat tidak tepercaya: pilih **Lanjutkan** (Chrome: *Advanced* lalu *Proceed*). Ini wajar karena sertifikatnya buatan sendiri.
4. Login, lalu izinkan akses kamera saat diminta.

Server Laravel dari skrip ini hanya terbuka di komputer; yang terbuka ke jaringan hanya proksi HTTPS di port 8443. Pakai hanya di jaringan tepercaya dengan data uji.

## Langkah mencoba
1. Login sebagai admin sekolah (akun demo ada di `DEMO.md`). Buka **Kehadiran > Daftar wajah siswa**, pilih kelas, klik **Ambil wajah** pada seorang siswa. Hadapkan wajah ke kamera dan gerakkan kepala sedikit antar sampel (5 sampel).
2. Buka **Kehadiran > Absen wajah**, pilih kelas yang sama, klik **Mulai pindai**, hadapkan wajah, lalu berkedip. Kehadiran hari ini tercatat dan terlihat di **Catat kehadiran siswa**.
3. Wali kelas juga bisa memindai kelasnya sendiri. Hanya admin sekolah yang bisa mendaftarkan atau menghapus data wajah.

Pemuatan pertama mengunduh model sekitar 7 MB dan menyiapkan pengenalan wajah (bisa sampai setengah menit pada perangkat lambat). Berikutnya jauh lebih cepat.

## Tips agar akurat
- Cahaya cukup dan merata, wajah menghadap kamera, tanpa kacamata hitam atau masker.
- Daftarkan wajah dengan kondisi mirip saat absen (jarak dan cahaya).
- Bila siswa tidak dikenali, daftarkan ulang dengan **Ambil ulang**.

## Batasan yang perlu diketahui
- Server tidak bisa memastikan hasil pencocokan di peramban. Perangkat yang sengaja dibobol bisa memalsukan kehadiran. Pengamannya: hanya akun yang login dan berhak atas kelas itu yang bisa mencatat, ada batas 120 pencatatan per menit, dan setiap catatan mencantumkan pencatatnya.
- Tes kedipan menyulitkan penipuan dengan foto, tetapi tidak membuatnya mustahil.
- Anak kembar atau wajah yang sangat mirip bisa tertukar. Sistem menolak mencatat bila dua siswa terlalu mirip, tetapi tidak sempurna.
- Data wajah anak adalah data biometrik yang sensitif. Hapus data wajah siswa yang keluar atau pindah (tombol **Hapus**).
