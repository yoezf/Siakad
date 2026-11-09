<?php

namespace App\Models;

use App\Modules\Kehadiran\Status;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Kehadiran guru atau tenaga kependidikan pada satu tanggal. Hanya pegawai aktif yang bisa dicatat. */
#[Fillable(['staff_id', 'date', 'status', 'note'])]
class StaffAttendance extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(function (StaffAttendance $a) {
            if (! Status::sah($a->status)) {
                throw ValidationException::withMessages(['status' => 'Status kehadiran harus H, S, I, A, atau T.']);
            }
            if ($a->exists && $a->isDirty(['staff_id', 'date'])) {
                throw new LogicException('Pegawai dan tanggal pada catatan kehadiran tidak boleh diubah; hanya status dan catatan.');
            }
            $a->note = ($a->note === null || trim((string) $a->note) === '') ? null : mb_substr(trim((string) $a->note), 0, 255);
            if (! $a->isDirty('recorded_by') && auth()->id()) {
                $a->recorded_by = auth()->id(); // layanan mengisinya lebih dulu dengan pencatat sebenarnya
            }

            if ($a->exists) {
                return;
            }
            if ($a->date->gt(today())) {
                throw ValidationException::withMessages(['date' => 'Kehadiran tidak boleh dicatat untuk tanggal yang belum tiba.']);
            }
            if (! Staff::whereKey($a->staff_id)->where('status', 'aktif')->exists()) {
                throw ValidationException::withMessages(['staff_id' => 'Pegawai tidak ditemukan atau tidak berstatus aktif.']);
            }
        });
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['staff_id', 'date', 'status', 'note'])->logOnlyDirty()->useLogName('kehadiran_pegawai');
    }
}
