<?php

namespace Tests\Concerns;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Platform\RegisterSchool;
use App\Support\Peran;
use App\Support\Tenancy\CurrentSchool;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait SiapkanSekolah
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        CurrentSchool::set(null); // keadaan statis tidak boleh bocor antar uji
        parent::tearDown();
    }

    /** Daftarkan sekolah lewat jalur resmi; admin-nya ikut dibuat. */
    protected function sekolah(string $nama = 'SD Contoh', string $paket = 'dasar', string $status = 'aktif'): School
    {
        $slug = str($nama)->slug();

        return app(RegisterSchool::class)->handle([
            'name' => $nama, 'plan' => $paket, 'status' => $status,
            'admin_name' => "Admin {$nama}", 'admin_email' => "admin-{$slug}@contoh.test", 'admin_password' => 'password-aman-123',
        ]);
    }

    /** Jalankan kode seolah-olah sekolah ini sedang aktif (tanpa perlu login), lalu kembalikan keadaan. */
    protected function untuk(School $s, callable $f): mixed
    {
        $sebelumnya = CurrentSchool::override(); // dipulihkan, bukan dikosongkan, agar pemanggilan bersarang aman
        CurrentSchool::set($s->id);
        try {
            return $f();
        } finally {
            CurrentSchool::set($sebelumnya);
        }
    }

    /** Buat tahun ajaran 2025/2026 (atau nama lain) untuk sebuah sekolah, tanpa perlu login. */
    protected function tahunAjaran(School $s, string $nama = '2025/2026', string $mulai = '2025-07-01', string $selesai = '2026-06-30'): AcademicYear
    {
        return $this->untuk($s, fn () => AcademicYear::create(['name' => $nama, 'start_date' => $mulai, 'end_date' => $selesai]));
    }

    protected function semester(School $s, AcademicYear $y, int $no = 1, string $mulai = '2025-07-01', string $selesai = '2025-12-31'): Semester
    {
        return $this->untuk($s, fn () => Semester::create(['academic_year_id' => $y->id, 'number' => $no, 'start_date' => $mulai, 'end_date' => $selesai]));
    }

    protected function kelas(School $s, AcademicYear $y, string $nama = '4A', int $tingkat = 4, ?int $waliId = null): \App\Models\Classroom
    {
        return $this->untuk($s, fn () => \App\Models\Classroom::create(['academic_year_id' => $y->id, 'name' => $nama, 'grade' => $tingkat, 'homeroom_staff_id' => $waliId]));
    }

    protected function siswaDi(School $s, string $nama = 'Budi', array $lain = []): \App\Models\Student
    {
        return $this->untuk($s, fn () => \App\Models\Student::create(['name' => $nama, 'gender' => 'L'] + $lain));
    }

    /** Guru (atau tendik) dengan akun masuk opsional. */
    protected function guruDi(School $s, string $nama = 'Bu Sari', ?User $akun = null, string $jenis = 'guru', string $status = 'aktif'): \App\Models\Staff
    {
        return $this->untuk($s, fn () => \App\Models\Staff::create(['name' => $nama, 'kind' => $jenis, 'status' => $status, 'user_id' => $akun?->id]));
    }

    protected function mapel(School $s, string $kode = 'MTK', string $nama = 'Matematika'): \App\Models\Subject
    {
        return $this->untuk($s, fn () => \App\Models\Subject::create(['code' => $kode, 'name' => $nama]));
    }

    protected function jam(School $s, int $no = 1, string $mulai = '07:00', string $selesai = '07:45', bool $istirahat = false, ?string $nama = null): \App\Models\TimeSlot
    {
        return $this->untuk($s, fn () => \App\Models\TimeSlot::create(['number' => $no, 'name' => $nama ?? "Jam ke-$no", 'start_time' => $mulai, 'end_time' => $selesai, 'is_break' => $istirahat]));
    }

    protected function penugasan(School $s, \App\Models\Classroom $kelas, \App\Models\Subject $mapel, \App\Models\Staff $guru): \App\Models\TeachingAssignment
    {
        return $this->untuk($s, fn () => \App\Models\TeachingAssignment::create(['classroom_id' => $kelas->id, 'subject_id' => $mapel->id, 'staff_id' => $guru->id]));
    }

    protected function jadwal(School $s, \App\Models\Classroom $kelas, \App\Models\Subject $mapel, \App\Models\TimeSlot $jam, int $hari = 1): \App\Models\ScheduleEntry
    {
        return $this->untuk($s, fn () => \App\Models\ScheduleEntry::create(['classroom_id' => $kelas->id, 'subject_id' => $mapel->id, 'time_slot_id' => $jam->id, 'day' => $hari]));
    }

    protected function adminDari(School $s): User
    {
        return $s->users()->role(Peran::ADMIN_SEKOLAH)->firstOrFail();
    }

    protected function penggunaSekolah(School $s, string $peran, string $nama = 'Pengguna'): User
    {
        $u = new User(['name' => $nama, 'email' => str()->random(8).'@contoh.test', 'password' => 'password-aman-123']);
        $u->school_id = $s->id;
        $u->save();
        $u->assignRole($peran);

        return $u;
    }

    protected function superAdmin(): User
    {
        $u = new User(['name' => 'Super', 'email' => 'super@contoh.test', 'password' => 'password-aman-123']);
        $u->save();
        $u->assignRole(Peran::SUPER_ADMIN);

        return $u;
    }

    /** Masuk sebagai pengguna dalam panel dan (untuk panel Sekolah) sekolah tertentu. */
    protected function masukPanel(User $u, string $panel, ?School $tenant = null): void
    {
        $this->actingAs($u);
        Filament::setCurrentPanel($panel);
        Filament::setTenant($tenant);
    }
}
