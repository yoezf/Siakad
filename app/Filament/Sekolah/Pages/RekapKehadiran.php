<?php

namespace App\Filament\Sekolah\Pages;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Semester;
use App\Modules\Kehadiran\Rekap;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Rekap kehadiran harian siswa satu kelas: per hari, per bulan, atau per semester. */
class RekapKehadiran extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Kehadiran';

    protected static ?string $navigationLabel = 'Rekap kehadiran siswa';

    protected static ?string $title = 'Rekap kehadiran siswa';

    protected static ?string $slug = 'rekap-kehadiran';

    protected string $view = 'filament.sekolah.pages.rekap-kehadiran';

    public ?int $kelasId = null;

    public string $periode = 'bulan'; // 'hari' | 'bulan' | 'semester'

    public string $tanggal = '';

    public string $bulan = '';

    public ?int $semesterId = null;

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u && ($u->can('lihat-kehadiran-siswa-semua') || $u->can('lihat-siswa-kelas-sendiri'));
    }

    public function mount(): void
    {
        $this->tanggal = today()->toDateString();
        $this->bulan = today()->format('Y-m');
        $this->kelasId = array_key_first($this->opsiKelas());
        $this->semesterId = Semester::where('aktif', 1)->value('id') ?? Semester::orderByDesc('start_date')->value('id');
    }

    /** @return array<int, string> */
    public function opsiKelas(): array
    {
        $tahun = AcademicYear::tahunBerjalanId(CurrentSchool::id() ?? 0);

        return Classroom::with('academicYear')->where('academic_year_id', $tahun ?? 0)->orderBy('grade')->orderBy('name')->get()
            ->filter(fn (Classroom $k) => Rekap::bisaMelihat(auth()->user(), $k))->mapWithKeys(fn ($k) => [$k->id => $k->name])->all();
    }

    /** @return array<int, string> */
    public function opsiSemester(): array
    {
        return Semester::with('academicYear')->orderByDesc('start_date')->get()->mapWithKeys(fn ($s) => [$s->id => "{$s->academicYear->name} {$s->label}"])->all();
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    private function rentang(): ?array
    {
        try {
            return match ($this->periode) {
                'hari' => [Carbon::createFromFormat('!Y-m-d', $this->tanggal), Carbon::createFromFormat('!Y-m-d', $this->tanggal)],
                'semester' => ($s = Semester::find($this->semesterId)) ? [$s->start_date->copy(), $s->end_date->copy()] : null,
                default => [Carbon::createFromFormat('!Y-m', $this->bulan)->startOfMonth(), Carbon::createFromFormat('!Y-m', $this->bulan)->endOfMonth()->startOfDay()],
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /** Kelas dari browser tidak dipercaya: hanya kelas yang berhak dilihat pengguna ini yang direkap. */
    public function baris(): array
    {
        $kelas = $this->kelasId ? Classroom::find($this->kelasId) : null;
        $rentang = $this->rentang();
        if (! $kelas || ! $rentang || ! Rekap::bisaMelihat(auth()->user(), $kelas)) {
            return [];
        }

        return Rekap::kelas($kelas, $rentang[0], $rentang[1]);
    }
}
