<?php

namespace Tests\Feature\Rapor;

use App\Filament\Sekolah\Pages\Rapor;
use App\Modules\Rapor\CatatanWali;
use App\Support\Peran;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

class PanelRaporTest extends RaporTestCase
{
    private function masuk($akun): void
    {
        $this->masukPanel($akun, 'sekolah', $this->s);
    }

    /** Membuka halaman untuk kelas dan siswa tertentu, seperti pengguna yang memilih dari daftar. */
    private function buka($akun, $siswa = null, $kelas = null)
    {
        $this->masuk($akun);

        return Livewire::test(Rapor::class)->set('kelasId', ($kelas ?? $this->kelasA)->id)->call('pilih', ($siswa ?? $this->a1)->id);
    }

    // ---------- daftar dan akses ----------

    public function test_daftar_menampilkan_siswa_kelas_dan_statusnya(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        $this->masuk($this->akunKepala);

        Livewire::test(Rapor::class)->set('kelasId', $this->kelasA->id)
            ->assertSee('Siswa A1')->assertSee('Siswa A2')->assertDontSee('Siswa B1')->assertSee('Disahkan')->assertSee('Draf');
    }

    public function test_pilihan_kelas_menurut_peran(): void
    {
        $this->masuk($this->waliA);
        $this->assertSame([$this->kelasA->id => '4A'], Livewire::test(Rapor::class)->instance()->opsiKelas());

        $this->masuk($this->akunKepala);
        $this->assertSame([$this->kelasA->id => '4A', $this->kelasB->id => '4B'], Livewire::test(Rapor::class)->instance()->opsiKelas());
    }

    public function test_hak_akses_halaman_rapor(): void
    {
        $harapan = [Peran::ADMIN_SEKOLAH => 200, Peran::KEPALA_SEKOLAH => 200, Peran::WALI_KELAS => 200,
            Peran::GURU_MAPEL => 403, Peran::STAF_TU => 403, Peran::GURU_BK => 403, Peran::STAF_KEUANGAN => 403];
        foreach ($harapan as $peran => $kode) {
            $akun = $peran === Peran::ADMIN_SEKOLAH ? $this->adminDari($this->s) : $this->penggunaSekolah($this->s, $peran);
            $this->flushSession();
            auth()->forgetGuards();
            $this->assertSame($kode, $this->actingAs($akun)->get("/sekolah/{$this->s->slug}/rapor")->getStatusCode(), $peran);
        }
    }

    // ---------- pemeriksaan ----------

    public function test_pemeriksaan_menampilkan_masalah_dan_menonaktifkan_tombol_sahkan(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);

