<?php

namespace App\Filament\Sekolah\Pages;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Modules\Wajah\AbsensiWajah;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Mendaftarkan atau menghapus data wajah siswa untuk absensi wajah. Hanya admin sekolah; fitur absensi_wajah harus menyala. */
class DaftarWajah extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceSmile;

    protected static string|\UnitEnum|null $navigationGroup = 'Kehadiran';

    protected static ?string $navigationLabel = 'Daftar wajah siswa';

    protected static ?string $title = 'Daftar wajah siswa';

    protected static ?string $slug = 'daftar-wajah';

    protected string $view = 'filament.sekolah.pages.daftar-wajah';

    public ?int $kelasId = null;

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u !== null && AbsensiWajah::bisaKelola($u);
    }

    public function mount(): void
    {
        $this->kelasId = array_key_first($this->opsiKelas());
    }

    /** @return array<int, string> */
    public function opsiKelas(): array
    {
        $tahun = AcademicYear::tahunBerjalanId(CurrentSchool::id() ?? 0);

        return Classroom::where('academic_year_id', $tahun ?? 0)->orderBy('grade')->orderBy('name')->get()->mapWithKeys(fn ($k) => [$k->id => $k->name])->all();
    }

    /** @return Collection<int, Student> */
    public function daftarSiswa(): Collection
    {
        if (! $this->kelasId || ! Classroom::find($this->kelasId)) {
            return collect();
        }

        return Enrollment::where('classroom_id', $this->kelasId)->whereDate('joined_at', '<=', today())
            ->where(fn ($q) => $q->whereNull('left_at')->orWhereDate('left_at', '>=', today()))
            ->with('student')->get()->pluck('student')->filter()->unique('id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /** @return list<int> */
    public function terdaftar(): array
    {
        return AbsensiWajah::idTerdaftar();
    }

    /**
     * Dipanggil dari peramban setelah mengambil beberapa sampel wajah.
     *
     * @param  array<int, array<int, float>>  $vektor
     * @return array{ok: bool, pesan?: string}
     */
    public function simpanWajah(int $siswaId, array $vektor): array
    {
        try {
            $siswa = Student::find($siswaId);
            if (! $siswa) {
                return ['ok' => false, 'pesan' => 'Siswa tidak ditemukan.'];
            }
            AbsensiWajah::daftarkan(auth()->user(), $siswa, $vektor);

            return ['ok' => true];
        } catch (ValidationException $e) {
            return ['ok' => false, 'pesan' => collect($e->errors())->flatten()->implode(' ')];
        } catch (AuthorizationException $e) {
            return ['ok' => false, 'pesan' => $e->getMessage()];
        }
    }

    public function hapusWajah(int $siswaId): void
    {
        try {
            $siswa = Student::find($siswaId);
            if (! $siswa) {
                return;
            }
            AbsensiWajah::hapus(auth()->user(), $siswa);
            Notification::make()->success()->title("Data wajah {$siswa->name} dihapus.")->send();
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
