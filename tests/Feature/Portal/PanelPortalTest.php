<?php

namespace Tests\Feature\Portal;

use App\Filament\Portal\Pages\Beranda;
use App\Support\Peran;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

class PanelPortalTest extends PortalTestCase
{
    private function buka($akun, ?int $anakId = null)
    {
        $this->masukPortal($akun);
        $k = Livewire::test(Beranda::class);

        return $anakId ? $k->set('anakId', $anakId) : $k;
    }

    // ---------- akses panel ----------

    private function kodeHalaman(string $url, $akun): int
    {
        $this->flushSession();
        auth()->forgetGuards();

        return $this->actingAs($akun)->get($url)->getStatusCode();
    }

    public function test_orang_tua_dan_siswa_masuk_portal_tetapi_tidak_panel_sekolah_atau_admin(): void
    {
        foreach ([$this->ortu1, $this->akunSiswaA1] as $akun) {
            $this->assertSame(200, $this->kodeHalaman("/portal/{$this->s->slug}", $akun));
            $this->assertSame(403, $this->kodeHalaman("/sekolah/{$this->s->slug}", $akun));
            $this->assertSame(403, $this->kodeHalaman('/admin', $akun));
        }
    }

    public function test_staf_sekolah_tidak_masuk_portal(): void
    {
        foreach ([$this->akunAdmin, $this->akunKepala, $this->waliA, $this->guruMtk] as $akun) {
            $this->assertSame(403, $this->kodeHalaman("/portal/{$this->s->slug}", $akun), $akun->name);
        }
    }

    public function test_setiap_lapisan_penjaga_portal_menolak_staf_dengan_sendirinya(): void
    {
        $panel = \Filament\Facades\Filament::getPanel('portal');
        foreach ([$this->akunAdmin, $this->akunKepala, $this->waliA, $this->guruMtk, $this->akunTu] as $akun) {
            $this->assertFalse($akun->canAccessPanel($panel), "panel: {$akun->name}");
            $this->actingAs($akun);
            $this->assertFalse(Beranda::canAccess(), "halaman: {$akun->name}");
        }
        foreach ([$this->ortu1, $this->akunSiswaA1] as $akun) {
            $this->assertTrue($akun->canAccessPanel($panel));
            $this->actingAs($akun);
            $this->assertTrue(Beranda::canAccess());
        }
    }

    public function test_orang_tua_tidak_bisa_membuka_portal_sekolah_lain(): void
    {
        $b = $this->sekolah('SD B');

        $this->assertContains($this->kodeHalaman("/portal/{$b->slug}", $this->ortu1), [403, 404]);
    }

    public function test_portal_ditolak_untuk_sekolah_yang_dinonaktifkan(): void
    {
        $this->s->forceFill(['status' => 'nonaktif'])->save();

        $this->assertContains($this->kodeHalaman("/portal/{$this->s->slug}", $this->ortu1), [403, 404]);
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->assertContains($this->get("/portal/{$this->s->slug}")->getStatusCode(), [302]);
    }

    // ---------- anak ----------

    public function test_akun_tanpa_tautan_diberi_penjelasan(): void
    {
        $akun = $this->penggunaSekolah($this->s, Peran::ORANG_TUA, 'Tanpa Anak');

        $this->buka($akun)->assertSee('belum terhubung ke data siswa');
    }

    public function test_orang_tua_dengan_dua_anak_bisa_berpindah_anak(): void
    {
        $this->isiPenuh($this->b1, $this->mtk, $this->kelasB, [90, 90, 90]);

        $k = $this->buka($this->ortu2)->assertSee('Siswa A2')->assertSee('Siswa B1');
        $this->assertSame($this->a2->id, $k->instance()->anak()->id);
        $k->set('anakId', $this->b1->id)->call('pilihTab', 'nilai')->assertSee('Matematika')->assertSee('90');
        $this->assertSame($this->b1->id, $k->instance()->anak()->id);
    }

    public function test_anak_orang_lain_dari_browser_diabaikan(): void
    {
        $this->siapSahkan();
        $this->sahkan();

        $k = $this->buka($this->ortu2, $this->a1->id); // A1 bukan anak Ortu 2
        $this->assertSame($this->a2->id, $k->instance()->anak()->id);
        $k->assertDontSee('Siswa A1')->assertSet('anakId', $this->a1->id);
        $this->assertNull($k->instance()->rapor());
        $k->call('unduh')->assertNotified('Rapor belum tersedia untuk diunduh.');
    }

    public function test_siswa_melihat_dirinya_tanpa_pilihan_anak(): void
    {
        $this->buka($this->akunSiswaA1)->assertSee('Siswa A1')->assertDontSee('Siswa A2');
    }

    // ---------- rapor ----------

