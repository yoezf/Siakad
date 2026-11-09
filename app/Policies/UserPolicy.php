<?php

namespace App\Policies;

use App\Models\User;

/** Admin sekolah mengelola pengguna di sekolahnya sendiri saja. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('kelola-pengguna');
    }

    public function view(User $user, User $target): bool
    {
        return $this->kelola($user, $target);
    }

    public function create(User $user): bool
    {
        return $user->can('kelola-pengguna');
    }

    public function update(User $user, User $target): bool
    {
        return $this->kelola($user, $target);
    }

    // Pengguna tidak dihapus agar riwayat audit tetap utuh.
    public function delete(User $user, User $target): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function kelola(User $user, User $target): bool
    {
        return $user->can('kelola-pengguna') && $user->school_id !== null && $user->school_id === $target->school_id;
    }
}