        $this->buka($this->akunKepala)->assertSee('Belum bisa disahkan')->assertSee('Nilai IPA belum lengkap.')
            ->assertSee('Nilai Bahasa Indonesia belum lengkap.')->assertSee('Sahkan rapor');
    }

    public function test_peringatan_tampil_tanpa_menghalangi(): void
    {
        $this->lengkapi();

        $k = $this->buka($this->akunKepala);
        $k->assertSee('Perlu diperhatikan')->assertSee('Catatan wali kelas belum diisi.')->assertDontSee('Belum bisa disahkan');
    }

    // ---------- wali kelas ----------

    public function test_wali_kelas_menyimpan_catatan_tetapi_tidak_melihat_tombol_sahkan_atau_buka_kunci(): void
    {
        $this->lengkapi();
        $k = $this->buka($this->waliA);

        $k->assertSet('catatan', '')->set('catatan', 'Anak yang rajin.')->call('simpanCatatan')->assertNotified('Catatan wali kelas tersimpan.');

        $this->assertSame('Anak yang rajin.', $this->rapor()->homeroom_note);
        $k->assertDontSee('Sahkan rapor');
    }

    public function test_wali_kelas_tidak_bisa_mengesahkan_walau_memanggil_aksinya_langsung(): void
    {
        $this->siapSahkan();

        $this->buka($this->waliA)->call('sahkan')->assertNotified('Anda tidak berhak mengesahkan rapor ini.');

        $this->assertSame(0, $this->jumlahVersi());
    }

    // ---------- pengesahan dan revisi lewat halaman ----------

    public function test_kepala_sekolah_mengesahkan_dan_halaman_menampilkan_kunci_serta_versi(): void
    {
        $this->siapSahkan();

        $k = $this->buka($this->akunKepala)->call('sahkan')->assertNotified('Rapor disahkan dan dikunci.');

        $this->assertSame(['disahkan', 1], [$this->rapor()->status, $this->rapor()->current_version]);
        $k->assertSee('Buka kunci untuk revisi')->assertSee('Versi 1')->assertSee('(berlaku)')->assertSee('Unduh PDF')->assertSee('terkunci');
    }

    public function test_pengesahan_yang_gagal_dijelaskan_dan_tidak_menyimpan(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);

        $this->buka($this->akunKepala)->call('sahkan')->assertNotified('Tidak bisa diproses');

        $this->assertSame(0, $this->jumlahVersi());
    }

    public function test_wali_kelas_tidak_melihat_buka_kunci_dan_tidak_bisa_memanggilnya(): void
    {
        $this->siapSahkan();
        $this->sahkan();

        $this->buka($this->waliA)->assertDontSee('Buka kunci untuk revisi')->set('alasan', 'Alasan yang cukup panjang')->call('bukaKunci')
            ->assertNotified('Anda tidak berhak membuka kunci rapor ini.');

        $this->assertSame('disahkan', $this->rapor()->status);
    }

    public function test_buka_kunci_wajib_beralasan_lalu_sahkan_ulang_menghasilkan_versi_2(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        $k = $this->buka($this->akunKepala);

        $k->set('alasan', 'singkat')->call('bukaKunci')->assertNotified('Tidak bisa diproses');
        $this->assertSame('disahkan', $this->rapor()->status);

        $k->set('alasan', 'Nilai IPA salah ketik')->call('bukaKunci')->assertNotified()->assertSee('Sahkan ulang (versi 2)')->assertSee('Rapor dalam revisi');
        $this->assertSame('revisi', $this->rapor()->status);

        $this->nilai($this->guruIpa, $this->kelasA, $this->ipa, [$this->a1->id => [$this->uh->id => 75]]);
        $k->call('sahkan')->assertNotified('Rapor disahkan dan dikunci.')->assertSee('Versi 2')->assertSee('(digantikan)')->assertSee('revisi: Nilai IPA salah ketik');
        $this->assertSame(2, $this->rapor()->current_version);
        $this->assertSame(2, $this->jumlahVersi());
    }

    // ---------- unduh PDF ----------

    public function test_unduh_pdf_per_versi(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();

        $this->buka($this->akunKepala)->call('unduh', $v->id)->assertFileDownloaded('rapor-Siswa-A1-2026-2027-ganjil-v1.pdf');
    }

    public function test_wali_kelas_mengunduh_rapor_kelasnya_tetapi_tidak_versi_siswa_lain_yang_tidak_dipilih(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();
        $this->lengkapi($this->a2);
        $v2 = $this->sahkan(null, $this->a2);

        $k = $this->buka($this->waliA);
        $k->call('unduh', $v->id)->assertFileDownloaded();
        $k->call('unduh', $v2->id)->assertNotified('Versi rapor tidak ditemukan.'); // milik A2, sedangkan yang dipilih A1
    }

    public function test_versi_sekolah_lain_tidak_bisa_diunduh(): void
    {
        $b = $this->sekolah('SD B');
        $this->masukPanel($this->adminDari($b), 'sekolah', $b);
        $this->siapSahkan();
        $v = $this->sahkan();

        Livewire::test(Rapor::class)->call('unduh', $v->id)->assertNotified('Versi rapor tidak ditemukan.');
    }

    // ---------- tidak mempercayai pilihan dari browser ----------

    public function test_wali_kelas_mengubah_kelas_dari_browser_tidak_melihat_dan_tidak_memproses_kelas_lain(): void
    {
        $this->isiPenuh($this->b1, $this->mtk, $this->kelasB, [90, 90, 90]);
        $this->masuk($this->waliA);

        $k = Livewire::test(Rapor::class)->set('kelasId', $this->kelasB->id)->call('pilih', $this->b1->id);

        $this->assertSame(0, $k->instance()->daftar()->count());
        $this->assertNull($k->instance()->detail());
        $k->assertDontSee('Siswa B1')->call('sahkan')->assertNotified('Pilih kelas dan siswa yang berhak Anda lihat.');
        $k->set('catatan', 'Siluman')->call('simpanCatatan')->assertNotified('Pilih kelas dan siswa yang berhak Anda lihat.');
        $this->assertSame(0, $this->jumlahVersi());
        $this->assertNull($this->rapor($this->b1));
    }

    public function test_siswa_dari_kelas_lain_tidak_bisa_dipilih_walau_kelas_sendiri_dipilih(): void
    {
        $this->masuk($this->waliA);

        $k = Livewire::test(Rapor::class)->set('kelasId', $this->kelasA->id)->call('pilih', $this->b1->id);

        $this->assertNull($k->instance()->detail());
        $k->assertDontSee('Siswa B1');
    }

    public function test_semester_sekolah_lain_tidak_dikenali(): void
    {
        $b = $this->sekolah('SD B');
        $semB = $this->semester($b, $this->tahunAjaran($b, '2026/2027', '2026-07-01', '2027-06-30'), 1, '2026-07-01', '2026-12-31');
        $this->masuk($this->akunKepala);

        $k = Livewire::test(Rapor::class)->set('semesterId', $semB->id);

        $this->assertSame([], $k->instance()->opsiKelas());
        $this->assertSame(0, $k->instance()->daftar()->count());
    }

    // ---------- integritas ----------

    public function test_halaman_memperingatkan_bila_salinan_diubah_di_luar_sistem(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();
        $k = $this->buka($this->akunKepala);
        $k->assertDontSee('Peringatan integritas');

        $diubah = $v->snapshot;
        $diubah['rata'] = '99.00';
        DB::table('report_card_versions')->where('id', $v->id)->update(['snapshot' => json_encode($diubah)]);

        $this->buka($this->akunKepala)->assertSee('Peringatan integritas');
    }

    public function test_catatan_terkunci_di_halaman_setelah_disahkan(): void
    {
        $this->siapSahkan();
        $this->sahkan();

        $k = $this->buka($this->waliA);
        $k->assertDontSee('Simpan catatan')->assertSee('Siswa rajin dan santun.');
        $k->set('catatan', 'Dipaksa')->call('simpanCatatan')->assertNotified('Tidak bisa diproses');
        $this->assertSame('Siswa rajin dan santun.', $this->rapor()->homeroom_note);
        $this->assertNotNull(CatatanWali::class);
    }
}
