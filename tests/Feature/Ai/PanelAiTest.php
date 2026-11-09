<?php

namespace Tests\Feature\Ai;

use App\Filament\Sekolah\Pages\PalmistryAnalysis;
use App\Filament\Sekolah\Pages\PhysiognomyAnalysis;
use App\Filament\Sekolah\Pages\RiwayatAnalisis;
use App\Filament\Sekolah\Pages\StatistikAnalisis;
use App\Models\AiAnalysis;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

class PanelAiTest extends AiTestCase
{
    private const HALAMAN = ['analisis-palmistry', 'analisis-physiognomy', 'riwayat-analisis-ai', 'statistik-analisis-ai'];

    private function masuk($akun): void
    {
        $this->masukPanel($akun, 'sekolah', $this->s);
    }

    private function kodeHalaman(string $slug, $akun): int
    {
        $this->flushSession();
        auth()->forgetGuards();

        return $this->actingAs($akun)->get("/sekolah/{$this->s->slug}/$slug")->getStatusCode();
    }

    private function selesai(string $jenis = 'palmistry', array $mentah = []): AiAnalysis
    {
        $this->fakeHttp(['*' => Http::response($this->responsGemini($jenis === 'palmistry' ? $this->palmMentah($mentah) : $this->wajahMentah($mentah)))]);
        $a = $this->buat(null, $jenis);
        $this->prosesAnalisis($a);

        return $a->fresh();
    }

    // ---------- akses ----------

    public function test_hanya_admin_sekolah_membuka_halaman_dan_hanya_bila_fitur_menyala(): void
    {
        foreach (self::HALAMAN as $slug) {
            $this->assertSame(200, $this->kodeHalaman($slug, $this->admin), "admin: $slug");
            $this->assertSame(403, $this->kodeHalaman($slug, $this->kepala), "kepala: $slug");
            $this->assertSame(403, $this->kodeHalaman($slug, $this->guru), "guru: $slug");
        }
        $this->s->update(['features' => []]);
        foreach (self::HALAMAN as $slug) {
            $this->assertSame(403, $this->kodeHalaman($slug, $this->admin->fresh()), "fitur mati: $slug");
        }
    }

    // ---------- formulir ----------

    private function isi($halaman, array $ubah = [])
    {
        $nilai = $ubah + ['subjek' => 'Budi Santoso', 'pemberi' => 'Budi Santoso', 'hubungan' => 'diri_sendiri', 'dewasa' => true, 'setuju' => true];
        foreach ($nilai as $k => $v) {
            $halaman->set($k, $v);
        }

        return $halaman;
    }

    public function test_formulir_membuat_analisis_menunggu_dan_menampilkan_statusnya(): void
    {
        $this->masuk($this->admin);

        $k = $this->isi(Livewire::test(PalmistryAnalysis::class))->set('foto', $this->foto())->call('kirim');

        $k->assertNotified('Foto diterima dan sedang dianalisis.')->assertSee('Foto sedang dianalisis')->assertSee('Budi Santoso')->assertSet('setuju', false)->assertSet('dewasa', false);
        $a = AiAnalysis::first();
        $this->assertSame(['palmistry', 'menunggu'], [$a->type, $a->status]);
        $this->assertSame($a->id, $k->instance()->analisis()->id);
        $k->assertSeeHtml('wire:poll.4s');
    }

    public function test_formulir_physiognomy_membuat_jenis_wajah(): void
    {
        $this->masuk($this->admin);

        $this->isi(Livewire::test(PhysiognomyAnalysis::class))->set('foto', $this->foto())->call('kirim');

        $this->assertSame('physiognomy', AiAnalysis::first()->type);
    }

    public function test_formulir_menolak_tanpa_foto_persetujuan_atau_pernyataan_dewasa(): void
    {
        $this->masuk($this->admin);
        $k = Livewire::test(PalmistryAnalysis::class);

        $this->isi($k)->call('kirim')->assertNotified('Pilih foto terlebih dahulu.');
        $this->isi($k, ['setuju' => false])->set('foto', $this->foto())->call('kirim')->assertNotified('Tidak bisa diproses');
        $this->isi($k, ['dewasa' => false])->set('foto', $this->foto())->call('kirim')->assertNotified('Tidak bisa diproses');
        $this->isi($k, ['subjek' => ''])->set('foto', $this->foto())->call('kirim')->assertNotified('Tidak bisa diproses');

        $this->assertSame(0, AiAnalysis::count());
        $this->assertSame([], Storage::disk(AiAnalysis::DISK)->allFiles());
    }

