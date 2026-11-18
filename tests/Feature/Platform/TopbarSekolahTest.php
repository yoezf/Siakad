<?php

namespace Tests\Feature\Platform;

use App\Support\Peran;
use Illuminate\Support\Facades\Blade;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

/** Nama sekolah di tengah bilah atas: tampil sebagai tautan profil bagi yang berhak, teks biasa bagi lainnya, dan tidak tampil tanpa sekolah. */
class TopbarSekolahTest extends TestCase
{
    use SiapkanSekolah;

    private function tampil(): string
    {
        return Blade::render('<x-topbar-sekolah />');
    }

    public function test_admin_melihat_nama_sekolah_sebagai_tautan_profil(): void
    {
        $s = $this->sekolah('SD Harapan Uji');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        $html = $this->tampil();

        $this->assertStringContainsString('SD Harapan Uji', $html);
        $this->assertStringContainsString('<a href=', $html);
        $this->assertStringContainsString('profile', $html);
    }

    public function test_peran_tanpa_akses_profil_melihat_teks_biasa(): void
    {
        $s = $this->sekolah('SD Harapan Uji');
        $this->masukPanel($this->penggunaSekolah($s, Peran::GURU_MAPEL, 'Guru'), 'sekolah', $s);

        $html = $this->tampil();

        $this->assertStringContainsString('SD Harapan Uji', $html);
        $this->assertStringNotContainsString('<a href=', $html);
    }

    public function test_tanpa_sekolah_tidak_menampilkan_apa_pun(): void
    {
        $this->masukPanel($this->superAdmin(), 'admin', null);

        $this->assertSame('', trim($this->tampil()));
    }
}
