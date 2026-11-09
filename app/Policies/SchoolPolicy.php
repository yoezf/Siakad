<?php

namespace App\Policies;

use App\Models\User;

/** Daftar sekolah hanya untuk tim developer (Super Admin). Sekolah dinonaktifkan lewat status, tidak dihapus. */
class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('kelola-sekolah');
    }

    public function view(User $user): bool
    {
        return $user->can('kelola-sekolah');
    }

    public function create(User $user): bool
    {
        return $user->can('kelola-sekolah');
    }

    public function update(User $user): bool
    {
        return $user->can('kelola-sekolah');
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
