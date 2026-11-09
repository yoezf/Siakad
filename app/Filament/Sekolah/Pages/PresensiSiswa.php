<?php

namespace App\Filament\Sekolah\Pages;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\ScheduleEntry;
use App\Models\StudentAttendance;
use App\Modules\Kehadiran\CatatKehadiranSiswa;
use App\Modules\Kehadiran\Status;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Mencatat kehadiran siswa: harian (wali kelas, admin, TU) atau per pelajaran (guru pengampu, admin, TU). */
class PresensiSiswa extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Kehadiran';

    protected static ?string $navigationLabel = 'Catat kehadiran siswa';

    protected static ?string $title = 'Kehadiran siswa';

    protected static ?string $slug = 'presensi-siswa';

    protected string $view = 'filament.sekolah.pages.presensi-siswa';

    public string $jenis = 'harian'; // 'harian' | 'pelajaran'

    public ?int $kelasId = null;

    public string $tanggal = '';

    public ?int $entriId = null;

    /** @var array<int, string> id siswa => kode status */
    public array $status = [];

    /** @var array<int, ?string> */
    public array $catatan = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('catat-kehadiran-siswa') ?? false;
    }

    public function mount(): void
    {
        $this->tanggal = today()->toDateString();
        $this->kelasId = array_key_first($this->opsiKelas());
        $this->muat();
    }

    public function updatedJenis(): void
    {
        $this->kelasId = array_key_first($this->opsiKelas());
        $this->entriId = null;
        $this->muat();
    }

    public function updatedKelasId(): void
    {
        $this->entriId = null;
        $this->muat();
    }

    public function updatedTanggal(): void
    {
        $this->entriId = null;
        $this->muat();
    }

    public function updatedEntriId(): void
    {
        $this->muat();
    }

    private function tanggalCarbon(): ?Carbon
    {
        try {
            $t = Carbon::createFromFormat('!Y-m-d', $this->tanggal);

            return $t && $t->format('Y-m-d') === $this->tanggal ? $t : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<int, string> kelas tahun berjalan yang boleh dicatat pengguna ini */
    public function opsiKelas(): array
    {
        $tahun = AcademicYear::tahunBerjalanId(CurrentSchool::id() ?? 0);
        $user = auth()->user();

        return Classroom::with('academicYear')->where('academic_year_id', $tahun ?? 0)->orderBy('grade')->orderBy('name')->get()
            ->filter(fn (Classroom $k) => $this->jenis === 'harian'
                ? CatatKehadiranSiswa::bisa($user, $k)
                : ScheduleEntry::where('classroom_id', $k->id)->get()->contains(fn ($e) => CatatKehadiranSiswa::bisa($user, $k, $e)))
            ->mapWithKeys(fn ($k) => [$k->id => $k->name])->all();
    }

    /** @return Collection<int, ScheduleEntry> pelajaran kelas ini pada hari yang dipilih yang boleh dicatat pengguna ini */
    public function opsiEntri(): Collection
    {
        $tgl = $this->tanggalCarbon();
        $kelas = $this->kelasId ? Classroom::find($this->kelasId) : null;
        if (! $tgl || ! $kelas || $this->jenis !== 'pelajaran') {
            return collect();
        }

        return ScheduleEntry::with(['subject', 'timeSlot'])->where('classroom_id', $kelas->id)->where('day', $tgl->dayOfWeekIso)->get()
            ->filter(fn ($e) => CatatKehadiranSiswa::bisa(auth()->user(), $kelas, $e))
            ->sortBy(fn ($e) => $e->timeSlot->number)->values();
    }

    /** Kelas hanya dipakai bila pengguna berhak atasnya; nilai dari browser tidak dipercaya. */
    private function kelasSah(): ?Classroom
    {
        $kelas = $this->kelasId ? Classroom::find($this->kelasId) : null;
        if (! $kelas) {
            return null;
        }
        $entri = $this->jenis === 'pelajaran' ? ($this->entriId ? ScheduleEntry::find($this->entriId) : null) : null;
        if ($this->jenis === 'pelajaran' && ! $entri) {
            return null;
        }

        return CatatKehadiranSiswa::bisa(auth()->user(), $kelas, $entri) ? $kelas : null;
    }

    /** @return Collection<int, \App\Models\Student> siswa yang terdaftar di kelas ini pada tanggal itu */
    public function daftarSiswa(): Collection
    {
        $kelas = $this->kelasSah();
        $tgl = $this->tanggalCarbon();
        if (! $kelas || ! $tgl) {
            return collect();
        }

        return Enrollment::where('classroom_id', $kelas->id)->whereDate('joined_at', '<=', $tgl)
            ->where(fn ($q) => $q->whereNull('left_at')->orWhereDate('left_at', '>=', $tgl))
            ->with('student')->get()->pluck('student')->filter()->unique('id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function muat(): void
    {
        $this->status = [];
        $this->catatan = [];
        $siswa = $this->daftarSiswa();
        $tgl = $this->tanggalCarbon();
        if ($siswa->isEmpty() || ! $tgl) {
            return;
        }
        $ada = StudentAttendance::where('classroom_id', $this->kelasId)->whereDate('date', $tgl)
            ->where('schedule_entry_id', $this->jenis === 'pelajaran' ? $this->entriId : null)->get()->keyBy('student_id');
        foreach ($siswa as $s) {
            $this->status[$s->id] = $ada->get($s->id)?->status ?? Status::HADIR; // belum tersimpan sampai tombol Simpan ditekan
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
        $kelas = $this->kelasSah();
        if (! $kelas) {
            Notification::make()->danger()->title('Pilih kelas yang berhak Anda catat.')->send();

            return;
        }
        $baris = [];
        foreach ($this->daftarSiswa() as $s) {
            $baris[$s->id] = ['status' => $this->status[$s->id] ?? null, 'note' => $this->catatan[$s->id] ?? null];
        }
        try {
            $n = CatatKehadiranSiswa::handle(auth()->user(), $kelas, $this->tanggal, $this->jenis === 'pelajaran' ? ScheduleEntry::find($this->entriId) : null, $baris);
            Notification::make()->success()->title("Kehadiran {$n} siswa tersimpan.")->send();
        } catch (ValidationException $e) {
            Notification::make()->danger()->persistent()->title('Kehadiran tidak tersimpan')->body(collect($e->errors())->flatten()->implode(' '))->send();
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
        $this->muat();
    }
}
