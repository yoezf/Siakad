<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Jam pelajaran dan jadwal: semua staf boleh melihat (bukan data pribadi), hanya admin sekolah dan staf TU yang mengubah.
 * Berbeda dari data riwayat, jadwal adalah rencana kerja sehingga boleh dihapus oleh yang berwenang (tercatat di audit).
 */
class JadwalPolicy extends SchoolDataPolicy
{
    protected string $lihat = 'lihat-jadwal';

    protected string $kelola = 'kelola-jadwal';

    public function delete(User $user, Model $data): bool
    {
        return $user->can($this->kelola) && $this->sesekolah($user, $data);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can($this->kelola);
    }
}
