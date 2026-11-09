<?php

namespace App\Modules\Kehadiran;

use App\Models\StaffAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Mencatat kehadiran guru dan tenaga kependidikan satu tanggal, semua atau tidak sama sekali. Hanya bila sekolah mengaktifkannya. */
final class CatatKehadiranGuru
{
    public static function aktif(User $user): bool
    {
        return (bool) $user->school?->setting('absensi_guru');
    }

    /**
     * @param  array<int|string, array{status: string, note?: ?string}>  $baris  id pegawai => status dan catatan
     */
    public static function handle(User $oleh, string $tanggal, array $baris): int
    {
        if (! $oleh->can('kelola-kehadiran-guru')) {
            throw new AuthorizationException('Anda tidak berhak mencatat kehadiran guru.');
        }
        if (! self::aktif($oleh)) {
            throw ValidationException::withMessages(['staff_id' => 'Kehadiran guru tidak diaktifkan oleh sekolah ini. Aktifkan di Profil sekolah.']);
        }
        if ($baris === []) {
            throw ValidationException::withMessages(['staff_id' => 'Tidak ada pegawai untuk dicatat.']);
        }
        try {
            $tgl = Carbon::createFromFormat('!Y-m-d', $tanggal);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => 'Tanggal tidak valid.']);
        }
        if ($tgl === false || $tgl->format('Y-m-d') !== $tanggal) {
            throw ValidationException::withMessages(['date' => 'Tanggal tidak valid.']);
        }

        return DB::transaction(function () use ($oleh, $tgl, $baris) {
            $jumlah = 0;
            foreach ($baris as $stafId => $data) {
                $catatan = StaffAttendance::where('staff_id', (int) $stafId)->whereDate('date', $tgl)->first()
                    ?? new StaffAttendance(['staff_id' => (int) $stafId, 'date' => $tgl->toDateString()]);
                $catatan->fill(['status' => $data['status'] ?? null, 'note' => $data['note'] ?? null]);
                $catatan->recorded_by = $oleh->id;
                $catatan->save();
                $jumlah++;
            }

            return $jumlah;
        });
    }
}
