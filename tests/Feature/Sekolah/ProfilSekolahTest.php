<?php

namespace Tests\Feature\Sekolah;

use App\Filament\Sekolah\Pages\ProfilSekolah;
use App\Support\Peran;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class ProfilSekolahTest extends TestCase
{
    use SiapkanSekolah;

    private function dataSah(array $ubah = []): array
    {
        return array_replace_recursive([
            'name' => 'SD Contoh', 'levels' => ['sd'], 'city' => 'Bandung', 'address' => 'Jl. Contoh 1',
            'phone' => '022123456', 'contact_email' => 'info@sdcontoh.test',
            'settings' => ['kurikulum' => 'Kurikulum Merdeka', 'kkm' => 70, 'peringkat_aktif' => false],
        ], $ubah);
    }

    public function test_sekolah_baru_memakai_pengaturan_bawaan(): void
    {
        $s = $this->sekolah('SD Baru');

        $this->assertSame('Kurikulum Merdeka', $s->setting('kurikulum'));
        $this->assertSame(70, $s->setting('kkm'));
        $this->assertFalse($s->setting('peringkat_aktif')); // peringkat mati kecuali dipilih sekolah
        $this->assertSame(['sd'], $s->levels);
    }

    public function test_admin_menyimpan_profil_dan_pengaturan(): void
    {
        $s = $this->sekolah('SD A');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ProfilSekolah::class)
            ->fillForm($this->dataSah([
                'name' => 'SD A Mandiri', 'levels' => ['sd', 'smp'],
                'settings' => ['kurikulum' => 'Kurikulum 2013', 'kkm' => 75, 'peringkat_aktif' => true],
            ]))
            ->call('save')->assertHasNoFormErrors();

        $s->refresh();
        $this->assertSame('SD A Mandiri', $s->name);
        $this->assertEqualsCanonicalizing(['sd', 'smp'], $s->levels);
        $this->assertSame('Kurikulum 2013', $s->setting('kurikulum'));
        $this->assertSame(75, $s->setting('kkm'));
        $this->assertTrue($s->setting('peringkat_aktif'));
    }

    public function test_slug_tidak_berubah_walau_nama_diganti(): void
    {
        $s = $this->sekolah('SD A');
        $slug = $s->slug;
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ProfilSekolah::class)->fillForm($this->dataSah(['name' => 'Nama Baru']))->call('save')->assertHasNoFormErrors();

        $this->assertSame($slug, $s->fresh()->slug); // alamat panel dan tautan tersimpan tetap berlaku
    }

    public function test_isian_tidak_valid_ditolak(): void
    {
        $s = $this->sekolah('SD A');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ProfilSekolah::class)
            ->fillForm($this->dataSah(['settings' => ['kkm' => 101]]))->call('save')->assertHasFormErrors(['settings.kkm']);
        Livewire::test(ProfilSekolah::class)
            ->fillForm($this->dataSah(['settings' => ['kkm' => -1]]))->call('save')->assertHasFormErrors(['settings.kkm']);
        Livewire::test(ProfilSekolah::class)
            ->fillForm($this->dataSah(['contact_email' => 'bukan-email']))->call('save')->assertHasFormErrors(['contact_email']);
        Livewire::test(ProfilSekolah::class)
            ->fillForm(array_merge($this->dataSah(), ['levels' => [], 'name' => '']))->call('save')->assertHasFormErrors(['levels', 'name']);
    }

    public function test_admin_sekolah_tidak_bisa_mengubah_paket_fitur_atau_status_lewat_profil(): void
    {
        $s = $this->sekolah('SD A', 'dasar');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ProfilSekolah::class)
            ->fillForm($this->dataSah())
            ->set('data.plan', 'premium')->set('data.features', ['premium', 'ujian'])->set('data.status', 'aktif')->set('data.school_id', 999)
            ->call('save');

        $s->refresh();
        $this->assertSame('dasar', $s->plan);
        $this->assertSame([], $s->features);
    }

    public function test_guru_tidak_bisa_membuka_profil_sekolah(): void
    {
        $s = $this->sekolah('SD A');
        $guru = $this->penggunaSekolah($s, Peran::GURU_MAPEL);

        $this->actingAs($guru)->get("/sekolah/{$s->slug}/profile")->assertNotFound();
    }

    public function test_profil_sekolah_lain_tidak_bisa_dibuka(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');

        $this->actingAs($this->adminDari($a))->get("/sekolah/{$b->slug}/profile")->assertNotFound();
    }

    public function test_admin_membuka_profil_sekolahnya_sendiri(): void
    {
        $a = $this->sekolah('SD A');

        $this->actingAs($this->adminDari($a))->get("/sekolah/{$a->slug}/profile")->assertOk();
    }

    public function test_perubahan_profil_tercatat_di_audit(): void
    {
        $s = $this->sekolah('SD A');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ProfilSekolah::class)->fillForm($this->dataSah(['name' => 'Nama Diubah']))->call('save');

        $this->assertTrue(Activity::where('log_name', 'sekolah')->where('subject_id', $s->id)->where('event', 'updated')->exists());
    }
}
