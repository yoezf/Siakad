<?php

// Pengaturan modul analisis AI (palmistry dan physiognomy). Semua dapat diubah lewat .env.
return [
    // true: foto dihapus segera setelah analisis berhasil (hasil teks tetap). Bawaan false agar area deteksi bisa ditampilkan di atas foto.
    'hapus_foto_otomatis' => (bool) env('AI_HAPUS_FOTO_OTOMATIS', false),

    // Batas jumlah analisis per sekolah per hari, untuk mengendalikan biaya dan paparan data.
    'batas_harian' => (int) env('AI_BATAS_HARIAN', 30),
];
