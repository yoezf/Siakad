<?php

namespace App\Modules\Pengumuman;

use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\User;
use App\Modules\Portal\Anak;
use App\Support\Peran;
use Illuminate\Support\Collection;

/**
 * Pengumuman yang boleh dibaca seorang pengguna portal. Hanya yang terbit, dari sekolah akun itu sendiri.
 * Sasaran "kelas" mengikuti kelas aktif anak pengguna (orang tua) atau dirinya (siswa).
 */
final class Daftar
{
    /** @return Collection<int, Announcement> */
    public static function untuk(User $user, int $batas = 30): Collection
    {
        if ($user->school_id === null || ! $user->hasAnyRole(Peran::PORTAL)) {
            return collect();
        }
        $peran = array_values(array_filter([
            $user->hasRole(Peran::ORANG_TUA) ? 'orang_tua' : null,
            $user->hasRole(Peran::SISWA) ? 'siswa' : null,
        ]));
        $kelas = Enrollment::withoutGlobalScopes()->where('school_id', $user->school_id)->where('aktif', 1)
            ->whereIn('student_id', Anak::untuk($user)->pluck('id'))->pluck('classroom_id')->unique()->all();

        return Announcement::withoutGlobalScopes()->where('school_id', $user->school_id)->where('status', 'terbit')
            ->where(fn ($q) => $q->where('audience', 'semua')->orWhereIn('audience', $peran)
                ->orWhere(fn ($k) => $k->where('audience', 'kelas')->whereIn('classroom_id', $kelas)))
            ->orderByDesc('published_at')->orderByDesc('id')->limit($batas)->get();
    }
}
