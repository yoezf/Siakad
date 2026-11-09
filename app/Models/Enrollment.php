<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Penempatan siswa di sebuah kelas pada satu tahun ajaran. Baris tidak pernah dihapus atau dipindahkan:
 * pindah kelas menutup baris lama (aktif = NULL, left_at terisi) dan membuka baris baru, sehingga riwayat utuh.
 * "aktif" berarti penempatan yang berlaku pada tahun ajarannya, jadi penempatan tahun lalu tetap tercatat berlaku di tahun itu.
 */
#[Fillable(['student_id', 'classroom_id', 'note'])]
class Enrollment extends Model
{
    use BelongsToSchool, LogsActivity;

    protected $attributes = ['aktif' => 1];

    protected function casts(): array
    {
        return ['joined_at' => 'date', 'left_at' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(function (Enrollment $e) {
            // Pencarian terkena penyaring sekolah, jadi siswa dan kelas sekolah lain dianggap tidak ada.
            $siswa = Student::find($e->student_id);
            if (! $siswa) {
                throw ValidationException::withMessages(['student_id' => 'Siswa tidak ditemukan.']);
            }
            if ($siswa->status !== 'aktif') {
                throw ValidationException::withMessages(['student_id' => "{$siswa->name} tidak berstatus aktif."]);
            }
            $kelas = Classroom::find($e->classroom_id);
            if (! $kelas) {
                throw ValidationException::withMessages(['classroom_id' => 'Kelas tidak ditemukan.']);
            }
            $e->academic_year_id = $kelas->academic_year_id;
            $e->joined_at ??= today();
        });

        // Riwayat tidak boleh ditulis ulang.
        static::updating(function (Enrollment $e) {
            if ($e->isDirty(['student_id', 'classroom_id', 'academic_year_id', 'joined_at'])) {
                throw new LogicException('Penempatan yang sudah tercatat tidak boleh diubah; pindahkan siswa agar riwayat tetap utuh.');
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function isActive(): bool
    {
        return (bool) $this->aktif;
    }

    public function tutup(): void
    {
        $this->forceFill(['aktif' => null, 'left_at' => today()])->save();
    }

    /** Menempatkan siswa di kelas. Ditolak bila siswa sudah punya kelas pada tahun ajaran itu (gunakan pindahkan). */
    public static function tempatkan(Student $siswa, Classroom $kelas, ?string $catatan = null): self
    {
        return DB::transaction(function () use ($siswa, $kelas, $catatan) {
            $sudah = static::where('student_id', $siswa->id)->where('academic_year_id', $kelas->academic_year_id)->where('aktif', 1)->lockForUpdate()->exists();
            if ($sudah) {
                throw ValidationException::withMessages(['student_id' => "{$siswa->name} sudah punya kelas pada tahun ajaran ini. Gunakan Pindahkan."]);
            }

            return static::create(['student_id' => $siswa->id, 'classroom_id' => $kelas->id, 'note' => $catatan]);
        });
    }

    /** Memindahkan siswa ke kelas lain pada tahun ajaran yang sama. Naik kelas = menempatkan di kelas tahun berikutnya. */
    public function pindahkan(Classroom $baru, ?string $catatan = null): self
    {
        return DB::transaction(function () use ($baru, $catatan) {
            $ini = static::whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            if (! $ini->isActive()) {
                throw ValidationException::withMessages(['classroom_id' => 'Penempatan ini sudah tidak berlaku.']);
            }
            if ($baru->academic_year_id !== $ini->academic_year_id) {
                throw ValidationException::withMessages(['classroom_id' => 'Pindah kelas hanya dalam tahun ajaran yang sama. Untuk naik kelas, tempatkan siswa di kelas tahun ajaran berikutnya.']);
            }
            if ($baru->id === $ini->classroom_id) {
                throw ValidationException::withMessages(['classroom_id' => 'Siswa sudah berada di kelas ini.']);
            }
            $ini->tutup();

            return static::create(['student_id' => $ini->student_id, 'classroom_id' => $baru->id, 'note' => $catatan]);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['student_id', 'classroom_id', 'academic_year_id', 'aktif', 'left_at'])->logOnlyDirty()->useLogName('penempatan');
    }
}
