<?php

namespace App\Modules\Kehadiran;

/** Status kehadiran. Terlambat tetap dihitung hadir pada persentase kehadiran. */
final class Status
{
    public const HADIR = 'H';

    public const SAKIT = 'S';

    public const IZIN = 'I';

    public const ALPA = 'A';

    public const TERLAMBAT = 'T';

    public const LABEL = ['H' => 'Hadir', 'S' => 'Sakit', 'I' => 'Izin', 'A' => 'Alpa', 'T' => 'Terlambat'];

    public static function sah(?string $kode): bool
    {
        return $kode !== null && array_key_exists($kode, self::LABEL);
    }
}
