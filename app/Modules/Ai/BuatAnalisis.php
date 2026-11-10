<?php

namespace App\Modules\Ai;

use App\Jobs\ProsesAnalisisAi;
use App\Models\AiAnalysis;
use App\Models\AiPrompt;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menerima foto, memeriksanya, membersihkannya (EXIF seperti lokasi GPS dibuang dengan menyandikan ulang gambar), menyimpannya di disk
 * privat, lalu mengantrekan analisis. Tidak ada foto yang disimpan atau dikirim sebelum persetujuan tercatat.
 */
final class BuatAnalisis
{
    public const MAKS_BYTE = 10 * 1024 * 1024; // foto HP 3-8 MB; toh diperkecil sebelum disimpan dan dikirim

    public const MAKS_SISI = 1600;

    /** @param  array{subject_name?: mixed}  $persetujuan hanya keterangan foto (opsional); nama parameter dipertahankan agar pemanggil tidak berubah */
    public static function handle(User $oleh, string $jenis, UploadedFile $foto, array $persetujuan, ?AiPrompt $prompt = null): AiAnalysis
    {
        if ($oleh->school_id === null || CurrentSchool::id() !== $oleh->school_id) {
            throw new AuthorizationException('Anda tidak berhak membuat analisis di sekolah ini.');
        }
        Gate::forUser($oleh)->authorize('create', AiAnalysis::class);

        if (! array_key_exists($jenis, AiAnalysis::TYPE)) {
            throw ValidationException::withMessages(['type' => 'Jenis analisis tidak dikenal.']);
        }
        if ($jenis === 'custom' && (! $prompt || $prompt->school_id !== $oleh->school_id)) {
            throw ValidationException::withMessages(['prompt' => 'Pilih prompt tersimpan terlebih dahulu.']);
        }
        if ($jenis !== 'custom') {
            $prompt = null; // prompt hanya untuk jenis kustom
        }
        if (AiAnalysis::whereDate('created_at', today())->count() >= (int) config('ai.batas_harian')) {
            throw ValidationException::withMessages(['foto' => 'Batas analisis harian sekolah ini sudah tercapai. Coba lagi besok.']);
        }
        $jpeg = self::siapkanFoto($foto);

        // Persetujuan tidak lagi diminta (keputusan pemilik produk). Kolom persetujuan diisi otomatis, dan versinya mencatat bahwa tidak ada persetujuan yang diminta.
        $subjek = trim((string) ($persetujuan['subject_name'] ?? '')) ?: 'Tanpa nama'; // keterangan foto bersifat opsional
        $a = new AiAnalysis([
            'type' => $jenis,
            'subject_name' => $subjek,
            'consent_name' => $subjek,
            'consent_relation' => 'diri_sendiri',
        ]);
        $a->forceFill(['created_by' => $oleh->id, 'consented_at' => now(), 'consent_version' => Skema::VERSI_PERSETUJUAN,
            'prompt_name' => $prompt?->name, 'prompt_text' => $prompt?->body]);
        $a->validasiDulu(); // isian salah ditolak sebelum foto menyentuh disk

        $jalur = 'analisis-ai/'.$oleh->school_id.'/'.Str::uuid().'.jpg';
        Storage::disk(AiAnalysis::DISK)->put($jalur, $jpeg);
        try {
            $a->forceFill(['image_path' => $jalur])->save();
        } catch (\Throwable $e) {
            Storage::disk(AiAnalysis::DISK)->delete($jalur); // tidak meninggalkan foto tanpa catatan
            throw $e;
        }
        ProsesAnalisisAi::dispatch($a->id)->afterCommit();

        return $a;
    }

    /** Memori yang dibutuhkan GD: gambar terurai (4 byte per piksel) ditambah salinan saat diputar dan diperkecil, plus ruang aplikasi. */
    public static function memoriDibutuhkan(int $lebar, int $tinggi): int
    {
        return (int) ($lebar * $tinggi * 4 * 2.5) + 64 * 1024 * 1024;
    }

    /** Foto HP 12 megapiksel butuh sekitar 100 MB; batas bawaan PHP 128 MB sudah terpakai aplikasi. Dinaikkan seperlunya, paling banyak 768 MB. */
    private static function naikkanMemori(int $lebar, int $tinggi): void
    {
        $batas = ini_parse_quantity((string) ini_get('memory_limit'));
        $perlu = memory_get_usage() + self::memoriDibutuhkan($lebar, $tinggi);
        if ($batas > 0 && $batas < $perlu) {
            ini_set('memory_limit', (string) min($perlu, 768 * 1024 * 1024));
        }
    }

    /** Memeriksa dan membersihkan foto; hasilnya JPEG tanpa metadata dengan sisi terpanjang paling banyak 1600 piksel. */
    private static function siapkanFoto(UploadedFile $foto): string
    {
        $salah = fn (string $pesan) => ValidationException::withMessages(['foto' => $pesan]);
        if (! $foto->isValid() || ($foto->getSize() ?: 0) > self::MAKS_BYTE) {
            throw $salah('Foto tidak valid atau lebih dari 10 MB.');
        }
        $isi = (string) file_get_contents($foto->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($isi);
        $info = in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? @getimagesizefromstring($isi) : false;
        if (! $info) {
            throw $salah('Berkas harus berupa foto JPG, PNG, atau WEBP.');
        }
        [$lebar, $tinggi] = $info;
        if ($lebar < 200 || $tinggi < 200) {
            throw $salah('Foto terlalu kecil (minimal 200 x 200 piksel).');
        }
        if ($lebar * $tinggi > 40_000_000) {
            throw $salah('Resolusi foto terlalu besar.'); // mencegah bom dekompresi
        }
        $batasAsal = ini_get('memory_limit');
        self::naikkanMemori($lebar, $tinggi);
        try {
            $gambar = @imagecreatefromstring($isi);
            if (! $gambar) {
                throw $salah('Foto tidak bisa dibaca.');
            }
            if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
                $putar = [3 => 180, 6 => -90, 8 => 90][(int) (@exif_read_data('data://image/jpeg;base64,'.base64_encode($isi))['Orientation'] ?? 1)] ?? 0;
                if ($putar !== 0) {
                    $gambar = imagerotate($gambar, $putar, 0) ?: $gambar; // foto ponsel sering tersimpan menyamping lewat EXIF
                }
            }
            $skala = min(1, self::MAKS_SISI / max(imagesx($gambar), imagesy($gambar)));
            if ($skala < 1) {
                $gambar = imagescale($gambar, (int) round(imagesx($gambar) * $skala), (int) round(imagesy($gambar) * $skala)) ?: $gambar;
            }
            $putih = imagecreatetruecolor(imagesx($gambar), imagesy($gambar)); // latar putih untuk PNG transparan
            imagefill($putih, 0, 0, imagecolorallocate($putih, 255, 255, 255));
            imagecopy($putih, $gambar, 0, 0, 0, 0, imagesx($gambar), imagesy($gambar));
            ob_start();
            imagejpeg($putih, null, 85);

            return (string) ob_get_clean();
        } finally {
            $gambar = $putih = null; // bebaskan gambar GD dulu: PHP menolak menurunkan batas di bawah pemakaian saat ini
            @ini_set('memory_limit', (string) $batasAsal); // kenaikan hanya untuk pengolahan satu foto ini
        }
    }
}
