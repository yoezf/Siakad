<?php

namespace App\Filament\Sekolah\Pages;

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Modules\Kehadiran\CatatKehadiranGuru;
use App\Modules\Kehadiran\Rekap;
use App\Modules\Kehadiran\Status;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Kehadiran guru dan tenaga kependidikan. Dicatat admin sekolah atau staf TU; kepala sekolah hanya melihat. */
class PresensiGuru extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Kehadiran';

    protected static ?string $navigationLabel = 'Kehadiran guru';

    protected static ?string $title = 'Kehadiran guru dan tenaga kependidikan';

    protected static ?string $slug = 'presensi-guru';

    protected string $view = 'filament.sekolah.pages.presensi-guru';

    public string $tanggal = '';

    public string $bulan = '';

    /** @var array<int, string> */
    public array $status = [];

    /** @var array<int, ?string> */
    public array $catatan = [];

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u && ($u->can('kelola-kehadiran-guru') || $u->can('lihat-kehadiran-guru')) && CatatKehadiranGuru::aktif($u);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->tanggal = today()->toDateString();
        $this->bulan = today()->format('Y-m');
        $this->muat();
    }

    public function updatedTanggal(): void
    {
        $this->muat();
    }

    public function bisaMencatat(): bool
    {
        return auth()->user()->can('kelola-kehadiran-guru');
    }

    public function daftarPegawai()
    {
        return Staff::where('status', 'aktif')->orderBy('name')->get();
    }

    public function muat(): void
    {
        $this->status = [];
        $this->catatan = [];
        try {
            $tgl = Carbon::createFromFormat('!Y-m-d', $this->tanggal);
        } catch (\Throwable) {
            return;
        }
        $ada = StaffAttendance::whereDate('date', $tgl)->get()->keyBy('staff_id');
        foreach ($this->daftarPegawai() as $s) {
            $this->status[$s->id] = $ada->get($s->id)?->status ?? Status::HADIR; // belum tersimpan sampai Simpan ditekan
            $this->catatan[$s->id] = $ada->get($s->id)?->note;
        }
    }

    public function semuaHadir(): void
    {
        foreach (array_keys($this->status) as $id) {
            $this->status[$id] = Status::HADIR;
        }
    }

    public function simpan(): void
    {
        $baris = [];
        foreach ($this->daftarPegawai() as $s) {
            $baris[$s->id] = ['status' => $this->status[$s->id] ?? null, 'note' => $this->catatan[$s->id] ?? null];
        }
        try {
            $n = CatatKehadiranGuru::handle(auth()->user(), $this->tanggal, $baris);
            Notification::make()->success()->title("Kehadiran {$n} pegawai tersimpan.")->send();
        } catch (ValidationException $e) {
            Notification::make()->danger()->persistent()->title('Kehadiran tidak tersimpan')->body(collect($e->errors())->flatten()->implode(' '))->send();
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
        $this->muat();
    }

    public function rekap(): array
    {
        try {
            $awal = Carbon::createFromFormat('!Y-m', $this->bulan)->startOfMonth();
        } catch (\Throwable) {
            return [];
        }

        return Rekap::pegawai($awal, $awal->copy()->endOfMonth()->startOfDay());
    }
}
