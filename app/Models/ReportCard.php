<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Kepala rapor satu siswa pada satu semester: status penguncian dan catatan wali kelas yang masih bisa diedit.
 * Status berubah hanya lewat layanan (SahkanRapor, BukaKunciRapor), karena itu tidak ada di fillable.
 * draf = belum pernah disahkan | disahkan = terkunci | revisi = dibuka untuk perbaikan setelah pernah disahkan.
 */
#[Fillable(['semester_id', 'student_id', 'classroom_id', 'homeroom_note'])]
class ReportCard extends Model
{
    use BelongsToSchool, LogsActivity;

    public const STATUS = ['draf' => 'Draf', 'disahkan' => 'Disahkan', 'revisi' => 'Dalam revisi'];

    protected $attributes = ['status' => 'draf', 'current_version' => 0];

    protected function casts(): array
    {
        return ['unlocked_at' => 'datetime', 'current_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (ReportCard $r) {
            if (! array_key_exists((string) $r->status, self::STATUS)) {
                throw ValidationException::withMessages(['status' => 'Status rapor tidak dikenal.']);
            }
            $r->homeroom_note = ($r->homeroom_note === null || trim((string) $r->homeroom_note) === '') ? null : trim((string) $r->homeroom_note);
            if ($r->homeroom_note !== null && mb_strlen($r->homeroom_note) > 1000) {
                throw ValidationException::withMessages(['homeroom_note' => 'Catatan wali kelas maksimal 1000 karakter.']);
            }
            // Selagi disahkan, catatan wali ikut terkunci (sudah termuat dalam salinan versi yang berlaku).
            if ($r->exists && $r->getOriginal('status') === 'disahkan' && $r->isDirty('homeroom_note')) {
                throw ValidationException::withMessages(['homeroom_note' => 'Rapor sudah disahkan; catatan wali kelas terkunci. Buka kunci rapor untuk merevisi.']);
            }
            if ($r->exists && $r->isDirty(['semester_id', 'student_id'])) {
                throw new \LogicException('Semester dan siswa pada rapor tidak boleh diubah.');
            }
        });
    }

    /** Apakah rapor siswa ini pada semester ini sedang disahkan (nilai dan kehadirannya terkunci)? */
    public static function terkunci(int $semesterId, int $siswaId): bool
    {
        return static::where('semester_id', $semesterId)->where('student_id', $siswaId)->where('status', 'disahkan')->exists();
    }

    /** Apakah ada rapor siswa ini yang disahkan untuk semester yang mencakup tanggal itu? */
    public static function terkunciPadaTanggal(int $siswaId, CarbonInterface $tanggal): bool
    {
        return static::where('student_id', $siswaId)->where('status', 'disahkan')
            ->whereHas('semester', fn ($q) => $q->whereDate('start_date', '<=', $tanggal)->whereDate('end_date', '>=', $tanggal))->exists();
    }

    public function isLocked(): bool
    {
        return $this->status === 'disahkan';
    }

    /**
     * Pemeriksaan integritas: nomor versi berlaku harus cocok dengan jumlah salinan (1, 2, 3, ... tanpa celah) dan sidik tiap
     * salinan harus utuh. Penghapusan atau perubahan lewat SQL langsung tidak bisa dicegah tanpa trigger basis data, tetapi
     * dengan pemeriksaan ini tidak akan lolos tanpa diketahui.
     */
    public function utuh(): bool
    {
        $versi = $this->versions()->get();
        if ($versi->count() !== (int) $this->current_version) {
            return false;
        }
        foreach ($versi->values() as $i => $v) {
            if ($v->version !== $i + 1 || ! $v->utuh()) {
                return false;
            }
        }

        return true;
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ReportCardVersion::class)->orderBy('version');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'current_version', 'homeroom_note', 'unlock_reason', 'classroom_id'])->logOnlyDirty()->useLogName('rapor');
    }
}
