<?php

namespace Tests\Feature\Penilaian;

use App\Models\AssessmentComponent;
use App\Models\Enrollment;
use App\Models\Score;
use App\Modules\Penilaian\CatatNilai;
use App\Support\Peran;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

/**
 * Kerangka bersama penilaian. Sekolah punya kelas 4A (wali: Bu Wali A) dan 4B, tiga mata pelajaran, komponen
 * Ulangan Harian 30%, UTS 30%, UAS 40%, dan semester 1 yang aktif.
 *   MTK di 4A: Pak MTK   IPA di 4A: Bu IPA   MTK di 4B: Pak MTK   IND di 4A: Bu Wali A
 */
abstract class NilaiTestCase extends TestCase
{
    // setUp milik trait tertimpa setUp kelas ini, jadi dipanggil lewat alias agar peran tetap terisi.
    use SiapkanSekolah { setUp as private setUpSekolah; }

    protected $s;

    protected $y;

    protected $sem;

    protected $kelasA;

    protected $kelasB;

    protected $a1;

    protected $a2;

    protected $b1;

    protected $mtk;

    protected $ipa;

    protected $ind;

    protected $uh;

    protected $uts;

    protected $uas;

    protected $akunAdmin;

    protected $akunKepala;

    protected $akunTu;

    protected $guruMtk;

    protected $guruIpa;

    protected $waliA;

    protected $waliB;

    protected $stafMtk;

    protected $stafIpa;

    protected $stafWaliA;

    protected function setUp(): void
    {
        $this->setUpSekolah();
        Carbon::setTestNow('2026-11-12 09:00:00');

        $this->s = $this->sekolah('SD A');
        $this->y = $this->tahunAjaran($this->s, '2026/2027', '2026-07-01', '2027-06-30');
        $this->sem = $this->semester($this->s, $this->y, 1, '2026-07-01', '2026-12-31');
        $this->untuk($this->s, fn () => $this->sem->activate());
        $this->kelasA = $this->kelas($this->s, $this->y, '4A');
        $this->kelasB = $this->kelas($this->s, $this->y, '4B');
        $this->a1 = $this->siswaDi($this->s, 'Siswa A1');
        $this->a2 = $this->siswaDi($this->s, 'Siswa A2');
        $this->b1 = $this->siswaDi($this->s, 'Siswa B1');
        $this->untuk($this->s, function () {
            Enrollment::tempatkan($this->a1, $this->kelasA);
            Enrollment::tempatkan($this->a2, $this->kelasA);
            Enrollment::tempatkan($this->b1, $this->kelasB);
        });

        $this->akunAdmin = $this->adminDari($this->s);
        $this->akunKepala = $this->penggunaSekolah($this->s, Peran::KEPALA_SEKOLAH, 'Kepala');
        $this->akunTu = $this->penggunaSekolah($this->s, Peran::STAF_TU, 'TU');

        $this->guruMtk = $this->penggunaSekolah($this->s, Peran::GURU_MAPEL, 'Pak MTK');
        $this->stafMtk = $this->guruDi($this->s, 'Pak MTK', $this->guruMtk);
        $this->guruIpa = $this->penggunaSekolah($this->s, Peran::GURU_MAPEL, 'Bu IPA');
        $this->stafIpa = $this->guruDi($this->s, 'Bu IPA', $this->guruIpa);
        $this->waliA = $this->penggunaSekolah($this->s, Peran::WALI_KELAS, 'Wali A');
        $this->stafWaliA = $this->guruDi($this->s, 'Bu Wali A', $this->waliA);
        $this->waliB = $this->penggunaSekolah($this->s, Peran::WALI_KELAS, 'Wali B');
        $stafWaliB = $this->guruDi($this->s, 'Bu Wali B', $this->waliB);
        $this->untuk($this->s, fn () => $this->kelasA->update(['homeroom_staff_id' => $this->stafWaliA->id]));
        $this->untuk($this->s, fn () => $this->kelasB->update(['homeroom_staff_id' => $stafWaliB->id]));

        $this->mtk = $this->mapel($this->s, 'MTK', 'Matematika');
        $this->ipa = $this->mapel($this->s, 'IPA', 'IPA');
        $this->ind = $this->mapel($this->s, 'IND', 'Bahasa Indonesia');
        $this->penugasan($this->s, $this->kelasA, $this->mtk, $this->stafMtk);
        $this->penugasan($this->s, $this->kelasA, $this->ipa, $this->stafIpa);
        $this->penugasan($this->s, $this->kelasA, $this->ind, $this->stafWaliA);
        $this->penugasan($this->s, $this->kelasB, $this->mtk, $this->stafMtk);

        $this->uh = $this->komponen('Ulangan Harian', 30, 1);
        $this->uts = $this->komponen('UTS', 30, 2);
        $this->uas = $this->komponen('UAS', 40, 3);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function komponen(string $nama, int|float $bobot, int|float $urut = 1, bool $aktif = true): AssessmentComponent
    {
        return $this->untuk($this->s, fn () => AssessmentComponent::create(['name' => $nama, 'weight' => $bobot, 'sort_order' => $urut, 'active' => $aktif]));
    }

    /** Menyimpan nilai lewat layanan resmi. $nilai: id siswa => [UH, UTS, UAS] atau id komponen => nilai. */
    protected function nilai($oleh, $kelas, $mapel, array $nilai, $semester = null): int
    {
        return $this->untuk($this->s, fn () => CatatNilai::handle($oleh, $semester ?? $this->sem, $kelas, $mapel, $nilai));
    }

    protected function isiPenuh($siswa, $mapel, $kelas, array $tiga, $oleh = null): void
    {
        $this->nilai($oleh ?? $this->akunAdmin, $kelas, $mapel, [$siswa->id => [$this->uh->id => $tiga[0], $this->uts->id => $tiga[1], $this->uas->id => $tiga[2]]]);
    }

    protected function skor($siswa, $mapel, $komponen): ?Score
    {
        return Score::withoutGlobalScopes()->where('student_id', $siswa->id)->where('subject_id', $mapel->id)->where('component_id', $komponen->id)->first();
    }

    protected function jumlahSkor(): int
    {
        return Score::withoutGlobalScopes()->count();
    }

    protected function pindahkanSiswa($siswa, $dari, $ke): void
    {
        $lama = Enrollment::withoutGlobalScopes()->where('student_id', $siswa->id)->where('classroom_id', $dari->id)->firstOrFail();
        DB::table('enrollments')->where('id', $lama->id)->update(['aktif' => null, 'left_at' => '2026-11-10']);
        $this->untuk($this->s, fn () => Enrollment::tempatkan($siswa, $ke));
    }
}
