<?php

namespace Tests\Feature\Penilaian;

use App\Models\Subject;
use App\Modules\Penilaian\Akses;
use App\Modules\Penilaian\Rekap;
use App\Support\Peran;
use Illuminate\Validation\ValidationException;

class RekapNilaiTest extends NilaiTestCase
{
    private function kelas4A($mapel = null): array
    {
        return $this->untuk($this->s, fn () => collect(Rekap::kelas($this->sem, $this->kelasA, $mapel ?? $this->mtk, 70))->keyBy(fn ($r) => $r['student']->name)->all());
    }

    // ---------- rekap satu mata pelajaran ----------

    public function test_rekap_kelas_menghitung_nilai_akhir_ketuntasan_dan_predikat(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);
        $this->isiPenuh($this->a2, $this->mtk, $this->kelasA, [60, 60, 60]);

        $r = $this->kelas4A();

        $this->assertSame([79.0, 79, true, 'C'], [$r['Siswa A1']['akhir'], $r['Siswa A1']['bulat'], $r['Siswa A1']['tuntas'], $r['Siswa A1']['predikat']]);
        $this->assertSame([60.0, 60, false, 'D'], [$r['Siswa A2']['akhir'], $r['Siswa A2']['bulat'], $r['Siswa A2']['tuntas'], $r['Siswa A2']['predikat']]);
        $this->assertSame([$this->uh->id => 80.0, $this->uts->id => 90.0, $this->uas->id => 70.0], $r['Siswa A1']['nilai']);
    }

    public function test_nilai_belum_lengkap_tidak_menghasilkan_nilai_akhir(): void
    {
        $this->nilai($this->akunAdmin, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 100, $this->uts->id => 100]]); // UAS kosong

        $r = $this->kelas4A();

        $this->assertNull($r['Siswa A1']['akhir']);
        $this->assertNull($r['Siswa A1']['tuntas']);
        $this->assertNull($r['Siswa A1']['predikat']);
        $this->assertNull($r['Siswa A2']['akhir'], 'siswa tanpa nilai sama sekali juga null, bukan nol');
    }

    public function test_rekap_hanya_memuat_siswa_kelas_itu_dan_berurutan_nama(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 80, 80]);

        $this->assertSame(['Siswa A1', 'Siswa A2'], array_keys($this->kelas4A()));
    }

    public function test_nilai_mengikuti_siswa_yang_pindah_kelas(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);
        $this->pindahkanSiswa($this->a1, $this->kelasA, $this->kelasB);

        $r = $this->untuk($this->s, fn () => collect(Rekap::kelas($this->sem, $this->kelasB, $this->mtk, 70))->keyBy(fn ($x) => $x['student']->name)->all());

        $this->assertSame(79.0, $r['Siswa A1']['akhir'], 'nilai yang dibuat di 4A tetap dihitung di 4B');
    }

    // ---------- KKM ----------

    public function test_kkm_khusus_mata_pelajaran_menggantikan_kkm_sekolah(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]); // 79
        $this->assertTrue($this->kelas4A()['Siswa A1']['tuntas']);

        $this->untuk($this->s, fn () => $this->mtk->update(['kkm' => 80]));

        $r = $this->kelas4A();
        $this->assertFalse($r['Siswa A1']['tuntas']);
        $this->assertSame('D', $r['Siswa A1']['predikat']);
    }

    public function test_kkm_kosong_ikut_sekolah_dan_isian_kosong_tidak_menjadi_nol(): void
    {
        $this->untuk($this->s, fn () => $this->mtk->update(['kkm' => 80]));
        $this->untuk($this->s, fn () => $this->mtk->fresh()->update(['kkm' => '']));

        $this->assertNull($this->mtk->fresh()->kkm, "'' harus menjadi NULL, bukan 0 (0 membuat semua nilai tuntas)");
        $this->assertSame(70, $this->mtk->fresh()->kkmBerlaku(70));
    }

    public function test_kkm_di_luar_rentang_ditolak(): void
    {
        foreach ([101, -1, 'tinggi'] as $salah) {
            try {
                $this->untuk($this->s, fn () => $this->mtk->update(['kkm' => $salah]));
                $this->fail("KKM $salah lolos");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('kkm', $e->errors());
            }
        }
        $this->untuk($this->s, fn () => $this->mtk->update(['kkm' => 0]));
        $this->assertSame(0, $this->mtk->fresh()->kkm, '0 adalah KKM yang sah, bedakan dari kosong');
    }

    // ---------- komponen berubah ----------

    public function test_komponen_yang_dihentikan_keluar_dari_perhitungan_tetapi_nilainya_tetap_tersimpan(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);
        $this->untuk($this->s, fn () => $this->uas->update(['active' => false]));

        $r = $this->kelas4A();

        $this->assertSame(85.0, $r['Siswa A1']['akhir']); // (80*30 + 90*30) / 60
        $this->assertNotNull($this->skor($this->a1, $this->mtk, $this->uas), 'nilai lama tidak boleh hilang');
    }

    public function test_komponen_baru_membuat_nilai_lama_belum_lengkap(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);
        $this->komponen('Tugas', 10, 4);

        $this->assertNull($this->kelas4A()['Siswa A1']['akhir'], 'komponen baru belum terisi, jadi nilai akhir belum lengkap');
    }

    // ---------- ringkasan satu siswa ----------

    public function test_ringkasan_siswa_mencakup_semua_mata_pelajaran_kelasnya_dan_rata_rata(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);   // 79
        $this->isiPenuh($this->a1, $this->ipa, $this->kelasA, [90, 90, 90]);   // 90

        $r = $this->untuk($this->s, fn () => Rekap::siswa($this->sem, $this->a1, 70));

        $this->assertSame(['Bahasa Indonesia', 'IPA', 'Matematika'], collect($r['mapel'])->map(fn ($m) => $m['subject']->name)->all());
        $this->assertSame(84.5, $r['rata'], 'rata-rata hanya dari mata pelajaran yang sudah punya nilai akhir');
        $this->assertFalse($r['lengkap'], 'Bahasa Indonesia belum dinilai');

        $this->isiPenuh($this->a1, $this->ind, $this->kelasA, [70, 70, 70], $this->waliA);
        $r = $this->untuk($this->s, fn () => Rekap::siswa($this->sem, $this->a1, 70));
        $this->assertTrue($r['lengkap']);
        $this->assertSame(79.67, $r['rata']);
    }

    public function test_ringkasan_siswa_tanpa_nilai_apa_pun(): void
    {
        $r = $this->untuk($this->s, fn () => Rekap::siswa($this->sem, $this->a2, 70));

        $this->assertCount(3, $r['mapel']);
        $this->assertNull($r['rata']);
        $this->assertFalse($r['lengkap']);
    }

    public function test_siswa_pindah_kelas_membawa_mata_pelajaran_kedua_kelas_tanpa_duplikat(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);
        $this->pindahkanSiswa($this->a1, $this->kelasA, $this->kelasB);

        $r = $this->untuk($this->s, fn () => Rekap::siswa($this->sem, $this->a1, 70));

        $nama = collect($r['mapel'])->map(fn ($m) => $m['subject']->name);
        $this->assertSame($nama->unique()->count(), $nama->count(), 'Matematika (ada di 4A dan 4B) tidak boleh muncul dua kali');
        $this->assertCount(3, $r['mapel']);
    }

    // ---------- riwayat ----------

    public function test_riwayat_menampilkan_nilai_lama_baru_pelaku_dan_urutan_terbaru(): void
    {
        $this->actingAs($this->guruMtk);
        $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 70]]);
        $this->actingAs($this->akunAdmin);
        $this->nilai($this->akunAdmin, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 85]]);

        $h = $this->untuk($this->s, fn () => Rekap::riwayat($this->sem, $this->kelasA, $this->mtk));

        $this->assertCount(2, $h);
        $this->assertSame(['Siswa A1', 'Ulangan Harian', '70.00', '85.00', $this->akunAdmin->name], [$h[0]['siswa'], $h[0]['komponen'], $h[0]['lama'], $h[0]['baru'], $h[0]['oleh']], 'terbaru dahulu');
        $this->assertSame([null, '70.00', 'Pak MTK'], [$h[1]['lama'], $h[1]['baru'], $h[1]['oleh']]);
    }

    public function test_riwayat_hanya_memuat_mata_pelajaran_dan_kelas_yang_diminta(): void
    {
        $this->nilai($this->akunAdmin, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 70]]);
        $this->nilai($this->akunAdmin, $this->kelasA, $this->ipa, [$this->a1->id => [$this->uh->id => 60]]);

        $h = $this->untuk($this->s, fn () => Rekap::riwayat($this->sem, $this->kelasA, $this->ipa));

        $this->assertCount(1, $h);
        $this->assertSame('60.00', $h[0]['baru']);
    }

    public function test_rekap_sekolah_lain_tidak_bocor(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);
        $b = $this->sekolah('SD B');

        $this->assertSame([], $this->untuk($b, fn () => Rekap::kelas($this->sem, $this->kelasA, $this->mtk, 70)));
    }

    // ---------- siapa boleh melihat ----------

    public function test_hak_melihat_nilai_per_peran(): void
    {
        $bk = $this->penggunaSekolah($this->s, Peran::GURU_BK);
        $keu = $this->penggunaSekolah($this->s, Peran::STAF_KEUANGAN);
        $b = $this->sekolah('SD B');
        $semua = [$this->mtk, $this->ipa, $this->ind];
        $harapan = [ // [4A: mtk, ipa, ind]  [4B: mtk]
            'admin' => [$this->akunAdmin, [true, true, true], [true]],
            'kepala' => [$this->akunKepala, [true, true, true], [true]],
            'wali A (semua mapel kelasnya)' => [$this->waliA, [true, true, true], [false]],
            'wali B' => [$this->waliB, [false, false, false], [true]],
            'guru MTK (hanya mapelnya)' => [$this->guruMtk, [true, false, false], [true]],
            'guru IPA (hanya mapelnya)' => [$this->guruIpa, [false, true, false], [false]],
            'staf TU' => [$this->akunTu, [false, false, false], [false]],
            'guru BK' => [$bk, [false, false, false], [false]],
            'staf keuangan' => [$keu, [false, false, false], [false]],
            'admin sekolah lain' => [$this->adminDari($b), [false, false, false], [false]],
        ];
        foreach ($harapan as $nama => [$akun, $kelasA, $kelasB]) {
            foreach ($semua as $i => $m) {
                $this->assertSame($kelasA[$i], Akses::bisaMelihat($akun, $this->kelasA, $m), "$nama melihat {$m->name} di 4A");
            }
            $this->assertSame($kelasB[0], Akses::bisaMelihat($akun, $this->kelasB, $this->mtk), "$nama melihat Matematika di 4B");
        }
    }

    public function test_hak_semester_guru_hanya_aktif_admin_semua(): void
    {
        $sem2 = $this->semester($this->s, $this->y, 2, '2027-01-01', '2027-06-30');

        $this->assertTrue(Akses::semesterBoleh($this->guruMtk, $this->sem));
        $this->assertFalse(Akses::semesterBoleh($this->guruMtk, $sem2));
        $this->assertTrue(Akses::semesterBoleh($this->akunAdmin, $sem2));
        $this->assertFalse(Akses::semesterBoleh($this->adminDari($this->sekolah('SD B')), $this->sem), 'semester sekolah lain');
    }

    public function test_kkm_sekolah_diambil_dari_pengaturan_sekolah(): void
    {
        $this->assertSame(70, Akses::kkmSekolah($this->akunAdmin));
        $this->untuk($this->s, fn () => $this->s->update(['settings' => ['kkm' => 75]]));

        $this->assertSame(75, Akses::kkmSekolah($this->akunAdmin->fresh()));
        $this->assertInstanceOf(Subject::class, $this->mtk);
    }
}
