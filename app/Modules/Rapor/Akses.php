<?php

namespace App\Modules\Rapor;

use App\Models\Classroom;
use App\Models\Staff;
use App\Models\User;

/** Siapa boleh melihat, menyusun, mengesahkan, dan membuka kunci rapor. Tidak bergantung pada tampilan. */
final class Akses
{
    private static function walikelas(User $user, Classroom $kelas): bool
    {
        $staf = Staff::withoutGlobalScopes()->where('school_id', $user->school_id)->where('user_id', $user->id)->where('status', 'aktif')->value('id');

        return $staf !== null && $kelas->homeroom_staff_id === $staf;
    }

    private static function sesekolah(User $user, Classroom $kelas): bool
    {
        return $user->school_id !== null && $user->school_id === $kelas->school_id;
    }

    /** Admin sekolah dan kepala sekolah melihat semua; wali kelas hanya rapor kelas yang diwalikannya. */
    public static function bisaMelihat(User $user, Classroom $kelas): bool
    {
        if (! self::sesekolah($user, $kelas)) {
            return false;
        }

        return $user->can('lihat-rapor-semua') || ($user->can('lihat-rapor-kelas-sendiri') && self::walikelas($user, $kelas));
    }

    /** Menyusun rapor (catatan wali): wali kelas untuk kelasnya, admin sekolah untuk semua. */
    public static function bisaMenyusun(User $user, Classroom $kelas): bool
    {
        if (! self::sesekolah($user, $kelas) || ! $user->can('olah-rapor')) {
            return false;
        }

        return $user->can('lihat-rapor-semua') || self::walikelas($user, $kelas);
    }

    public static function bisaMengesahkan(User $user, Classroom $kelas): bool
    {
        return self::sesekolah($user, $kelas) && $user->can('sahkan-rapor');
    }

    public static function bisaBukaKunci(User $user, Classroom $kelas): bool
    {
        return self::sesekolah($user, $kelas) && $user->can('buka-kunci-rapor');
    }
}
