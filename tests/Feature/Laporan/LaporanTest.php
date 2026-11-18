<?php

namespace Tests\Feature\Laporan;

use App\Modules\Laporan\Ekspor;
use App\Modules\Laporan\Laporan;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Rapor\RaporTestCase;

class LaporanTest extends RaporTestCase
{
    private function rekapNilai($akun = null, $kelas = null, $sem = null): array
    {
        return $this->untuk($this->s, fn () => Laporan::nilai($akun ?? $this->akunKepala, $sem ?? $this->sem, $kelas ?? $this->kelasA));
    }

    private function hadir($akun = null, $kelas = null, ?Carbon $dari = null, ?Carbon $sampai = null): array
    {
        return $this->untuk($this->s, fn () => Laporan::kehadiran($akun ?? $this->akunKepala, $this->sem, $kelas ?? $this->kelasA, $dari, $sampai));
    }

    private function bacaXlsx(string $isi)
    {
        $berkas = tempnam(sys_get_temp_dir(), 'lap').'.xlsx';
        file_put_contents($berkas, $isi);
        try {
            return IOFactory::load($berkas)->getActiveSheet();
        } finally {
            @unlink($berkas);
        }
    }

    // ---------- rekap nilai ----------

    public function test_rekap_nilai_memuat_mapel_kelas_nilai_akhir_dan_rata_rata(): void
    {
        $this->lengkapi();

        $t = $this->rekapNilai();

        $this->assertSame(['No', 'Nama', 'NIS', 'Bahasa Indonesia', 'IPA', 'Matematika', 'Rata-rata'], $t['kolom']);
        $this->assertSame([1, 'Siswa A1', null, 90, 60, 79, 76.33], $t['baris'][0]);
        $this->assertSame([2, 'Siswa A2', null, null, null, null, null], $t['baris'][1], 'belum ada nilai: kosong, bukan nol');
        $this->assertCount(2, $t['baris'], 'hanya siswa kelas ini');
        $this->assertSame('SD A', $t['sekolah']);
        $this->assertContains('Kelas 4A', $t['keterangan']);
    }

