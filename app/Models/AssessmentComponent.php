<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Komponen penilaian milik sekolah (Ulangan Harian, UTS, UAS, ...). Nilai akhir = rata-rata berbobot semua komponen aktif,
 * dinormalkan terhadap jumlah bobot, sehingga bobot tidak harus tepat 100 (halaman komponen memperingatkan bila tidak).
 * Komponen yang dihentikan tidak dihapus agar nilai lama tetap utuh.
 */
#[Fillable(['name', 'weight', 'sort_order', 'active'])]
class AssessmentComponent extends Model
{
    use BelongsToSchool, LogsActivity;

    protected $attributes = ['active' => true, 'sort_order' => 1];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'weight' => 'integer', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (AssessmentComponent $c) {
            $c->name = trim((string) $c->name);
            $err = [];
            if ($c->name === '') {
                $err['name'] = 'Nama komponen wajib diisi.';
            }
            // Diperiksa pada nilai MENTAH: cast integer akan diam-diam membuang desimal (10,5 menjadi 10) sebelum sempat diperiksa.
            $bobot = $c->getAttributes()['weight'] ?? null;
            if (! is_numeric($bobot) || floor((float) $bobot) != (float) $bobot || $bobot < 1 || $bobot > 100) {
                $err['weight'] = 'Bobot harus bilangan bulat 1 sampai 100.';
            }
            $urut = $c->getAttributes()['sort_order'] ?? null;
            if (! is_numeric($urut) || floor((float) $urut) != (float) $urut || $urut < 1 || $urut > 250) {
                $err['sort_order'] = 'Urutan harus bilangan bulat 1 sampai 250.';
            }
            if ($err) {
                throw ValidationException::withMessages($err);
            }
        });
    }

    /** Komponen aktif sekolah ini, berurutan; dasar perhitungan nilai akhir. */
    public static function aktifBerurutan(): Collection
    {
        return static::where('active', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'component_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'weight', 'sort_order', 'active'])->logOnlyDirty()->useLogName('komponen_nilai');
    }
}
