<?php

namespace App\Filament\Sekolah\Widgets;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentAttendance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ringkasan sekolah di beranda untuk pengelola (admin, kepala sekolah, TU). Semua angka dibatasi pada sekolah yang sedang dibuka. */
class RingkasanSekolah extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected static bool $isLazy = false; // angka tampil bersama halaman, tanpa kotak kosong beberapa detik

    protected ?string $heading = 'Ringkasan sekolah';

    public static function canView(): bool
    {
        return auth()->user()?->can('lihat-data-pribadi') ?? false;
    }

    protected function getStats(): array
    {
        $sem = Semester::where('aktif', 1)->first();
        $siswa = Student::where('status', 'aktif')->count();
        $disahkan = $sem ? ReportCard::where('semester_id', $sem->id)->where('status', 'disahkan')->count() : 0;

        // Kehadiran pada hari terakhir yang tercatat (hari ini bila sudah dicatat).
        $tanggal = StudentAttendance::whereNull('schedule_entry_id')->max('date');
        $hadir = null;
        if ($tanggal) {
            $baris = StudentAttendance::whereNull('schedule_entry_id')->whereDate('date', $tanggal)->get(['status']);
            $hadir = $baris->count() > 0 ? (int) round($baris->whereIn('status', ['H', 'T'])->count() / $baris->count() * 100) : null;
        }

        return [
            Stat::make('Siswa aktif', $siswa)->description(Classroom::count().' kelas')->icon('heroicon-o-academic-cap'),
            Stat::make('Guru aktif', Staff::where('status', 'aktif')->where('kind', 'guru')->count())->description('Guru dan wali kelas')->icon('heroicon-o-user-group'),
            Stat::make('Kehadiran siswa', $hadir === null ? '-' : $hadir.'%')->description($tanggal ? 'Tanggal '.\Illuminate\Support\Carbon::parse($tanggal)->format('d-m-Y') : 'Belum ada data')->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Rapor disahkan', $disahkan.' dari '.$siswa)->description($sem ? 'Semester '.$sem->label : 'Belum ada semester aktif')->icon('heroicon-o-document-check'),
            Stat::make('Pengumuman terbit', Announcement::where('status', 'terbit')->count())->description('Dibaca orang tua dan siswa')->icon('heroicon-o-megaphone'),
        ];
    }
}
