<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Dasar policy untuk data milik sekolah: dua izin (lihat dan kelola) dan wajib sesekolah.
 * Data tidak pernah dihapus, jadi delete selalu ditolak.
 */
abstract class SchoolDataPolicy
{
    protected string $lihat;

    protected string $kelola;

    public function viewAny(User $user): bool
    {
        return $user->can($this->lihat);
    }

    public function view(User $user, Model $data): bool
    {
        return $user->can($this->lihat) && $this->sesekolah($user, $data);
    }

    public function create(User $user): bool
    {
        return $user->can($this->kelola);
    }

    public function update(User $user, Model $data): bool
    {
        return $user->can($this->kelola) && $this->sesekolah($user, $data);
    }

    public function delete(User $user, Model $data): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    protected function sesekolah(User $user, Model $data): bool
    {
        return $user->school_id !== null && $user->school_id === $data->school_id;
    }
}
