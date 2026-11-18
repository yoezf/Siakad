<?php

namespace App\Modules\Laporan;

use App\Models\Classroom;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use Carbon\Carbon;

/** Angka ringkas untuk dasbor sekolah. Semua query memakai penyaring sekolah yang sedang dibuka. "Hadir" berarti status H atau T. */
final class DasborSekolah
{
    /** @return array{persen: ?int, tanggal: ?Carbon} kehadiran harian siswa pada hari terakhir yang tercatat */
    public static function kehadiranSiswa(): array
    {
        $tgl = StudentAttendance::whereNull('schedule_entry_id')->max('date');
        if (! $tgl) {
            return ['persen' => null, 'tanggal' => null];
        }
        $baris = StudentAttendance::whereNull('schedule_entry_id')->whereDate('date', $tgl)->pluck('status');

        return ['persen' => $baris->isEmpty() ? null : self::persen($baris->filter(fn ($s) => in_array($s, ['H', 'T'], true))->count(), $baris->count()), 'tanggal' => Carbon::parse($tgl)];
    }

    /** @return array{persen: ?int, tanggal: ?Carbon} */
    public static function kehadiranGuru(): array
    {
        $tgl = StaffAttendance::max('date');
        if (! $tgl) {
            return ['persen' => null, 'tanggal' => null];
        }
        $baris = StaffAttendance::whereDate('date', $tgl)->pluck('status');

        return ['persen' => $baris->isEmpty() ? null : self::persen($baris->filter(fn ($s) => in_array($s, ['H', 'T'], true))->count(), $baris->count()), 'tanggal' => Carbon::parse($tgl)];
    }

    /** @return array{jumlah: int, total: int, persen: ?int, semester: ?string} */
    public static function raporDisahkan(): array
    {
        $sem = Semester::where('aktif', 1)->first();
        $total = Student::where('status', 'aktif')->count();
        $n = $sem ? ReportCard::where('semester_id', $sem->id)->where('status', 'disahkan')->count() : 0;

        return ['jumlah' => $n, 'total' => $total, 'persen' => self::persen($n, $total), 'semester' => $sem?->label];
    }

    /** @return list<array{kelas: string, persen: int, hadir: int, total: int}> kehadiran harian per kelas pada hari terakhir yang tercatat */
    public static function perKelas(): array
    {
        $tgl = StudentAttendance::whereNull('schedule_entry_id')->max('date');
        if (! $tgl) {
            return [];
        }
        $baris = StudentAttendance::whereNull('schedule_entry_id')->whereDate('date', $tgl)->get(['classroom_id', 'status'])->groupBy('classroom_id');
        $hasil = [];
        foreach (Classroom::orderBy('grade')->orderBy('name')->get() as $k) {
            $b = $baris->get($k->id);
            if (! $b) {
                continue;
            }
            $hadir = $b->filter(fn ($x) => in_array($x->status, ['H', 'T'], true))->count();
            $hasil[] = ['kelas' => $k->name, 'persen' => (int) self::persen($hadir, $b->count()), 'hadir' => $hadir, 'total' => $b->count()];
        }

        return $hasil;
    }

    /** @return list<string> tanggal (Y-m-d) pada bulan itu yang sudah ada kehadiran hariannya */
    public static function tanggalTercatat(Carbon $bulan): array
    {
        return StudentAttendance::whereNull('schedule_entry_id')->whereBetween('date', [$bulan->copy()->startOfMonth()->toDateString(), $bulan->copy()->endOfMonth()->toDateString()])
            ->select('date')->distinct()->pluck('date')->map->toDateString()->all();
    }

    public static function siswaAktif(): int
    {
        return Student::where('status', 'aktif')->count();
    }

    public static function jumlahKelas(): int
    {
        return Classroom::count();
    }

    private static function persen(int $bagian, int $total): ?int
    {
        return $total > 0 ? (int) round($bagian / $total * 100) : null;
    }
}
