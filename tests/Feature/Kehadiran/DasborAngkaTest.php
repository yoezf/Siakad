<?php

namespace Tests\Feature\Kehadiran;

use App\Filament\Sekolah\Pages\Dasbor;
use App\Filament\Sekolah\Widgets\KalenderBulan;
use App\Filament\Sekolah\Widgets\KartuSorot;
use App\Filament\Sekolah\Widgets\KehadiranPerKelas;
use App\Filament\Sekolah\Widgets\RingkasanRing;
use App\Models\StaffAttendance;
use App\Modules\Kehadiran\CatatKehadiranSiswa;
use App\Modules\Laporan\DasborSekolah;
use Livewire\Livewire;

/** Angka dan kartu dasbor sekolah: perhitungan, pembatasan sekolah, dan hak lihat. */
class DasborAngkaTest extends KehadiranTestCase
{
    private function catat($kelas, string $tanggal, array $status): void
    {
        $this->untuk($this->s, fn () => CatatKehadiranSiswa::handle($this->akunAdmin, $kelas, $tanggal, null, $status));
    }

    public function test_kehadiran_siswa_hari_terakhir_dalam_persen(): void
    {
        $this->catat($this->kelasA, self::SENIN, [$this->a1->id => ['status' => 'A'], $this->a2->id => ['status' => 'A']]);
        $this->catat($this->kelasA, self::SELASA, [$this->a1->id => ['status' => 'H'], $this->a2->id => ['status' => 'S']]);
        $this->catat($this->kelasB, self::SELASA, [$this->b1->id => ['status' => 'T']]); // terlambat tetap hadir

        $d = $this->untuk($this->s, fn () => DasborSekolah::kehadiranSiswa());

        $this->assertSame(67, $d['persen'], '2 dari 3 pada hari terakhir (Selasa), hari Senin tidak ikut');
        $this->assertSame(self::SELASA, $d['tanggal']->toDateString());
    }

    public function test_tanpa_data_persen_kosong_dan_sekolah_lain_tidak_ikut(): void
    {
        $this->assertNull($this->untuk($this->s, fn () => DasborSekolah::kehadiranSiswa())['persen']);
        $this->assertNull($this->untuk($this->s, fn () => DasborSekolah::kehadiranGuru())['persen']);
        $this->assertSame([], $this->untuk($this->s, fn () => DasborSekolah::perKelas()));

        $b = $this->sekolah('SD B');
        $this->assertNull($this->untuk($b, fn () => DasborSekolah::kehadiranSiswa())['persen']);
        $this->catat($this->kelasA, self::SELASA, [$this->a1->id => ['status' => 'H']]);
        $this->assertNull($this->untuk($b, fn () => DasborSekolah::kehadiranSiswa())['persen'], 'data SD A tidak terlihat di SD B');
    }

    public function test_kehadiran_guru_dan_per_kelas(): void
    {
        $this->untuk($this->s, function () {
            StaffAttendance::create(['staff_id' => $this->stafGuruMtk->id, 'date' => self::SELASA, 'status' => 'H']);
            StaffAttendance::create(['staff_id' => $this->stafWaliA->id, 'date' => self::SELASA, 'status' => 'S']);
        });
        $this->catat($this->kelasA, self::SELASA, [$this->a1->id => ['status' => 'H'], $this->a2->id => ['status' => 'A']]);
        $this->catat($this->kelasB, self::SELASA, [$this->b1->id => ['status' => 'H']]);

        $this->assertSame(50, $this->untuk($this->s, fn () => DasborSekolah::kehadiranGuru())['persen']);
        $per = $this->untuk($this->s, fn () => DasborSekolah::perKelas());
        $this->assertSame([['kelas' => '4A', 'persen' => 50, 'hadir' => 1, 'total' => 2], ['kelas' => '4B', 'persen' => 100, 'hadir' => 1, 'total' => 1]], $per);
    }

    public function test_rapor_disahkan_nol_dari_siswa_aktif(): void
    {
        $r = $this->untuk($this->s, fn () => DasborSekolah::raporDisahkan());

        $this->assertSame(0, $r['jumlah']);
        $this->assertSame(3, $r['total']);
        $this->assertSame(0, $r['persen']);
    }

    public function test_tanggal_tercatat_hanya_bulan_itu(): void
    {
        $this->catat($this->kelasA, self::SELASA, [$this->a1->id => ['status' => 'H']]);
        $this->catat($this->kelasA, '2026-10-30', [$this->a1->id => ['status' => 'H']]);

        $d = $this->untuk($this->s, fn () => DasborSekolah::tanggalTercatat(now()));

        $this->assertSame([self::SELASA], $d);
    }

    public function test_kartu_tampil_untuk_pengelola_dan_tidak_untuk_guru_atau_wali(): void
    {
        foreach ([RingkasanRing::class, KartuSorot::class, KehadiranPerKelas::class, KalenderBulan::class] as $w) {
            $this->masukPanel($this->akunAdmin, 'sekolah', $this->s);
            $this->assertTrue($w::canView(), $w);
            $this->masukPanel($this->guruMtk, 'sekolah', $this->s);
            $this->assertFalse($w::canView(), $w.' guru');
            $this->masukPanel($this->waliA, 'sekolah', $this->s);
            $this->assertFalse($w::canView(), $w.' wali');
        }
    }

    public function test_halaman_dasbor_menampilkan_kartu_dan_kelas(): void
    {
        $this->catat($this->kelasA, self::SELASA, [$this->a1->id => ['status' => 'H'], $this->a2->id => ['status' => 'H']]);
        $this->masukPanel($this->akunAdmin, 'sekolah', $this->s);

        Livewire::test(Dasbor::class)->assertSeeHtml('data-testid="ringkasan-ring"')->assertSee('Kehadiran siswa')->assertSee('Kehadiran per kelas')->assertSee('4A')
            ->assertSeeHtml('data-testid="kalender-bulan"')->assertSee('Siswa aktif')->assertSee('100%');
    }
}
