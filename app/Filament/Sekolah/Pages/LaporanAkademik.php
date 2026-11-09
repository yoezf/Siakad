<?php

namespace App\Filament\Sekolah\Pages;

use App\Models\Classroom;
use App\Models\Semester;
use App\Modules\Kehadiran\Rekap as RekapKehadiran;
use App\Modules\Laporan\Ekspor;
use App\Modules\Laporan\Laporan;
use App\Modules\Penilaian\Akses as AksesNilai;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;

/** Laporan akademik per kelas (rekap nilai akhir dan rekap kehadiran) yang bisa dilihat, diunduh sebagai PDF atau Excel. */
class LaporanAkademik extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Rapor';

    protected static ?string $navigationLabel = 'Laporan akademik';

    protected static ?string $title = 'Laporan akademik';

    protected static ?string $slug = 'laporan-akademik';

    protected string $view = 'filament.sekolah.pages.laporan-akademik';

    public string $jenis = 'nilai';

    public ?int $semesterId = null;

    public ?int $kelasId = null;

    public string $periode = 'semester'; // 'semester' | 'bulan' (hanya kehadiran)

    public string $bulan = '';

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u && ($u->can('lihat-nilai-semua') || $u->can('lihat-nilai-kelas-sendiri')
            || $u->can('lihat-kehadiran-siswa-semua') || $u->can('lihat-siswa-kelas-sendiri'));
    }

    public function mount(): void
    {
        $this->bulan = today()->format('Y-m');
        $this->semesterId = Semester::where('aktif', 1)->value('id') ?? Semester::orderByDesc('start_date')->value('id');
        $this->kelasId = array_key_first($this->opsiKelas());
    }

    public function updatedJenis(): void
    {
        $this->jenis = array_key_exists($this->jenis, Laporan::JENIS) ? $this->jenis : 'nilai';
        $this->kelasId = array_key_first($this->opsiKelas());
    }

    public function updatedSemesterId(): void
    {
        $this->kelasId = array_key_first($this->opsiKelas());
    }

    /** @return array<int, string> */
    public function opsiSemester(): array
    {
        return Semester::with('academicYear')->orderByDesc('start_date')->get()
            ->mapWithKeys(fn ($s) => [$s->id => "{$s->academicYear->name} {$s->label}".($s->isActive() ? ' (aktif)' : '')])->all();
    }

    /** @return array<int, string> kelas pada tahun ajaran semester terpilih yang boleh dilihat pengguna untuk jenis laporan ini */
    public function opsiKelas(): array
    {
        $sem = $this->semester();
        if (! $sem) {
            return [];
        }

        return Classroom::where('academic_year_id', $sem->academic_year_id)->orderBy('grade')->orderBy('name')->get()
            ->filter(fn (Classroom $k) => $this->jenis === 'kehadiran' ? RekapKehadiran::bisaMelihat(auth()->user(), $k) : AksesNilai::bisaMelihat(auth()->user(), $k))
            ->mapWithKeys(fn ($k) => [$k->id => $k->name])->all();
    }

    private function semester(): ?Semester
    {
        return $this->semesterId ? Semester::find($this->semesterId) : null;
    }

    /** Pilihan dari browser tidak dipercaya: hasil null bila tidak sah atau tidak berhak. */
    public function tabel(): ?array
    {
        $sem = $this->semester();
        $kelas = $this->kelasId ? Classroom::find($this->kelasId) : null;
        if (! $sem || ! $kelas) {
            return null;
        }
        try {
            if ($this->jenis === 'kehadiran') {
                [$dari, $sampai] = $this->rentang($sem);

                return Laporan::kehadiran(auth()->user(), $sem, $kelas, $dari, $sampai);
            }

            return Laporan::nilai(auth()->user(), $sem, $kelas);
        } catch (AuthorizationException) {
            return null;
        }
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function rentang(Semester $sem): array
    {
        if ($this->periode !== 'bulan') {
            return [null, null];
        }
        try {
            $awal = Carbon::createFromFormat('!Y-m', $this->bulan)->startOfMonth();
        } catch (\Throwable) {
            return [null, null];
        }

        return [$awal, $awal->copy()->endOfMonth()->startOfDay()];
    }

    public function unduh(string $format)
    {
        $t = $this->tabel();
        if (! $t || ! in_array($format, ['pdf', 'xlsx'], true)) {
            Notification::make()->danger()->title('Pilih semester dan kelas yang berhak Anda lihat.')->send();

            return null;
        }
        activity('laporan')->causedBy(auth()->user())->withProperties(['jenis' => $this->jenis, 'kelas_id' => $this->kelasId, 'semester_id' => $this->semesterId, 'format' => $format])
            ->log("Mengunduh {$t['judul']} ({$format})");
        $isi = $format === 'pdf' ? Ekspor::pdf($t) : Ekspor::xlsx($t);
        $tipe = $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(fn () => print ($isi), Ekspor::namaBerkas($t, $format), ['Content-Type' => $tipe]);
    }
}
