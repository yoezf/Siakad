<?php

namespace Tests\Feature\Tenancy;

use App\Filament\Sekolah\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\Peran;
use Livewire\Livewire;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class UserIsolationTest extends TestCase
{
    use SiapkanSekolah;

    public function test_daftar_pengguna_hanya_berisi_pengguna_sekolah_sendiri(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $guruA = $this->penggunaSekolah($a, Peran::GURU_MAPEL);
        $guruB = $this->penggunaSekolah($b, Peran::GURU_MAPEL);

        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageUsers::class)
            ->assertCanSeeTableRecords([$this->adminDari($a), $guruA])
            ->assertCanNotSeeTableRecords([$this->adminDari($b), $guruB]);
    }

    public function test_halaman_pengguna_lewat_http_hanya_menampilkan_sekolah_sendiri(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->penggunaSekolah($b, Peran::GURU_MAPEL, 'Guru Milik B');
        $this->penggunaSekolah($a, Peran::GURU_MAPEL, 'Guru Milik A'); // nama ini hanya bisa tampil dari tabel, bukan menu akun

        $this->actingAs($this->adminDari($a))->get("/sekolah/{$a->slug}/pengguna")
            ->assertOk()
            ->assertSee('Guru Milik A')
            ->assertSee('admin-sd-a@contoh.test')
            ->assertDontSee('admin-sd-b@contoh.test')
            ->assertDontSee('Guru Milik B');
    }

    public function test_alamat_sekolah_lain_tidak_bisa_dibuka(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');

        $this->actingAs($this->adminDari($a))->get("/sekolah/{$b->slug}/pengguna")->assertNotFound();
    }

    public function test_admin_membuka_halaman_pengguna_sekolahnya_sendiri(): void
    {
        $a = $this->sekolah('SD A');

        $this->actingAs($this->adminDari($a))->get("/sekolah/{$a->slug}/pengguna")->assertOk();
    }

    public function test_guru_tidak_boleh_mengelola_pengguna(): void
    {
        $a = $this->sekolah('SD A');
        $guru = $this->penggunaSekolah($a, Peran::GURU_MAPEL);

        $this->actingAs($guru)->get("/sekolah/{$a->slug}/pengguna")->assertForbidden();
    }

    public function test_pengguna_baru_selalu_masuk_sekolah_aktif_dengan_peran_pilihan(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageUsers::class)
            ->callAction('create', ['name' => 'Bu Rina', 'email' => 'rina@contoh.test', 'role' => Peran::GURU_MAPEL, 'password' => 'password-aman-123'])
            ->assertHasNoActionErrors();

        $baru = User::where('email', 'rina@contoh.test')->firstOrFail();
        $this->assertSame($a->id, $baru->school_id);
        $this->assertNotSame($b->id, $baru->school_id);
        $this->assertTrue($baru->hasRole(Peran::GURU_MAPEL));
    }

    public function test_admin_sekolah_tidak_bisa_membuat_super_admin(): void
    {
        $a = $this->sekolah('SD A');
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageUsers::class)
            ->callAction('create', ['name' => 'Penyusup', 'email' => 'x@contoh.test', 'role' => Peran::SUPER_ADMIN, 'password' => 'password-aman-123'])
            ->assertHasActionErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'x@contoh.test']);
    }

    public function test_pengguna_sekolah_lain_tidak_bisa_diubah(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $guruB = $this->penggunaSekolah($b, Peran::GURU_MAPEL, 'Guru B');
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        // Rekaman sekolah B tidak ada dalam tabel sekolah A, sehingga aksi sunting tidak bisa dijalankan atasnya.
        Livewire::test(ManageUsers::class)->assertCanNotSeeTableRecords([$guruB]);
        $this->assertSame('Guru B', $guruB->fresh()->name);
    }

    public function test_email_kembar_ditolak(): void
    {
        $a = $this->sekolah('SD A');
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageUsers::class)
            ->callAction('create', ['name' => 'Kembar', 'email' => $this->adminDari($a)->email, 'role' => Peran::GURU_MAPEL, 'password' => 'password-aman-123'])
            ->assertHasActionErrors(['email']);
    }
}
