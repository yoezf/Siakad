<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Siswa: admin sekolah, kepala sekolah, dan staf TU melihat semua. Wali kelas dan guru mata pelajaran hanya melihat
 * siswa di kelas yang mereka walikan atau ampu pada tahun ajaran berjalan. Mengubah data tetap hanya untuk yang berizin kelola.
 */
class StudentPolicy extends DataPribadiPolicy
{
    private const KELAS_SENDIRI = 'lihat-siswa-kelas-sendiri';

    public function viewAny(User $user): bool
    {
        return parent::viewAny($user) || $user->can(self::KELAS_SENDIRI);
    }

    public function view(User $user, Model $data): bool
    {
        if (! $this->sesekolah($user, $data)) {
            return false;
        }

        return $user->can($this->lihat) || ($user->can(self::KELAS_SENDIRI) && $this->diKelasSaya($user, $data));
    }

    private function diKelasSaya(User $user, Model $siswa): bool
    {
        $kelas = $user->idKelasSaya();

        return $kelas !== [] && Enrollment::withoutGlobalScopes()->where('school_id', $user->school_id)
            ->where('student_id', $siswa->getKey())->where('aktif', 1)->whereIn('classroom_id', $kelas)->exists();
    }
}
