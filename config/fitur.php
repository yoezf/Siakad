<?php

// Fitur yang bisa dinyalakan per sekolah. Fitur akademik inti (nilai, rapor, kehadiran) selalu aktif.
return [
    'fitur' => [
        'ujian' => 'Ujian dalam aplikasi',
        'tugas' => 'Tugas digital',
        'keuangan' => 'Keuangan dan SPP',
        'premium' => 'Layanan premium orang tua',
        'analisis_ai' => 'Analisis foto dengan AI (prompt kustom)',
        'absensi_wajah' => 'Absensi siswa dengan wajah',
    ],

    // Fitur awal tiap paket; Super Admin boleh menyalakan atau mematikan satuan setelahnya.
    'paket' => [
        'dasar' => ['label' => 'Dasar', 'fitur' => []],
        'lengkap' => ['label' => 'Lengkap', 'fitur' => ['ujian', 'tugas']],
        'premium' => ['label' => 'Premium', 'fitur' => ['ujian', 'tugas', 'premium']],
    ],
];