    public function test_nilai_akhir_kosong_bila_komponen_belum_lengkap_tidak_dihitung_nol(): void
    {
        $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 80, $this->uts->id => 90]]); // UAS belum ada

        $this->assertSame([1, 'Siswa A1', null, null, null, null, null], $this->rekapNilai()['baris'][0]);
    }

    public function test_rata_rata_kosong_bila_ada_mapel_yang_belum_bernilai(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]); // IPA dan Bahasa Indonesia masih kosong

        $this->assertSame([1, 'Siswa A1', null, null, null, 79, null], $this->rekapNilai()['baris'][0]);
    }

    // ---------- rekap kehadiran ----------

    public function test_rekap_kehadiran_menghitung_per_status_dan_persentase(): void
    {
        $this->kehadiranA1();

        $t = $this->hadir();

        $this->assertSame(['No', 'Nama', 'NIS', 'Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpa', 'Hari tercatat', 'Kehadiran (%)'], $t['kolom']);
        $this->assertSame([1, 'Siswa A1', null, 1, 1, 1, 0, 0, 3, 67], $t['baris'][0]);
        $this->assertSame([2, 'Siswa A2', null, 0, 0, 0, 0, 0, 0, null], $t['baris'][1]);
    }

    public function test_rekap_kehadiran_dibatasi_rentang_tanggal(): void
    {
        $this->kehadiranA1();

        $t = $this->hadir(null, null, Carbon::parse('2026-11-10'), Carbon::parse('2026-11-10'));

        $this->assertSame([1, 'Siswa A1', null, 0, 1, 0, 0, 0, 1, 100], $t['baris'][0]);
        $this->assertContains('Periode 10-11-2026 s.d. 10-11-2026', $t['keterangan']);
    }

    public function test_rentang_terbalik_ditolak(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->hadir(null, null, Carbon::parse('2026-11-12'), Carbon::parse('2026-11-01'));
    }

    // ---------- hak akses ----------

    public function test_hak_akses_rekap_nilai(): void
    {
        $this->lengkapi();
        $boleh = [$this->akunKepala, $this->akunAdmin, $this->waliA];
        foreach ($boleh as $akun) {
            $this->assertCount(2, $this->rekapNilai($akun)['baris'], $akun->name);
        }
        foreach ([$this->waliB, $this->guruMtk, $this->guruIpa, $this->akunTu, $this->penggunaSekolah($this->s, \App\Support\Peran::STAF_KEUANGAN)] as $akun) {
            try {
                $this->rekapNilai($akun);
                $this->fail("{$akun->name} seharusnya ditolak");
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_hak_akses_rekap_kehadiran(): void
    {
        foreach ([$this->akunKepala, $this->akunAdmin, $this->akunTu, $this->waliA, $this->guruMtk] as $akun) {
            $this->assertCount(2, $this->hadir($akun)['baris'], $akun->name);
        }
        foreach ([$this->waliB, $this->penggunaSekolah($this->s, \App\Support\Peran::STAF_KEUANGAN)] as $akun) {
            try {
                $this->hadir($akun, $this->kelasA);
                $this->fail("{$akun->name} seharusnya ditolak");
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_kelas_dan_semester_harus_sejalan_dan_sesekolah(): void
    {
        $tahun2 = $this->tahunAjaran($this->s, '2027/2028', '2027-07-01', '2028-06-30');
        $kelasLain = $this->kelas($this->s, $tahun2, '5A');
        $b = $this->sekolah('SD B');
        $semB = $this->semester($b, $this->tahunAjaran($b, '2026/2027', '2026-07-01', '2027-06-30'), 1, '2026-07-01', '2026-12-31');

        foreach ([fn () => $this->rekapNilai(null, $kelasLain), fn () => $this->hadir(null, $kelasLain), fn () => $this->rekapNilai(null, null, $semB)] as $f) {
            try {
                $f();
                $this->fail('seharusnya ditolak');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    // ---------- PDF ----------

    public function test_pdf_sah_dan_isinya_dilepas_dari_html(): void
    {
        $this->untuk($this->s, fn () => $this->a1->update(['name' => 'Budi <script>alert(1)</script>']));
        $this->lengkapi();
        $t = $this->rekapNilai();

        $this->assertStringStartsWith('%PDF-', $this->untuk($this->s, fn () => Ekspor::pdf($t)));
        $html = view('laporan.pdf', ['t' => $t])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('76,33', $html);
        $this->assertStringContainsString('Rekap nilai akhir', $html);
    }

    public function test_pdf_lebar_memakai_kertas_mendatar_tanpa_galat(): void
    {
        $this->lengkapi();
        $this->assertStringStartsWith('%PDF-', Ekspor::pdf($this->hadir()));
    }

    // ---------- Excel ----------

    public function test_excel_memuat_judul_kolom_dan_angka_sebagai_angka(): void
    {
        $this->lengkapi();
        $lembar = $this->bacaXlsx(Ekspor::xlsx($this->rekapNilai()));

        $this->assertSame('SD A', $lembar->getCell('A1')->getValue());
        $this->assertSame('Rekap nilai akhir', $lembar->getCell('A2')->getValue());
        $baris = collect($lembar->toArray())->first(fn ($r) => ($r[0] ?? null) === 'No');
        $this->assertSame(['No', 'Nama', 'NIS', 'Bahasa Indonesia', 'IPA', 'Matematika', 'Rata-rata'], array_slice($baris, 0, 7));
        $awal = collect($lembar->toArray())->search(fn ($r) => ($r[0] ?? null) === 'No') + 2; // nomor baris lembar (mulai dari 1) untuk data pertama
        $this->assertSame('Siswa A1', $lembar->getCell("B{$awal}")->getValue());
        $this->assertEquals(90, $lembar->getCell("D{$awal}")->getValue());
        $this->assertSame('n', $lembar->getCell("D{$awal}")->getDataType());
        $this->assertEquals(76.33, $lembar->getCell("G{$awal}")->getValue());
    }

    public function test_excel_berkepala_indigo_bergaris_dan_angka_rata_tengah(): void
    {
        $this->lengkapi();
        $lembar = $this->bacaXlsx(Ekspor::xlsx($this->rekapNilai()));
        $kepala = collect($lembar->toArray())->search(fn ($r) => ($r[0] ?? null) === 'No') + 1; // nomor baris lembar kepala tabel
        $data = $kepala + 1;

        $this->assertSame('4F46E5', $lembar->getStyle("A{$kepala}")->getFill()->getStartColor()->getRGB());
        $this->assertSame('FFFFFF', $lembar->getStyle("D{$kepala}")->getFont()->getColor()->getRGB());
        $this->assertTrue($lembar->getStyle("A{$kepala}")->getFont()->getBold());
        $this->assertSame('3730A3', $lembar->getStyle('A1')->getFont()->getColor()->getRGB(), 'nama sekolah berwarna tema');
        $this->assertSame('thin', $lembar->getStyle("D{$data}")->getBorders()->getTop()->getBorderStyle());
        $this->assertSame('center', $lembar->getStyle("D{$data}")->getAlignment()->getHorizontal());
        $this->assertNotSame('center', $lembar->getStyle("B{$data}")->getAlignment()->getHorizontal(), 'kolom nama tetap rata kiri');
        $this->assertSame('A'.$data, $lembar->getFreezePane());
    }

    public function test_excel_tidak_menjalankan_rumus_dari_nama_siswa(): void
    {
        $rumus = '=HYPERLINK("http://jahat.example","klik")';
        $this->untuk($this->s, fn () => $this->a1->update(['name' => $rumus]));
        $lembar = $this->bacaXlsx(Ekspor::xlsx($this->rekapNilai()));

        $sel = collect($lembar->toArray())->search(fn ($r) => ($r[1] ?? null) === $rumus);
        $this->assertNotFalse($sel, 'nama harus tersimpan apa adanya');
        $this->assertSame('s', $lembar->getCell('B'.($sel + 1))->getDataType(), 'harus bertipe teks, bukan rumus');
    }

    public function test_nama_berkas_aman_dan_deskriptif(): void
    {
        $nama = Ekspor::namaBerkas($this->rekapNilai(), 'xlsx');

        $this->assertSame('rekap-nilai-akhir-Kelas-4A-Semester-Ganjil-Tahun-Ajaran-2026-2027.xlsx', $nama);
        $this->assertStringNotContainsString('/', $nama);
    }
}
