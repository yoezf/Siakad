<?php

namespace App\Modules\Penilaian;

use App\Models\Classroom;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;

/** Siapa boleh menilai dan siapa boleh melihat nilai. Dipakai oleh layanan dan halaman; tidak bergantung pada tampilan. */
final class Akses
{
    private static function stafAktif(User $user): ?int
    {
        return Staff::withoutGlobalScopes()->where('school_id', $user->school_id)->where('user_id', $user->id)->where('status', 'aktif')->value('id');
    }

    private static function sesekolah(User $user, Classroom $kelas, ?Subject $mapel): bool
    {
        return $user->school_id !== null && $user->school_id === $kelas->school_id && ($mapel === null || $user->school_id === $mapel->school_id);
    }

    private static function mengampu(User $user, Classroom $kelas, Subject $mapel): bool
    {
        $staf = self::stafAktif($user);

        return $staf !== null && TeachingAssignment::withoutGlobalScopes()->where('school_id', $user->school_id)
            ->where('classroom_id', $kelas->id)->where('subject_id', $mapel->id)->where('staff_id', $staf)->exists();
    }

    /** Admin sekolah boleh menilai semua; guru hanya mata pelajaran yang diampunya di kelas itu (penugasan guru). */
    public static function bisaMenilai(User $user, Classroom $kelas, Subject $mapel): bool
    {
        if (! self::sesekolah($user, $kelas, $mapel) || ! $user->can('input-nilai')) {
            return false;
        }

        return $user->can('input-nilai-semua') || self::mengampu($user, $kelas, $mapel);
    }

    /** Selain admin, nilai hanya bisa diisi pada semester yang sedang aktif. */
    public static function semesterBoleh(User $user, Semester $semester): bool
    {
        return $semester->school_id === $user->school_id && ($user->can('input-nilai-semua') || $semester->isActive());
    }

    /**
     * Admin dan kepala sekolah melihat semua. Wali kelas melihat semua mata pelajaran di kelas yang diwalikannya.
     * Guru mata pelajaran hanya melihat nilai mata pelajaran yang diampunya.
     */
    public static function bisaMelihat(User $user, Classroom $kelas, ?Subject $mapel = null): bool
    {
        if (! self::sesekolah($user, $kelas, $mapel)) {
            return false;
        }
        if ($user->can('lihat-nilai-semua')) {
            return true;
        }
        if (! $user->can('lihat-nilai-kelas-sendiri')) {
            return false;
        }
        $staf = self::stafAktif($user);
        if (! $staf) {
            return false;
        }
        if ($kelas->homeroom_staff_id === $staf) {
            return true;
        }

        return $mapel !== null && self::mengampu($user, $kelas, $mapel);
    }

    public static function kkmSekolah(User $user): int
    {
        return (int) ($user->school?->setting('kkm') ?? config('sekolah.pengaturan.kkm'));
    }
}
