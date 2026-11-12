<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Data numerik wajah satu siswa (bukan foto). Berisi 1 sampai 10 vektor dengan 128 angka; dienkripsi di basis data. */
#[Fillable(['student_id', 'descriptors', 'samples'])]
class FaceTemplate extends Model
{
    use BelongsToSchool, LogsActivity;

    public const DIM = 128;

    public const MAKS_SAMPEL = 10;

    protected $hidden = ['descriptors'];

    protected function casts(): array
    {
        return ['descriptors' => 'encrypted:array'];
    }

    protected static function booted(): void
    {
        static::saving(function (FaceTemplate $t) {
            $d = $t->descriptors;
            $ok = is_array($d) && $d !== [] && count($d) <= self::MAKS_SAMPEL;
            foreach ($ok ? $d : [] as $v) {
                if (! is_array($v) || count($v) !== self::DIM) {
                    $ok = false;
                    break;
                }
                foreach ($v as $x) {
                    if (! is_int($x) && ! is_float($x) || ! is_finite((float) $x) || abs($x) > 5) {
                        $ok = false;
                        break 2;
                    }
                }
            }
            if (! $ok) {
                throw ValidationException::withMessages(['descriptors' => 'Data wajah tidak sah. Ulangi pengambilan wajah.']);
            }
            $t->samples = count($d);
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['student_id', 'samples'])->logOnlyDirty()->useLogName('data_wajah'); // vektor tidak pernah dicatat
    }
}
