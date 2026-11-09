<?php

namespace App\Modules\Rapor;

use App\Models\ReportCard;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Membuka kunci rapor yang sudah disahkan agar nilai dan kehadirannya bisa direvisi. Wajib disertai alasan tertulis.
 * Versi yang sudah disahkan tidak berubah; hasil revisi menjadi versi baru saat disahkan ulang.
 */
final class BukaKunciRapor
{
    public const MIN_ALASAN = 10;

    public static function handle(User $oleh, ReportCard $rapor, string $alasan): ReportCard
    {
        $kelas = $rapor->classroom;
        if (! $kelas || ! Akses::bisaBukaKunci($oleh, $kelas)) {
            throw new AuthorizationException('Anda tidak berhak membuka kunci rapor ini.');
        }
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < self::MIN_ALASAN || mb_strlen($alasan) > 255) {
            throw ValidationException::withMessages(['alasan' => 'Alasan revisi wajib diisi, '.self::MIN_ALASAN.' sampai 255 karakter, agar perubahan bisa dipertanggungjawabkan.']);
        }

        return DB::transaction(function () use ($oleh, $rapor, $alasan) {
            $rapor = ReportCard::whereKey($rapor->id)->lockForUpdate()->firstOrFail();
            if ($rapor->status !== 'disahkan') {
                throw ValidationException::withMessages(['rapor' => 'Rapor ini tidak sedang disahkan, jadi tidak ada kunci yang perlu dibuka.']);
            }
            $rapor->forceFill(['status' => 'revisi', 'unlock_reason' => $alasan, 'unlocked_by' => $oleh->id, 'unlocked_at' => now()])->save();

            return $rapor;
        });
    }
}
