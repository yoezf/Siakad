<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Kelas (rombongan belajar) pada satu tahun ajaran, dengan wali kelas. Siswa ditempatkan lewat Enrollment. */
#[Fillable(['academic_year_id', 'name', 'grade', 'homeroom_staff_id'])]
class Classroom extends Model
{
    use BelongsToSchool, LogsActivity;

    protected static function booted(): void
    {
        static::saving(function (Classroom $c) {
            $c->name = trim((string) $c->name);
            if ($c->name === '') {
                throw ValidationException::withMessages(['name' => 'Nama kelas wajib diisi.']);
            }
            if ((int) $c->grade < 1 || (int) $c->grade > 12) {
                throw ValidationException::withMessages(['grade' => 'Tingkat harus antara 1 dan 12.']);
            }
            // Pencarian terkena penyaring sekolah, jadi data sekolah lain dianggap tidak ada.
            if (! AcademicYear::whereKey($c->academic_year_id)->exists()) {
                throw ValidationException::withMessages(['academic_year_id' => 'Tahun ajaran tidak ditemukan.']);
            }
            // Kelas tidak berpindah tahun ajaran, karena penempatan siswa dan penugasan guru bergantung padanya.
            if ($c->exists && $c->isDirty('academic_year_id')) {
                throw ValidationException::withMessages(['academic_year_id' => 'Tahun ajaran sebuah kelas tidak boleh diubah.']);
            }
            if ($c->homeroom_staff_id !== null
                && ! Staff::whereKey($c->homeroom_staff_id)->where('kind', 'guru')->where('status', 'aktif')->exists()) {
                throw ValidationException::withMessages(['homeroom_staff_id' => 'Wali kelas harus guru yang berstatus aktif.']);
            }
        });
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function homeroom(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'homeroom_staff_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['academic_year_id', 'name', 'grade', 'homeroom_staff_id'])->logOnlyDirty()->useLogName('kelas');
    }
}
