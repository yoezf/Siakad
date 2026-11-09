<?php

namespace App\Modules\Kehadiran;

use App\Models\Classroom;
use App\Models\ScheduleEntry;
use App\Models\Staff;
use App\Models\StudentAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mencatat kehadiran siswa satu kelas pada satu tanggal, harian atau pada satu pelajaran, semua atau tidak sama sekali.
 * Otorisasi dijaga di sini (bukan hanya di halaman) agar tidak bisa dilewati.
 */
final class CatatKehadiranSiswa
{
    /**
     * Admin sekolah dan staf TU boleh mencatat untuk kelas mana pun di sekolahnya. Wali kelas hanya kehadiran harian kelas
     * yang diwalikannya. Guru hanya pelajaran yang jadwalnya miliknya (guru pengampu pada jadwal).
     */
    public static function bisa(User $user, Classroom $kelas, ?ScheduleEntry $entri = null): bool
    {
        if ($user->school_id === null || $user->school_id !== $kelas->school_id || ! $user->can('catat-kehadiran-siswa')) {
            return false;
        }
        if ($user->can('lihat-kehadiran-siswa-semua')) {
            return true;
        }
        $staf = Staff::withoutGlobalScopes()->where('school_id', $user->school_id)->where('user_id', $user->id)->where('status', 'aktif')->value('id');
        if (! $staf) {
            return false;
        }

        return $entri
            ? $entri->classroom_id === $kelas->id && $entri->staff_id === $staf
            : $kelas->homeroom_staff_id === $staf;
    }

    /**
     * @param  array<int|string, array{status: string, note?: ?string}>  $baris  id siswa => status dan catatan
     * @return int jumlah catatan yang disimpan atau diperbarui
     */
    public static function handle(User $oleh, Classroom $kelas, string $tanggal, ?ScheduleEntry $entri, array $baris): int
    {
        if (! self::bisa($oleh, $kelas, $entri)) {
            throw new AuthorizationException('Anda tidak berhak mencatat kehadiran untuk kelas atau pelajaran ini.');
        }
        if ($entri && $entri->classroom_id !== $kelas->id) {
            throw ValidationException::withMessages(['schedule_entry_id' => 'Pelajaran tidak ditemukan pada kelas ini.']);
        }
        if ($baris === []) {
            throw ValidationException::withMessages(['student_id' => 'Tidak ada siswa untuk dicatat.']);
        }
        try {
            $tgl = Carbon::createFromFormat('!Y-m-d', $tanggal);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => 'Tanggal tidak valid.']);
        }
        if ($tgl === false || $tgl->format('Y-m-d') !== $tanggal) {
            throw ValidationException::withMessages(['date' => 'Tanggal tidak valid.']);
        }

        return DB::transaction(function () use ($oleh, $kelas, $tgl, $entri, $baris) {
            $jumlah = 0;
            foreach ($baris as $siswaId => $data) {
                $siswaId = (int) $siswaId;
                $ada = StudentAttendance::where('student_id', $siswaId)->whereDate('date', $tgl)->where('schedule_entry_id', $entri?->id)->first();
                if ($ada && $ada->classroom_id !== $kelas->id) {
                    throw ValidationException::withMessages(['student_id' => 'Kehadiran siswa ini pada tanggal tersebut sudah dicatat oleh kelas lain.']);
                }
                $isi = ['status' => $data['status'] ?? null, 'note' => $data['note'] ?? null];
                $catatan = $ada ?? new StudentAttendance(['student_id' => $siswaId, 'classroom_id' => $kelas->id, 'schedule_entry_id' => $entri?->id, 'date' => $tgl->toDateString()]);
                $catatan->fill($isi);
                $catatan->recorded_by = $oleh->id;
                $catatan->save();
                $jumlah++;
            }

            return $jumlah;
        });
    }
}
