<?php

namespace Tests\Feature\Akademik;

use App\Filament\Sekolah\Resources\AcademicYears\Pages\ManageAcademicYears;
use App\Filament\Sekolah\Resources\Semesters\Pages\ManageSemesters;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Support\Peran;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class TahunAjaranTest extends TestCase
{
    use SiapkanSekolah;

    // ---------- isolasi ----------

    public function test_tahun_ajaran_dan_semester_sekolah_lain_tidak_terbaca(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->semester($a, $this->tahunAjaran($a));

        $this->actingAs($this->adminDari($b));

        $this->assertSame(0, AcademicYear::count());
        $this->assertSame(0, Semester::count());
    }

    public function test_halaman_tahun_ajaran_hanya_menampilkan_sekolah_sendiri(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->tahunAjaran($a, '2025/2026');
        $this->tahunAjaran($b, '2030/2031');

        $this->actingAs($this->adminDari($a))->get("/sekolah/{$a->slug}/tahun-ajaran")
            ->assertOk()->assertSee('2025/2026')->assertDontSee('2030/2031');
    }

    public function test_semester_tidak_bisa_menempel_ke_tahun_ajaran_sekolah_lain(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $tahunB = $this->tahunAjaran($b);

        CurrentSchool::set($a->id);
        $this->expectException(ValidationException::class);
        Semester::create(['academic_year_id' => $tahunB->id, 'number' => 1, 'start_date' => '2025-07-01', 'end_date' => '2025-12-31']);
    }

    // ---------- aturan data ----------

    public function test_nama_tahun_ajaran_unik_per_sekolah_tapi_boleh_sama_antar_sekolah(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->tahunAjaran($a, '2025/2026');
        $this->tahunAjaran($b, '2025/2026'); // sekolah lain boleh memakai nama yang sama

        $this->expectException(QueryException::class);
        $this->tahunAjaran($a, '2025/2026');
    }

    public function test_tanggal_selesai_tahun_ajaran_harus_setelah_mulai(): void
    {
        $this->expectException(ValidationException::class);
        $this->tahunAjaran($this->sekolah('SD A'), '2025/2026', '2026-06-30', '2025-07-01');
    }

    public function test_semester_harus_dalam_rentang_tahun_ajaran(): void
    {
        $a = $this->sekolah('SD A');
        $y = $this->tahunAjaran($a);

        $this->expectException(ValidationException::class);
        $this->semester($a, $y, 1, '2025-06-01', '2025-12-31'); // mulai sebelum tahun ajaran
    }

    public function test_semester_hanya_boleh_ganjil_atau_genap(): void
    {
        $a = $this->sekolah('SD A');
        $y = $this->tahunAjaran($a);

        $this->expectException(ValidationException::class);
        $this->semester($a, $y, 3);
    }

    public function test_semester_yang_sama_tidak_bisa_dibuat_dua_kali_pada_satu_tahun(): void
    {
        $a = $this->sekolah('SD A');
        $y = $this->tahunAjaran($a);
        $this->semester($a, $y, 1);

        $this->expectException(QueryException::class);
        $this->semester($a, $y, 1);
    }

    public function test_rentang_tahun_tidak_boleh_diubah_hingga_semester_keluar_rentang(): void
    {
        $a = $this->sekolah('SD A');
        $y = $this->tahunAjaran($a);
        $this->semester($a, $y, 1);

        CurrentSchool::set($a->id);
        $this->expectException(ValidationException::class);
        $y->update(['start_date' => '2025-09-01']); // semester 1 mulai 1 Juli
    }

    // ---------- semester aktif ----------

    public function test_hanya_satu_semester_aktif_per_sekolah(): void
    {
        $a = $this->sekolah('SD A');
        $y = $this->tahunAjaran($a);
        $s1 = $this->semester($a, $y, 1);
        $s2 = $this->semester($a, $y, 2, '2026-01-01', '2026-06-30');

        CurrentSchool::set($a->id);
        $s1->activate();
        $this->assertTrue($s1->fresh()->isActive());

        $s2->activate();
        $this->assertFalse($s1->fresh()->isActive());
        $this->assertTrue($s2->fresh()->isActive());
        $this->assertSame(1, Semester::where('aktif', 1)->count());
    }

    public function test_mengaktifkan_semester_tidak_memengaruhi_sekolah_lain(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $sa = $this->semester($a, $this->tahunAjaran($a));
        $sb = $this->semester($b, $this->tahunAjaran($b));

        CurrentSchool::set($b->id);
        $sb->activate();
        CurrentSchool::set($a->id);
        $sa->activate();

        $this->assertTrue(Semester::withoutGlobalScopes()->find($sb->id)->isActive()); // tetap aktif
        $this->assertTrue(Semester::withoutGlobalScopes()->find($sa->id)->isActive());
    }

    public function test_basis_data_sendiri_menolak_dua_semester_aktif(): void
    {
        $a = $this->sekolah('SD A');
        $y = $this->tahunAjaran($a);
        $s1 = $this->semester($a, $y, 1);
        $s2 = $this->semester($a, $y, 2, '2026-01-01', '2026-06-30');
        DB::table('semesters')->where('id', $s1->id)->update(['aktif' => 1]);

        $this->expectException(QueryException::class); // jalur mana pun, termasuk SQL langsung, tidak bisa melanggar aturan ini
        DB::table('semesters')->where('id', $s2->id)->update(['aktif' => 1]);
    }

    public function test_aktivasi_tercatat_di_audit(): void
    {
        $a = $this->sekolah('SD A');
        $s = $this->semester($a, $this->tahunAjaran($a));
        CurrentSchool::set($a->id);
        $s->activate();

        $this->assertTrue(Activity::where('log_name', 'semester')->where('subject_id', $s->id)->where('event', 'updated')->exists());
    }

    // ---------- panel ----------

    public function test_admin_menambah_tahun_ajaran_lewat_panel(): void
    {
        $a = $this->sekolah('SD A');
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageAcademicYears::class)
            ->callAction('create', ['name' => '2025/2026', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30'])
            ->assertHasNoActionErrors();

        $y = AcademicYear::withoutGlobalScopes()->where('name', '2025/2026')->firstOrFail();
        $this->assertSame($a->id, $y->school_id);
    }

    public function test_format_nama_tahun_ajaran_dan_nama_kembar_ditolak_di_formulir(): void
    {
        $a = $this->sekolah('SD A');
        $this->tahunAjaran($a, '2025/2026');
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageAcademicYears::class)
            ->callAction('create', ['name' => '2025/2027', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30'])
            ->assertHasActionErrors(['name']);
        Livewire::test(ManageAcademicYears::class)
            ->callAction('create', ['name' => '2025/2026', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30'])
            ->assertHasActionErrors(['name']);
    }

    public function test_admin_menambah_semester_dan_mengaktifkannya(): void
    {
        $a = $this->sekolah('SD A');
        $y = $this->tahunAjaran($a);
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageSemesters::class)
            ->callAction('create', ['academic_year_id' => $y->id, 'number' => 1, 'start_date' => '2025-07-01', 'end_date' => '2025-12-31'])
            ->assertHasNoActionErrors();

        $s = Semester::firstOrFail();
        Livewire::test(ManageSemesters::class)->callTableAction('activate', $s)->assertHasNoTableActionErrors();
        $this->assertTrue($s->fresh()->isActive());
    }

    public function test_semester_ganda_pada_tahun_yang_sama_ditolak_di_formulir(): void
    {
        $a = $this->sekolah('SD A');
        $y = $this->tahunAjaran($a);
        $this->semester($a, $y, 1);
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageSemesters::class)
            ->callAction('create', ['academic_year_id' => $y->id, 'number' => 1, 'start_date' => '2025-07-01', 'end_date' => '2025-12-31'])
            ->assertHasActionErrors(['number']);
    }

    public function test_guru_boleh_melihat_tetapi_tidak_boleh_mengubah(): void
    {
        $a = $this->sekolah('SD A');
        $y = $this->tahunAjaran($a);
        $s = $this->semester($a, $y);
        $guru = $this->penggunaSekolah($a, Peran::GURU_MAPEL);

        $this->actingAs($guru)->get("/sekolah/{$a->slug}/tahun-ajaran")->assertOk();
        $this->actingAs($guru)->get("/sekolah/{$a->slug}/semester")->assertOk();

        $this->masukPanel($guru, 'sekolah', $a);
        Livewire::test(ManageAcademicYears::class)->assertActionHidden('create')->assertTableActionHidden('edit', $y);
        Livewire::test(ManageSemesters::class)->assertActionHidden('create')->assertTableActionHidden('activate', $s);
        $this->assertFalse($s->fresh()->isActive());
    }

    public function test_guru_tidak_bisa_membuat_atau_mengubah_walau_memanggil_aksi_langsung(): void
    {
        $a = $this->sekolah('SD A');
        $y = $this->tahunAjaran($a, '2025/2026');
        $s = $this->semester($a, $y);
        $this->masukPanel($this->penggunaSekolah($a, Peran::GURU_MAPEL), 'sekolah', $a);

        try {
            Livewire::test(ManageAcademicYears::class)
                ->callAction('create', ['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']);
        } catch (\Throwable) {
            // penolakan berupa pengecualian juga dianggap benar; yang dinilai adalah data di basis data
        }
        try {
            Livewire::test(ManageSemesters::class)->callTableAction('activate', $s);
        } catch (\Throwable) {
        }

        $this->assertSame(1, AcademicYear::withoutGlobalScopes()->count(), 'guru berhasil membuat tahun ajaran');
        $this->assertFalse($s->fresh()->isActive(), 'guru berhasil mengaktifkan semester');
    }

    public function test_tahun_ajaran_tidak_punya_tombol_hapus(): void
    {
        $a = $this->sekolah('SD A');
        $y = $this->tahunAjaran($a);
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageAcademicYears::class)->assertTableActionDoesNotExist('delete');
        $this->assertTrue(AcademicYear::whereKey($y->id)->exists());
    }
}
