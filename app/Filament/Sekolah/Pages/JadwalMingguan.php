<?php

namespace App\Filament\Sekolah\Pages;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ScheduleEntry;
use App\Models\Staff;
use App\Models\TimeSlot;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** Jadwal pelajaran dalam tampilan mingguan: per kelas atau per guru. Hanya membaca. */
class JadwalMingguan extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'Jadwal';

    protected static ?string $navigationLabel = 'Tampilan mingguan';

    protected static ?string $title = 'Jadwal mingguan';

    protected static ?string $slug = 'jadwal-mingguan';

    protected string $view = 'filament.sekolah.pages.jadwal-mingguan';

    public string $mode = 'kelas'; // 'kelas' | 'guru'

    public ?int $pilihan = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', ScheduleEntry::class) ?? false;
    }

    public function mount(): void
    {
        // Guru langsung melihat jadwal mengajarnya sendiri; yang lain mulai dari kelas pertama tahun berjalan.
        $saya = Staff::where('user_id', auth()->id())->where('kind', 'guru')->value('id');
        if ($saya) {
            $this->mode = 'guru';
            $this->pilihan = $saya;

            return;
        }
        $this->pilihan = array_key_first($this->opsi());
    }

    public function updatedMode(): void
    {
        $this->pilihan = array_key_first($this->opsi());
    }

    /** @return array<int, string> */
    public function opsi(): array
    {
        if ($this->mode === 'guru') {
            return Staff::where('kind', 'guru')->where('status', 'aktif')->orderBy('name')->pluck('name', 'id')->all();
        }
        $tahun = AcademicYear::tahunBerjalanId(\App\Support\Tenancy\CurrentSchool::id() ?? 0);

        return Classroom::with('academicYear')->when($tahun, fn ($q) => $q->where('academic_year_id', $tahun))->orderBy('grade')->orderBy('name')->get()
            ->mapWithKeys(fn ($c) => [$c->id => "{$c->name} ({$c->academicYear->name})"])->all();
    }

    /** @return array{jam: Collection, hari: array<int, string>, sel: Collection} */
    public function grid(): array
    {
        $jam = TimeSlot::orderBy('number')->get();
        $entri = $this->pilihan ? ScheduleEntry::with(['subject', 'classroom', 'staff'])
            ->where($this->mode === 'guru' ? 'staff_id' : 'classroom_id', $this->pilihan)->get() : collect();
        $hariTerpakai = $entri->pluck('day')->max() ?? 0;
        $hari = array_slice(ScheduleEntry::HARI, 0, max(5, $hariTerpakai), true); // Senin-Jumat; Sabtu/Minggu muncul bila terpakai

        return ['jam' => $jam, 'hari' => $hari, 'sel' => $entri->groupBy(fn ($e) => $e->time_slot_id.'-'.$e->day)];
    }
}
