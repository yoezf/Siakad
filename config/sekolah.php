<?php

// Jenjang yang dikenal sistem. Satu sekolah boleh punya lebih dari satu (misalnya SD dan SMP satu yayasan).
return [
    'jenjang' => [
        'sd' => 'SD / MI',
        'smp' => 'SMP / MTs',
        'sma' => 'SMA / MA',
        'smk' => 'SMK',
    ],

    // Nilai bawaan pengaturan sekolah; sekolah bebas mengubahnya.
    'pengaturan' => [
        'kurikulum' => 'Kurikulum Merdeka',
        'kkm' => 70,
        'peringkat_aktif' => false, // peringkat siswa hanya tampil jika sekolah memilih memakainya
        'absensi_guru' => true,     // kehadiran guru dan tenaga kependidikan; sekolah boleh mematikannya
        // Format rapor. Nilai ini disalin ke setiap versi rapor saat disahkan, jadi mengubahnya tidak mengubah rapor lama.
        'rapor_judul' => 'LAPORAN HASIL BELAJAR',
        'rapor_kota' => '',
        'kepala_sekolah_nama' => '',
        'kepala_sekolah_nip' => '',
        'rapor_tampil_kehadiran' => true,
    ],

    // Saran isian kurikulum; kolomnya teks bebas karena kebijakan kurikulum ada pada sekolah.
    'saran_kurikulum' => ['Kurikulum Merdeka', 'Kurikulum 2013', 'Kurikulum Cambridge', 'Kurikulum Nasional Plus'],
];
