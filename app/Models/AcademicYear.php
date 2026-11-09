<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'start_date', 'end_date'])]
class AcademicYear extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    protected static function booted(): void
    {
        // Aturan data dijaga di model, bukan hanya di formulir, supaya berlaku dari jalur mana pun.
        static::saving(function (AcademicYear $year) {
            if ($year->end_date->lte($year->start_date)) {
                throw ValidationException::withMessages(['end_date' => 'Tanggal selesai harus setelah tanggal mulai.']);
            }
            // Mengubah rentang tahun ajaran tidak boleh membuat semester yang sudah ada keluar dari rentang.
            if ($year->exists && $year->semesters()->where(
                fn ($q) => $q->where('start_date', '<', $year->start_date)->orWhere('end_date', '>', $year->end_date)
            )->exists()) {
                throw ValidationException::withMessages(['start_date' => 'Ada semester yang berada di luar rentang tanggal baru.']);
            }
        });
    }

    /**
     * Tahun ajaran yang sedang berjalan: tahun dari semester aktif; bila belum ada semester aktif, tahun terbaru.
     * Sekolah diberikan eksplisit agar bisa dipakai di policy tanpa bergantung pada sekolah aktif di sesi.
     */
    public static function tahunBerjalanId(int $schoolId): ?int
    {
        return Semester::withoutGlobalScopes()->where('school_id', $schoolId)->where('aktif', 1)->value('academic_year_id')
            ?? static::withoutGlobalScopes()->where('school_id', $schoolId)->orderByDesc('start_date')->value('id');
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'start_date', 'end_date'])->logOnlyDirty()->useLogName('tahun_ajaran');
    }
}
