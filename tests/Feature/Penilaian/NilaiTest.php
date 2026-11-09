<?php

namespace Tests\Feature\Penilaian;

use App\Models\AssessmentComponent;
use App\Models\Score;
use App\Models\TeachingAssignment;
use App\Support\Peran;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Activity;

class NilaiTest extends NilaiTestCase
{
    private function gagalValidasi(callable $f, string $kunci, ?string $isi = null): void
    {
        try {
            $f();
            $this->fail('Seharusnya ditolak dengan ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($kunci, $e->errors());
            if ($isi) {
                $this->assertStringContainsString($isi, $e->errors()[$kunci][0]);
            }
        }
    }

    private function buatSkor(array $lain = []): Score
    {
        return $this->untuk($this->s, fn () => Score::create($lain + ['semester_id' => $this->sem->id, 'student_id' => $this->a1->id,
            'classroom_id' => $this->kelasA->id, 'subject_id' => $this->mtk->id, 'component_id' => $this->uh->id, 'value' => 80]));
    }

    // ---------- komponen ----------

    public function test_komponen_dirapikan_dan_bobotnya_dibatasi(): void
    {
        $this->gagalValidasi(fn () => $this->komponen('X', 0), 'weight');
        $this->gagalValidasi(fn () => $this->komponen('X', 101), 'weight');
        $this->gagalValidasi(fn () => $this->komponen('X', 10 + 0.5), 'weight');
        $this->gagalValidasi(fn () => $this->komponen('   ', 10), 'name');
        $this->gagalValidasi(fn () => $this->komponen('X', 10, 0), 'sort_order');
        $this->assertSame('Tugas', $this->komponen('  Tugas  ', 10, 4)->name);
    }

    public function test_nama_komponen_unik_per_sekolah_dan_boleh_sama_antar_sekolah(): void
    {
        $b = $this->sekolah('SD B');
        $this->untuk($b, fn () => AssessmentComponent::create(['name' => 'UTS', 'weight' => 50])); // sekolah lain: sah

        $this->expectException(QueryException::class);
        $this->komponen('UTS', 10, 9);
    }

    public function test_komponen_sekolah_lain_tidak_terbaca(): void
    {
        $b = $this->sekolah('SD B');
        $this->actingAs($this->adminDari($b));

        $this->assertSame(0, AssessmentComponent::count());
    }

    // ---------- aturan satu nilai ----------

    public function test_nilai_valid_tersimpan_dengan_dua_desimal_dan_pencatat(): void
    {
        $this->nilai($this->akunAdmin, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => '85,555']]);

