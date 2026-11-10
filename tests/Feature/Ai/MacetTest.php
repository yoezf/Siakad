<?php

namespace Tests\Feature\Ai;

use App\Filament\Sekolah\Pages\AnalisisKustom;
use App\Filament\Sekolah\Pages\RiwayatAnalisis;
use App\Jobs\ProsesAnalisisAi;
use App\Models\AiAnalysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** Analisis tidak boleh menggantung "menunggu" tanpa akhir (kejadian nyata: pekerja berkode lama menolak data lalu gagal menandai gagal). */
class MacetTest extends AiTestCase
{
    private function segar(AiAnalysis $a): AiAnalysis
    {
        return AiAnalysis::withoutGlobalScopes()->find($a->id);
    }

    /** Meniru kejadian nyata: baris yang tidak lolos aturan model (jenis tidak dikenal oleh kode yang sedang berjalan). */
    private function rusak(AiAnalysis $a): void
    {
        DB::table('ai_analyses')->where('id', $a->id)->update(['type' => 'jenis_lain']);
    }

    // ---------- pekerja ----------

    public function test_galat_tak_terduga_langsung_ditandai_gagal_tanpa_menunggu_percobaan_ulang(): void
    {
        $this->fakeHttp();
        $a = $this->buat();
        $this->rusak($a);

        $this->prosesAnalisis($a); // tidak melempar: bila melempar, antrean mengulang 3 kali dan layar menunggu lebih dari 2 menit

        $baru = $this->segar($a);
        $this->assertSame('gagal', $baru->status);
        $this->assertSame('Pemrosesan gagal. Coba lagi, atau unggah ulang fotonya.', $baru->error);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_penandaan_gagal_berhasil_walau_aturan_model_menolak_barisnya(): void
    {
        $a = $this->buat();
        $this->rusak($a);

        (new ProsesAnalisisAi($a->id))->failed(new \RuntimeException('apa pun'));

        $this->assertSame('gagal', $this->segar($a)->status, 'dulu: ValidationException dari failed() membuat status tetap menunggu selamanya');
    }

    public function test_pesan_galat_mentah_tidak_disimpan(): void
    {
        $a = $this->buat();
        $this->rusak($a);

        $this->prosesAnalisis($a);

        $this->assertStringNotContainsString('jenis_lain', (string) $this->segar($a)->error);
    }

    // ---------- tombol coba lagi ----------

    private function gagal(): AiAnalysis
    {
        $this->fakeHttp(['*' => \Illuminate\Support\Facades\Http::response([], 403)]);
        $a = $this->buat();
        $this->prosesAnalisis($a);
        $this->assertSame('gagal', $this->segar($a)->status);

        return $a;
    }

    public function test_coba_lagi_mengantrekan_ulang_memakai_foto_yang_sama(): void
    {
        $a = $this->gagal();
        $this->masukPanel($this->admin, 'sekolah', $this->s);
        Queue::fake();

        Livewire::test(AnalisisKustom::class)->call('pilih', $a->id)->assertSeeHtml('data-testid="ulangi-ai"')->call('ulangi', $a->id)->assertNotified('Analisis dikirim ulang ke antrean.');

        $baru = $this->segar($a);
        $this->assertSame(['menunggu', null], [$baru->status, $baru->error]);
        Queue::assertPushed(ProsesAnalisisAi::class, fn ($j) => $j->analisisId === $a->id);
    }

    public function test_coba_lagi_lalu_berhasil(): void
    {
        $a = $this->gagal();
        $this->masukPanel($this->admin, 'sekolah', $this->s);
        Livewire::test(AnalisisKustom::class)->call('ulangi', $a->id);
        $this->fakeHttp(['*' => \Illuminate\Support\Facades\Http::response($this->responsGemini($this->palmMentah()))]);

        $this->prosesAnalisis($a);

        $this->assertSame('selesai', $this->segar($a)->status);
    }

    public function test_coba_lagi_ditolak_bila_foto_sudah_dihapus_atau_sudah_selesai(): void
    {
        $a = $this->gagal();
        $this->untuk($this->s, fn () => $a->fresh()->hapusFoto());
        $this->masukPanel($this->admin, 'sekolah', $this->s);
        Queue::fake();

        $k = Livewire::test(AnalisisKustom::class)->call('ulangi', $a->id)->assertNotified('Foto sudah dihapus, jadi tidak bisa diulang. Unggah foto lagi.');
        Queue::assertNothingPushed();

        $this->fakeHttp(['*' => \Illuminate\Support\Facades\Http::response($this->responsGemini($this->palmMentah()))]);
        $selesai = $this->buat();
        $this->prosesAnalisis($selesai);
        Queue::fake(); // membuat analisis pembanding tadi ikut mengantrekan job; hitung ulang dari nol
        $k->call('ulangi', $selesai->id)->assertNotified('Analisis ini sudah selesai.');
        Queue::assertNothingPushed();
    }

    public function test_coba_lagi_tidak_berlaku_untuk_analisis_sekolah_lain(): void
    {
        $b = $this->sekolah('SD B');
        $b->update(['features' => ['analisis_ai']]);
        $milikB = $this->buat($this->adminDari($b), 'palmistry', null, null, $b);
        AiAnalysis::withoutGlobalScopes()->whereKey($milikB->id)->update(['status' => 'gagal']);
        $this->masukPanel($this->admin, 'sekolah', $this->s);
        Queue::fake();

        Livewire::test(RiwayatAnalisis::class)->call('ulangi', $milikB->id)->assertNotified('Analisis tidak ditemukan atau Anda tidak berhak memprosesnya.');

        Queue::assertNothingPushed();
        $this->assertSame('gagal', $this->segar($milikB)->status);
    }

    // ---------- peringatan macet ----------

    public function test_peringatan_macet_muncul_setelah_90_detik_dan_tidak_sebelumnya(): void
    {
        $a = $this->buat();
        $this->masukPanel($this->admin, 'sekolah', $this->s);

        $k = Livewire::test(AnalisisKustom::class)->call('pilih', $a->id)->assertSeeHtml('data-testid="proses-ai"')->assertDontSeeHtml('data-testid="macet-ai"');

        AiAnalysis::withoutGlobalScopes()->whereKey($a->id)->update(['updated_at' => now()->subSeconds(200)]);

        $k->call('pilih', $a->id)->assertSeeHtml('data-testid="macet-ai"')->assertSee('tidak normal')->assertSee('terputus')->assertSee('Coba lagi')->assertDontSeeHtml('data-testid="proses-ai"');
    }

    public function test_hitungan_detik_dihitung_sejak_perubahan_terakhir_bukan_sejak_dibuat(): void
    {
        $a = $this->buat();
        AiAnalysis::withoutGlobalScopes()->whereKey($a->id)->update(['created_at' => now()->subSeconds(500), 'updated_at' => now()->subSeconds(5)]);
        $this->masukPanel($this->admin, 'sekolah', $this->s);

        Livewire::test(AnalisisKustom::class)->call('pilih', $a->id)->assertSeeHtml('data-testid="proses-ai"')->assertDontSeeHtml('data-testid="macet-ai"');
    }

    public function test_foto_tetap_ada_setelah_gagal_agar_bisa_diulang(): void
    {
        $a = $this->gagal();

        Storage::disk(AiAnalysis::DISK)->assertExists($this->segar($a)->image_path);
    }
}
