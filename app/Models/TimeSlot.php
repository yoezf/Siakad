<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Jam pelajaran milik sekolah: urutan, nama, jam mulai dan selesai. Istirahat tidak bisa diisi pelajaran. */
#[Fillable(['number', 'name', 'start_time', 'end_time', 'is_break'])]
class TimeSlot extends Model
{
    use BelongsToSchool, LogsActivity;

    protected $attributes = ['is_break' => false];

    protected function casts(): array
    {
        return ['is_break' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (TimeSlot $t) {
            $t->name = trim((string) $t->name);
            $err = [];
            if ($t->name === '') {
                $err['name'] = 'Nama jam pelajaran wajib diisi.';
            }
            if ((int) $t->number < 1 || (int) $t->number > 30) {
                $err['number'] = 'Urutan harus antara 1 dan 30.';
            }
            foreach (['start_time', 'end_time'] as $k) {
                $t->{$k} = self::normal($t->{$k});
                if ($t->{$k} === null) {
                    $err[$k] = 'Jam harus berformat JJ:MM (00:00 sampai 23:59).';
                }
            }
            if ($err) {
                throw ValidationException::withMessages($err);
            }
            if ($t->end_time <= $t->start_time) {
                throw ValidationException::withMessages(['end_time' => 'Jam selesai harus setelah jam mulai.']);
            }
            // Jam pelajaran satu sekolah tidak boleh saling menimpa (pencarian terkena penyaring sekolah).
            $tabrak = static::where('start_time', '<', $t->end_time)->where('end_time', '>', $t->start_time)
                ->when($t->exists, fn ($q) => $q->whereKeyNot($t->getKey()))->first();
            if ($tabrak) {
                throw ValidationException::withMessages(['start_time' => "Bertabrakan dengan {$tabrak->name} ({$tabrak->rentang()})."]);
            }
        });
    }

    /** Menormalkan 'H:i' atau 'H:i:s' menjadi 'H:i:s'; null bila bukan jam yang sah. */
    public static function normal(mixed $jam): ?string
    {
        $jam = trim((string) $jam);
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', $jam, $m)) {
            return null;
        }

        return sprintf('%s:%s:%s', $m[1], $m[2], $m[4] ?? '00');
    }

    public function rentang(): string
    {
        return substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5);
    }

    public function label(): string
    {
        return "{$this->name} ({$this->rentang()})";
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['number', 'name', 'start_time', 'end_time', 'is_break'])->logOnlyDirty()->useLogName('jam_pelajaran');
    }
}
