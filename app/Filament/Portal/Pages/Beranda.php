<?php

namespace App\Filament\Portal\Pages;

use App\Models\AssessmentComponent;
use App\Models\ReportCard;
use App\Models\ReportCardVersion;
use App\Models\ScheduleEntry;
use App\Models\School;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TimeSlot;
use App\Modules\Kehadiran\Rekap as RekapKehadiran;
use App\Modules\Penilaian\Rekap as RekapNilai;
use App\Modules\Pengumuman\Daftar;
use App\Modules\Portal\Anak;
use App\Modules\Rapor\PdfRapor;
use App\Modules\Rapor\Susun;
use App\Support\Peran;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Illuminate\Support\Collection;

/**
 * Halaman tunggal portal orang tua dan siswa: rapor, nilai, kehadiran, dan jadwal anak. Hanya membaca.
 * Pilihan anak, semester, dan versi rapor dari browser tidak dipercaya: setiap bacaan dicocokkan ulang dengan Anak::untuk().
 */
class Beranda extends Dashboard
{
    protected static ?string $title = 'Anak saya';

    protected static ?string $navigationLabel = 'Anak saya';

    protected string $view = 'filament.portal.beranda';

    public ?int $anakId = null;

    public ?int $semesterId = null;

    public string $tab = 'rapor';

    public const TAB = ['rapor' => 'Rapor', 'nilai' => 'Nilai', 'kehadiran' => 'Kehadiran', 'jadwal' => 'Jadwal', 'pengumuman' => 'Pengumuman'];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(Peran::PORTAL) ?? false;
    }

    public function getWidgets(): array
    {
        return [];
    }

    public function mount(): void
    {
        $this->anakId = $this->daftarAnak()->first()?->id;
        $this->semesterId = Semester::where('aktif', 1)->value('id') ?? Semester::orderByDesc('start_date')->value('id');
    }

    /** @return Collection<int, Student> */
    public function daftarAnak(): Collection
    {
        return Anak::untuk(auth()->user());
    }

    /** Anak terpilih, atau anak pertama bila pilihan dari browser bukan anak pengguna ini. */
    public function anak(): ?Student
    {
        $daftar = $this->daftarAnak();

        return $daftar->firstWhere('id', $this->anakId) ?? $daftar->first();
    }

    /** @return Collection<int, \App\Models\Announcement> */
    public function pengumuman(): Collection
    {
        return Daftar::untuk(auth()->user());
    }

    /** @return array<int, string> */
    public function opsiSemester(): array
    {
        return Semester::with('academicYear')->orderByDesc('start_date')->get()
            ->mapWithKeys(fn ($s) => [$s->id => "{$s->academicYear->name} {$s->label}".($s->isActive() ? ' (aktif)' : '')])->all();
    }

    public function semester(): ?Semester
    {
        return $this->semesterId ? Semester::find($this->semesterId) : null; // dibatasi sekolah oleh penyaring tenant
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TAB) ? $tab : 'rapor';
    }

    /**
     * Hanya rapor yang sudah disahkan dan versi yang berlaku. Rapor yang sedang direvisi tidak ditampilkan
     * sampai disahkan ulang, agar orang tua tidak memegang isi yang sedang diperbaiki.
     *
     * @return array{versi: ReportCardVersion, snapshot: array, utuh: bool}|null
     */
    public function rapor(): ?array
    {
        $anak = $this->anak();
        $sem = $this->semester();
        if (! $anak || ! $sem) {
            return null;
        }
        $rapor = ReportCard::where('semester_id', $sem->id)->where('student_id', $anak->id)->where('status', 'disahkan')->first();
        $versi = $rapor ? ReportCardVersion::where('report_card_id', $rapor->id)->where('version', $rapor->current_version)->first() : null;

        return $versi ? ['versi' => $versi, 'snapshot' => $versi->snapshot, 'utuh' => $rapor->utuh()] : null;
    }

    public function unduh()
    {
        $r = $this->rapor();
        if (! $r || ! $r['utuh']) {
            Notification::make()->danger()->title('Rapor belum tersedia untuk diunduh.')->send();

            return null;
        }
        $versi = $r['versi'];

        return response()->streamDownload(fn () => print (PdfRapor::render($versi)), PdfRapor::namaBerkas($versi), ['Content-Type' => 'application/pdf']);
    }

    /** @return array{komponen: Collection, hasil: array}|null */
    public function nilai(): ?array
    {
        $anak = $this->anak();
        $sem = $this->semester();
        if (! $anak || ! $sem) {
            return null;
        }
        $kkm = (int) School::findOrFail($anak->school_id)->setting('kkm');

        return ['komponen' => AssessmentComponent::aktifBerurutan(), 'hasil' => RekapNilai::siswa($sem, $anak, $kkm)];
    }

    /** @return array{rekap: array, harian: Collection}|null */
    public function kehadiran(): ?array
    {
        $anak = $this->anak();
        $sem = $this->semester();
        if (! $anak || ! $sem) {
            return null;
        }
        $harian = StudentAttendance::where('student_id', $anak->id)->whereNull('schedule_entry_id')
            ->whereBetween('date', [$sem->start_date->toDateString(), $sem->end_date->toDateString()])->orderByDesc('date')->limit(30)->get();

        return ['rekap' => RekapKehadiran::siswa($anak, $sem->start_date, $sem->end_date), 'harian' => $harian];
    }

    /** @return array{kelas: ?\App\Models\Classroom, jam: Collection, hari: array<int, string>, sel: Collection}|null */
    public function jadwal(): ?array
    {
        $anak = $this->anak();
        $sem = $this->semester();
        if (! $anak || ! $sem) {
            return null;
        }
        $kelas = Susun::kelasSiswa($sem, $anak);
        $entri = $kelas ? ScheduleEntry::with(['subject', 'staff'])->where('classroom_id', $kelas->id)->get() : collect();

        return ['kelas' => $kelas, 'jam' => TimeSlot::orderBy('number')->get(),
            'hari' => array_slice(ScheduleEntry::HARI, 0, max(5, $entri->pluck('day')->max() ?? 0), true),
            'sel' => $entri->groupBy(fn ($e) => $e->time_slot_id.'-'.$e->day)];
    }
}
