<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Analisis AI memuat foto pribadi: hanya admin sekolah, dan hanya bila Super Admin menyalakan fitur analisis_ai untuk sekolahnya.
 * Berbeda dari data lain, penghapusan diizinkan (hak menghapus foto dan hasil).
 */
class AnalisisAiPolicy extends SchoolDataPolicy
{
    protected string $lihat = 'lihat-analisis-ai';

    protected string $kelola = 'kelola-analisis-ai';

    private function aktif(User $user): bool
    {
        return (bool) $user->school?->isActive() && $user->school->hasFeature('analisis_ai');
    }

    public function viewAny(User $user): bool
    {
        return $this->aktif($user) && parent::viewAny($user);
    }

    public function view(User $user, Model $data): bool
    {
        return $this->aktif($user) && parent::view($user, $data);
    }

    public function create(User $user): bool
    {
        return $this->aktif($user) && parent::create($user);
    }

    public function update(User $user, Model $data): bool
    {
        return false; // hasil tidak disunting; analisis ulang berarti analisis baru
    }

    public function delete(User $user, Model $data): bool
    {
        return $this->aktif($user) && $user->can($this->kelola) && $this->sesekolah($user, $data);
    }

    public function deleteAny(User $user): bool
    {
        return false; // tidak ada hapus massal
    }
}
