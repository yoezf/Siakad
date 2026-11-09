<?php

namespace Tests\Feature\Platform;

use App\Models\School;
use App\Modules\Platform\RegisterSchool;
use App\Support\Peran;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class RegisterSchoolTest extends TestCase
{
    use SiapkanSekolah;

    public function test_dua_sekolah_terdaftar_dengan_admin_masing_masing(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');

        $this->assertSame(2, School::count());
        $this->assertSame($a->id, $this->adminDari($a)->school_id);
        $this->assertSame($b->id, $this->adminDari($b)->school_id);
        $this->assertTrue($this->adminDari($a)->hasRole(Peran::ADMIN_SEKOLAH));
        $this->assertNotSame($this->adminDari($a)->id, $this->adminDari($b)->id);
    }

    public function test_nama_sama_menghasilkan_slug_berbeda(): void
    {
        $a = $this->sekolah('SD Sama');
        $b = app(RegisterSchool::class)->handle([
            'name' => 'SD Sama', 'plan' => 'dasar',
            'admin_name' => 'Admin 2', 'admin_email' => 'lain@contoh.test', 'admin_password' => 'password-aman-123',
        ]);

        $this->assertNotSame($a->slug, $b->slug);
    }

    public function test_fitur_awal_mengikuti_paket(): void
    {
        $this->assertSame([], $this->sekolah('SD Dasar', 'dasar')->features);
        $this->assertEqualsCanonicalizing(['ujian', 'tugas'], $this->sekolah('SD Lengkap', 'lengkap')->features);
        $this->assertContains('premium', $this->sekolah('SD Premium', 'premium')->features);
    }

    public function test_paket_tidak_dikenal_ditolak_dan_tidak_ada_data_tersimpan(): void
    {
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->sekolah('SD X', 'platinum');
        } finally {
            $this->assertSame(0, School::count());
        }
    }

    public function test_email_admin_kembar_membatalkan_seluruh_pendaftaran(): void
    {
        $this->sekolah('SD A');

        try {
            app(RegisterSchool::class)->handle([
                'name' => 'SD Lain', 'plan' => 'dasar',
                'admin_name' => 'Admin', 'admin_email' => 'admin-sd-a@contoh.test', 'admin_password' => 'password-aman-123',
            ]);
            $this->fail('Seharusnya gagal karena email kembar.');
        } catch (QueryException) {
            $this->assertSame(1, School::count()); // sekolah "SD Lain" tidak boleh tersisa
        }
    }

    public function test_pendaftaran_dan_perubahan_fitur_tercatat_di_audit(): void
    {
        $s = $this->sekolah('SD Audit');
        $this->assertTrue(Activity::where('log_name', 'sekolah')->where('subject_id', $s->id)->where('event', 'created')->exists());

        $s->update(['features' => ['ujian']]);
        $this->assertTrue(Activity::where('log_name', 'sekolah')->where('subject_id', $s->id)->where('event', 'updated')->exists());
    }

    public function test_kata_sandi_tersimpan_dalam_bentuk_hash(): void
    {
        $admin = $this->adminDari($this->sekolah('SD A'));
        $this->assertNotSame('password-aman-123', $admin->getRawOriginal('password'));
    }
}
