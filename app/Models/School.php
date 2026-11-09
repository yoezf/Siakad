<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'slug', 'city', 'plan', 'features', 'status', 'levels', 'address', 'phone', 'contact_email', 'settings'])]
class School extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['features' => 'array', 'levels' => 'array', 'settings' => 'array'];
    }

    /** Pengaturan sekolah; jika belum diubah, memakai nilai bawaan di config/sekolah.php. */
    public function setting(string $key): mixed
    {
        return $this->settings[$key] ?? config("sekolah.pengaturan.{$key}");
    }

    protected static function booted(): void
    {
        static::creating(function (School $school) {
            $school->features ??= config("fitur.paket.{$school->plan}.fitur", []);
            $school->levels ??= ['sd']; // acuan awal SD; struktur tetap umum
            if (blank($school->slug)) {
                $base = Str::slug($school->name) ?: 'sekolah';
                $slug = $base;
                for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }
                $school->slug = $slug;
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasFeature(string $key): bool
    {
        return in_array($key, $this->features ?? [], true);
    }

    public function isActive(): bool
    {
        return $this->status !== 'nonaktif';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'city', 'levels', 'address', 'phone', 'contact_email', 'plan', 'features', 'status', 'settings'])->logOnlyDirty()->useLogName('sekolah');
    }
}
