<?php

namespace App\Filament\Sekolah\Pages;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Modules\Wajah\AbsensiWajah;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Kiosk absensi wajah: kamera mengenali siswa satu kelas dan mencatat kehadiran harian. Pencocokan terjadi di peramban. */
class AbsenWajah extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static string|\UnitEnum|null $navigationGroup = 'Kehadiran';

    protected static ?string $navigationLabel = 'Absen wajah';

    protected static ?string $title = 'Absen dengan wajah';

    protected static ?string $slug = 'absen-wajah';

    protected string $view = 'filament.sekolah.pages.absen-wajah';

    public ?int $kelasId = null;

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u !== null && AbsensiWajah::aktif($u) && $u->can('catat-kehadiran-siswa');
    }

    public function mount(): void
    {
        $this->kelasId = array_key_first($this->opsiKelas());
    }

    /** @return array<int, string> kelas yang boleh dipindai pengguna ini */
    public function opsiKelas(): array
    {
        $tahun = AcademicYear::tahunBerjalanId(CurrentSchool::id() ?? 0);
        $u = auth()->user();

        return Classroom::where('academic_year_id', $tahun ?? 0)->orderBy('grade')->orderBy('name')->get()
            ->filter(fn (Classroom $k) => AbsensiWajah::bisaMemindai($u, $k))->mapWithKeys(fn ($k) => [$k->id => $k->name])->all();
    }

    /** @return list<array{id: int, nama: string, vektor: list<list<float>>}> */
    public function dataKelas(int $kelasId): array
    {
        $kelas = Classroom::find($kelasId);

        return $kelas ? AbsensiWajah::dataKelas(auth()->user(), $kelas) : [];
    }

    /** @return array{hasil: string, pesan?: string} */
    public function catatHadir(int $kelasId, int $siswaId): array
    {
        try {
            $kelas = Classroom::find($kelasId);
            if (! $kelas || ! Student::find($siswaId)) {
                return ['hasil' => 'galat', 'pesan' => 'Kelas atau siswa tidak ditemukan.'];
            }

            return ['hasil' => AbsensiWajah::catatHadir(auth()->user(), $kelas, $siswaId)];
        } catch (ValidationException $e) {
            return ['hasil' => 'galat', 'pesan' => collect($e->errors())->flatten()->implode(' ')];
        } catch (AuthorizationException $e) {
            return ['hasil' => 'galat', 'pesan' => $e->getMessage()];
        }
    }
}
