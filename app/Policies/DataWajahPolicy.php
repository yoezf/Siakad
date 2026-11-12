<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Data wajah siswa (biometrik): hanya admin sekolah, dan hanya bila fitur absensi_wajah menyala untuk sekolahnya. Berbeda dari data lain, boleh dihapus. */
class DataWajahPolicy extends SchoolDataPolicy
{
    protected string $lihat = 'kelola-data-wajah';

    protected string $kelola = 'kelola-data-wajah';

    private function aktif(User $user): bool
    {
        return (bool) $user->school?->isActive() && $user->school->hasFeature('absensi_wajah');
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
        return $this->aktif($user) && parent::update($user, $data);
    }

    public function delete(User $user, Model $data): bool
    {
        return $this->aktif($user) && $user->can($this->kelola) && $this->sesekolah($user, $data);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
