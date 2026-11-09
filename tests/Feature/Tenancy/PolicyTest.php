<?php

namespace Tests\Feature\Tenancy;

use App\Models\AcademicYear;
use App\Support\Peran;
use App\Support\Tenancy\CurrentSchool;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use SiapkanSekolah;

    public function test_admin_tidak_boleh_mengubah_pengguna_sekolah_lain(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $adminA = $this->adminDari($a);
        $guruA = $this->penggunaSekolah($a, Peran::GURU_MAPEL);
        $guruB = $this->penggunaSekolah($b, Peran::GURU_MAPEL);

        $this->assertTrue($adminA->can('update', $guruA));
        $this->assertFalse($adminA->can('update', $guruB));
        $this->assertFalse($adminA->can('view', $guruB));
    }

    public function test_guru_tidak_boleh_mengelola_pengguna_atau_sekolah(): void
    {
        $a = $this->sekolah('SD A');
        $guru = $this->penggunaSekolah($a, Peran::GURU_MAPEL);

        $this->assertFalse($guru->can('viewAny', \App\Models\User::class));
        $this->assertFalse($guru->can('create', \App\Models\User::class));
        $this->assertFalse($guru->can('viewAny', \App\Models\School::class));
    }

    public function test_hanya_super_admin_yang_boleh_mengelola_daftar_sekolah(): void
    {
        $a = $this->sekolah('SD A');

        $this->assertTrue($this->superAdmin()->can('create', \App\Models\School::class));
        $this->assertFalse($this->adminDari($a)->can('create', \App\Models\School::class));
        $this->assertFalse($this->adminDari($a)->can('update', $a));
    }

    public function test_tahun_ajaran_sekolah_lain_tidak_boleh_diubah_walau_pengguna_berizin(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $tahunB = $this->tahunAjaran($b);
        CurrentSchool::set($b->id);
        $tahunB = AcademicYear::find($tahunB->id);
        CurrentSchool::set(null);

        $adminA = $this->adminDari($a);
        $this->assertTrue($adminA->can('create', AcademicYear::class));
        $this->assertFalse($adminA->can('update', $tahunB));
        $this->assertFalse($adminA->can('view', $tahunB));
    }

    public function test_tidak_ada_yang_boleh_menghapus_riwayat_akademik_atau_pengguna(): void
    {
        $a = $this->sekolah('SD A');
        $admin = $this->adminDari($a);
        $tahun = $this->tahunAjaran($a);

        $this->assertFalse($admin->can('delete', $tahun));
        $this->assertFalse($admin->can('delete', $admin));
        $this->assertFalse($this->superAdmin()->can('delete', $a));
    }
}
