<?php

namespace App\Filament\Sekolah\Pages;

use App\Models\AssessmentComponent;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Modules\Penilaian\Akses;
use App\Modules\Penilaian\CatatNilai;
use App\Modules\Penilaian\Rekap;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Guru mengisi nilai komponen tiap siswa untuk mata pelajaran yang diampunya. Admin sekolah boleh semua. */
class InputNilai extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?string $navigationLabel = 'Input nilai';

    protected static ?string $title = 'Input nilai';

    protected static ?string $slug = 'input-nilai';

    protected string $view = 'filament.sekolah.pages.input-nilai';

    public ?int $semesterId = null;

    public ?int $kelasId = null;

    public ?int $mapelId = null;

    /** @var array<int, array<int, ?string>> id siswa => (id komponen => nilai) */
    public array $nilai = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('input-nilai') ?? false;
    }

    public function mount(): void
    {
        $this->semesterId = array_key_first($this->opsiSemester());
        $this->kelasId = array_key_first($this->opsiKelas());
        $this->mapelId = array_key_first($this->opsiMapel());
        $this->muat();
    }

    public function updatedSemesterId(): void
    {
        $this->kelasId = array_key_first($this->opsiKelas());
        $this->mapelId = array_key_first($this->opsiMapel());
        $this->muat();
    }

    public function updatedKelasId(): void
    {
        $this->mapelId = array_key_first($this->opsiMapel());
        $this->muat();
    }

    public function updatedMapelId(): void
    {
        $this->muat();
    }

    /** @return array<int, string> semester yang boleh diisi pengguna ini (guru: hanya yang aktif) */
    public function opsiSemester(): array
    {
        $user = auth()->user();

        return Semester::with('academicYear')->orderByDesc('start_date')->get()
            ->filter(fn (Semester $s) => Akses::semesterBoleh($user, $s))
            ->mapWithKeys(fn ($s) => [$s->id => "{$s->academicYear->name} {$s->label}".($s->isActive() ? ' (aktif)' : '')])->all();
    }

    private function semester(): ?Semester
    {
        return $this->semesterId ? Semester::find($this->semesterId) : null;
    }

    /** @return array<int, string> kelas pada tahun ajaran semester terpilih yang mata pelajarannya boleh dinilai pengguna ini */
    public function opsiKelas(): array
    {
        $sem = $this->semester();
        if (! $sem || ! Akses::semesterBoleh(auth()->user(), $sem)) {
            return [];
        }
        $user = auth()->user();

        return Classroom::where('academic_year_id', $sem->academic_year_id)->orderBy('grade')->orderBy('name')->get()
            ->filter(fn (Classroom $k) => $this->mapelUntuk($k)->isNotEmpty())->mapWithKeys(fn ($k) => [$k->id => $k->name])->all();
    }

    /** @return Collection<int, Subject> */
    private function mapelUntuk(Classroom $kelas): Collection
    {
        $user = auth()->user();

        return Subject::whereIn('id', TeachingAssignment::where('classroom_id', $kelas->id)->select('subject_id'))->orderBy('name')->get()
            ->filter(fn (Subject $m) => Akses::bisaMenilai($user, $kelas, $m));
    }

    /** @return array<int, string> */
    public function opsiMapel(): array
    {
        $kelas = $this->kelasId ? Classroom::find($this->kelasId) : null;

        return $kelas ? $this->mapelUntuk($kelas)->mapWithKeys(fn ($m) => [$m->id => $m->name])->all() : [];
    }

    /** Konteks hanya dipakai bila pengguna berhak atasnya; nilai dari browser tidak dipercaya. */
    private function konteks(): ?array
    {
        $sem = $this->semester();
        $kelas = $this->kelasId ? Classroom::find($this->kelasId) : null;
        $mapel = $this->mapelId ? Subject::find($this->mapelId) : null;
        $user = auth()->user();
        if (! $sem || ! $kelas || ! $mapel || $kelas->academic_year_id !== $sem->academic_year_id
            || ! Akses::semesterBoleh($user, $sem) || ! Akses::bisaMenilai($user, $kelas, $mapel)) {
            return null;
        }

        return [$sem, $kelas, $mapel];
    }

    public function komponen(): Collection
    {
        return AssessmentComponent::aktifBerurutan();
    }

    /** @return list<array> siswa beserta nilai akhir dari nilai yang SUDAH TERSIMPAN */
    public function baris(): array
    {
        $k = $this->konteks();

        return $k ? Rekap::kelas($k[0], $k[1], $k[2], Akses::kkmSekolah(auth()->user())) : [];
    }

    public function muat(): void
    {
        $this->nilai = [];
        $k = $this->konteks();
        if (! $k) {
            return;
        }
        $siswaIds = Enrollment::where('classroom_id', $k[1]->id)->where('academic_year_id', $k[0]->academic_year_id)->pluck('student_id');
        $ada = Score::where('semester_id', $k[0]->id)->where('subject_id', $k[2]->id)->whereIn('student_id', $siswaIds)->get()->groupBy('student_id');
        foreach ($siswaIds->unique() as $sid) {
            foreach ($this->komponen() as $komp) {
                $v = $ada->get($sid)?->firstWhere('component_id', $komp->id)?->value;
                $this->nilai[$sid][$komp->id] = $v === null ? null : rtrim(rtrim((string) $v, '0'), '.');
            }
        }
    }

    public function simpan(): void
    {
        $k = $this->konteks();
        if (! $k) {
            Notification::make()->danger()->title('Pilih semester, kelas, dan mata pelajaran yang berhak Anda nilai.')->send();

            return;
        }
        try {
            $n = CatatNilai::handle(auth()->user(), $k[0], $k[1], $k[2], $this->nilai);
            Notification::make()->success()->title($n === 0 ? 'Tidak ada nilai yang berubah.' : "{$n} nilai tersimpan.")->send();
        } catch (ValidationException $e) {
            Notification::make()->danger()->persistent()->title('Nilai tidak tersimpan')->body(collect($e->errors())->flatten()->implode(' '))->send();
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
        $this->muat();
    }
}
