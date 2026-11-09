<?php

namespace App\Models;

use App\Models\Concerns\LinkedToUser;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Orang tua atau wali. Satu orang boleh terhubung ke beberapa anak. */
#[Fillable(['user_id', 'name', 'relationship', 'phone', 'email'])]
class Guardian extends Model
{
    use BelongsToSchool, LinkedToUser, LogsActivity;

    public const RELATIONSHIP = ['ayah' => 'Ayah', 'ibu' => 'Ibu', 'wali' => 'Wali'];

    protected $attributes = ['relationship' => 'wali'];

    protected static function booted(): void
    {
        static::saving(function (Guardian $g) {
            $g->name = trim((string) $g->name);
            if ($g->name === '' || ! array_key_exists((string) $g->relationship, self::RELATIONSHIP)) {
                throw ValidationException::withMessages(['name' => 'Nama wajib diisi dan hubungan harus ayah, ibu, atau wali.']);
            }
        });
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withTimestamps();
    }

    /**
     * Menetapkan daftar anak. Hanya siswa sekolah ini yang diterima; selebihnya ditolak.
     * Basis data menjaga hal yang sama lewat kunci asing gabungan.
     */
    public function linkStudents(array $studentIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $studentIds)));
        if (Student::whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['students' => 'Ada siswa yang tidak ditemukan di sekolah ini.']);
        }
        DB::transaction(fn () => $this->students()->syncWithPivotValues($ids, ['school_id' => $this->school_id]));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'relationship', 'user_id'])->logOnlyDirty()->useLogName('orang_tua');
    }
}
