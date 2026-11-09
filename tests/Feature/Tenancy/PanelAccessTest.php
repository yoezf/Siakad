<?php

namespace Tests\Feature\Tenancy;

use App\Support\Peran;
use Filament\Facades\Filament;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use SiapkanSekolah;

    public function test_super_admin_hanya_boleh_masuk_panel_admin(): void
    {
        $su = $this->superAdmin();
        $this->assertTrue($su->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($su->canAccessPanel(Filament::getPanel('sekolah')));
    }

    public function test_admin_sekolah_hanya_boleh_masuk_panel_sekolah(): void
    {
        $admin = $this->adminDari($this->sekolah('SD A'));
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('sekolah')));
        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_orang_tua_dan_siswa_tidak_memakai_panel_sekolah(): void
    {
        $s = $this->sekolah('SD A');
        foreach ([Peran::ORANG_TUA, Peran::SISWA] as $peran) {
            $this->assertFalse($this->penggunaSekolah($s, $peran)->canAccessPanel(Filament::getPanel('sekolah')), $peran);
        }
    }

    public function test_pengguna_tanpa_peran_tidak_bisa_masuk_panel_mana_pun(): void
    {
        $s = $this->sekolah('SD A');
        $u = $this->penggunaSekolah($s, Peran::GURU_MAPEL);
        $u->syncRoles([]);

        $this->assertFalse($u->canAccessPanel(Filament::getPanel('sekolah')));
        $this->assertFalse($u->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_sekolah_nonaktif_menutup_akses_seluruh_penggunanya(): void
    {
        $s = $this->sekolah('SD A');
        $admin = $this->adminDari($s);
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('sekolah')));

        $s->update(['status' => 'nonaktif']);

        $this->assertFalse($admin->fresh()->canAccessPanel(Filament::getPanel('sekolah')));
        $this->assertFalse($admin->fresh()->canAccessTenant($s->fresh()));
    }

    public function test_pengguna_hanya_boleh_mengakses_sekolahnya_sendiri_sebagai_tenant(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $adminA = $this->adminDari($a);

        $this->assertTrue($adminA->canAccessTenant($a));
        $this->assertFalse($adminA->canAccessTenant($b));
        $this->assertSame([$a->id], $adminA->getTenants(Filament::getPanel('sekolah'))->pluck('id')->all());
    }

    public function test_halaman_masuk_kedua_panel_tampil(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/sekolah/login')->assertOk();
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/admin')->assertRedirect();
        $this->get('/sekolah')->assertRedirect();
    }

    public function test_admin_sekolah_ditolak_di_alamat_panel_super_admin(): void
    {
        $admin = $this->adminDari($this->sekolah('SD A'));
        $this->actingAs($admin)->get('/admin')->assertForbidden()->assertSee('Akses ditolak');
    }

    public function test_super_admin_ditolak_di_alamat_panel_sekolah(): void
    {
        $s = $this->sekolah('SD A');
        $this->actingAs($this->superAdmin())->get("/sekolah/{$s->slug}")->assertStatus(403);
    }
}
