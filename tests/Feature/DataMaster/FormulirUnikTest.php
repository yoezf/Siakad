<?php

namespace Tests\Feature\DataMaster;

use App\Filament\Sekolah\Resources\Classrooms\Pages\ManageClassrooms;
use App\Filament\Sekolah\Resources\Staff\Pages\ManageStaff;
use App\Filament\Sekolah\Resources\Subjects\Pages\ManageSubjects;
use App\Models\Classroom;
use App\Models\Staff;
use App\Models\Subject;
use Livewire\Livewire;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

/**
 * Aturan "unik per sekolah" pada formulir panel. Closure modifyRuleUsing harus memakai nama parameter yang dikenali
 * Filament; kesalahan nama baru terlihat saat formulir benar-benar dipakai, jadi tiap formulir diuji di sini.
 */
class FormulirUnikTest extends TestCase
{
    use SiapkanSekolah;

    public function test_kode_mata_pelajaran_kembar_ditolak_dan_boleh_sama_di_sekolah_lain(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->mapel($a, 'MTK');
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageSubjects::class)->callAction('create', ['code' => 'MTK', 'name' => 'Lagi'])->assertHasActionErrors(['code']);
        Livewire::test(ManageSubjects::class)->callAction('create', ['code' => 'IPA', 'name' => 'IPA'])->assertHasNoActionErrors();

        $this->masukPanel($this->adminDari($b), 'sekolah', $b);
        Livewire::test(ManageSubjects::class)->callAction('create', ['code' => 'MTK', 'name' => 'Matematika B'])->assertHasNoActionErrors();
        $this->assertSame(1, Subject::withoutGlobalScopes()->where('school_id', $b->id)->count());
    }

    public function test_nip_guru_kembar_ditolak_dan_boleh_sama_di_sekolah_lain(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->untuk($a, fn () => Staff::create(['name' => 'Ada', 'nip' => '198001012005012001']));
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageStaff::class)->callAction('create', ['name' => 'Kembar', 'nip' => '198001012005012001', 'kind' => 'guru', 'status' => 'aktif'])->assertHasActionErrors(['nip']);
        Livewire::test(ManageStaff::class)->callAction('create', ['name' => 'Beda', 'nip' => '198001012005012002', 'kind' => 'guru', 'status' => 'aktif'])->assertHasNoActionErrors();

        $this->masukPanel($this->adminDari($b), 'sekolah', $b);
        Livewire::test(ManageStaff::class)->callAction('create', ['name' => 'Di B', 'nip' => '198001012005012001', 'kind' => 'guru', 'status' => 'aktif'])->assertHasNoActionErrors();
    }

    public function test_nama_kelas_kembar_ditolak_per_tahun_tetapi_boleh_di_tahun_lain(): void
    {
        $s = $this->sekolah('SD A');
        $y1 = $this->tahunAjaran($s, '2025/2026');
        $y2 = $this->tahunAjaran($s, '2026/2027', '2026-07-01', '2027-06-30');
        $this->kelas($s, $y1, '4A');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageClassrooms::class)->callAction('create', ['academic_year_id' => $y1->id, 'name' => '4A', 'grade' => 4])->assertHasActionErrors(['name']);
        Livewire::test(ManageClassrooms::class)->callAction('create', ['academic_year_id' => $y2->id, 'name' => '4A', 'grade' => 4])->assertHasNoActionErrors();

        $this->assertSame(2, Classroom::withoutGlobalScopes()->where('school_id', $s->id)->count());
    }

    public function test_kelas_baru_bisa_langsung_diberi_wali_kelas_lewat_formulir(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $guru = $this->guruDi($s, 'Bu Sari');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageClassrooms::class)
            ->callAction('create', ['academic_year_id' => $y->id, 'name' => '4A', 'grade' => 4, 'homeroom_staff_id' => $guru->id])
            ->assertHasNoActionErrors();

        $this->assertSame($guru->id, Classroom::withoutGlobalScopes()->firstOrFail()->homeroom_staff_id);
    }
}