    public function test_berkas_bukan_foto_ditolak_oleh_formulir(): void
    {
        $this->masuk($this->admin);

        $this->isi(Livewire::test(PalmistryAnalysis::class))->set('foto', UploadedFile::fake()->createWithContent('x.jpg', 'bukan gambar'))->call('kirim')->assertNotified('Tidak bisa diproses');

        $this->assertSame(0, AiAnalysis::count());
    }

    public function test_peringatan_hiburan_dan_teks_persetujuan_selalu_tampil(): void
    {
        $this->masuk($this->admin);

        Livewire::test(PhysiognomyAnalysis::class)->assertSee('Hanya untuk hiburan')->assertSee('bukan penilaian ilmiah')->assertSee('Google Gemini')->assertSee('berusia 18 tahun ke atas');
    }

    // ---------- hasil ----------

    public function test_hasil_selesai_menampilkan_bagian_keterbacaan_json_dan_catatan(): void
    {
        $a = $this->selesai();
        $this->masuk($this->admin);

        $k = Livewire::test(PalmistryAnalysis::class)->call('pilih', $a->id);

        $k->assertSee('Garis kehidupan')->assertSee('Terlihat jelas')->assertSee('Terlihat samar')->assertSee('Tidak terlihat')
            ->assertSee('Telapak tangan kanan dengan tiga garis utama yang terlihat.')->assertSee('Keterbacaan gambar:')->assertSeeHtml('<strong>Baik</strong>')
            ->assertSee('Tidak terdeteksi: Garis nasib')->assertSee('Foto diambil dari atas.')->assertSee('Catatan keterbatasan analisis')
            ->assertSee('interpretasi tradisional atau budaya')->assertSeeHtml('data-testid="json-ai"')->assertSeeHtml('data-testid="foto-ai"')
            ->assertDontSeeHtml('wire:poll.4s');
        $k->assertSeeHtml('top:12%; left:8%; height:82%; width:82%');
        $k->assertSeeInOrder(['Garis kehidupan', 'Garis kepala', 'Garis hati', 'Garis nasib', 'Bentuk telapak tangan', 'Bentuk jari']); // urutan baku, bukan urutan kunci JSON MySQL
    }

    public function test_hasil_fisiognomi_menampilkan_bagian_wajah(): void
    {
        $a = $this->selesai('physiognomy');
        $this->masuk($this->admin);

        Livewire::test(PhysiognomyAnalysis::class)->call('pilih', $a->id)->assertSee('Bentuk wajah')->assertSee('Proporsi visual wajah')->assertSee('Mata berbentuk almond.');
    }

    public function test_analisis_gagal_menampilkan_alasan_aman(): void
    {
        $this->fakeHttp(['*' => Http::response([], 403)]);
        $a = $this->buat();
        $this->prosesAnalisis($a);
        $this->masuk($this->admin);

        Livewire::test(PalmistryAnalysis::class)->call('pilih', $a->id)->assertSee('Analisis gagal')->assertSee('Kunci API Gemini ditolak')->assertDontSee(self::KUNCI);
    }

    public function test_html_dari_hasil_tidak_dijalankan(): void
    {
        $a = $this->selesai('palmistry', ['garis_hati' => ['status' => 'terlihat_jelas', 'deskripsi_visual' => '<script>alert(1)</script>Garis hati', 'interpretasi_tradisi' => '<b>tebal</b>']]);
        $this->masuk($this->admin);

        Livewire::test(PalmistryAnalysis::class)->call('pilih', $a->id)->assertDontSeeHtml('<script>alert(1)</script>')->assertSee('Garis hati');
    }

    public function test_tampilan_melepas_html_walau_data_tersimpan_sudah_tercemar(): void
    {
        $a = $this->selesai();
        $hasil = $a->result;
        $hasil['bagian']['garis_hati']['deskripsi_visual'] = '<img src=x onerror=alert(1)>Garis hati';
        $hasil['bagian']['garis_hati']['interpretasi_tradisi'] = '<script>alert(2)</script>';
        $this->untuk($this->s, fn () => $a->forceFill(['result' => $hasil, 'summary' => '<script>alert(3)</script>ringkas'])->save());
        $this->masuk($this->admin);

        Livewire::test(PalmistryAnalysis::class)->call('pilih', $a->id)->assertDontSeeHtml('<img src=x')->assertDontSeeHtml('<script>alert')->assertSeeHtml('&lt;script&gt;alert(2)');
    }

    // ---------- hapus ----------