    public function test_rapor_belum_disahkan_tidak_tampil(): void
    {
        $this->siapSahkan();

        $this->buka($this->ortu1)->assertSee('Rapor semester ini belum disahkan.')->assertDontSee('76,33');
    }

    public function test_rapor_disahkan_tampil_dan_bisa_diunduh(): void
    {
        $this->siapSahkan();
        $this->sahkan();

        $k = $this->buka($this->ortu1)->assertSee('Matematika')->assertSee('76,33')->assertSee('Siswa rajin dan santun.')->assertSee('Versi')->assertSee('Unduh PDF');
        $k->call('unduh')->assertFileDownloaded('rapor-Siswa-A1-2026-2027-ganjil-v1.pdf');
    }

    public function test_rapor_yang_sedang_direvisi_disembunyikan_sampai_disahkan_ulang(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        $this->bukaKunci('Salah ketik nilai IPA siswa');

        $this->buka($this->ortu1)->assertSee('Rapor semester ini belum disahkan.')->assertDontSee('Unduh PDF');

        $this->sahkan();
        $k = $this->buka($this->ortu1);
        $k->assertSee('Unduh PDF');
        $this->assertSame(2, $k->instance()->rapor()['versi']->version);
    }

    public function test_rapor_yang_salinannya_diubah_di_luar_sistem_tidak_bisa_diunduh(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();
        $diubah = $v->snapshot;
        $diubah['rata'] = '99.00';
        DB::table('report_card_versions')->where('id', $v->id)->update(['snapshot' => json_encode($diubah)]);

        $this->buka($this->ortu1)->assertSee('sedang diperiksa pihak sekolah')->call('unduh')->assertNotified('Rapor belum tersedia untuk diunduh.');
    }

    // ---------- nilai, kehadiran, jadwal ----------

    public function test_nilai_tampil_per_komponen_dan_akhir_kosong_bila_belum_lengkap(): void
    {
        $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 80, $this->uts->id => 90]]);

        $k = $this->buka($this->ortu1)->call('pilihTab', 'nilai');
        $k->assertSee('Ulangan Harian')->assertSee('80')->assertSee('Nilai akhir baru muncul');
        $n = $k->instance()->nilai();
        $mtk = collect($n['hasil']['mapel'])->first(fn ($m) => $m['subject']->id === $this->mtk->id);
        $this->assertNull($mtk['akhir']);
    }

    public function test_nilai_anak_lain_tidak_bocor(): void
    {
        $this->isiPenuh($this->a2, $this->mtk, $this->kelasA, [11, 22, 33]);
        $this->lengkapi();

        $this->buka($this->ortu1)->call('pilihTab', 'nilai')->assertSee('90')->assertDontSee('22');
    }

    public function test_kehadiran_tampil_rekap_dan_harian(): void
    {
        $this->kehadiranA1();

        $this->buka($this->ortu1)->call('pilihTab', 'kehadiran')->assertSee('Hadir 1, terlambat 1, sakit 1')->assertSee('67%')->assertSee('09-11-2026: Hadir')->assertSee('10-11-2026: Terlambat');
    }

    public function test_kehadiran_kosong_diberi_penjelasan(): void
    {
        $this->buka($this->ortu1)->call('pilihTab', 'kehadiran')->assertSee('Belum ada kehadiran tercatat');
    }

    public function test_jadwal_menampilkan_pelajaran_kelas_anak_saja(): void
    {
        $jam = $this->jam($this->s, 1);
        $this->jadwal($this->s, $this->kelasA, $this->mtk, $jam, 1);
        $this->jadwal($this->s, $this->kelasB, $this->mtk, $jam, 2);

        $k = $this->buka($this->ortu1)->call('pilihTab', 'jadwal');
        $k->assertSee('Kelas 4A')->assertSee('Matematika')->assertSee('Pak MTK');
        $this->assertCount(1, $k->instance()->jadwal()['sel']);
    }

    public function test_jadwal_kosong_diberi_penjelasan(): void
    {
        $this->buka($this->ortu1)->call('pilihTab', 'jadwal')->assertSee('Jadwal belum tersedia.');
    }

    public function test_tab_tidak_dikenal_kembali_ke_rapor(): void
    {
        $this->buka($this->ortu1)->call('pilihTab', 'rahasia')->assertSet('tab', 'rapor');
    }

    public function test_semester_sekolah_lain_tidak_dikenali(): void
    {
        $b = $this->sekolah('SD B');
        $semB = $this->semester($b, $this->tahunAjaran($b, '2026/2027', '2026-07-01', '2027-06-30'), 1, '2026-07-01', '2026-12-31');

        $k = $this->buka($this->ortu1)->set('semesterId', $semB->id);

        $this->assertNull($k->instance()->semester());
        $this->assertNull($k->instance()->rapor());
    }
}
