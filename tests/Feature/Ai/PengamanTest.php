<?php

namespace Tests\Feature\Ai;

use App\Models\AiAnalysis;
use App\Modules\Ai\GeminiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Pengaman tambahan Tahap B: pernyataan dewasa, batas harian, penghapusan otomatis, dan area deteksi. */
class PengamanTest extends AiTestCase
{
    // ---------- dewasa ----------

    public function test_pernyataan_dewasa_wajib(): void
    {
        foreach ([false, null, 'ya', 1] as $nilai) {
            try {
                $this->buat(null, 'palmistry', null, $this->persetujuan(['dewasa' => $nilai]));
                $this->fail('seharusnya ditolak');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('dewasa', $e->errors());
            }
        }
        $this->assertSame(0, AiAnalysis::withoutGlobalScopes()->count());
        $this->assertSame([], Storage::disk(AiAnalysis::DISK)->allFiles());
    }

    // ---------- batas harian ----------

    public function test_batas_harian_per_sekolah(): void
    {
        config(['ai.batas_harian' => 2]);
        $this->buat();
        $this->buat();

        try {
            $this->buat();
            $this->fail('seharusnya ditolak oleh batas harian');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Batas analisis harian', $e->errors()['foto'][0]);
        }
        $this->assertSame(2, AiAnalysis::withoutGlobalScopes()->count());
        $this->assertCount(2, Storage::disk(AiAnalysis::DISK)->allFiles(), 'penolakan tidak menulis foto ketiga');

        $b = $this->sekolah('SD B');
        $b->update(['features' => ['analisis_ai']]);
        $this->assertNotNull($this->buat($this->adminDari($b), 'palmistry', null, null, $b), 'sekolah lain punya jatah sendiri');
    }

    public function test_batas_harian_dihitung_per_hari(): void
    {
        config(['ai.batas_harian' => 1]);
        $a = $this->buat();
        AiAnalysis::withoutGlobalScopes()->whereKey($a->id)->update(['created_at' => now()->subDay()]);

        $this->assertNotNull($this->buat(), 'analisis kemarin tidak mengurangi jatah hari ini');
    }

    // ---------- hapus otomatis ----------

    public function test_foto_dihapus_otomatis_bila_diatur_dan_hasil_tetap(): void
    {
        config(['ai.hapus_foto_otomatis' => true]);
        $this->fakeHttp(['*' => Http::response($this->responsGemini($this->palmMentah()))]);
        $a = $this->buat();
        $jalur = $a->image_path;

        $this->prosesAnalisis($a);

        $baru = AiAnalysis::withoutGlobalScopes()->find($a->id);
        $this->assertSame('selesai', $baru->status);
        $this->assertNull($baru->image_path);
        Storage::disk(AiAnalysis::DISK)->assertMissing($jalur);
        $this->assertNotNull($baru->result);
    }

    public function test_foto_tetap_ada_bila_hapus_otomatis_mati(): void
    {
        config(['ai.hapus_foto_otomatis' => false]);
        $this->fakeHttp(['*' => Http::response($this->responsGemini($this->palmMentah()))]);
        $a = $this->buat();

        $this->prosesAnalisis($a);

        Storage::disk(AiAnalysis::DISK)->assertExists($a->image_path);
    }

    public function test_foto_tetap_ada_bila_analisis_gagal_agar_bisa_dihapus_manual(): void
    {
        config(['ai.hapus_foto_otomatis' => true]);
        $this->fakeHttp(['*' => Http::response([], 403)]);
        $a = $this->buat();

        $this->prosesAnalisis($a);

        $this->assertSame('gagal', AiAnalysis::withoutGlobalScopes()->find($a->id)->status);
        $this->assertNotNull(AiAnalysis::withoutGlobalScopes()->find($a->id)->image_path);
    }

    // ---------- area deteksi ----------

    private function area(array $ubah): ?array
    {
        $this->fakeHttp(['*' => Http::response($this->responsGemini($this->palmMentah($ubah)))]);

        return app(GeminiService::class)->analisis('palmistry', 'x', 'image/jpeg')['hasil']['area'];
    }

    public function test_area_sah_disimpan_dan_yang_tidak_sah_dibuang(): void
    {
        $this->assertSame([120, 80, 940, 900], $this->area([]));
        $this->assertNull($this->area(['area' => [900, 80, 120, 900]]), 'ymin lebih besar dari ymax');
        $this->assertNull($this->area(['area' => [0, 0, 1001, 900]]), 'di luar skala 0-1000');
        $this->assertNull($this->area(['area' => [-5, 0, 100, 100]]), 'negatif');
        $this->assertNull($this->area(['area' => [1, 2, 3]]), 'bukan empat angka');
        $this->assertNull($this->area(['area' => ['a', 'b', 'c', 'd']]), 'bukan angka');
        $this->assertNull($this->area(['area' => []]));
        $this->assertNull($this->area(['gambar_valid' => false]), 'foto bukan telapak tangan: tanpa kotak');
    }

    public function test_prompt_dan_skema_meminta_area(): void
    {
        $this->fakeHttp(['*' => Http::response($this->responsGemini($this->palmMentah()))]);
        app(GeminiService::class)->analisis('palmistry', 'x', 'image/jpeg');

        Http::assertSent(fn ($q) => $q->data()['generationConfig']['responseSchema']['properties']['area']['type'] === 'ARRAY'
            && str_contains($q->data()['contents'][0]['parts'][0]['text'], 'ymin'));
    }
}
