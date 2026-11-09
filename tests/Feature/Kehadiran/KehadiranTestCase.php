<?php

namespace Tests\Feature\Kehadiran;

use App\Models\Enrollment;
use App\Models\ScheduleEntry;
use App\Support\Peran;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

/**
 * Kerangka bersama. Waktu dibekukan pada Kamis 12 November 2026 supaya hari dalam seminggu dan "hari ini" pasti:
 *   Senin 9 Nov, Selasa 10 Nov, Rabu 11 Nov, Kamis 12 Nov (hari ini), Jumat 13 Nov (masa depan).
 */
abstract class KehadiranTestCase extends TestCase
{
    // setUp milik trait tertimpa setUp kelas ini, jadi dipanggil lewat alias agar peran tetap terisi.
    use SiapkanSekolah { setUp as private setUpSekolah; }

    protected const SENIN = '2026-11-09';

    protected const SELASA = '2026-11-10';

    protected const RABU = '2026-11-11';

    protected const KAMIS = '2026-11-12';

    protected const JUMAT = '2026-11-13';

    protected $s;

    protected $y;

    protected $kelasA;

    protected $kelasB;

    protected $a1;

    protected $a2;

    protected $b1;

    protected $akunAdmin;

    protected $akunTu;

    protected $akunKepala;

    protected $waliA;

    protected $waliB;

    protected $guruMtk;

    protected $stafWaliA;

    protected $stafGuruMtk;

    protected $mtk;

    protected $jam1;

    protected $entriSenin;

    protected function setUp(): void
    {
        $this->setUpSekolah();
        Carbon::setTestNow(self::KAMIS.' 09:00:00');

        $this->s = $this->sekolah('SD A');
        $this->y = $this->tahunAjaran($this->s, '2026/2027', '2026-07-01', '2027-06-30');
        $this->kelasA = $this->kelas($this->s, $this->y, '4A');
        $this->kelasB = $this->kelas($this->s, $this->y, '4B');
        $this->a1 = $this->siswaDi($this->s, 'Siswa A1');
        $this->a2 = $this->siswaDi($this->s, 'Siswa A2');
        $this->b1 = $this->siswaDi($this->s, 'Siswa B1');
        $this->daftarkan($this->a1, $this->kelasA);
        $this->daftarkan($this->a2, $this->kelasA);
        $this->daftarkan($this->b1, $this->kelasB);

        $this->akunAdmin = $this->adminDari($this->s);
        $this->akunTu = $this->penggunaSekolah($this->s, Peran::STAF_TU, 'TU');
        $this->akunKepala = $this->penggunaSekolah($this->s, Peran::KEPALA_SEKOLAH, 'Kepala');

        $this->waliA = $this->penggunaSekolah($this->s, Peran::WALI_KELAS, 'Wali A');
        $this->stafWaliA = $this->guruDi($this->s, 'Bu Wali A', $this->waliA);
        $this->waliB = $this->penggunaSekolah($this->s, Peran::WALI_KELAS, 'Wali B');
        $stafWaliB = $this->guruDi($this->s, 'Bu Wali B', $this->waliB);
        $this->untuk($this->s, fn () => $this->kelasA->update(['homeroom_staff_id' => $this->stafWaliA->id]));
        $this->untuk($this->s, fn () => $this->kelasB->update(['homeroom_staff_id' => $stafWaliB->id]));

        $this->guruMtk = $this->penggunaSekolah($this->s, Peran::GURU_MAPEL, 'Guru MTK');
        $this->stafGuruMtk = $this->guruDi($this->s, 'Pak MTK', $this->guruMtk);
        $this->mtk = $this->mapel($this->s, 'MTK', 'Matematika');
        $this->penugasan($this->s, $this->kelasA, $this->mtk, $this->stafGuruMtk);
        $this->jam1 = $this->jam($this->s, 1, '07:00', '07:45');
        $this->entriSenin = $this->jadwal($this->s, $this->kelasA, $this->mtk, $this->jam1, 1); // Senin
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Menempatkan siswa lalu memundurkan tanggal masuknya, supaya kehadiran tanggal lampau sah. */
    protected function daftarkan($siswa, $kelas, string $sejak = '2026-07-20'): Enrollment
    {
        $e = $this->untuk($this->s, fn () => Enrollment::tempatkan($siswa, $kelas));
        DB::table('enrollments')->where('id', $e->id)->update(['joined_at' => $sejak]);

        return $e->fresh();
    }

    protected function entri(string $kode = 'IPA', string $nama = 'IPA', int $hari = 2): ScheduleEntry
    {
        $mapel = $this->mapel($this->s, $kode, $nama);
        $this->penugasan($this->s, $this->kelasA, $mapel, $this->stafGuruMtk);
        $jam = $this->jam($this->s, 2 + random_int(0, 0), '08:00', '08:45', false, 'Jam ke-2');

        return $this->jadwal($this->s, $this->kelasA, $mapel, $jam, $hari);
    }
}
