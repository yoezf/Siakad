<?php

namespace App\Support\Tenancy;

use App\Models\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Pasang pada setiap model data sekolah (siswa, kelas, nilai, dan seterusnya). Wajib ada kolom school_id. */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function ($model) {
            $model->school_id ??= CurrentSchool::id();
            if ($model->school_id === null) {
                throw new LogicException('Data sekolah tidak boleh dibuat tanpa sekolah aktif.');
            }
        });

        // Data tidak boleh dipindahkan ke sekolah lain.
        static::updating(function ($model) {
            if ($model->isDirty('school_id')) {
                throw new LogicException('Data tidak boleh dipindahkan ke sekolah lain.');
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
