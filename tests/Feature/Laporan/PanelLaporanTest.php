<?php

namespace Tests\Feature\Laporan;

use App\Filament\Sekolah\Pages\LaporanAkademik;
use App\Support\Peran;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Rapor\RaporTestCase;

class PanelLaporanTest extends RaporTestCase
{
    private function buka($akun)
    {
        $this->masukPanel($akun, 'sekolah', $this->s);

        return Livewire::test(LaporanAkademik::class);
    }

    public function test_hak_akses_halaman_laporan(): void
    {
        $harapan = [Peran::ADMIN_SEKOLAH => 200, Peran::KEPALA_SEKOLAH => 200, Peran::WALI_KELAS => 200, Peran::GURU_MAPEL => 200, Peran::STAF_TU => 200,
            Peran::STAF_KEUANGAN => 403, Peran::GURU_BK => 403];
        foreach ($harapan as $peran => $kode) {
            $akun = $peran === Peran::ADMIN_SEKOLAH ? $this->adminDari($this->s) : $this->penggunaSekolah($this->s, $peran);
            $this->flushSession();
            auth()->forgetGuards();
            $this->assertSame($kode, $this->actingAs($akun)->get("/sekolah/{$this->s->slug}/laporan-akademik")->getStatusCode(), $peran);
        }
    }

    public function test_pilihan_kelas_menurut_peran_dan_jenis_laporan(): void
    {
        $semua = [$this->kelasA->id => '4A', $this->kelasB->id => '4B'];

        $this->assertSame($semua, $this->buka($this->akunKepala)->instance()->opsiKelas());
        $this->assertSame([$this->kelasA->id => '4A'], $this->buka($this->waliA)->instance()->opsiKelas());
        $this->assertSame([], $this->buka($this->guruMtk)->instance()->opsiKelas(), 'guru mapel bukan wali: tidak melihat rekap nilai semua mapel');
        $k = $this->buka($this->guruMtk)->set('jenis', 'kehadiran');
        $this->assertSame([$this->kelasA->id => '4A', $this->kelasB->id => '4B'], $k->instance()->opsiKelas(), 'guru mapel melihat kehadiran kelas yang diampunya (4A dan 4B)');
        $this->assertSame($semua, $this->buka($this->akunTu)->set('jenis', 'kehadiran')->instance()->opsiKelas());
        $this->assertSame([], $this->buka($this->akunTu)->instance()->opsiKelas(), 'TU tidak melihat nilai');
    }

    public function test_tabel_nilai_dan_kehadiran_tampil(): void
    {
        $this->siapSahkan();

        $this->buka($this->akunKepala)->assertSee('Rekap nilai akhir')->assertSee('Siswa A1')->assertSee('76,33')->assertSee('Matematika')
            ->set('jenis', 'kehadiran')->assertSee('Rekap kehadiran')->assertSee('67');
    }

    public function test_kehadiran_per_bulan(): void
    {
        $this->kehadiranA1();

        $k = $this->buka($this->akunKepala)->set('jenis', 'kehadiran')->set('periode', 'bulan')->set('bulan', '2026-11');
        $this->assertSame(3, $k->instance()->tabel()['baris'][0][8]);
        $k->set('bulan', '2026-10');
        $this->assertSame(0, $k->instance()->tabel()['baris'][0][8]);
        $k->set('bulan', 'bukan-bulan');
        $this->assertSame(3, $k->instance()->tabel()['baris'][0][8], 'bulan tidak sah jatuh ke satu semester penuh');
    }

    public function test_unduh_pdf_dan_excel_dan_tercatat(): void
    {
        $this->siapSahkan();
        $k = $this->buka($this->akunKepala);

        $k->call('unduh', 'pdf')->assertFileDownloaded('rekap-nilai-akhir-Kelas-4A-Semester-Ganjil-Tahun-Ajaran-2026-2027.pdf');
        $k->call('unduh', 'xlsx')->assertFileDownloaded('rekap-nilai-akhir-Kelas-4A-Semester-Ganjil-Tahun-Ajaran-2026-2027.xlsx');

        $log = Activity::where('log_name', 'laporan')->get();
        $this->assertCount(2, $log);
        $this->assertSame($this->akunKepala->id, $log->first()->causer_id);
        $this->assertStringContainsString('Mengunduh Rekap nilai akhir', $log->first()->description);
    }

    public function test_format_tidak_dikenal_ditolak(): void
    {
        $this->buka($this->akunKepala)->call('unduh', 'exe')->assertNotified('Pilih semester dan kelas yang berhak Anda lihat.');

        $this->assertSame(0, Activity::where('log_name', 'laporan')->count());
    }

    public function test_kelas_dari_browser_yang_bukan_hak_pengguna_tidak_tampil_dan_tidak_bisa_diunduh(): void
    {
        $this->isiPenuh($this->b1, $this->mtk, $this->kelasB, [90, 90, 90]);

        $k = $this->buka($this->waliA)->set('kelasId', $this->kelasB->id);

        $this->assertNull($k->instance()->tabel());
        $k->assertDontSee('Siswa B1')->call('unduh', 'pdf')->assertNotified('Pilih semester dan kelas yang berhak Anda lihat.');
        $this->assertSame(0, Activity::where('log_name', 'laporan')->count());
    }

    public function test_semester_sekolah_lain_tidak_dikenali(): void
    {
        $b = $this->sekolah('SD B');
        $semB = $this->semester($b, $this->tahunAjaran($b, '2026/2027', '2026-07-01', '2027-06-30'), 1, '2026-07-01', '2026-12-31');

        $k = $this->buka($this->akunKepala)->set('semesterId', $semB->id);

        $this->assertNull($k->instance()->tabel());
        $this->assertSame([], $k->instance()->opsiKelas());
    }

    public function test_jenis_tidak_dikenal_kembali_ke_nilai(): void
    {
        $this->buka($this->akunKepala)->set('jenis', 'rahasia')->assertSet('jenis', 'nilai');
    }
}
