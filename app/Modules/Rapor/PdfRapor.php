<?php

namespace App\Modules\Rapor;

use App\Models\ReportCardVersion;
use Barryvdh\DomPDF\Facade\Pdf;

/** Membuat PDF dari salinan tetap sebuah versi rapor (bukan dari data yang berjalan, sehingga isinya tidak bisa bergeser). */
final class PdfRapor
{
    /** Data untuk templat rapor.pdf. Dipisah agar isi HTML bisa diperiksa tanpa mengurai PDF. */
    public static function data(ReportCardVersion $versi): array
    {
        $versi->loadMissing('reportCard');

        return [
            's' => $versi->snapshot,
            'versi' => $versi->version,
            'versiBerlaku' => $versi->reportCard->current_version,
            'alasanRevisi' => $versi->revision_reason,
            'kode' => strtoupper(substr($versi->checksum, 0, 16)),
            'utuh' => $versi->utuh(),
        ];
    }

    public static function render(ReportCardVersion $versi): string
    {
        return Pdf::loadView('rapor.pdf', self::data($versi))->setPaper('a4')->output();
    }

    public static function namaBerkas(ReportCardVersion $versi): string
    {
        $s = $versi->snapshot;
        // Semua bagian dibersihkan: tahun ajaran memuat "/" (2026/2027), yang akan dibaca sebagai jalur direktori.
        $bersih = fn ($t) => trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) $t), '-');

        return 'rapor-'.$bersih($s['siswa']['nama'] ?? 'siswa').'-'.$bersih($s['semester']['tahun']).'-'.strtolower($bersih($s['semester']['label'])).'-v'.$versi->version.'.pdf';
    }
}
