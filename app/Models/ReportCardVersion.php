<?php

namespace App\Models;

use App\Modules\Rapor\Sidik;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Salinan tetap sebuah rapor pada saat disahkan. TIDAK PERNAH diubah atau dihapus: revisi membuat versi baru.
 * Penjagaan di model, ditambah sidik (checksum) yang memperlihatkan bila isinya diubah lewat SQL langsung.
 */
#[Fillable(['report_card_id', 'version', 'snapshot', 'revision_reason', 'approved_by', 'approved_at'])]
class ReportCardVersion extends Model
{
    use BelongsToSchool;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (ReportCardVersion $v) {
            $v->checksum = Sidik::hitung((array) $v->snapshot);
        });
        static::updating(fn () => throw new LogicException('Versi rapor yang sudah disahkan tidak boleh diubah; buat revisi sebagai versi baru.'));
        static::deleting(fn () => throw new LogicException('Versi rapor yang sudah disahkan tidak boleh dihapus.'));
    }

    /** Apakah isi salinan masih sama dengan saat disahkan? */
    public function utuh(): bool
    {
        return hash_equals((string) $this->checksum, Sidik::hitung((array) $this->snapshot));
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
