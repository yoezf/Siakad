<?php

namespace Tests\Feature\Platform;

use App\Support\Peran;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class FeatureGateTest extends TestCase
{
    // setUp milik trait tertimpa setUp kelas ini, jadi dipanggil lewat alias agar peran tetap terisi.
    use SiapkanSekolah { setUp as private setUpSekolah; }

    protected function setUp(): void
    {
        $this->setUpSekolah();
        Route::middleware(['web', 'auth', 'fitur:ujian'])->get('/_uji/ujian', fn () => 'ok');
    }

    public function test_fitur_mati_ditolak_di_server(): void
    {
        $s = $this->sekolah('SD Dasar', 'dasar');
        $this->actingAs($this->adminDari($s))->get('/_uji/ujian')->assertForbidden();
    }

    public function test_fitur_aktif_diizinkan(): void
    {
        $s = $this->sekolah('SD Lengkap', 'lengkap');
        $this->actingAs($this->adminDari($s))->get('/_uji/ujian')->assertOk();
    }

    public function test_menyalakan_dan_mematikan_fitur_langsung_berlaku(): void
    {
        $s = $this->sekolah('SD Dasar', 'dasar');
        $guru = $this->penggunaSekolah($s, Peran::GURU_MAPEL);

        $s->update(['features' => ['ujian']]);
        $this->actingAs($guru->fresh())->get('/_uji/ujian')->assertOk();

        $s->update(['features' => []]);
        $this->actingAs($guru->fresh())->get('/_uji/ujian')->assertForbidden();
    }

    public function test_fitur_sekolah_lain_tidak_berlaku_untuk_sekolah_ini(): void
    {
        $this->sekolah('SD Lengkap', 'lengkap');
        $dasar = $this->sekolah('SD Dasar', 'dasar');

        $this->actingAs($this->adminDari($dasar))->get('/_uji/ujian')->assertForbidden();
    }

    public function test_sekolah_nonaktif_ditolak_walau_fitur_menyala(): void
    {
        $s = $this->sekolah('SD Lengkap', 'lengkap', 'nonaktif');
        $this->actingAs($this->adminDari($s))->get('/_uji/ujian')->assertForbidden();
    }

    public function test_super_admin_tanpa_sekolah_ditolak(): void
    {
        $this->actingAs($this->superAdmin())->get('/_uji/ujian')->assertForbidden();
    }
}
