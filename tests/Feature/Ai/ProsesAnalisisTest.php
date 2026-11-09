<?php

namespace Tests\Feature\Ai;

use App\Jobs\ProsesAnalisisAi;
use App\Models\AiAnalysis;
use App\Modules\Ai\GeminiGagal;
use App\Modules\Ai\GeminiService;
use Illuminate\Support\Facades\Http;

class ProsesAnalisisTest extends AiTestCase
{
    private function proses(AiAnalysis $a): void
    {
        (new ProsesAnalisisAi($a->id))->handle(app(GeminiService::class));
    }

    private function segar(AiAnalysis $a): ?AiAnalysis
    {
        return AiAnalysis::withoutGlobalScopes()->find($a->id);
    }

    public function test_analisis_berhasil_menyimpan_hasil_terstruktur_dan_teks_indonesia(): void
    {
        $this->fakeHttp(['*' => Http::response($this->responsGemini($this->palmMentah()))]);
        $a = $this->buat();

        $this->proses($a);

        $a = $this->segar($a);
        $this->assertSame('selesai', $a->status);
        $this->assertSame('baik', $a->readability);
        $this->assertSame('gemini-2.5-flash', $a->model);
        $this->assertNotNull($a->processed_at);
        $this->assertNull($a->error);
        $this->assertSame('Telapak tangan kanan dengan tiga garis utama yang terlihat.', $a->summary);
        $this->assertSame('terlihat_jelas', $a->result['bagian']['garis_kehidupan']['status']);
        $this->assertContains('Garis nasib', $a->result['bagian_tidak_terdeteksi']);
        Http::assertSentCount(1);
    }

    public function test_fisiognomi_diproses_dengan_jenisnya(): void
    {
        $this->fakeHttp(['*' => Http::response($this->responsGemini($this->wajahMentah()))]);
        $a = $this->buat(null, 'physiognomy');

        $this->proses($a);

        $this->assertSame('physiognomy', $this->segar($a)->result['jenis']);
        Http::assertSent(fn ($q) => str_contains($q->data()['contents'][0]['parts'][0]['text'], 'WAJAH'));
    }

    public function test_kegagalan_tetap_ditandai_gagal_dengan_pesan_aman_tanpa_melempar(): void
    {
        $this->fakeHttp(['*' => Http::response(['error' => ['message' => 'kunci '.self::KUNCI.' salah']], 403)]);
        $a = $this->buat();

        $this->proses($a);

        $a = $this->segar($a);
        $this->assertSame('gagal', $a->status);
        $this->assertStringContainsString('ditolak', $a->error);
        $this->assertStringNotContainsString(self::KUNCI, (string) $a->error);
        $this->assertNull($a->result);
    }

    public function test_kegagalan_sementara_dilempar_untuk_diulang_dan_gagal_setelah_percobaan_habis(): void
    {
        $this->fakeHttp(['*' => Http::response([], 429)]);
        $a = $this->buat();
        $job = new ProsesAnalisisAi($a->id);

        try {
            $job->handle(app(GeminiService::class));
            $this->fail('seharusnya dilempar agar antrean mengulang');
        } catch (GeminiGagal $e) {
            $this->assertTrue($e->bisaDiulang);
        }
        $this->assertSame('diproses', $this->segar($a)->status);

        $job->failed($e); // percobaan terakhir habis

        $this->assertSame('gagal', $this->segar($a)->status);
        $this->assertNotNull($this->segar($a)->error);
    }

    public function test_galat_tak_terduga_tidak_membocorkan_pesan_mentah(): void
    {
        $a = $this->buat();

        (new ProsesAnalisisAi($a->id))->failed(new \RuntimeException('SQLSTATE password=rahasia-db host=10.0.0.1'));

        $this->assertSame('Pemrosesan gagal. Coba analisis ulang.', $this->segar($a)->error);
    }

    public function test_dihapus_saat_menunggu_tidak_memanggil_gemini(): void
    {
        $this->fakeHttp();
        $a = $this->buat();
        $this->untuk($this->s, fn () => $a->delete());

        $this->proses($a);

        Http::assertNothingSent();
    }

    public function test_foto_dihapus_sebelum_diproses_ditandai_gagal_tanpa_memanggil_gemini(): void
    {
        $this->fakeHttp();
        $a = $this->buat();
        $this->untuk($this->s, fn () => $a->fresh()->hapusFoto());

        $this->proses($a);

        $this->assertSame('gagal', $this->segar($a)->status);
        $this->assertStringContainsString('Foto sudah dihapus', $this->segar($a)->error);
        Http::assertNothingSent();
    }

    public function test_analisis_selesai_tidak_diproses_ulang(): void
    {
        $this->fakeHttp(['*' => Http::response($this->responsGemini($this->palmMentah()))]);
        $a = $this->buat();
        $this->proses($a);

        $this->proses($a);

        Http::assertSentCount(1);
    }

    public function test_dihapus_saat_gemini_bekerja_tidak_dihidupkan_kembali(): void
    {
        $a = $this->buat();
        $this->fakeHttp(function () use ($a) {
            AiAnalysis::withoutGlobalScopes()->whereKey($a->id)->delete();

            return Http::response($this->responsGemini($this->palmMentah()));
        });

        $this->proses($a);

        $this->assertNull($this->segar($a));
    }

    public function test_tanpa_kunci_ditandai_gagal(): void
    {
        config(['services.gemini.key' => '']);
        $this->fakeHttp();
        $a = $this->buat();

        $this->proses($a);

        $this->assertSame('gagal', $this->segar($a)->status);
        $this->assertStringContainsString('GEMINI_API_KEY', $this->segar($a)->error);
        Http::assertNothingSent();
    }
}