    public function test_hapus_foto_dan_hapus_analisis_dari_halaman(): void
    {
        $a = $this->selesai();
        $jalur = $a->image_path;
        $this->masuk($this->admin);
        $k = Livewire::test(PalmistryAnalysis::class)->call('pilih', $a->id);

        $k->call('hapusFoto', $a->id)->assertNotified('Foto dihapus. Hasil analisis tetap tersimpan.')->assertSee('Foto sudah dihapus.')->assertSee('Garis kehidupan');
        Storage::disk(AiAnalysis::DISK)->assertMissing($jalur);

        $k->call('hapusAnalisis', $a->id)->assertNotified('Foto dan hasil analisis dihapus.');
        $this->assertSame(0, AiAnalysis::count());
        $this->assertNull($k->instance()->analisis());
    }

    public function test_id_dari_browser_milik_sekolah_lain_tidak_tampil_dan_tidak_bisa_dihapus(): void
    {
        $b = $this->sekolah('SD B');
        $b->update(['features' => ['analisis_ai']]);
        $milikB = $this->buat($this->adminDari($b), 'palmistry', null, $this->persetujuan(['subject_name' => 'Rahasia Sekolah B']), $b);
        $this->masuk($this->admin);

        $k = Livewire::test(RiwayatAnalisis::class)->set('analisisId', $milikB->id);

        $this->assertNull($k->instance()->analisis());
        $k->assertDontSee('Rahasia Sekolah B')->call('hapusFoto', $milikB->id)->assertNotified('Analisis tidak ditemukan atau Anda tidak berhak menghapusnya.');
        $k->call('hapusAnalisis', $milikB->id)->assertNotified('Analisis tidak ditemukan atau Anda tidak berhak menghapusnya.');
        Storage::disk(AiAnalysis::DISK)->assertExists($milikB->image_path);
        $this->assertSame(1, AiAnalysis::withoutGlobalScopes()->count());
    }

    // ---------- riwayat dan statistik ----------

    public function test_riwayat_menampilkan_menyaring_dan_membuka_hasil(): void
    {
        $palm = $this->selesai('palmistry');
        $wajah = $this->selesai('physiognomy');
        $this->masuk($this->admin);

        $k = Livewire::test(RiwayatAnalisis::class)->assertSee('Palmistry')->assertSee('Physiognomy');
        $k->set('filterJenis', 'physiognomy')->assertSeeHtml('data-testid="riwayat-ai"');
        $this->assertSame([$wajah->id], $k->instance()->daftar()->pluck('id')->all());
        $k->set('filterJenis', '')->set('filterStatus', 'gagal');
        $this->assertSame([], $k->instance()->daftar()->pluck('id')->all());
        $k->assertSee('Belum ada analisis.');
        $k->set('filterStatus', '')->call('pilih', $palm->id)->assertSee('Garis kehidupan');
    }

    public function test_filter_tidak_sah_diabaikan(): void
    {
        $this->selesai();
        $this->masuk($this->admin);

        $k = Livewire::test(RiwayatAnalisis::class)->set('filterJenis', "x' OR 1=1 --")->set('filterStatus', 'aneh');

        $this->assertCount(1, $k->instance()->daftar());
    }

    public function test_statistik_menghitung_jenis_status_keberhasilan_dan_pemakaian_harian(): void
    {
        $this->selesai('palmistry');
        $this->selesai('physiognomy');
        $this->fakeHttp(['*' => Http::response([], 403)]);
        $this->prosesAnalisis($this->buat());
        $this->buat(); // menunggu
        $this->masuk($this->admin);

        $d = Livewire::test(StatistikAnalisis::class)->instance()->data();

        $this->assertSame(4, $d['total']);
        $this->assertSame(['palmistry' => 3, 'physiognomy' => 1], $d['jenis']);
        $this->assertSame(['menunggu' => 1, 'diproses' => 0, 'selesai' => 2, 'gagal' => 1], $d['status']);
        $this->assertSame(67, $d['berhasil']);
        $this->assertSame(2, $d['keterbacaan']['baik']);
        $this->assertSame(4, $d['hariIni']);
        $this->assertSame(4, $d['harian'][now()->format('Y-m-d')]);
        $this->assertCount(14, $d['harian']);
        Livewire::test(StatistikAnalisis::class)->assertSee('4 / 30')->assertSee('67%');
    }

    public function test_statistik_tidak_menghitung_sekolah_lain(): void
    {
        $b = $this->sekolah('SD B');
        $b->update(['features' => ['analisis_ai']]);
        $this->buat($this->adminDari($b), 'palmistry', null, null, $b);
        $this->masuk($this->admin);

        $this->assertSame(0, Livewire::test(StatistikAnalisis::class)->instance()->data()['total']);
    }
}
