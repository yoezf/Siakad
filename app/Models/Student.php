<?php

namespace App\Models;

use App\Models\Concerns\LinkedToUser;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['user_id', 'name', 'nis', 'nisn', 'gender', 'birth_place', 'birth_date', 'status'])]
class Student extends Model
{
    use BelongsToSchool, LinkedToUser, LogsActivity;

    public const STATUS = ['aktif' => 'Aktif', 'lulus' => 'Lulus', 'pindah' => 'Pindah sekolah', 'keluar' => 'Keluar'];

    public const GENDER = ['L' => 'Laki-laki', 'P' => 'Perempuan'];

    protected $attributes = ['status' => 'aktif'];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    protected static function booted(): void
    {
        // Aturan data dijaga di model agar berlaku dari jalur mana pun (formulir, impor, kode lain).
        static::saving(function (Student $s) {
            $s->name = trim((string) $s->name);
            // Isian kosong disimpan sebagai NULL (tidak dianggap kembar oleh indeks unik); normalkan sebelum divalidasi.
            foreach (['nis', 'nisn'] as $k) {
                $s->{$k} = ($s->{$k} === null || trim((string) $s->{$k}) === '') ? null : trim((string) $s->{$k});
            }
            $err = [];
            if ($s->name === '') {
                $err['name'] = 'Nama wajib diisi.';
            }
            if (! array_key_exists((string) $s->gender, self::GENDER)) {
                $err['gender'] = 'Jenis kelamin harus L atau P.';
            }
            if (! array_key_exists((string) $s->status, self::STATUS)) {
                $err['status'] = 'Status tidak dikenal.';
            }
            if ($s->nisn !== null && ! preg_match('/^\d{10}$/', $s->nisn)) {
                $err['nisn'] = 'NISN harus 10 digit angka.';
            }
            if ($s->birth_date !== null && $s->birth_date->isFuture()) {
                $err['birth_date'] = 'Tanggal lahir tidak boleh di masa depan.';
            }
            if ($err) {
                throw ValidationException::withMessages($err);
            }
        });

        // Siswa yang lulus, pindah, atau keluar tidak lagi berada di kelas; penempatannya ditutup (riwayat tetap).
        static::saved(function (Student $s) {
            if ($s->wasChanged('status') && $s->status !== 'aktif') {
                Enrollment::withoutGlobalScopes()->where('school_id', $s->school_id)->where('student_id', $s->id)->where('aktif', 1)
                    ->get()->each->tutup();
            }
        });
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'nis', 'nisn', 'gender', 'status'])->logOnlyDirty()->useLogName('siswa');
    }
}
