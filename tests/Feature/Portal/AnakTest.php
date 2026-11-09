<?php

namespace Tests\Feature\Portal;

use App\Models\Guardian;
use App\Models\Student;
use App\Modules\Portal\Anak;
use App\Support\Peran;
use Illuminate\Validation\ValidationException;

class AnakTest extends PortalTestCase
{
    private function nama($akun): array
    {
        return Anak::untuk($akun)->pluck('name')->all();
    }

    public function test_orang_tua_hanya_melihat_anaknya_sendiri(): void
    {
        $this->assertSame(['Siswa A1'], $this->nama($this->ortu1));
        $this->assertSame(['Siswa A2', 'Siswa B1'], $this->nama($this->ortu2));
    }

    public function test_siswa_hanya_melihat_dirinya_sendiri(): void
    {
        $this->assertSame(['Siswa A1'], $this->nama($this->akunSiswaA1));
    }

    public function test_akun_tanpa_peran_portal_tidak_melihat_siapa_pun_walau_datanya_terhubung(): void
    {
        $this->ortu1->syncRoles([Peran::GURU_MAPEL]);
        $this->akunSiswaA1->syncRoles([Peran::ORANG_TUA]); // peran orang tua, tetapi tidak punya data wali

        $this->assertSame([], $this->nama($this->ortu1->fresh()));
        $this->assertSame([], $this->nama($this->akunSiswaA1->fresh()));
    }

    public function test_orang_tua_yang_juga_siswa_mendapat_gabungan_tanpa_ganda(): void
    {
        $this->ortu1->assignRole(Peran::SISWA);
        $this->untuk($this->s, fn () => $this->a2->update(['user_id' => $this->ortu1->id]));

        $this->assertSame(['Siswa A1', 'Siswa A2'], $this->nama($this->ortu1->fresh()));
    }

    public function test_pengguna_tanpa_sekolah_tidak_melihat_siapa_pun(): void
    {
        $this->assertSame([], $this->nama($this->superAdmin()));
    }

    public function test_akun_sekolah_lain_tidak_bisa_ditautkan_ke_siswa(): void
    {
        $b = $this->sekolah('SD B');
        $asing = $this->penggunaSekolah($b, Peran::SISWA, 'Asing');

        $this->expectException(ValidationException::class);
        $this->untuk($this->s, fn () => $this->a2->update(['user_id' => $asing->id]));
    }

    public function test_satu_akun_tidak_bisa_menjadi_dua_siswa(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        \DB::table('students')->where('id', $this->a2->id)->update(['user_id' => $this->akunSiswaA1->id]);
    }

    public function test_siswa_pindah_status_tetap_terlihat_oleh_orang_tuanya(): void
    {
        $this->untuk($this->s, fn () => $this->a1->update(['status' => 'lulus']));

        $this->assertSame(['Siswa A1'], $this->nama($this->ortu1));
    }

    public function test_boleh_hanya_untuk_anak_sendiri(): void
    {
        $this->assertTrue(Anak::boleh($this->ortu1, $this->a1->id));
        $this->assertFalse(Anak::boleh($this->ortu1, $this->a2->id));
        $this->assertFalse(Anak::boleh($this->ortu1, 999999));
        $this->assertNotNull(Student::class);
        $this->assertNotNull(Guardian::class);
    }
}
