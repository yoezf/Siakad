<?php

namespace App\Modules\Ai;

use App\Jobs\ProsesAnalisisAi;
use App\Models\AiAnalysis;
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
    public const MAKS_BYTE = 5 * 1024 * 1024;

    public const MAKS_SISI = 1600;

    /** @param  array{subject_name?: mixed, consent_name?: mixed, consent_relation?: mixed, setuju?: mixed, dewasa?: mixed}  $persetujuan */
    public static function handle(User $oleh, string $jenis, UploadedFile $foto, array $persetujuan): AiAnalysis
    {
        if ($oleh->school_id === null || CurrentSchool::id() !== $oleh->school_id) {
            throw new AuthorizationException('Anda tidak berhak membuat analisis di sekolah ini.');
        }
        Gate::forUser($oleh)->authorize('create', AiAnalysis::class);

        if (! array_key_exists($jenis, AiAnalysis::TYPE)) {
            throw ValidationException::withMessages(['type' => 'Jenis analisis tidak dikenal.']);
        }
        if (($persetujuan['setuju'] ?? false) !== true) {
            throw ValidationException::withMessages(['setuju' => 'Persetujuan pemrosesan foto wajib diberikan sebelum foto diunggah.']);
        }
        if (($persetujuan['dewasa'] ?? false) !== true) {
            throw ValidationException::withMessages(['dewasa' => 'Orang yang difoto wajib dinyatakan berusia 18 tahun ke atas.']);
        }
        if (AiAnalysis::whereDate('created_at', today())->count() >= (int) config('ai.batas_harian')) {
            throw ValidationException::withMessages(['foto' => 'Batas analisis harian sekolah ini sudah tercapai. Coba lagi besok.']);
        }
        $jpeg = self::siapkanFoto($foto);

        $a = new AiAnalysis([
            'type' => $jenis,
            'subject_name' => $persetujuan['subject_name'] ?? '',
            'consent_name' => $persetujuan['consent_name'] ?? '',
            'consent_relation' => $persetujuan['consent_relation'] ?? '',
        ]);
        $a->forceFill(['created_by' => $oleh->id, 'consented_at' => now(), 'consent_version' => Skema::VERSI_PERSETUJUAN]);
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

    /** Memeriksa dan membersihkan foto; hasilnya JPEG tanpa metadata dengan sisi terpanjang paling banyak 1600 piksel. */
    private static function siapkanFoto(UploadedFile $foto): string
    {
        $salah = fn (string $pesan) => ValidationException::withMessages(['foto' => $pesan]);
        if (! $foto->isValid() || ($foto->getSize() ?: 0) > self::MAKS_BYTE) {
            throw $salah('Foto tidak valid atau lebih dari 5 MB.');
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
    }
}
