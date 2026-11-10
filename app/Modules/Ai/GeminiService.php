<?php

namespace App\Modules\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Satu-satunya pintu ke Google Gemini untuk kedua modul (palmistry dan physiognomy). Kunci API dikirim lewat header (bukan alamat URL
 * yang bisa tercatat di log) dan hanya dibaca dari konfigurasi server.
 */
final class GeminiService
{
    /**
     * @return array{hasil: array, ringkasan: string, keterbacaan: string, model: string}
     *
     * @throws GeminiGagal
     */
    public function analisis(string $jenis, string $gambar, string $mime, ?string $promptKustom = null): array
    {
        if (! isset(Skema::BAGIAN[$jenis]) && $jenis !== 'custom') {
            throw new GeminiGagal('Jenis analisis tidak dikenal.');
        }
        if ($jenis === 'custom' && trim((string) $promptKustom) === '') {
            throw new GeminiGagal('Prompt kustom kosong.');
        }
        $kunci = (string) config('services.gemini.key');
        if ($kunci === '') {
            throw new GeminiGagal('Kunci API Gemini belum diatur di server (GEMINI_API_KEY).');
        }
        $model = (string) config('services.gemini.model');

        try {
            $res = Http::baseUrl(rtrim((string) config('services.gemini.base_url'), '/'))->withHeaders(['x-goog-api-key' => $kunci])->withOptions(array_filter(['verify' => config('services.gemini.ca_bundle') ?: null]))->acceptJson()
                ->connectTimeout(10)->timeout((int) config('services.gemini.timeout'))
                ->post("/models/{$model}:generateContent", [
                    'contents' => [['role' => 'user', 'parts' => [['text' => Skema::prompt($jenis, $promptKustom)], ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($gambar)]]]]],
                    'generationConfig' => ['temperature' => 0.2, 'responseMimeType' => 'application/json', 'responseSchema' => Skema::skema($jenis), 'maxOutputTokens' => 4096],
                ]);
        } catch (ConnectionException) {
            throw new GeminiGagal('Tidak bisa terhubung ke layanan AI (jaringan atau waktu habis).', true);
        }

        if (! $res->successful()) {
            $kode = $res->status();
            throw match (true) {
                $kode === 429 || $kode >= 500 => new GeminiGagal('Layanan AI sedang sibuk atau terbatas kuotanya. Coba lagi nanti.', true),
                $kode === 401 || $kode === 403 => new GeminiGagal('Kunci API Gemini ditolak atau tidak punya izin.'),
                $kode === 400 => new GeminiGagal('Layanan AI menolak permintaan (foto atau format tidak dapat diproses).'),
                default => new GeminiGagal("Layanan AI mengembalikan galat ($kode)."),
            };
        }

        $data = $res->json();
        if (! empty($data['promptFeedback']['blockReason'])) {
            throw new GeminiGagal('Foto ditolak oleh filter keamanan layanan AI.');
        }
        $alasanBerhenti = $data['candidates'][0]['finishReason'] ?? null;
        if ($alasanBerhenti !== null && ! in_array($alasanBerhenti, ['STOP', 'MAX_TOKENS'], true)) {
            throw new GeminiGagal('Respons AI dihentikan oleh filter keamanan.');
        }
        $teks = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $mentah = is_string($teks) ? json_decode($teks, true) : null;
        if (! is_array($mentah)) {
            throw new GeminiGagal('Respons AI tidak dapat dibaca sebagai JSON.', true);
        }

        return Skema::bersihkan($jenis, $mentah, $promptKustom) + ['model' => $model];
    }
}