        $s = $this->skor($this->a1, $this->mtk, $this->uh);
        $this->assertSame('85.56', (string) $s->value);
        $this->assertSame($this->akunAdmin->id, $s->recorded_by);
        $this->assertSame($this->s->id, $s->school_id);
        $this->assertSame($this->kelasA->id, $s->classroom_id);
    }

    public function test_nilai_di_luar_rentang_atau_bukan_angka_ditolak(): void
    {
        foreach ([101, -0.01, 'abc', null] as $salah) {
            $this->gagalValidasi(fn () => $this->buatSkor(['value' => $salah]), 'value');
        }
        $this->buatSkor(['value' => 0]);      // batas bawah sah
        $this->buatSkor(['student_id' => $this->a2->id, 'value' => 100]); // batas atas sah
        $this->assertSame(2, $this->jumlahSkor());
    }

    public function test_siswa_harus_terdaftar_di_kelas_itu_pada_tahun_ajaran_semester(): void
    {
        $this->gagalValidasi(fn () => $this->buatSkor(['student_id' => $this->b1->id]), 'student_id', 'tidak terdaftar di kelas 4A');
    }

    public function test_mata_pelajaran_harus_diampu_di_kelas_itu(): void
    {
        $fisika = $this->mapel($this->s, 'FIS', 'Fisika');

        $this->gagalValidasi(fn () => $this->buatSkor(['subject_id' => $fisika->id]), 'subject_id', 'belum punya guru pengampu');
    }

    public function test_komponen_yang_dihentikan_tidak_bisa_diisi(): void
    {
        $this->untuk($this->s, fn () => $this->uas->update(['active' => false]));

        $this->gagalValidasi(fn () => $this->buatSkor(['component_id' => $this->uas->id]), 'component_id', 'sudah dihentikan');
    }

    public function test_data_sekolah_lain_tidak_bisa_dipakai(): void
    {
        $b = $this->sekolah('SD B');
        $komponenB = $this->untuk($b, fn () => AssessmentComponent::create(['name' => 'UH B', 'weight' => 100]));
        $siswaB = $this->siswaDi($b, 'Siswa B');

        $this->gagalValidasi(fn () => $this->buatSkor(['component_id' => $komponenB->id]), 'component_id');
        $this->gagalValidasi(fn () => $this->buatSkor(['student_id' => $siswaB->id]), 'student_id');
        $this->assertSame(0, $this->jumlahSkor());
    }

    public function test_kunci_nilai_tidak_bisa_diubah_hanya_nilainya(): void
    {
        $s = $this->buatSkor();
        $this->untuk($this->s, fn () => $s->update(['value' => 90]));
        $this->assertSame('90.00', (string) $s->fresh()->value);

        foreach (['student_id' => $this->a2->id, 'subject_id' => $this->ipa->id, 'component_id' => $this->uts->id, 'semester_id' => 999] as $kolom => $nilai) {
            try {
                $this->untuk($this->s, fn () => $s->fresh()->update([$kolom => $nilai]));
                $this->fail("$kolom berhasil diubah");
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---------- penjaga di basis data ----------

    public function test_basis_data_menolak_nilai_ganda_lewat_sql_langsung(): void
    {
        $this->buatSkor();

        $this->expectException(QueryException::class);
        DB::table('scores')->insert(['school_id' => $this->s->id, 'semester_id' => $this->sem->id, 'student_id' => $this->a1->id,
            'classroom_id' => $this->kelasA->id, 'subject_id' => $this->mtk->id, 'component_id' => $this->uh->id, 'value' => 50]);
    }

    public function test_basis_data_menolak_mata_pelajaran_yang_tidak_diampu_dan_data_lintas_sekolah(): void
    {
        $fisika = $this->mapel($this->s, 'FIS', 'Fisika');
        $b = $this->sekolah('SD B');
        $siswaB = $this->siswaDi($b, 'Siswa B');
        $dasar = ['school_id' => $this->s->id, 'semester_id' => $this->sem->id, 'student_id' => $this->a1->id, 'classroom_id' => $this->kelasA->id,
            'subject_id' => $this->mtk->id, 'component_id' => $this->uh->id, 'value' => 50];

        foreach ([
            'mapel tanpa penugasan' => ['subject_id' => $fisika->id],
            'siswa sekolah lain' => ['student_id' => $siswaB->id],
        ] as $nama => $ubah) {
            try {
                DB::table('scores')->insert($ubah + $dasar);
                $this->fail("SQL langsung lolos untuk $nama");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_komponen_penugasan_dan_siswa_yang_punya_nilai_tidak_bisa_dihapus(): void
    {
        $this->buatSkor();
        $penugasan = TeachingAssignment::withoutGlobalScopes()->where('classroom_id', $this->kelasA->id)->where('subject_id', $this->mtk->id)->firstOrFail();

        foreach ([['assessment_components', $this->uh->id], ['teaching_assignments', $penugasan->id], ['students', $this->a1->id]] as [$tabel, $id]) {
            try {
                DB::table($tabel)->where('id', $id)->delete();
                $this->fail("$tabel yang punya nilai berhasil dihapus");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---------- riwayat nilai ----------

    public function test_perubahan_nilai_tercatat_dengan_nilai_lama_dan_baru(): void
    {
        $this->actingAs($this->akunAdmin);
        $this->nilai($this->akunAdmin, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 70]]);
        $this->nilai($this->akunAdmin, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 85]]);

        $s = $this->skor($this->a1, $this->mtk, $this->uh);
        $log = Activity::where('log_name', 'nilai')->where('subject_id', $s->id)->orderBy('id')->get();
        $this->assertSame(['created', 'updated'], $log->pluck('event')->all());
        // activitylog v5 menyimpan perubahan atribut di kolom attribute_changes
        $this->assertSame(85.0, (float) $log[1]->attribute_changes['attributes']['value']);
        $this->assertSame(70.0, (float) $log[1]->attribute_changes['old']['value']);
        $this->assertSame($this->akunAdmin->id, $log[1]->causer_id);
    }

    // ---------- layanan pencatatan ----------

    public function test_guru_pengampu_menyimpan_nilai_kelasnya_dan_kosong_berarti_tidak_diubah(): void
    {
        $n = $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [
            $this->a1->id => [$this->uh->id => '80', $this->uts->id => '90', $this->uas->id => ''],
            $this->a2->id => [$this->uh->id => 70, $this->uts->id => null],
        ]);

        $this->assertSame(3, $n);
        $this->assertNull($this->skor($this->a1, $this->mtk, $this->uas), 'kosong tidak boleh disimpan sebagai nol');
        $this->assertSame($this->guruMtk->id, $this->skor($this->a1, $this->mtk, $this->uh)->recorded_by);
    }

    public function test_menyimpan_nilai_yang_sama_tidak_dihitung_berubah(): void
    {
        $data = [$this->a1->id => [$this->uh->id => 80, $this->uts->id => 90]];
        $this->assertSame(2, $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, $data));
        $this->assertSame(0, $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, $data));
        $this->assertSame(1, $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 81, $this->uts->id => 90]]));
    }

    public function test_satu_nilai_salah_membatalkan_seluruh_penyimpanan(): void
    {
        $this->gagalValidasi(fn () => $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [
            $this->a1->id => [$this->uh->id => 80],
            $this->a2->id => [$this->uh->id => 150],
        ]), 'value');

        $this->assertSame(0, $this->jumlahSkor(), 'nilai siswa pertama tidak boleh tersimpan');
        $this->gagalValidasi(fn () => $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 'delapan puluh']]), 'value');
    }

    public function test_siswa_kelas_lain_dalam_daftar_membatalkan_semuanya(): void
    {
        $this->gagalValidasi(fn () => $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [
            $this->a1->id => [$this->uh->id => 80],
            $this->b1->id => [$this->uh->id => 80],
        ]), 'student_id');

        $this->assertSame(0, $this->jumlahSkor());
    }

    public function test_komponen_tidak_aktif_atau_tidak_dikenal_ditolak_dan_tanpa_komponen_aktif_ditolak(): void
    {
        $this->gagalValidasi(fn () => $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [99999 => 80]]), 'component_id');

        foreach ([$this->uh, $this->uts, $this->uas] as $k) {
            $this->untuk($this->s, fn () => $k->update(['active' => false]));
        }
        $this->gagalValidasi(fn () => $this->nilai($this->akunAdmin, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 80]]), 'component_id', 'Belum ada komponen');
    }

    public function test_hanya_pengampu_dan_admin_yang_boleh_menyimpan_nilai(): void
    {
        $this->assertSame(1, $this->nilai($this->akunAdmin, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 80]])); // admin boleh

        $tidakBerhak = [
            'guru IPA di MTK' => [$this->guruIpa, $this->kelasA, $this->mtk],
            'wali kelas A di MTK (bukan pengampu)' => [$this->waliA, $this->kelasA, $this->mtk],
            'guru MTK di IPA' => [$this->guruMtk, $this->kelasA, $this->ipa],
            'wali kelas B' => [$this->waliB, $this->kelasA, $this->ind],
            'kepala sekolah' => [$this->akunKepala, $this->kelasA, $this->mtk],
            'staf TU' => [$this->akunTu, $this->kelasA, $this->mtk],
            'guru BK' => [$this->penggunaSekolah($this->s, Peran::GURU_BK), $this->kelasA, $this->mtk],
            'staf keuangan' => [$this->penggunaSekolah($this->s, Peran::STAF_KEUANGAN), $this->kelasA, $this->mtk],
        ];
        foreach ($tidakBerhak as $nama => [$akun, $kelas, $mapel]) {
            try {
                $this->nilai($akun, $kelas, $mapel, [$this->a2->id => [$this->uh->id => 10]]);
                $this->fail("$nama berhasil menyimpan nilai");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(1, $this->jumlahSkor());
    }

    public function test_wali_kelas_yang_juga_pengampu_boleh_menilai_mata_pelajarannya(): void
    {
        $this->assertSame(1, $this->nilai($this->waliA, $this->kelasA, $this->ind, [$this->a1->id => [$this->uh->id => 88]]));
    }

    public function test_guru_nonaktif_atau_tanpa_catatan_guru_tidak_bisa_menilai(): void
    {
        $tanpaStaf = $this->penggunaSekolah($this->s, Peran::GURU_MAPEL, 'Tanpa Staf');
        try {
            $this->nilai($tanpaStaf, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 80]]);
            $this->fail('tanpa catatan guru berhasil menilai');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->untuk($this->s, fn () => $this->stafMtk->update(['status' => 'nonaktif']));
        $this->expectException(AuthorizationException::class);
        $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 80]]);
    }

    public function test_admin_sekolah_lain_tidak_bisa_menilai(): void
    {
        $b = $this->sekolah('SD B');

        $this->expectException(AuthorizationException::class);
        $this->nilai($this->adminDari($b), $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 80]]);
    }

    public function test_guru_hanya_bisa_mengisi_semester_aktif_sedangkan_admin_boleh_semester_lain(): void
    {
        $sem2 = $this->semester($this->s, $this->y, 2, '2027-01-01', '2027-06-30'); // tidak aktif

        $this->gagalValidasi(fn () => $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 80]], $sem2), 'semester_id', 'sedang aktif');
        $this->assertSame(1, $this->nilai($this->akunAdmin, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 80]], $sem2));
    }

    public function test_kelas_dari_tahun_ajaran_lain_dengan_semester_ini_ditolak(): void
    {
        $y2 = $this->tahunAjaran($this->s, '2027/2028', '2027-07-01', '2028-06-30');
        $kelasLain = $this->kelas($this->s, $y2, '5A', 5);

        $this->gagalValidasi(fn () => $this->nilai($this->akunAdmin, $kelasLain, $this->mtk, [$this->a1->id => [$this->uh->id => 80]]), 'classroom_id');
    }

    public function test_nilai_mengikuti_siswa_yang_pindah_kelas(): void
    {
        $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 80]]);
        $this->pindahkanSiswa($this->a1, $this->kelasA, $this->kelasB);

        // guru MTK juga mengampu 4B: ia bisa melengkapi nilai a1 di kelas barunya tanpa membuat nilai ganda
        $this->nilai($this->guruMtk, $this->kelasB, $this->mtk, [$this->a1->id => [$this->uts->id => 90, $this->uh->id => 82]]);

        $this->assertSame(2, $this->jumlahSkor());
        $this->assertSame($this->kelasB->id, $this->skor($this->a1, $this->mtk, $this->uh)->classroom_id, 'kelas pada nilai ikut kelas penilaian terakhir');
        $this->assertSame('82.00', (string) $this->skor($this->a1, $this->mtk, $this->uh)->value);
    }
}
