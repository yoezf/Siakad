<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Satu pelajaran pada jadwal: kelas, mata pelajaran, hari, dan jam. Gurunya TIDAK diisi pengguna, melainkan diambil dari
 * Penugasan guru, sehingga jadwal tidak mungkin memuat guru yang bukan pengampu. Bentrok dicegah dua lapis: pemeriksaan
 * di sini (pesan yang jelas) dan indeks unik basis data (penjaga terakhir, termasuk saat dua orang menyimpan bersamaan).
 */
#[Fillable(['classroom_id', 'subject_id', 'time_slot_id', 'day'])]
class ScheduleEntry extends Model
{
    use BelongsToSchool, LogsActivity;

    public const HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    protected static function booted(): void
    {
        static::saving(function (ScheduleEntry $e) {
            $e->day = (int) $e->day;
            if (! array_key_exists($e->day, self::HARI)) {
                throw ValidationException::withMessages(['day' => 'Hari tidak dikenal.']);
            }
            if ($e->exists && $e->isDirty('classroom_id')) {
                throw new LogicException('Kelas pada jadwal tidak boleh diubah; hapus lalu buat jadwal baru.');
            }
            // Kehadiran yang sudah dicatat pada pelajaran ini mengacu ke hari dan mata pelajarannya; keduanya dikunci.
            if ($e->exists && $e->isDirty(['day', 'subject_id']) && $e->attendances()->exists()) {
                throw ValidationException::withMessages(['day' => 'Pelajaran ini sudah punya data kehadiran, jadi hari dan mata pelajarannya tidak bisa diubah. Hapus tidak bisa; buat pelajaran baru di jadwal.']);
            }
            // Pencarian terkena penyaring sekolah, jadi data sekolah lain dianggap tidak ada.
            $kelas = Classroom::find($e->classroom_id);
            if (! $kelas) {
                throw ValidationException::withMessages(['classroom_id' => 'Kelas tidak ditemukan.']);
            }
            $jam = TimeSlot::find($e->time_slot_id);
            if (! $jam) {
                throw ValidationException::withMessages(['time_slot_id' => 'Jam pelajaran tidak ditemukan.']);
            }
            if ($jam->is_break) {
                throw ValidationException::withMessages(['time_slot_id' => "{$jam->name} adalah waktu istirahat dan tidak bisa diisi pelajaran."]);
            }
            $penugasan = TeachingAssignment::where('classroom_id', $e->classroom_id)->where('subject_id', $e->subject_id)->first();
            if (! $penugasan) {
                throw ValidationException::withMessages(['subject_id' => "Mata pelajaran ini belum punya guru pengampu di kelas {$kelas->name}. Tetapkan dulu di Penugasan guru."]);
            }
            $e->staff_id = $penugasan->staff_id; // guru mengikuti penugasan, bukan isian pengguna

            $lain = fn ($q) => $q->when($e->exists, fn ($q) => $q->whereKeyNot($e->getKey()));
            $bentrokKelas = $lain(static::where('classroom_id', $e->classroom_id)->where('day', $e->day)->where('time_slot_id', $e->time_slot_id))->with('subject')->first();
            if ($bentrokKelas) {
                throw ValidationException::withMessages(['time_slot_id' => "Kelas {$kelas->name} sudah punya pelajaran {$bentrokKelas->subject->name} pada ".self::HARI[$e->day].", {$jam->name}."]);
            }
            $bentrokGuru = $lain(static::where('staff_id', $e->staff_id)->where('day', $e->day)->where('time_slot_id', $e->time_slot_id))->with('classroom')->first();
            if ($bentrokGuru) {
                $guru = Staff::find($e->staff_id)?->name ?? 'Guru ini';
                throw ValidationException::withMessages(['subject_id' => "{$guru} sudah mengajar di kelas {$bentrokGuru->classroom->name} pada ".self::HARI[$e->day].", {$jam->name}."]);
            }
        });
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['classroom_id', 'subject_id', 'staff_id', 'time_slot_id', 'day'])->logOnlyDirty()->useLogName('jadwal');
    }
}
