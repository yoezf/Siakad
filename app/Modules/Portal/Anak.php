<?php

namespace App\Modules\Portal;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Support\Peran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya sumber jawaban "siapa saja yang boleh dilihat pengguna portal ini". Orang tua mendapat anak yang terhubung
 * ke dirinya; siswa hanya dirinya sendiri. Peran wajib ada: akun tanpa peran portal tidak melihat siapa pun, walau datanya terhubung.
 * Sekolah diambil dari akun, bukan dari sekolah yang sedang dibuka, sehingga tidak bisa dibelokkan lewat alamat atau tenant.
 */
final class Anak
{
    /** @return Collection<int, Student> */
    public static function untuk(User $user): Collection
    {
        if ($user->school_id === null) {
            return collect();
        }
        $ids = collect();
        if ($user->hasRole(Peran::ORANG_TUA)) {
            $wali = Guardian::withoutGlobalScopes()->where('school_id', $user->school_id)->where('user_id', $user->id)->pluck('id');
            $ids = $ids->merge(DB::table('guardian_student')->where('school_id', $user->school_id)->whereIn('guardian_id', $wali)->pluck('student_id'));
        }
        if ($user->hasRole(Peran::SISWA)) {
            $ids = $ids->merge(Student::withoutGlobalScopes()->where('school_id', $user->school_id)->where('user_id', $user->id)->pluck('id'));
        }

        return Student::withoutGlobalScopes()->where('school_id', $user->school_id)->whereIn('id', $ids->unique()->all())
            ->orderBy('name')->get();
    }

    public static function boleh(User $user, int $siswaId): bool
    {
        return self::untuk($user)->contains('id', $siswaId);
    }
}
