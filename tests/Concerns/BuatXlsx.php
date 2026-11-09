<?php

namespace Tests\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

trait BuatXlsx
{
    private array $berkasUji = [];

    protected function tearDownBuatXlsx(): void
    {
        foreach ($this->berkasUji as $f) {
            @unlink($f);
        }
    }

    /**
     * Buat berkas .xlsx. Nilai string ditulis sebagai teks, angka (int/float) sebagai angka, seperti sel Excel sungguhan.
     *
     * @param  list<list<mixed>>  $baris  baris pertama = judul kolom
     */
    protected function xlsx(array $baris): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        foreach ($baris as $r => $row) {
            foreach ($row as $c => $v) {
                $sel = [$c + 1, $r + 1];
                is_string($v) ? $sheet->setCellValueExplicit($sel, $v, DataType::TYPE_STRING) : $sheet->setCellValue($sel, $v);
            }
        }

        return $this->simpanBuku($book);
    }

    protected function simpanBuku(Spreadsheet $book): string
    {
        $f = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        (new Xlsx($book))->save($f);
        $book->disconnectWorksheets(); // bebaskan memori; satu proses uji membuat banyak berkas
        unset($book);

        return $this->berkasUji[] = $f;
    }

    protected function headerSiswa(): array
    {
        return ['Nama', 'NISN', 'NIS', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir'];
    }
}
