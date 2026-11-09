<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['code', 'name', 'kkm'])]
class Subject extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return ['kkm' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Subject $s) {
            // Isian kosong harus menjadi NULL (ikut KKM sekolah), jangan 0: cast integer akan mengubah '' menjadi 0.
            if (($s->getAttributes()['kkm'] ?? null) === '') {
                $s->setAttribute('kkm', null);
            }
            $s->code = strtoupper(trim((string) $s->code));
            $s->name = trim((string) $s->name);
            if ($s->code === '' || $s->name === '') {
                throw ValidationException::withMessages(['code' => 'Kode dan nama mata pelajaran wajib diisi.']);
            }
            // KKM khusus mata pelajaran bersifat opsional (kosong = ikut KKM sekolah).
            // Diperiksa pada nilai MENTAH: cast integer mengubah 'tinggi' menjadi 0 dan membuang desimal sebelum sempat diperiksa.
            $kkm = $s->getAttributes()['kkm'] ?? null;
            if ($kkm !== null && (! is_numeric($kkm) || floor((float) $kkm) != (float) $kkm || $kkm < 0 || $kkm > 100)) {
                throw ValidationException::withMessages(['kkm' => 'KKM harus bilangan bulat 0 sampai 100, atau dikosongkan.']);
            }
        });
    }

    /** KKM yang berlaku: milik mata pelajaran bila diisi, selain itu KKM sekolah. */
    public function kkmBerlaku(int $kkmSekolah): int
    {
        return $this->kkm ?? $kkmSekolah;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['code', 'name', 'kkm'])->logOnlyDirty()->useLogName('mata_pelajaran');
    }
}
