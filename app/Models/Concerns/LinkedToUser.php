<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/** Untuk model yang boleh ditautkan ke akun masuk (guru, orang tua). Akun harus milik sekolah yang sama. */
trait LinkedToUser
{
    public static function bootLinkedToUser(): void
    {
        static::saving(function ($model) {
            // Pada data baru, school_id baru diisi oleh event creating yang berjalan sesudah saving.
            $sekolah = $model->school_id ?? CurrentSchool::id();
            if ($model->user_id !== null && $model->isDirty('user_id')
                && ($sekolah === null || ! User::whereKey($model->user_id)->where('school_id', $sekolah)->exists())) {
                throw ValidationException::withMessages(['user_id' => 'Akun tidak ditemukan di sekolah ini.']);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
