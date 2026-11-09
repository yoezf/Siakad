<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Pengumuman sekolah untuk orang tua dan siswa. Tidak dihapus: pengumuman yang tidak berlaku lagi diarsipkan. */
#[Fillable(['title', 'body', 'audience', 'classroom_id', 'status'])]
class Announcement extends Model
{
    use BelongsToSchool, LogsActivity;

    public const AUDIENCE = [
        'semua' => 'Semua orang tua dan siswa',
        'orang_tua' => 'Orang tua saja',
        'siswa' => 'Siswa saja',
        'kelas' => 'Orang tua dan siswa kelas tertentu',
    ];

    public const STATUS = ['draf' => 'Draf', 'terbit' => 'Terbit', 'arsip' => 'Arsip'];

    protected $attributes = ['audience' => 'semua', 'status' => 'draf'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (Announcement $a) {
            $a->title = trim((string) $a->title);
            $a->body = trim((string) $a->body);
            $err = [];
            if (mb_strlen($a->title) < 3 || mb_strlen($a->title) > 150) {
                $err['title'] = 'Judul harus 3 sampai 150 karakter.';
            }
            if ($a->body === '' || mb_strlen($a->body) > 5000) {
                $err['body'] = 'Isi pengumuman wajib diisi dan paling banyak 5000 karakter.';
            }
            if (! array_key_exists((string) $a->audience, self::AUDIENCE)) {
                $err['audience'] = 'Sasaran tidak dikenal.';
            }
            if (! array_key_exists((string) $a->status, self::STATUS)) {
                $err['status'] = 'Status tidak dikenal.';
            }
            if ($a->audience === 'kelas') {
                if ($a->classroom_id === null || ! Classroom::withoutGlobalScopes()->where('school_id', $a->school_id ?? \App\Support\Tenancy\CurrentSchool::id())->whereKey($a->classroom_id)->exists()) {
                    $err['classroom_id'] = 'Pilih kelas sasaran yang ada di sekolah ini.';
                }
            } else {
                $a->classroom_id = null; // kelas hanya bermakna untuk sasaran "kelas"
            }
            // Pengumuman yang sudah terbit tidak kembali menjadi draf; tarik dengan mengarsipkannya.
            if ($a->exists && $a->getOriginal('status') !== 'draf' && $a->status === 'draf') {
                $err['status'] = 'Pengumuman yang sudah terbit tidak bisa kembali menjadi draf. Arsipkan saja.';
            }
            if ($err) {
                throw ValidationException::withMessages($err);
            }
            if ($a->status === 'terbit' && $a->published_at === null) {
                $a->published_at = now();
            }
        });

        static::creating(function (Announcement $a) {
            $a->created_by ??= auth()->id();
        });
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'audience', 'classroom_id', 'status'])->logOnlyDirty()->useLogName('pengumuman');
    }
}
