<?php

namespace App\Support\Tenancy;

use Filament\Facades\Filament;

/** Sekolah yang sedang aktif. Tanpa sekolah aktif, data milik sekolah tidak boleh terbaca (gagal tertutup). */
final class CurrentSchool
{
    private static ?int $override = null;

    public static function id(): ?int
    {
        return self::$override ?? Filament::getTenant()?->getKey() ?? auth()->user()?->school_id;
    }

    /** Nilai penimpa saat ini (bukan hasil turunan dari login), supaya pemanggil bisa memulihkannya. */
    public static function override(): ?int
    {
        return self::$override;
    }

    /** Untuk pekerjaan latar belakang atau uji yang berjalan tanpa pengguna masuk. */
    public static function set(?int $id): void
    {
        self::$override = $id;
    }
}
