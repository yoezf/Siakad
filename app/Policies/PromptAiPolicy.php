<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Prompt kustom: aturan sama dengan analisis AI (admin sekolah, fitur menyala), tetapi prompt boleh diubah dan dihapus. */
class PromptAiPolicy extends AnalisisAiPolicy
{
    public function update(User $user, Model $data): bool
    {
        return $this->delete($user, $data);
    }
}
