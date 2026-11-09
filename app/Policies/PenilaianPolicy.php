<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Komponen penilaian: semua staf boleh melihat (bukan data pribadi), hanya admin sekolah yang mengelola. Komponen yang
 * sudah dipakai nilai tidak bisa dihapus (dijaga basis data); hentikan lewat tombol "aktif".
 */
class PenilaianPolicy extends SchoolDataPolicy
{
    protected string $lihat = 'lihat-komponen-penilaian';

    protected string $kelola = 'kelola-penilaian';

    public function delete(User $user, Model $data): bool
    {
        return $user->can($this->kelola) && $this->sesekolah($user, $data);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can($this->kelola);
    }
}
