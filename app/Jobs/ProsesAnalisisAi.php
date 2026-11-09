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
        $a->forceFill(['status' => 'diproses', 'error' => null])->save();

        try {
            $r = $gemini->analisis($a->type, Storage::disk(AiAnalysis::DISK)->get($a->image_path), 'image/jpeg');
        } catch (GeminiGagal $e) {
            if ($e->bisaDiulang && $this->attempts() < $this->tries) {
                throw $e; // antrean mengulang sesuai backoff
            }
            $this->tandaiGagal($a, $e->getMessage());

            return;
        }

        // Analisis bisa dihapus saat Gemini bekerja; jangan menghidupkan kembali.
        $segar = AiAnalysis::withoutGlobalScopes()->find($a->id);
        $segar?->forceFill(['status' => 'selesai', 'result' => $r['hasil'], 'summary' => $r['ringkasan'], 'readability' => $r['keterbacaan'],
            'model' => $r['model'], 'error' => null, 'processed_at' => now()])->save();
    }

    public function failed(Throwable $e): void
    {
        $a = AiAnalysis::withoutGlobalScopes()->find($this->analisisId);
        if ($a && $a->status !== 'selesai') {
            $this->tandaiGagal($a, $e instanceof GeminiGagal ? $e->getMessage() : 'Pemrosesan gagal. Coba analisis ulang.'); // pesan galat mentah tidak disimpan
        }
    }

    private function tandaiGagal(AiAnalysis $a, string $pesan): void
    {
        $a->forceFill(['status' => 'gagal', 'error' => mb_substr($pesan, 0, 255)])->save();
    }
}
