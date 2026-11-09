<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Satu analisis foto (telapak tangan atau wajah). Foto adalah data pribadi: disimpan di disk privat, hanya dapat dibaca
 * pihak berwenang, dan bisa dihapus (foto saja, atau foto beserta hasil). Kolom status, hasil, dan jalur foto hanya diubah layanan.
 */
#[Fillable(['type', 'subject_name', 'consent_name', 'consent_relation'])]
class AiAnalysis extends Model
{
    use BelongsToSchool, LogsActivity;

    public const TYPE = ['palmistry' => 'Palmistry', 'physiognomy' => 'Physiognomy'];

    public const STATUS = ['menunggu' => 'Menunggu', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'gagal' => 'Gagal'];

    public const RELATION = ['diri_sendiri' => 'Diri sendiri', 'orang_tua_wali' => 'Orang tua atau wali'];

    public const DISK = 'local'; // storage/app/private: tidak bisa dibuka lewat URL publik

    protected $table = 'ai_analyses';

    protected $attributes = ['status' => 'menunggu'];

    protected function casts(): array
    {
        return ['result' => 'array', 'consented_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(fn (AiAnalysis $a) => $a->validasiDulu());

        // Menghapus analisis ikut menghapus berkas fotonya.
        static::deleting(function (AiAnalysis $a) {
            $a->hapusBerkas();
        });
    }

    /** Aturan data, dipanggil saat menyimpan dan oleh layanan sebelum foto ditulis ke disk. */
    public function validasiDulu(): void
    {
        $this->subject_name = trim((string) $this->subject_name);
        $this->consent_name = trim((string) $this->consent_name);
        $err = [];
        if (! array_key_exists((string) $this->type, self::TYPE)) {
            $err['type'] = 'Jenis analisis tidak dikenal.';
        }
        if (mb_strlen($this->subject_name) < 2 || mb_strlen($this->subject_name) > 150) {
            $err['subject_name'] = 'Nama orang yang difoto wajib diisi (2 sampai 150 karakter).';
        }
        if (mb_strlen($this->consent_name) < 2 || mb_strlen($this->consent_name) > 150) {
            $err['consent_name'] = 'Nama pemberi persetujuan wajib diisi.';
        }
        if (! array_key_exists((string) $this->consent_relation, self::RELATION)) {
            $err['consent_relation'] = 'Hubungan pemberi persetujuan tidak dikenal.';
        }
        if ($this->consented_at === null) {
            $err['consented_at'] = 'Persetujuan wajib ada sebelum foto diproses.';
        }
        if (! array_key_exists((string) $this->status, self::STATUS)) {
            $err['status'] = 'Status tidak dikenal.';
        }
        if ($err) {
            throw ValidationException::withMessages($err);
        }
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function adaFoto(): bool
    {
        return $this->image_path !== null && Storage::disk(self::DISK)->exists($this->image_path);
    }

    private function hapusBerkas(): void
    {
        if ($this->image_path !== null) {
            Storage::disk(self::DISK)->delete($this->image_path);
        }
    }

    /** Menghapus foto saja; hasil analisis tetap ada. */
    public function hapusFoto(): void
    {
        $this->hapusBerkas();
        $this->forceFill(['image_path' => null])->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Tanpa nama, foto, atau hasil: log audit cukup mencatat siapa melakukan apa pada jenis dan status apa.
        return LogOptions::defaults()->logOnly(['type', 'status', 'consent_relation'])->logOnlyDirty()->useLogName('analisis_ai');
    }
}
