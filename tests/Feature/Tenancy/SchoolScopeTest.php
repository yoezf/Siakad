<?php

namespace Tests\Feature\Tenancy;

use App\Support\Peran;
use App\Support\Tenancy\BelongsToSchool;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

/** Model uji memakai tabel users (punya school_id) untuk memeriksa trait tanpa membuat tabel baru. */
class DataSekolah extends Model
{
    use BelongsToSchool;

    protected $table = 'users';
    protected $guarded = [];
}

class SchoolScopeTest extends TestCase
{
    use SiapkanSekolah;

    public function test_pengguna_hanya_membaca_data_sekolahnya_sendiri(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->penggunaSekolah($b, Peran::GURU_MAPEL);

        $this->actingAs($this->adminDari($a));

        $this->assertSame([$a->id], DataSekolah::pluck('school_id')->unique()->values()->all());
        $this->assertSame(1, DataSekolah::count()); // hanya admin A, guru milik B tidak terlihat
    }

    public function test_tanpa_sekolah_aktif_tidak_ada_data_yang_terbaca(): void
    {
        $this->sekolah('SD A');
        $this->assertSame(0, DataSekolah::count()); // belum login
    }

    public function test_super_admin_tanpa_sekolah_tidak_melihat_data_sekolah_lewat_scope(): void
    {
        $this->sekolah('SD A');
        $this->actingAs($this->superAdmin());

        $this->assertSame(0, DataSekolah::count());
        $this->assertGreaterThan(0, DataSekolah::withoutGlobalScopes()->count()); // hanya lewat pengecualian eksplisit
    }

    public function test_mencari_data_sekolah_lain_berdasarkan_id_gagal(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $milikB = $this->adminDari($b);

        $this->actingAs($this->adminDari($a));
        $this->expectException(ModelNotFoundException::class);
        DataSekolah::findOrFail($milikB->id);
    }

    public function test_data_baru_otomatis_masuk_sekolah_aktif(): void
    {
        $a = $this->sekolah('SD A');
        $this->actingAs($this->adminDari($a));

        $baru = DataSekolah::create(['name' => 'X', 'email' => 'x@contoh.test', 'password' => 'x']);

        $this->assertSame($a->id, $baru->school_id);
    }

    public function test_data_tidak_bisa_dibuat_tanpa_sekolah_aktif(): void
    {
        $this->expectException(LogicException::class);
        DataSekolah::create(['name' => 'X', 'email' => 'x@contoh.test', 'password' => 'x']);
    }

    public function test_data_tidak_bisa_dipindahkan_ke_sekolah_lain(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->actingAs($this->adminDari($a));

        $data = DataSekolah::first();
        $data->school_id = $b->id;

        $this->expectException(LogicException::class);
        $data->save();
    }

    public function test_sekolah_aktif_bisa_ditetapkan_manual_untuk_pekerjaan_latar(): void
    {
        $a = $this->sekolah('SD A');
        CurrentSchool::set($a->id);

        $this->assertSame(1, DataSekolah::count());
    }
}
