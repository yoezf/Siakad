<?php

namespace Tests\Feature\Kehadiran;

use App\Filament\Sekolah\Widgets\GrafikKehadiran;
use App\Filament\Sekolah\Widgets\KomposisiSiswa;
use App\Filament\Sekolah\Widgets\PapanPengumuman;
use App\Models\Announcement;
use App\Modules\Kehadiran\CatatKehadiranSiswa;
use Livewire\Livewire;
use ReflectionMethod;

/** Widget dasbor sekolah: hanya untuk yang berhak, dan hanya memuat data sekolah yang sedang dibuka. */
class DasborSekolahTest extends KehadiranTestCase
{
    private function data(string $widget): array
    {
        return (new ReflectionMethod($widget, 'getData'))->invoke(new $widget);
    }

    private function catat(string $tanggal, string $a1, string $a2): void
    {
        $this->untuk($this->s, fn () => CatatKehadiranSiswa::handle($this->akunAdmin, $this->kelasA, $tanggal, null, [$this->a1->id => ['status' => $a1], $this->a2->id => ['status' => $a2]]));
    }

    public function test_hanya_pengelola_yang_melihat_grafik_dan_komposisi(): void
    {
        foreach ([GrafikKehadiran::class, KomposisiSiswa::class] as $w) {
            $this->masukPanel($this->akunAdmin, 'sekolah', $this->s);
            $this->assertTrue($w::canView(), $w);
            $this->masukPanel($this->akunKepala, 'sekolah', $this->s);
            $this->assertTrue($w::canView(), $w);
            $this->masukPanel($this->guruMtk, 'sekolah', $this->s);
            $this->assertFalse($w::canView(), $w.' guru mapel');
            $this->masukPanel($this->waliA, 'sekolah', $this->s);
            $this->assertFalse($w::canView(), $w.' wali kelas');
        }
    }

    public function test_grafik_menghitung_hadir_dan_tidak_hadir_per_tanggal_dan_mengabaikan_sekolah_lain(): void
    {
        $this->catat(self::SENIN, 'H', 'T');   // dua hadir (terlambat tetap hadir)
        $this->catat(self::SELASA, 'H', 'S');  // satu hadir, satu tidak
        $b = $this->sekolah('SD B');
        $y = $this->tahunAjaran($b, '2026/2027', '2026-07-01', '2027-06-30');
        $kb = $this->kelas($b, $y, '4A');
        $sb = $this->siswaDi($b, 'Asing');
        $e = $this->untuk($b, fn () => \App\Models\Enrollment::tempatkan($sb, $kb));
        \Illuminate\Support\Facades\DB::table('enrollments')->where('id', $e->id)->update(['joined_at' => '2026-07-20']);
        $this->untuk($b, fn () => CatatKehadiranSiswa::handle($this->adminDari($b), $kb, self::SENIN, null, [$sb->id => ['status' => 'A']]));

        $this->masukPanel($this->akunAdmin, 'sekolah', $this->s);
        $d = $this->data(GrafikKehadiran::class);

        $this->assertSame(['09/11', '10/11'], $d['labels']);
        $this->assertSame([2, 1], $d['datasets'][0]['data'], 'hadir');
        $this->assertSame([0, 1], $d['datasets'][1]['data'], 'tidak hadir, tanpa data SD B');
    }

    public function test_grafik_kosong_bila_belum_ada_kehadiran_dan_dibatasi_tujuh_hari(): void
    {
        $this->masukPanel($this->akunAdmin, 'sekolah', $this->s);
        $this->assertSame([], $this->data(GrafikKehadiran::class)['labels']);

        foreach (['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', self::SENIN, self::SELASA, self::RABU] as $t) {
            $this->catat($t, 'H', 'H');
        }
        $this->masukPanel($this->akunAdmin, 'sekolah', $this->s);
        $d = $this->data(GrafikKehadiran::class);
        $this->assertCount(7, $d['labels']);
        $this->assertSame('11/11', end($d['labels']), 'tanggal terbaru terakhir');
        $this->assertSame('03/11', $d['labels'][0], 'tanggal paling lama dibuang');
    }

    public function test_komposisi_hanya_siswa_aktif_sekolah_ini(): void
    {
        $this->untuk($this->s, fn () => $this->b1->update(['status' => 'pindah'])); // laki-laki, tidak aktif
        $perempuan = fn ($sekolah, string $nama) => $this->untuk($sekolah, fn () => \App\Models\Student::create(['name' => $nama, 'gender' => 'P']));
        $perempuan($this->s, 'Perempuan Satu');
        $perempuan($this->sekolah('SD B'), 'Asing');

        $this->masukPanel($this->akunAdmin, 'sekolah', $this->s);
        $d = $this->data(KomposisiSiswa::class);

        $this->assertSame([2, 1], $d['datasets'][0]['data'], 'a1 dan a2 laki-laki aktif; b1 pindah tidak dihitung');
        $this->assertStringContainsString('Laki-laki (2)', $d['labels'][0]);
        $this->assertStringContainsString('Perempuan (1)', $d['labels'][1]);
    }

    public function test_papan_pengumuman_empat_terbit_terbaru_milik_sekolah_ini(): void
    {
        $buat = fn (string $judul, string $status, $sekolah = null) => $this->untuk($sekolah ?? $this->s, fn () => Announcement::create(['title' => $judul, 'body' => 'Isi pengumuman', 'status' => $status]));
        foreach (['Satu', 'Dua', 'Tiga', 'Empat', 'Lima'] as $i => $j) {
            $a = $buat("Pengumuman $j", 'terbit');
            \Illuminate\Support\Facades\DB::table('announcements')->where('id', $a->id)->update(['published_at' => now()->subDays(10 - $i)]);
        }
        $buat('Draf rahasia', 'draf');
        $b = $this->sekolah('SD B');
        $buat('Milik sekolah lain', 'terbit', $b);

        $this->masukPanel($this->akunAdmin, 'sekolah', $this->s);
        Livewire::test(PapanPengumuman::class)->assertSee('Pengumuman Lima')->assertSee('Pengumuman Dua')->assertDontSee('Pengumuman Satu')
            ->assertDontSee('Draf rahasia')->assertDontSee('Milik sekolah lain');

        $this->masukPanel($this->guruMtk, 'sekolah', $this->s);
        $this->assertFalse(PapanPengumuman::canView(), 'guru mapel tidak punya izin lihat pengumuman');
    }
}
