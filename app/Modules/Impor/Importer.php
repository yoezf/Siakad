<?php

namespace App\Modules\Impor;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Activitylog\Support\ActivityLogStatus;

/**
 * Impor Excel (.xlsx) semua-atau-tidak-sama-sekali: bila ada satu baris bermasalah, tidak ada yang tersimpan
 * dan seluruh kesalahan dilaporkan dengan nomor barisnya. Impor hanya menambah data baru, tidak mengubah yang ada.
 */
abstract class Importer
{
    public const MAKS_BYTE = 2 * 1024 * 1024;

    public const MAKS_BARIS = 2000;

    private const MAKS_KOLOM = 30;

    /** @return array<string, array{key:string, label:string, wajib?:bool, teks?:bool}> label huruf kecil => definisi kolom */
    abstract protected function kolom(): array;

    /** @return array<string, mixed> aturan validasi per key */
    abstract protected function aturan(): array;

    /** @return list<string> key yang tidak boleh kembar di dalam berkas maupun terhadap data yang sudah ada */
    abstract protected function kunciUnik(): array;

    abstract protected function modelUnik(): string;

    /** @return int ID data yang dibuat */
    abstract protected function simpan(array $row): int;

    /** @return list<string> contoh satu baris untuk templat */
    abstract protected function contoh(): array;

    protected function normalisasi(string $key, string $nilai): string
    {
        return $nilai;
    }

