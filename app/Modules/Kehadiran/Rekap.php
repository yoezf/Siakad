<?php

namespace App\Modules\Kehadiran;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rekap kehadiran. Persentase kehadiran = (hadir + terlambat) / jumlah hari yang tercatat; terlambat tetap dihitung hadir.
 * Rekap siswa hanya memakai kehadiran HARIAN; kehadiran per pelajaran tidak dicampur.
 */
final class Rekap
{
    /** Kehadiran siswa boleh dilihat: admin, kepala, TU untuk semua kelas; wali kelas dan guru hanya untuk kelas mereka. */
    public static function bisaMelihat(User $user, Classroom $kelas): bool
    {
        if ($user->school_id === null || $user->school_id !== $kelas->school_id) {
            return false;
        }
        if ($user->can('lihat-kehadiran-siswa-semua')) {
            return true;
        }

        return $user->can('lihat-siswa-kelas-sendiri') && in_array($kelas->id, $user->idKelasSaya(), true);
    }

    /**
     * @return list<array{student: Student, H: int, S: int, I: int, A: int, T: int, total: int, persen: ?int}>
     */
    public static function kelas(Classroom $kelas, Carbon $dari, Carbon $sampai): array
    {
        $siswa = Enrollment::where('classroom_id', $kelas->id)->whereDate('joined_at', '<=', $sampai)
            ->where(fn ($q) => $q->whereNull('left_at')->orWhereDate('left_at', '>=', $dari))
            ->with('student')->get()->pluck('student')->filter()->unique('id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);

        $hitung = StudentAttendance::where('classroom_id', $kelas->id)->whereNull('schedule_entry_id')
            ->whereBetween('date', [$dari->toDateString(), $sampai->toDateString()])
            ->select('student_id', 'status', DB::raw('count(*) as n'))->groupBy('student_id', 'status')->get()
            ->groupBy('student_id');

        return $siswa->map(fn (Student $s) => self::baris($s, $hitung->get($s->id, collect())))->values()->all();
    }

    /** Rekap satu siswa di semua kelas pada rentang itu (dipakai rapor). */
    public static function siswa(Student $siswa, Carbon $dari, Carbon $sampai): array
    {
        $hitung = StudentAttendance::where('student_id', $siswa->id)->whereNull('schedule_entry_id')
            ->whereBetween('date', [$dari->toDateString(), $sampai->toDateString()])
            ->select('student_id', 'status', DB::raw('count(*) as n'))->groupBy('student_id', 'status')->get();

        return self::baris($siswa, $hitung);
    }

    /** @return list<array{staff: Staff, H: int, S: int, I: int, A: int, T: int, total: int, persen: ?int}> */
    public static function pegawai(Carbon $dari, Carbon $sampai): array
    {
        $hitung = StaffAttendance::whereBetween('date', [$dari->toDateString(), $sampai->toDateString()])
            ->select('staff_id', 'status', DB::raw('count(*) as n'))->groupBy('staff_id', 'status')->get()->groupBy('staff_id');

        return Staff::where('status', 'aktif')->orderBy('name')->get()->map(function (Staff $s) use ($hitung) {
            $r = self::baris($s, $hitung->get($s->id, collect()));
            $r['staff'] = $r['student'];
            unset($r['student']);

            return $r;
        })->all();
    }

    private static function baris(object $orang, $hitung): array
    {
        $n = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0, 'T' => 0];
        foreach ($hitung as $h) {
            $n[$h->status] = (int) $h->n;
        }
        $total = array_sum($n);

        return ['student' => $orang] + $n + ['total' => $total, 'persen' => $total > 0 ? (int) round(($n['H'] + $n['T']) / $total * 100) : null];
    }
}
