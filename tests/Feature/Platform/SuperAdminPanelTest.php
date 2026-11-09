<?php

namespace Tests\Feature\Platform;

use App\Filament\Resources\Schools\Pages\ManageSchools;
use App\Models\School;
use App\Models\User;
use App\Support\Peran;
use Livewire\Livewire;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class SuperAdminPanelTest extends TestCase
{
    use SiapkanSekolah;

    public function test_super_admin_mendaftarkan_sekolah_beserta_admin(): void
    {
        $this->masukPanel($this->superAdmin(), 'admin');

        Livewire::test(ManageSchools::class)
            ->callAction('create', [
                'name' => 'SD Baru', 'city' => 'Bandung', 'plan' => 'lengkap', 'features' => ['ujian'], 'status' => 'uji_coba',
                'admin_name' => 'Admin Baru', 'admin_email' => 'admin@baru.test', 'admin_password' => 'password-aman-123',
            ])
            ->assertHasNoActionErrors();

        $s = School::where('name', 'SD Baru')->firstOrFail();
        $this->assertSame(['ujian'], $s->features);
        $this->assertSame('uji_coba', $s->status);
        $admin = User::where('email', 'admin@baru.test')->firstOrFail();
        $this->assertSame($s->id, $admin->school_id);
        $this->assertTrue($admin->hasRole(Peran::ADMIN_SEKOLAH));
    }

    public function test_email_admin_kembar_dan_kata_sandi_pendek_ditolak(): void
    {
        $this->sekolah('SD A');
        $this->masukPanel($this->superAdmin(), 'admin');

        Livewire::test(ManageSchools::class)
            ->callAction('create', [
                'name' => 'SD Lain', 'plan' => 'dasar', 'status' => 'aktif',
                'admin_name' => 'X', 'admin_email' => 'admin-sd-a@contoh.test', 'admin_password' => 'pendek',
            ])
            ->assertHasActionErrors(['admin_email', 'admin_password']);

        $this->assertSame(1, School::count());
    }

    public function test_nama_sekolah_wajib_diisi(): void
    {
        $this->masukPanel($this->superAdmin(), 'admin');

        Livewire::test(ManageSchools::class)
            ->callAction('create', ['plan' => 'dasar', 'status' => 'aktif', 'admin_name' => 'X', 'admin_email' => 'x@x.test', 'admin_password' => 'password-aman-123'])
            ->assertHasActionErrors(['name' => 'required']);
    }

    public function test_daftar_sekolah_menampilkan_semua_sekolah(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->masukPanel($this->superAdmin(), 'admin');

        Livewire::test(ManageSchools::class)->assertCanSeeTableRecords([$a, $b]);
    }

    public function test_sekolah_dinonaktifkan_lewat_status_bukan_dihapus(): void
    {
        $a = $this->sekolah('SD A');
        $this->masukPanel($this->superAdmin(), 'admin');

        Livewire::test(ManageSchools::class)
            ->callTableAction('edit', $a, ['name' => 'SD A', 'plan' => 'dasar', 'status' => 'nonaktif', 'features' => []])
            ->assertHasNoTableActionErrors();

        $this->assertSame('nonaktif', $a->fresh()->status);
        $this->assertSame(1, School::count());
    }

    public function test_admin_sekolah_tidak_bisa_membuka_daftar_sekolah(): void
    {
        $a = $this->sekolah('SD A');
        $this->actingAs($this->adminDari($a))->get('/admin/sekolah')->assertForbidden();
    }
}
