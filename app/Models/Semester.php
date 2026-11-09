<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

// 'aktif' sengaja tidak boleh diisi massal dan tidak di-cast: nilainya hanya 1 atau NULL (lihat migrasi), diubah lewat activate().
#[Fillable(['academic_year_id', 'number', 'start_date', 'end_date'])]
class Semester extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(function (Semester $s) {
            // Pencarian ini terkena penyaring sekolah, jadi tahun ajaran sekolah lain dianggap tidak ada.
            $year = AcademicYear::find($s->academic_year_id);
            if (! $year) {
                throw ValidationException::withMessages(['academic_year_id' => 'Tahun ajaran tidak ditemukan.']);
            }
            if (! in_array((int) $s->number, [1, 2], true)) {
                throw ValidationException::withMessages(['number' => 'Semester harus 1 (Ganjil) atau 2 (Genap).']);
            }
            if ($s->end_date->lte($s->start_date)) {
                throw ValidationException::withMessages(['end_date' => 'Tanggal selesai harus setelah tanggal mulai.']);
            }
            if ($s->start_date->lt($year->start_date) || $s->end_date->gt($year->end_date)) {
                throw ValidationException::withMessages(['start_date' => 'Semester harus berada dalam rentang tahun ajaran.']);
            }
        });
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function isActive(): bool
    {
        return (bool) $this->aktif;
    }

    public function getLabelAttribute(): string
    {
        return (int) $this->number === 1 ? 'Ganjil' : 'Genap';
    }

    /** Menjadikan semester ini satu-satunya yang aktif di sekolahnya. */
    public function activate(): void
    {
        DB::transaction(function () {
            static::where('aktif', 1)->whereKeyNot($this->getKey())->get()
                ->each(fn (Semester $lain) => $lain->forceFill(['aktif' => null])->save());
            $this->forceFill(['aktif' => 1])->save();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['academic_year_id', 'number', 'start_date', 'end_date', 'aktif'])->logOnlyDirty()->useLogName('semester');
    }
}
