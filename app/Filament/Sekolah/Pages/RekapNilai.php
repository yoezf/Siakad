<?php

namespace App\Filament\Sekolah\Pages;

use App\Models\AssessmentComponent;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Modules\Penilaian\Akses;
use App\Modules\Penilaian\Rekap;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** Rekap nilai: per mata pelajaran di satu kelas, atau semua mata pelajaran satu siswa. */
class RekapNilai extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?string $navigationLabel = 'Rekap nilai';

    protected static ?string $title = 'Rekap nilai';

    protected static ?string $slug = 'rekap-nilai';

    protected string $view = 'filament.sekolah.pages.rekap-nilai';

    public string $mode = 'kelas'; // 'kelas' | 'siswa'

    public ?int $semesterId = null;

    public ?int $kelasId = null;

    public ?int $mapelId = null;

    public ?int $siswaId = null;

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u && ($u->can('lihat-nilai-semua') || $u->can('lihat-nilai-kelas-sendiri'));
    }

    public function mount(): void
    {
        $this->semesterId = Semester::where('aktif', 1)->value('id') ?? Semester::orderByDesc('start_date')->value('id');
        $this->pilihAwal();
    }

    public function updatedMode(): void
    {
        $this->pilihAwal();
    }

    public function updatedSemesterId(): void
    {
        $this->pilihAwal();
    }

    public function updatedKelasId(): void
    {
        $this->mapelId = array_key_first($this->opsiMapel());
        $this->siswaId = array_key_first($this->opsiSiswa());
    }

    private function pilihAwal(): void
    {
        $this->kelasId = array_key_first($this->opsiKelas());
        $this->mapelId = array_key_first($this->opsiMapel());
        $this->siswaId = array_key_first($this->opsiSiswa());
    }

    private function semester(): ?Semester
    {
        return $this->semesterId ? Semester::find($this->semesterId) : null;
    }

    /** @return array<int, string> */
    public function opsiSemester(): array
    {
        return Semester::with('academicYear')->orderByDesc('start_date')->get()->mapWithKeys(fn ($s) => [$s->id => "{$s->academicYear->name} {$s->label}".($s->isActive() ? ' (aktif)' : '')])->all();
    }

    /** @return array<int, string> kelas yang boleh dilihat pengguna ini pada tahun ajaran semester terpilih */
    public function opsiKelas(): array
    {
        $sem = $this->semester();
        if (! $sem) {
            return [];
        }

        return Classroom::where('academic_year_id', $sem->academic_year_id)->orderBy('grade')->orderBy('name')->get()
            ->filter(fn (Classroom $k) => $this->mapelUntuk($k)->isNotEmpty())->mapWithKeys(fn ($k) => [$k->id => $k->name])->all();
    }

    /** @return Collection<int, Subject> mata pelajaran kelas ini yang boleh dilihat pengguna ini */
    private function mapelUntuk(Classroom $kelas): Collection
    {
        $user = auth()->user();

        return Subject::whereIn('id', TeachingAssignment::where('classroom_id', $kelas->id)->select('subject_id'))->orderBy('name')->get()
            ->filter(fn (Subject $m) => Akses::bisaMelihat($user, $kelas, $m));
    }

    /** @return array<int, string> */
    public function opsiMapel(): array
    {
        $kelas = $this->kelasId ? Classroom::find($this->kelasId) : null;

        return $kelas ? $this->mapelUntuk($kelas)->mapWithKeys(fn ($m) => [$m->id => $m->name])->all() : [];
    }

    /** @return array<int, string> */
    public function opsiSiswa(): array
    {
        $sem = $this->semester();
        $kelas = $this->kelasId ? Classroom::find($this->kelasId) : null;
        if (! $sem || ! $kelas || $this->mapelUntuk($kelas)->isEmpty()) {
            return [];
        }

        return Enrollment::where('classroom_id', $kelas->id)->where('academic_year_id', $sem->academic_year_id)->with('student')->get()
            ->pluck('student')->filter()->unique('id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->mapWithKeys(fn ($s) => [$s->id => $s->name])->all();
    }

    public function komponen(): Collection
    {
        return AssessmentComponent::aktifBerurutan();
    }

    /** Pilihan dari browser tidak dipercaya: hanya yang berhak dilihat pengguna ini yang dihitung. */
    public function barisKelas(): array
    {
        $sem = $this->semester();
        $kelas = $this->kelasId ? Classroom::find($this->kelasId) : null;
        $mapel = $this->mapelId ? Subject::find($this->mapelId) : null;
        if (! $sem || ! $kelas || ! $mapel || $kelas->academic_year_id !== $sem->academic_year_id || ! Akses::bisaMelihat(auth()->user(), $kelas, $mapel)
            || ! TeachingAssignment::where('classroom_id', $kelas->id)->where('subject_id', $mapel->id)->exists()) {
            return [];
        }

        return Rekap::kelas($sem, $kelas, $mapel, Akses::kkmSekolah(auth()->user()));
    }

    /** @return array{mapel: list<array>, rata: ?float, lengkap: bool, penuh: bool} */
    public function ringkasanSiswa(): array
    {
        $kosong = ['mapel' => [], 'rata' => null, 'lengkap' => false, 'penuh' => false];
        $sem = $this->semester();
        $kelas = $this->kelasId ? Classroom::find($this->kelasId) : null;
        $siswa = $this->siswaId ? Student::find($this->siswaId) : null;
        $user = auth()->user();
        if (! $sem || ! $kelas || ! $siswa || $kelas->academic_year_id !== $sem->academic_year_id) {
            return $kosong;
        }
        // Pengguna harus berhak melihat sedikitnya satu mata pelajaran di kelas ini, dan siswa harus terdaftar di kelas itu.
        if ($this->mapelUntuk($kelas)->isEmpty()
            || ! Enrollment::where('student_id', $siswa->id)->where('classroom_id', $kelas->id)->where('academic_year_id', $sem->academic_year_id)->exists()) {
            return $kosong;
        }
        $semua = Rekap::siswa($sem, $siswa, Akses::kkmSekolah($user));
        $terlihat = collect($semua['mapel'])->filter(fn ($r) => TeachingAssignment::where('classroom_id', $kelas->id)->where('subject_id', $r['subject']->id)->exists()
            && Akses::bisaMelihat($user, $kelas, $r['subject']))->values();
        $penuh = $terlihat->count() === count($semua['mapel']); // rata-rata hanya berarti bila seluruh mata pelajaran terlihat
        $akhir = $terlihat->pluck('akhir')->filter(fn ($a) => $a !== null);

        return [
            'mapel' => $terlihat->all(),
            'rata' => $penuh && $akhir->isNotEmpty() ? round($akhir->avg(), 2) : null,
            'lengkap' => $penuh && $semua['lengkap'],
            'penuh' => $penuh,
        ];
    }

    public function riwayat(): array
    {
        $sem = $this->semester();
        $kelas = $this->kelasId ? Classroom::find($this->kelasId) : null;
        $mapel = $this->mapelId ? Subject::find($this->mapelId) : null;
        if (! $sem || ! $kelas || ! $mapel || ! Akses::bisaMelihat(auth()->user(), $kelas, $mapel)) {
            return [];
        }

        return Rekap::riwayat($sem, $kelas, $mapel);
    }
}
