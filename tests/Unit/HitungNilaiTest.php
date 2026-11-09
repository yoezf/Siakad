<?php

namespace Tests\Unit;

use App\Modules\Penilaian\Hitung;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Perhitungan nilai murni, tanpa basis data: inti kepercayaan orang tua pada rapor. */
class HitungNilaiTest extends TestCase
{
    private function komponen(array $bobot): \Illuminate\Support\Collection
    {
        return collect($bobot)->map(fn ($w, $i) => (object) ['id' => $i + 1, 'weight' => $w]);
    }

    public function test_nilai_akhir_adalah_rata_rata_berbobot(): void
    {
        $k = $this->komponen([30, 30, 40]);

        $this->assertSame(79.0, Hitung::akhir($k, [1 => 80, 2 => 90, 3 => 70]));   // (2400 + 2700 + 2800) / 100
        $this->assertSame(100.0, Hitung::akhir($k, [1 => 100, 2 => 100, 3 => 100]));
        $this->assertSame(0.0, Hitung::akhir($k, [1 => 0, 2 => 0, 3 => 0]));       // nol yang diisi adalah nilai, bukan kosong
    }

    public function test_bobot_dinormalkan_sehingga_tidak_harus_berjumlah_100(): void
    {
        $this->assertSame(85.0, Hitung::akhir($this->komponen([1, 1]), [1 => 80, 2 => 90]));
        $this->assertSame(86.0, Hitung::akhir($this->komponen([20, 30]), [1 => 80, 2 => 90]));   // (1600 + 2700) / 50
        $this->assertSame(70.0, Hitung::akhir($this->komponen([100]), [1 => 70]));
    }

    public function test_komponen_kosong_membuat_nilai_akhir_null_bukan_dianggap_nol(): void
    {
        $k = $this->komponen([30, 30, 40]);

        $this->assertNull(Hitung::akhir($k, [1 => 80, 2 => 90]));
        $this->assertNull(Hitung::akhir($k, [1 => 80, 2 => 90, 3 => null]));
        $this->assertNull(Hitung::akhir($k, [1 => 80, 2 => '', 3 => 70]));
        $this->assertNull(Hitung::akhir($k, []));
    }

    public function test_tanpa_komponen_atau_bobot_nol_tidak_menghasilkan_nilai(): void
    {
        $this->assertNull(Hitung::akhir(collect(), []));
        $this->assertNull(Hitung::akhir($this->komponen([0]), [1 => 80]));
    }

    public function test_hasil_dibulatkan_dua_desimal(): void
    {
        $this->assertSame(83.33, Hitung::akhir($this->komponen([1, 1, 1]), [1 => 80, 2 => 85, 3 => 85]));
        $this->assertSame(0.33, Hitung::akhir($this->komponen([1, 1, 1]), [1 => 1, 2 => 0, 3 => 0]));
    }

    #[DataProvider('pembulatan')]
    public function test_pembulatan_nilai_rapor(?float $akhir, ?int $bulat): void
    {
        $this->assertSame($bulat, Hitung::bulat($akhir));
    }

    public static function pembulatan(): array
    {
        return [[84.5, 85], [84.49, 84], [84.51, 85], [69.6, 70], [0.0, 0], [100.0, 100], [null, null]];
    }

    public function test_ketuntasan_dinilai_dari_nilai_bulat_yang_tampil_di_rapor(): void
    {
        $this->assertTrue(Hitung::tuntas(69.6, 70), '69,6 tampil sebagai 70, jadi tuntas');
        $this->assertFalse(Hitung::tuntas(69.4, 70), '69,4 tampil sebagai 69, jadi belum tuntas');
        $this->assertTrue(Hitung::tuntas(70.0, 70));
        $this->assertTrue(Hitung::tuntas(100.0, 100));
        $this->assertFalse(Hitung::tuntas(0.0, 1));
        $this->assertNull(Hitung::tuntas(null, 70));
    }

    #[DataProvider('predikat')]
    public function test_predikat(?float $akhir, int $kkm, ?string $harapan): void
    {
        $this->assertSame($harapan, Hitung::predikat($akhir, $kkm));
    }

    public static function predikat(): array
    {
        return [
            'A di 90' => [90.0, 70, 'A'], 'A dari pembulatan 89,5' => [89.5, 70, 'A'], 'B di 80' => [80.0, 70, 'B'], 'B di 89,4' => [89.4, 70, 'B'],
            'C di 79' => [79.0, 70, 'C'], 'C tepat KKM' => [70.0, 70, 'C'], 'D di bawah KKM' => [69.0, 70, 'D'], 'D dari 69,4' => [69.4, 70, 'D'],
            'KKM tinggi: di bawah KKM selalu D' => [82.0, 85, 'D'], 'KKM tinggi: tepat KKM' => [85.0, 85, 'B'], 'KKM 0' => [0.0, 0, 'C'],
            'kosong' => [null, 70, null],
        ];
    }

    public function test_tuntas_dan_predikat_D_selalu_sejalan(): void
    {
        foreach ([0, 30, 59.5, 69.4, 69.5, 70, 75, 85, 99.5, 100] as $nilai) {
            foreach ([0, 60, 70, 75, 85, 100] as $kkm) {
                $this->assertSame(! Hitung::tuntas((float) $nilai, $kkm), Hitung::predikat((float) $nilai, $kkm) === 'D', "nilai $nilai, KKM $kkm");
            }
        }
    }
}
