<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Support\AvatarInisial;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

/** Avatar inisial dibuat di halaman sendiri (tanpa layanan luar) dan mengikuti warna tema. */
class AvatarInisialTest extends TestCase
{
    use SiapkanSekolah;

    private function svg(string $uri): string
    {
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);

        return base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
    }

    public function test_pengguna_indigo_dengan_dua_inisial_dan_tanpa_layanan_luar(): void
    {
        $svg = $this->svg((new AvatarInisial)->get(new User(['name' => 'Admin SD Harapan'])));

        $this->assertStringContainsString('#6366f1', $svg);
        $this->assertStringContainsString('>AS<', $svg);
        $this->assertStringNotContainsString('http', str_replace('http://www.w3.org/2000/svg', '', $svg));
    }

    public function test_sekolah_putih_untuk_sidebar_berwarna(): void
    {
        $s = $this->sekolah('SD Bunga Harapan');

        $svg = $this->svg((new AvatarInisial)->get($s));

        $this->assertStringContainsString('fill="#ffffff"', $svg);
        $this->assertStringContainsString('>SB<', $svg);
    }

    public function test_nama_berisi_tanda_kurung_atau_kosong_tidak_merusak_svg(): void
    {
        $a = $this->svg((new AvatarInisial)->get(new User(['name' => '<script>x</script> Budi'])));
        $this->assertStringNotContainsString('<script>', $a);

        $this->assertStringContainsString('>?<', $this->svg((new AvatarInisial)->get(new User(['name' => '   ']))));
    }
}
