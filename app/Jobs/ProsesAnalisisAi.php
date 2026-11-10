<?php

namespace App\Jobs;

use App\Models\AiAnalysis;
use App\Modules\Ai\GeminiGagal;
use App\Modules\Ai\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Mengirim foto ke Gemini di latar belakang. Berjalan tanpa sekolah aktif (antrean tidak punya sesi), jadi memuat data tanpa penyaring
 * sekolah lewat id yang diberikan layanan BuatAnalisis. Kegagalan sementara diulang; kegagalan tetap ditandai "gagal" dengan pesan aman.
 */
class ProsesAnalisisAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $analisisId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(GeminiService $gemini): void
    {
        $a = AiAnalysis::withoutGlobalScopes()->find($this->analisisId);
        if (! $a || ! in_array($a->status, ['menunggu', 'diproses'], true)) {
            return; // dihapus saat menunggu, atau sudah selesai
        }
        if ($a->image_path === null || ! Storage::disk(AiAnalysis::DISK)->exists($a->image_path)) {
            $this->tandaiGagal($a, 'Foto sudah dihapus atau tidak ditemukan.');

            return;
        }
        try {
            $a->forceFill(['status' => 'diproses', 'error' => null])->save();
            $r = $gemini->analisis($a->type, Storage::disk(AiAnalysis::DISK)->get($a->image_path), 'image/jpeg', $a->prompt_text);

            // Analisis bisa dihapus saat Gemini bekerja; jangan menghidupkan kembali.
            $segar = AiAnalysis::withoutGlobalScopes()->find($a->id);
            $segar?->forceFill(['status' => 'selesai', 'result' => $r['hasil'], 'summary' => $r['ringkasan'], 'readability' => $r['keterbacaan'],
                'model' => $r['model'], 'error' => null, 'processed_at' => now()])->save();
            if ($segar && config('ai.hapus_foto_otomatis')) {
                $segar->hapusFoto(); // minimisasi data: hasil teks tetap, foto tidak
            }
        } catch (GeminiGagal $e) {
            if ($e->bisaDiulang && $this->attempts() < $this->tries) {
                throw $e; // antrean mengulang sesuai backoff
            }
            $this->tandaiGagal($a, $e->getMessage());
        } catch (Throwable $e) {
            // Galat tak terduga tidak membaik dengan diulang: tandai gagal sekarang agar layar tidak menunggu tanpa akhir.
            report($e);
            $this->tandaiGagal($a, 'Pemrosesan gagal. Coba lagi, atau unggah ulang fotonya.');
        }
    }

    public function failed(Throwable $e): void
    {
        $a = AiAnalysis::withoutGlobalScopes()->find($this->analisisId);
        if ($a && $a->status !== 'selesai') {
            $this->tandaiGagal($a, $e instanceof GeminiGagal ? $e->getMessage() : 'Pemrosesan gagal. Coba analisis ulang.'); // pesan galat mentah tidak disimpan
        }
    }

    /** Lewat query langsung, bukan model: penandaan gagal tidak boleh ikut gagal oleh aturan model (itulah yang membuat analisis menggantung). */
    private function tandaiGagal(AiAnalysis $a, string $pesan): void
    {
        AiAnalysis::withoutGlobalScopes()->whereKey($a->id)->update(['status' => 'gagal', 'error' => mb_substr($pesan, 0, 255), 'updated_at' => now()]);
    }
}
