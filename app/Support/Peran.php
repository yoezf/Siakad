<?php

namespace App\Support;

/** Daftar peran sistem. Peran bersifat global; keterikatan ke sekolah ada pada users.school_id. */
final class Peran
{
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN_SEKOLAH = 'admin_sekolah';
    public const KEPALA_SEKOLAH = 'kepala_sekolah';
    public const WALI_KELAS = 'wali_kelas';
    public const GURU_MAPEL = 'guru_mapel';
    public const GURU_BK = 'guru_bk';
    public const STAF_TU = 'staf_tu';
    public const STAF_KEUANGAN = 'staf_keuangan';
    public const ORANG_TUA = 'orang_tua';
    public const SISWA = 'siswa';

    /** Peran sekolah beserta labelnya. */
    public const SEKOLAH = [
        self::ADMIN_SEKOLAH => 'Admin sekolah',
        self::KEPALA_SEKOLAH => 'Kepala sekolah',
        self::WALI_KELAS => 'Wali kelas',
        self::GURU_MAPEL => 'Guru mata pelajaran',
        self::GURU_BK => 'Guru BK',
        self::STAF_TU => 'Staf tata usaha',
        self::STAF_KEUANGAN => 'Staf keuangan',
        self::ORANG_TUA => 'Orang tua',
        self::SISWA => 'Siswa',
    ];

    /** Peran yang memakai portal orang tua dan siswa (panel terpisah, tanpa akses ke data sekolah lain di luar anaknya). */
    public const PORTAL = [self::ORANG_TUA, self::SISWA];

    /** Peran yang memakai panel Sekolah. */
    public const PANEL_SEKOLAH = [
        self::ADMIN_SEKOLAH, self::KEPALA_SEKOLAH, self::WALI_KELAS,
        self::GURU_MAPEL, self::GURU_BK, self::STAF_TU, self::STAF_KEUANGAN,
    ];
}
