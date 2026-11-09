<?php

namespace App\Filament\Sekolah\Pages;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\ReportCardVersion;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Rapor\Akses;
use App\Modules\Rapor\BukaKunciRapor;
use App\Modules\Rapor\CatatanWali;
use App\Modules\Rapor\PdfRapor;
use App\Modules\Rapor\SahkanRapor;
use App\Modules\Rapor\Susun;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Menyusun, memeriksa, mengesahkan, merevisi, dan mengunduh rapor siswa. */
class Rapor extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Rapor';

    protected static ?string $navigationLabel = 'Rapor siswa';

    protected static ?string $title = 'Rapor siswa';

    protected static ?string $slug = 'rapor';

    protected string $view = 'filament.sekolah.pages.rapor';

    public ?int $semesterId = null;

    public ?int $kelasId = null;

    public ?int $siswaId = null;

    public string $catatan = '';

    public string $alasan = '';

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u && ($u->can('lihat-rapor-semua') || $u->can('lihat-rapor-kelas-sendiri'));
    }

    public function mount(): void
    {
        $this->semesterId = Semester::where('aktif', 1)->value('id') ?? Semester::orderByDesc('start_date')->value('id');
        $this->kelasId = array_key_first($this->opsiKelas());
    }

    public function updatedSemesterId(): void
    {
        $this->kelasId = array_key_first($this->opsiKelas());
        $this->siswaId = null;
    }

    public function updatedKelasId(): void
    {
        $this->siswaId = null;
    }

    /** @return array<int, string> */
    public function opsiSemester(): array
    {
        return Semester::with('academicYear')->orderByDesc('start_date')->get()
            ->mapWithKeys(fn ($s) => [$s->id => "{$s->academicYear->name} {$s->label}".($s->isActive() ? ' (aktif)' : '')])->all();
    }

    private function semester(): ?Semester
    {
        return $this->semesterId ? Semester::with('academicYear')->find($this->semesterId) : null;
    }

    /** @return array<int, string> kelas pada tahun ajaran semester terpilih yang boleh dilihat pengguna ini */
    public function opsiKelas(): array
    {
        $sem = $this->semester();
        if (! $sem) {
            return [];
        }

        return Classroom::where('academic_year_id', $sem->academic_year_id)->orderBy('grade')->orderBy('name')->get()
            ->filter(fn (Classroom $k) => Akses::bisaMelihat(auth()->user(), $k))->mapWithKeys(fn ($k) => [$k->id => $k->name])->all();
    }

    /** Kelas dari browser tidak dipercaya: hanya kelas yang berhak dilihat pengguna ini. */
    private function kelasSah(): ?Classroom
    {
        $sem = $this->semester();
        $kelas = $this->kelasId ? Classroom::find($this->kelasId) : null;
        if (! $sem || ! $kelas || $kelas->academic_year_id !== $sem->academic_year_id || ! Akses::bisaMelihat(auth()->user(), $kelas)) {
            return null;
        }

        return $kelas;
    }

    /** @return Collection<int, array{student: Student, rapor: ?ReportCard}> */
    public function daftar(): Collection
    {
        $sem = $this->semester();
        $kelas = $this->kelasSah();
        if (! $sem || ! $kelas) {
            return collect();
        }
        $siswa = Enrollment::where('classroom_id', $kelas->id)->where('academic_year_id', $sem->academic_year_id)->with('student')->get()
            ->pluck('student')->filter()->unique('id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $rapor = ReportCard::where('semester_id', $sem->id)->whereIn('student_id', $siswa->pluck('id'))->get()->keyBy('student_id');

        return $siswa->map(fn (Student $s) => ['student' => $s, 'rapor' => $rapor->get($s->id)]);
    }

    public function pilih(int $siswaId): void
    {
        $this->siswaId = $siswaId;
        $this->alasan = '';
        $this->catatan = (string) ($this->siswaTerpilih() ? ReportCard::where('semester_id', $this->semesterId)->where('student_id', $siswaId)->value('homeroom_note') : '');
    }

    /** Siswa terpilih hanya dipakai bila terdaftar di kelas yang sah pada tahun ajaran semester itu. */
    private function siswaTerpilih(): ?Student
    {
        $sem = $this->semester();
        $kelas = $this->kelasSah();
        $siswa = $this->siswaId ? Student::find($this->siswaId) : null;
        if (! $sem || ! $kelas || ! $siswa
            || ! Enrollment::where('student_id', $siswa->id)->where('classroom_id', $kelas->id)->where('academic_year_id', $sem->academic_year_id)->exists()) {
            return null;
        }

        return $siswa;
    }

    /** @return array{siswa: Student, kelas: Classroom, hasil: array, rapor: ?ReportCard, versi: Collection, bisaMenyusun: bool, bisaMengesahkan: bool, bisaBukaKunci: bool}|null */
    public function detail(): ?array
    {
        $sem = $this->semester();
        $siswa = $this->siswaTerpilih();
        if (! $sem || ! $siswa) {
            return null;
        }
        $hasil = Susun::untuk($sem, $siswa);
        $kelas = $hasil['kelas'] ?? $this->kelasSah();
        $rapor = ReportCard::with('versions')->where('semester_id', $sem->id)->where('student_id', $siswa->id)->first();
        $user = auth()->user();

        return [
            'siswa' => $siswa, 'kelas' => $kelas, 'hasil' => $hasil, 'rapor' => $rapor, 'versi' => $rapor?->versions ?? collect(),
            'bisaMenyusun' => $kelas && Akses::bisaMenyusun($user, $kelas),
            'bisaMengesahkan' => $kelas && Akses::bisaMengesahkan($user, $kelas),
            'bisaBukaKunci' => $kelas && Akses::bisaBukaKunci($user, $kelas),
        ];
    }

    private function jalankan(callable $f, string $sukses): void
    {
        try {
            $f();
            Notification::make()->success()->title($sukses)->send();
        } catch (ValidationException $e) {
            Notification::make()->danger()->persistent()->title('Tidak bisa diproses')->body(collect($e->errors())->flatten()->implode(' '))->send();
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    public function simpanCatatan(): void
    {
        $sem = $this->semester();
        $siswa = $this->siswaTerpilih();
        if (! $sem || ! $siswa) {
            Notification::make()->danger()->title('Pilih kelas dan siswa yang berhak Anda lihat.')->send();

            return;
        }
        $this->jalankan(fn () => CatatanWali::simpan(auth()->user(), $sem, $siswa, $this->catatan), 'Catatan wali kelas tersimpan.');
    }

    public function sahkan(): void
    {
        $sem = $this->semester();
        $siswa = $this->siswaTerpilih();
        if (! $sem || ! $siswa) {
            Notification::make()->danger()->title('Pilih kelas dan siswa yang berhak Anda lihat.')->send();

            return;
        }
        $this->jalankan(function () use ($sem, $siswa) {
            SahkanRapor::handle(auth()->user(), $sem, $siswa);
            $this->alasan = '';
        }, 'Rapor disahkan dan dikunci.');
    }

    public function bukaKunci(): void
    {
        $sem = $this->semester();
        $siswa = $this->siswaTerpilih();
        $rapor = $sem && $siswa ? ReportCard::where('semester_id', $sem->id)->where('student_id', $siswa->id)->first() : null;
        if (! $rapor) {
            Notification::make()->danger()->title('Rapor tidak ditemukan.')->send();

            return;
        }
        $this->jalankan(function () use ($rapor) {
            BukaKunciRapor::handle(auth()->user(), $rapor, $this->alasan);
            $this->alasan = '';
        }, 'Kunci dibuka. Nilai dan kehadiran siswa ini bisa direvisi, lalu sahkan ulang sebagai versi baru.');
    }

    public function unduh(int $versiId)
    {
        $sem = $this->semester();
        $siswa = $this->siswaTerpilih();
        $versi = $sem && $siswa ? ReportCardVersion::with('reportCard')->whereKey($versiId)
            ->whereHas('reportCard', fn ($q) => $q->where('semester_id', $sem->id)->where('student_id', $siswa->id))->first() : null;
        if (! $versi) {
            Notification::make()->danger()->title('Versi rapor tidak ditemukan.')->send();

            return null;
        }

        return response()->streamDownload(fn () => print (PdfRapor::render($versi)), PdfRapor::namaBerkas($versi), ['Content-Type' => 'application/pdf']);
    }

    public function sekolahAktif(): ?int
    {
        return CurrentSchool::id();
    }

    public function tahunBerjalan(): ?int
    {
        return AcademicYear::tahunBerjalanId(CurrentSchool::id() ?? 0);
    }
}
