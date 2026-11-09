<?php

namespace Tests\Feature\Ai;

use App\Modules\Ai\GeminiGagal;
use App\Modules\Ai\GeminiService;
use App\Modules\Ai\Skema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class GeminiServiceTest extends AiTestCase
{
    private function jalankan(string $jenis = 'palmistry'): array
    {
        return app(GeminiService::class)->analisis($jenis, 'bytes-gambar', 'image/jpeg');
    }

    private function gagal(callable $f): GeminiGagal
    {
        try {
            $f();
        } catch (GeminiGagal $e) {
            return $e;
        }
        $this->fail('seharusnya GeminiGagal');
    }

    // ---------- permintaan ----------

    public function test_permintaan_memakai_header_kunci_model_gambar_dan_skema(): void
    {
        Http::fake(['*' => Http::response($this->responsGemini($this->palmMentah()))]);

        $r = $this->jalankan();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $q) {
            $b = $q->data();

            return $q->hasHeader('x-goog-api-key', self::KUNCI)
                && ! str_contains($q->url(), self::KUNCI) && ! str_contains($q->url(), 'key=')
                && str_contains($q->url(), '/models/gemini-2.5-flash:generateContent')
                && $b['contents'][0]['parts'][1]['inline_data'] === ['mime_type' => 'image/jpeg', 'data' => base64_encode('bytes-gambar')]
                && $b['generationConfig']['responseMimeType'] === 'application/json'
                && array_key_exists('garis_kehidupan', $b['generationConfig']['responseSchema']['properties'])
                && str_contains($b['contents'][0]['parts'][0]['text'], 'Jangan mengarang');
        });
        $this->assertSame('gemini-2.5-flash', $r['model']);
    }

    public function test_prompt_fisiognomi_melarang_kesimpulan_terlarang(): void
    {
        $p = Skema::prompt('physiognomy');

        foreach (['kepribadian', 'kecerdasan', 'moralitas', 'suku', 'kesehatan', 'nasib'] as $kata) {
            $this->assertStringContainsString($kata, $p);
        }
    }

    // ---------- penyaringan hasil ----------

    public function test_hasil_palmistry_terstruktur_dengan_keterangan_wajib(): void
    {
        Http::fake(['*' => Http::response($this->responsGemini($this->palmMentah()))]);

        $h = $this->jalankan()['hasil'];

        $this->assertSame('palmistry', $h['jenis']);
        $this->assertSame('kanan', $h['sisi_tangan']);
        $this->assertSame('terlihat_jelas', $h['bagian']['garis_kehidupan']['status']);
        $this->assertSame('Garis kehidupan', $h['bagian']['garis_kehidupan']['label']);
        $this->assertSame('baik', $h['keterbacaan_gambar']['tingkat']);
        foreach (Skema::KETERANGAN as $k) {
            $this->assertContains($k, $h['catatan_keterbatasan'], 'keterangan tradisi/bukan ilmiah selalu ditambahkan server');
        }
        $this->assertContains('Foto diambil dari atas.', $h['catatan_keterbatasan']);
    }

    public function test_bagian_tidak_terlihat_dikosongkan_walau_model_mengarang(): void
    {
        $mentah = $this->palmMentah(['garis_nasib' => ['status' => 'tidak_terlihat', 'deskripsi_visual' => 'Garis nasib panjang sekali', 'interpretasi_tradisi' => 'Sukses besar']]);
        Http::fake(['*' => Http::response($this->responsGemini($mentah))]);

        $h = $this->jalankan()['hasil'];

        $this->assertSame('', $h['bagian']['garis_nasib']['deskripsi_visual']);
        $this->assertSame('', $h['bagian']['garis_nasib']['interpretasi_tradisi']);
        $this->assertContains('Garis nasib', $h['bagian_tidak_terdeteksi']);
        $this->assertNotContains('Garis nasib', $h['bagian_terdeteksi']);
    }

    public function test_daftar_terdeteksi_dihitung_server_bukan_dari_model(): void
    {
        $mentah = $this->palmMentah(['bagian_terdeteksi' => ['Garis nasib'], 'bagian_tidak_terdeteksi' => ['Garis hati']]);
        Http::fake(['*' => Http::response($this->responsGemini($mentah))]);

        $h = $this->jalankan()['hasil'];

        $this->assertEqualsCanonicalizing(['Garis kehidupan', 'Garis kepala', 'Garis hati', 'Bentuk telapak tangan', 'Bentuk jari'], $h['bagian_terdeteksi']);
        $this->assertSame(['Garis nasib'], $h['bagian_tidak_terdeteksi']);
    }

    public function test_nilai_tidak_sah_diperlakukan_aman_dan_kolom_asing_dibuang(): void
    {
        $mentah = $this->palmMentah(['keterbacaan_gambar' => ['tingkat' => 'sempurna', 'alasan' => '<b>x</b>'], 'sisi_tangan' => 'atas', 'kolom_asing' => 'rahasia',
            'garis_hati' => ['status' => 'sangat_jelas', 'deskripsi_visual' => 'ada', 'interpretasi_tradisi' => 'ada']]);
        Http::fake(['*' => Http::response($this->responsGemini($mentah))]);

        $r = $this->jalankan();

        $this->assertSame('buruk', $r['keterbacaan']);
        $this->assertSame('tidak_terlihat', $r['hasil']['sisi_tangan']);
        $this->assertSame('tidak_terlihat', $r['hasil']['bagian']['garis_hati']['status']);
        $this->assertSame('x', $r['hasil']['keterbacaan_gambar']['alasan'], 'tag HTML dibuang');
        $this->assertArrayNotHasKey('kolom_asing', $r['hasil']);
    }

    public function test_foto_bukan_telapak_tangan_tidak_menghasilkan_bagian_apa_pun(): void
    {
        $mentah = $this->palmMentah(['gambar_valid' => false]);
        Http::fake(['*' => Http::response($this->responsGemini($mentah))]);

        $r = $this->jalankan();

        $this->assertSame([], $r['hasil']['bagian_terdeteksi']);
        $this->assertSame(6, count($r['hasil']['bagian_tidak_terdeteksi']));
        $this->assertStringContainsString('tidak dikenali sebagai telapak tangan', $r['ringkasan']);
    }

    public function test_fisiognomi_menyaring_kesimpulan_terlarang(): void
    {
        $mentah = $this->wajahMentah([
            'mata' => ['status' => 'terlihat_jelas', 'deskripsi_visual' => 'Mata almond.', 'interpretasi_tradisi' => 'Orang dengan mata ini cerdas dan jujur.'],
            'hidung' => ['status' => 'terlihat_jelas', 'deskripsi_visual' => 'Hidung mancung khas suku tertentu.', 'interpretasi_tradisi' => 'Simbol keberuntungan.'],
            'ringkasan_indonesia' => 'Wajah ini menunjukkan kepribadian yang baik.',
        ]);
        Http::fake(['*' => Http::response($this->responsGemini($mentah))]);

        $r = $this->jalankan('physiognomy');
        $h = $r['hasil'];

        $isi = mb_strtolower(json_encode($h['bagian']));
        foreach (['cerdas', 'jujur', 'suku'] as $kata) {
            $this->assertStringNotContainsString($kata, $isi, "bagian hasil tidak boleh memuat: $kata");
        }
        $this->assertEqualsCanonicalizing(['mata', 'hidung', 'ringkasan'], $h['disaring']);
        $this->assertStringNotContainsString('kepribadian', $r['ringkasan'], 'ringkasan terlarang diganti ringkasan buatan server');
        $this->assertSame('Berbentuk oval dengan garis rahang halus.', $h['bagian']['bentuk_wajah']['deskripsi_visual'], 'bagian aman tidak terganggu');
        $this->assertSame(1, $h['jumlah_wajah']);
    }

    public function test_palmistry_menyaring_klaim_kematian_dan_penyakit(): void
    {
        $mentah = $this->palmMentah(['garis_kehidupan' => ['status' => 'terlihat_jelas', 'deskripsi_visual' => 'Pendek.', 'interpretasi_tradisi' => 'Pertanda kematian dini dan penyakit jantung.']]);
        Http::fake(['*' => Http::response($this->responsGemini($mentah))]);

        $h = $this->jalankan()['hasil'];

        $this->assertStringNotContainsString('kematian', json_encode($h['bagian']));
        $this->assertContains('garis_kehidupan', $h['disaring']);
    }

    public function test_fisiognomi_banyak_wajah_atau_tanpa_wajah_tidak_dianalisis(): void
    {
        Http::fake(['*' => Http::response($this->responsGemini($this->wajahMentah(['wajah_terdeteksi' => false, 'jumlah_wajah' => 0])))]);

        $r = $this->jalankan('physiognomy');

        $this->assertSame([], $r['hasil']['bagian_terdeteksi']);
        $this->assertStringContainsString('Tidak terdeteksi tepat satu wajah', $r['ringkasan']);
    }

    // ---------- kegagalan ----------

    public function test_kegagalan_sementara_bisa_diulang_dan_tetap_dipetakan_aman(): void
    {
        foreach ([429, 500, 503] as $kode) {
            Http::fake(['*' => Http::response(['error' => ['message' => 'rahasia '.self::KUNCI]], $kode)]);
            $e = $this->gagal(fn () => $this->jalankan());
            $this->assertTrue($e->bisaDiulang, "HTTP $kode");
            $this->assertStringNotContainsString(self::KUNCI, $e->getMessage());
            $this->assertStringNotContainsString('rahasia', $e->getMessage());
        }
    }

    public function test_kegagalan_tetap_tidak_diulang(): void
    {
        foreach ([400, 401, 403, 404] as $kode) {
            Http::fake(['*' => Http::response(['error' => ['message' => 'x']], $kode)]);
            $this->assertFalse($this->gagal(fn () => $this->jalankan())->bisaDiulang, "HTTP $kode");
        }
    }

    public function test_jaringan_putus_bisa_diulang(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertTrue($this->gagal(fn () => $this->jalankan())->bisaDiulang);
    }

    public function test_foto_diblokir_filter_keamanan(): void
    {
        Http::fake(['*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']])]);
        $this->assertFalse($this->gagal(fn () => $this->jalankan())->bisaDiulang);

        Http::fake(['*' => Http::response(['candidates' => [['finishReason' => 'SAFETY']]])]);
        $this->assertFalse($this->gagal(fn () => $this->jalankan())->bisaDiulang);
    }

    public function test_respons_bukan_json_bisa_diulang(): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'bukan json']]], 'finishReason' => 'STOP']]])]);

        $this->assertTrue($this->gagal(fn () => $this->jalankan())->bisaDiulang);
    }

    public function test_tanpa_kunci_tidak_ada_permintaan_keluar(): void
    {
        config(['services.gemini.key' => '']);
        Http::fake();

        $e = $this->gagal(fn () => $this->jalankan());

        $this->assertFalse($e->bisaDiulang);
        Http::assertNothingSent();
    }

    public function test_jenis_tidak_dikenal_ditolak_tanpa_permintaan(): void
    {
        Http::fake();

        $this->gagal(fn () => $this->jalankan('tarot'));

        Http::assertNothingSent();
    }
}
