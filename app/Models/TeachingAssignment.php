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

/** Guru pengampu sebuah mata pelajaran pada sebuah kelas. Satu guru per mata pelajaran per kelas. */
#[Fillable(['classroom_id', 'subject_id', 'staff_id'])]
class TeachingAssignment extends Model
{
    use BelongsToSchool, LogsActivity;

    protected static function booted(): void
    {
        static::saving(function (TeachingAssignment $a) {
            // Pencarian terkena penyaring sekolah, jadi data sekolah lain dianggap tidak ada.
            if (! Classroom::whereKey($a->classroom_id)->exists()) {
                throw ValidationException::withMessages(['classroom_id' => 'Kelas tidak ditemukan.']);
            }
            if (! Subject::whereKey($a->subject_id)->exists()) {
                throw ValidationException::withMessages(['subject_id' => 'Mata pelajaran tidak ditemukan.']);
            }
            if (! Staff::whereKey($a->staff_id)->where('kind', 'guru')->where('status', 'aktif')->exists()) {
                throw ValidationException::withMessages(['staff_id' => 'Pengampu harus guru yang berstatus aktif.']);
            }
            // Kelas dan mata pelajaran tidak berpindah; yang boleh berganti hanya gurunya.
            if ($a->exists && $a->isDirty(['classroom_id', 'subject_id'])) {
                throw new LogicException('Kelas dan mata pelajaran penugasan tidak boleh diubah; ganti gurunya saja.');
            }
            // Jadwal ikut guru baru (diteruskan oleh basis data). Tolak lebih dulu bila guru pengganti sudah mengajar
            // pada jam yang sama dengan salah satu pelajaran ini, supaya jadwal tidak bentrok.
            if ($a->exists && $a->isDirty('staff_id')) {
                $bentrok = DB::table('schedule_entries as lama')
                    ->join('schedule_entries as baru', fn ($j) => $j->on('lama.day', '=', 'baru.day')->on('lama.time_slot_id', '=', 'baru.time_slot_id'))
                    ->where('lama.school_id', $a->school_id)->where('lama.classroom_id', $a->classroom_id)->where('lama.subject_id', $a->subject_id)
                    ->where('baru.school_id', $a->school_id)->where('baru.staff_id', $a->staff_id)
                    ->exists();
                if ($bentrok) {
                    throw ValidationException::withMessages(['staff_id' => 'Guru pengganti sudah mengajar pada jam yang sama dengan jadwal pelajaran ini. Atur jadwalnya dulu.']);
                }
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['classroom_id', 'subject_id', 'staff_id'])->logOnlyDirty()->useLogName('penugasan_guru');
    }
}
