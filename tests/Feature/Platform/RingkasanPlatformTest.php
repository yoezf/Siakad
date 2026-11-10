<?php

namespace Tests\Feature\Platform;

use App\Filament\Widgets\RingkasanPlatform;
use App\Models\AiAnalysis;
use Livewire\Livewire;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class RingkasanPlatformTest extends TestCase
{
    use SiapkanSekolah;

    public function test_super_admin_melihat_jumlah_lintas_sekolah(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B', 'dasar', 'nonaktif');
        $a->update(['features' => ['analisis_ai']]);
        $this->siswaDi($a, 'Siswa A1');
        $this->siswaDi($a, 'Siswa A2');
        $this->siswaDi($b, 'Siswa B1');
        $this->masukPanel($this->superAdmin(), 'admin');

        Livewire::test(RingkasanPlatform::class)->assertSee('Sekolah aktif')->assertSee('2 terdaftar')->assertSee('Siswa di semua sekolah')->assertSee('Sekolah dengan Analisis AI');
        $stat = (new \ReflectionMethod(RingkasanPlatform::class, 'getStats'))->invoke(new RingkasanPlatform);
        $nilai = collect($stat)->mapWithKeys(fn ($s) => [(string) $s->getLabel() => $s->getValue()])->all();

        $this->assertSame(1, $nilai['Sekolah aktif'], 'sekolah nonaktif tidak dihitung aktif');
        $this->assertSame(3, $nilai['Siswa di semua sekolah'], 'siswa dari semua sekolah dihitung walau penyaring sekolah aktif');
        $this->assertSame(1, $nilai['Sekolah dengan Analisis AI']);
        $this->assertSame(0, AiAnalysis::withoutGlobalScopes()->count());
    }

    public function test_widget_hanya_untuk_super_admin(): void
    {
        $a = $this->sekolah('SD A');
        $this->actingAs($this->adminDari($a));
        $this->assertFalse(RingkasanPlatform::canView());

        $this->actingAs($this->superAdmin());
        $this->assertTrue(RingkasanPlatform::canView());
    }
}