    public function jalankan(string $path): HasilImpor
    {
        if (! is_file($path)) {
            return HasilImpor::gagal(0, 'Berkas tidak ditemukan.');
        }
        if (filesize($path) > self::MAKS_BYTE) {
            return HasilImpor::gagal(0, 'Berkas terlalu besar (maksimal 2 MB).');
        }

        try {
            $sheet = IOFactory::createReader('Xlsx')->setReadDataOnly(true)->load($path)->getActiveSheet();
            $barisTerakhir = $sheet->getHighestDataRow();
            if ($barisTerakhir - 1 > self::MAKS_BARIS) {
                return HasilImpor::gagal(0, 'Terlalu banyak baris (maksimal '.self::MAKS_BARIS.' baris data per berkas).');
            }
            $kolomTerakhir = Coordinate::stringFromColumnIndex(min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), self::MAKS_KOLOM));
            $rows = $sheet->rangeToArray("A1:{$kolomTerakhir}{$barisTerakhir}", null, false, false, false);
        } catch (\Throwable) {
            return HasilImpor::gagal(0, 'Berkas bukan Excel (.xlsx) yang valid.');
        }

        $peta = $this->petaKolom($rows[0] ?? []);
        if (is_string($peta)) {
            return HasilImpor::gagal(1, $peta);
        }

        $galat = [];
        $data = [];
        foreach (array_slice($rows, 1, null, true) as $i => $r) {
            $baris = $i + 1;
            $row = array_fill_keys(array_column($this->kolom(), 'key'), ''); // kolom opsional yang tidak ada di berkas = kosong
            foreach ($peta as $idx => $def) {
                $row[$def['key']] = $this->normalisasi($def['key'], $this->nilai($def['key'], $r[$idx] ?? null));
            }
            if (implode('', $row) === '') {
                continue; // baris kosong
            }
            foreach ($row as $k => $v) {
                if ($v !== '' && (str_starts_with($v, '=') || str_starts_with($v, '@'))) {
                    $galat[] = ['baris' => $baris, 'pesan' => "Kolom {$this->label($k)} tidak boleh diawali karakter = atau @."];
                }
            }
            $v = Validator::make($row, $this->aturan(), [], array_column($this->kolom(), 'label', 'key'));
            foreach ($v->errors()->all() as $pesan) {
                $galat[] = ['baris' => $baris, 'pesan' => $pesan];
            }
            $data[$baris] = $row;
        }
        if ($data === [] && $galat === []) {
            return HasilImpor::gagal(2, 'Tidak ada baris data untuk diimpor.');
        }
        array_push($galat, ...$this->cekKembar($data));
        if ($galat) {
            usort($galat, fn ($a, $b) => $a['baris'] <=> $b['baris']);

            return new HasilImpor(0, $galat);
        }

        // Naikkan batas waktu hanya bila ada batas yang lebih kecil (permintaan web). Pada proses tanpa batas (CLI, pekerja
        // antrean, uji) JANGAN memasang batas: set_time_limit berlaku untuk seluruh sisa proses dan akan mematikannya.
        $batas = (int) ini_get('max_execution_time');
        if ($batas > 0 && $batas < 120) {
            @set_time_limit(120);
        }
        // Log aktivitas per baris dimatikan selama impor (memakan sekitar 60% waktu) dan diganti satu catatan ringkasan
        // berisi pelaku, jumlah, dan rentang ID, sehingga jejak audit tetap bisa dilacak.
        $status = app(ActivityLogStatus::class);
        $sebelumnya = $status->disabled();
        $status->disable();
        $ids = [];
        try {
            DB::transaction(function () use ($data, &$ids) {
                foreach ($data as $baris => $row) {
                    try {
                        $ids[] = $this->simpan($row);
                    } catch (ValidationException $e) {
                        throw new BarisBermasalah($baris, collect($e->errors())->flatten()->implode(' '));
                    }
                }
            });
        } catch (BarisBermasalah $e) {
            return HasilImpor::gagal($e->baris, $e->getMessage());
        } catch (QueryException) {
            return HasilImpor::gagal(0, 'Data bentrok dengan data yang sudah ada. Tidak ada yang diimpor.');
        } finally {
            if (! $sebelumnya) {
                $status->enable();
            }
        }

        activity('impor')->causedBy(auth()->user())
            ->withProperties(['jumlah' => count($ids), 'jenis' => static::class, 'id_pertama' => min($ids), 'id_terakhir' => max($ids)])
            ->log('Impor Excel');

        return new HasilImpor(count($data));
    }

    /** Templat .xlsx berisi judul kolom dan satu baris contoh; kolom nomor diformat teks agar angka 0 di depan tidak hilang. */
    public function templat(): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $judul = array_column($this->kolom(), 'label');
        $sheet->fromArray([array_map('ucwords', $judul), $this->contoh()], null, 'A1', true);
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        foreach (array_values($this->kolom()) as $i => $def) {
            $kol = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($kol)->setAutoSize(true);
            if (! empty($def['teks'])) {
                $sheet->getStyle("{$kol}2:{$kol}".(self::MAKS_BARIS + 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                $sheet->setCellValueExplicit("{$kol}2", $this->contoh()[$i], DataType::TYPE_STRING);
            }
        }
        $tmp = tempnam(sys_get_temp_dir(), 'tpl');
        (new Xlsx($book))->save($tmp);
        $isi = file_get_contents($tmp);
        @unlink($tmp);

        return $isi;
    }

    /** @return array<int, array>|string peta indeks kolom => definisi, atau pesan kesalahan */
    private function petaKolom(array $header): array|string
    {
        $def = $this->kolom();
        $peta = [];
        foreach ($header as $i => $h) {
            $kunci = mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $h)));
            if (isset($def[$kunci])) {
                $peta[$i] = $def[$kunci];
            }
        }
        $ada = array_column($peta, 'key');
        $hilang = array_filter($def, fn ($d) => ! empty($d['wajib']) && ! in_array($d['key'], $ada, true));
        if ($hilang) {
            return 'Kolom wajib tidak ditemukan di baris pertama: '.implode(', ', array_column($hilang, 'label')).'. Gunakan templat yang disediakan.';
        }

        return $peta;
    }

    private function label(string $key): string
    {
        return array_column($this->kolom(), 'label', 'key')[$key] ?? $key;
    }

    private function nilai(string $key, mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_float($v) || is_int($v)) {
            if (str_contains($key, 'date')) { // tanggal Excel disimpan sebagai angka seri
                return Date::excelToDateTimeObject($v)->format('Y-m-d');
            }

            return $v == floor($v) ? sprintf('%.0f', $v) : (string) $v;
        }
        $s = trim((string) $v);
        if (str_contains($key, 'date') && $s !== '') {
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y'] as $f) {
                try {
                    $d = Carbon::createFromFormat('!'.$f, $s);
                } catch (\Throwable) {
                    continue; // bukan format ini; teks asli dibiarkan agar ditolak oleh validasi tanggal
                }
                if ($d->format($f) === $s) {
                    return $d->format('Y-m-d');
                }
            }
        }

        return $s;
    }

    /** @return list<array{baris:int, pesan:string}> */
    private function cekKembar(array $data): array
    {
        $galat = [];
        foreach ($this->kunciUnik() as $key) {
            $ada = []; // huruf kecil => baris pertama; basis data membandingkan tanpa peduli huruf besar-kecil
            foreach ($data as $baris => $row) {
                $nilai = mb_strtolower($row[$key]);
                if ($nilai === '') {
                    continue;
                }
                if (isset($ada[$nilai])) {
                    $galat[] = ['baris' => $baris, 'pesan' => "{$this->label($key)} {$row[$key]} kembar dengan baris {$ada[$nilai]} di berkas ini."];
                }
                $ada[$nilai] ??= $baris;
            }
            $sudah = $this->modelUnik()::query()->whereIn($key, array_keys($ada))->pluck($key);
            foreach ($sudah as $v) {
                $galat[] = ['baris' => $ada[mb_strtolower((string) $v)] ?? 0, 'pesan' => "{$this->label($key)} {$v} sudah terdaftar di sekolah ini."];
            }
        }

        return $galat;
    }
}
