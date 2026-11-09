<?php

namespace App\Modules\Laporan;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Mengubah tabel laporan menjadi PDF atau Excel. Isi sel yang berupa teks selalu disimpan sebagai teks (bukan rumus). */
final class Ekspor
{
    public static function pdf(array $tabel): string
    {
        $landscape = count($tabel['kolom']) > 8;

        return Pdf::loadView('laporan.pdf', ['t' => $tabel])->setPaper('a4', $landscape ? 'landscape' : 'portrait')->output();
    }

    public static function xlsx(array $tabel): string
    {
        $buku = new Spreadsheet;
        $lembar = $buku->getActiveSheet();
        $lembar->setTitle('Laporan');
        $r = 1;
        foreach ([$tabel['sekolah'], $tabel['judul'], ...$tabel['keterangan']] as $teks) {
            $lembar->setCellValueExplicit([1, $r++], (string) $teks, DataType::TYPE_STRING);
        }
        $r++;
        $kepala = $r;
        foreach (array_values($tabel['kolom']) as $c => $nama) {
            $lembar->setCellValueExplicit([$c + 1, $r], (string) $nama, DataType::TYPE_STRING);
            $lembar->getStyle([$c + 1, $r])->getFont()->setBold(true);
        }
        foreach ($tabel['baris'] as $baris) {
            $r++;
            foreach (array_values($baris) as $c => $sel) {
                if ($sel === null) {
                    continue;
                }
                is_int($sel) || is_float($sel)
                    ? $lembar->setCellValueExplicit([$c + 1, $r], $sel, DataType::TYPE_NUMERIC)
                    : $lembar->setCellValueExplicit([$c + 1, $r], (string) $sel, DataType::TYPE_STRING); // "=1+1" tetap teks
            }
        }
        $lembar->freezePane([1, $kepala + 1]);
        foreach (range(1, count($tabel['kolom'])) as $c) {
            $lembar->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $aliran = fopen('php://temp', 'r+');
        (new Xlsx($buku))->save($aliran);
        rewind($aliran);

        return (string) stream_get_contents($aliran);
    }

    public static function namaBerkas(array $tabel, string $ekstensi): string
    {
        $bersih = fn ($t) => trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) $t), '-');
        $kelasSemester = collect($tabel['keterangan'])->take(2)->map($bersih)->implode('-');

        return strtolower($bersih($tabel['judul'])).'-'.$kelasSemester.'.'.$ekstensi;
    }
}
