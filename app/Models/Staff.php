<?php

namespace App\Models;

use App\Models\Concerns\LinkedToUser;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['user_id', 'name', 'nip', 'kind', 'phone', 'email', 'status'])]
class Staff extends Model
{
    use BelongsToSchool, LinkedToUser, LogsActivity;

    protected $table = 'staff';

    protected $attributes = ['kind' => 'guru', 'status' => 'aktif'];

    public const KIND = ['guru' => 'Guru', 'tendik' => 'Tenaga kependidikan'];

    public const STATUS = ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];

    protected static function booted(): void
    {
        static::saving(function (Staff $s) {
            $s->name = trim((string) $s->name);
            $err = [];
            if ($s->name === '') {
                $err['name'] = 'Nama wajib diisi.';
            }
            if (! array_key_exists((string) $s->kind, self::KIND)) {
                $err['kind'] = 'Jenis harus guru atau tendik.';
            }
            if (! array_key_exists((string) $s->status, self::STATUS)) {
                $err['status'] = 'Status tidak dikenal.';
            }
            if ($err) {
                throw ValidationException::withMessages($err);
            }
            $s->nip = ($s->nip === null || trim((string) $s->nip) === '') ? null : trim((string) $s->nip);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'nip', 'kind', 'status', 'user_id'])->logOnlyDirty()->useLogName('staf');
    }
}
