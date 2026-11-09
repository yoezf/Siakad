<?php

namespace Tests\Feature\Penilaian;

use App\Filament\Sekolah\Pages\InputNilai;
use App\Filament\Sekolah\Pages\RekapNilai;
use App\Filament\Sekolah\Resources\AssessmentComponents\Pages\ManageAssessmentComponents;
use App\Filament\Sekolah\Resources\Subjects\Pages\ManageSubjects;
use App\Models\AssessmentComponent;
use App\Models\Subject;
use App\Support\Peran;
use Livewire\Livewire;

class PanelNilaiTest extends NilaiTestCase
{
    private function masuk($akun): void
    {
        $this->masukPanel($akun, 'sekolah', $this->s);
    }

    private function nilaiSimpan(int $siswaId, int $mapelId, int $komponenId): ?string
    {
        return \App\Models\Score::withoutGlobalScopes()->where('student_id', $siswaId)->where('subject_id', $mapelId)->where('component_id', $komponenId)->value('value');
    }

    // ---------- komponen penilaian ----------

    public function test_admin_menambah_komponen_lewat_formulir(): void
    {
        $this->masuk($this->akunAdmin);

        Livewire::test(ManageAssessmentComponents::class)
            ->callAction('create', ['name' => 'Tugas', 'weight' => 10, 'sort_order' => 4, 'active' => true])->assertHasNoActionErrors();

        $this->assertSame(10, AssessmentComponent::withoutGlobalScopes()->where('name', 'Tugas')->value('weight'));
    }

    public function test_formulir_komponen_menolak_nama_kembar_dan_bobot_tidak_sah(): void
    {
        $this->masuk($this->akunAdmin);

        // Komponen baru untuk tiap percobaan: setelah galat validasi, modal pada komponen lama tetap terbuka.
        foreach ([
            [['name' => 'UTS', 'weight' => 10, 'sort_order' => 4], 'name'],
            [['name' => 'Baru1', 'weight' => 0, 'sort_order' => 4], 'weight'],
            [['name' => 'Baru2', 'weight' => 101, 'sort_order' => 4], 'weight'],
            [['name' => 'Baru3', 'weight' => 10.5, 'sort_order' => 4], 'weight'],
            [['name' => 'Baru4', 'sort_order' => 4], 'weight'],
        ] as [$data, $kolom]) {
            Livewire::test(ManageAssessmentComponents::class)->callAction('create', $data)->assertHasActionErrors([$kolom]);
        }

        $this->assertSame(3, AssessmentComponent::withoutGlobalScopes()->count());
    }

    public function test_nama_komponen_yang_sama_boleh_dipakai_sekolah_lain(): void
    {
        $b = $this->sekolah('SD B');
        $this->masukPanel($this->adminDari($b), 'sekolah', $b);

        Livewire::test(ManageAssessmentComponents::class)->callAction('create', ['name' => 'UTS', 'weight' => 50, 'sort_order' => 1])->assertHasNoActionErrors();
    }

    public function test_komponen_diubah_dan_dihentikan_lewat_formulir(): void
    {
        $this->masuk($this->akunAdmin);

        Livewire::test(ManageAssessmentComponents::class)
            ->callTableAction('edit', $this->uas, ['name' => 'UAS', 'weight' => 35, 'sort_order' => 3, 'active' => false])->assertHasNoTableActionErrors();

        $this->assertSame(35, $this->uas->fresh()->weight);
        $this->assertFalse($this->uas->fresh()->active);
    }

    public function test_total_bobot_ditampilkan_dan_peringatan_bila_bukan_100(): void
    {
        $this->masuk($this->akunAdmin);
        Livewire::test(ManageAssessmentComponents::class)->assertSee('Total bobot komponen aktif: 100.');

        $this->komponen('Tugas', 10, 4);
        Livewire::test(ManageAssessmentComponents::class)->assertSee('Total bobot komponen aktif: 110.')->assertSee('Disarankan 100');
    }

    public function test_komponen_yang_dipakai_nilai_tidak_bisa_dihapus_tetapi_yang_bebas_bisa(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);
        $bebas = $this->komponen('Bebas', 5, 9);
        $this->masuk($this->akunAdmin);

        Livewire::test(ManageAssessmentComponents::class)->callTableAction('delete', $this->uh)->assertNotified('Komponen ini sudah dipakai nilai.');
        $this->assertTrue(AssessmentComponent::withoutGlobalScopes()->whereKey($this->uh->id)->exists());

        Livewire::test(ManageAssessmentComponents::class)->callTableAction('delete', $bebas);
        $this->assertFalse(AssessmentComponent::withoutGlobalScopes()->whereKey($bebas->id)->exists());
    }

    public function test_hanya_admin_yang_mengelola_komponen_peran_lain_hanya_melihat(): void
    {
        foreach ([Peran::GURU_MAPEL, Peran::WALI_KELAS, Peran::KEPALA_SEKOLAH, Peran::STAF_TU] as $peran) {
            $this->masuk($this->penggunaSekolah($this->s, $peran));
            Livewire::test(ManageAssessmentComponents::class)->assertActionHidden('create')->assertTableActionHidden('edit', $this->uh)->assertTableActionHidden('delete', $this->uh);
        }
    }

    public function test_guru_tidak_bisa_mengubah_komponen_walau_memanggil_aksi_langsung(): void
    {
        $this->masuk($this->guruMtk);
        foreach ([
            fn () => Livewire::test(ManageAssessmentComponents::class)->callAction('create', ['name' => 'Siluman', 'weight' => 50, 'sort_order' => 9]),
            fn () => Livewire::test(ManageAssessmentComponents::class)->callTableAction('edit', $this->uh, ['name' => 'UH', 'weight' => 99, 'sort_order' => 1, 'active' => true]),
            fn () => Livewire::test(ManageAssessmentComponents::class)->callTableAction('delete', $this->uh),
        ] as $coba) {
            try {
                $coba();
            } catch (\Throwable) {
                // aksi tersembunyi ditolak; yang dinilai adalah isi basis data
            }
        }

        $this->assertSame(3, AssessmentComponent::withoutGlobalScopes()->count());
        $this->assertSame(30, $this->uh->fresh()->weight);
    }

    // ---------- KKM di formulir mata pelajaran ----------

    public function test_kkm_khusus_diisi_dikosongkan_dan_dibatasi_lewat_formulir(): void
    {
        $this->masuk($this->akunAdmin);

        Livewire::test(ManageSubjects::class)->callTableAction('edit', $this->mtk, ['code' => 'MTK', 'name' => 'Matematika', 'kkm' => 80])->assertHasNoTableActionErrors();
        $this->assertSame(80, $this->mtk->fresh()->kkm);

        Livewire::test(ManageSubjects::class)->callTableAction('edit', $this->mtk, ['code' => 'MTK', 'name' => 'Matematika', 'kkm' => 101])->assertHasTableActionErrors(['kkm']);
        $this->assertSame(80, $this->mtk->fresh()->kkm);

        Livewire::test(ManageSubjects::class)->callTableAction('edit', $this->mtk, ['code' => 'MTK', 'name' => 'Matematika', 'kkm' => null])->assertHasNoTableActionErrors();
        $this->assertNull($this->mtk->fresh()->kkm, 'dikosongkan harus kembali ke KKM sekolah, bukan 0');
        $this->assertInstanceOf(Subject::class, $this->mtk->fresh());
    }

    // ---------- input nilai ----------

    public function test_guru_hanya_melihat_pilihan_miliknya_dan_menyimpan_nilai(): void
    {
        $this->masuk($this->guruMtk);

        $k = Livewire::test(InputNilai::class);
        $this->assertSame([$this->sem->id => '2026/2027 Ganjil (aktif)'], $k->instance()->opsiSemester());
        $this->assertSame([$this->kelasA->id => '4A', $this->kelasB->id => '4B'], $k->instance()->opsiKelas());
        $this->assertSame([$this->mtk->id => 'Matematika'], $k->instance()->opsiMapel());

        $k->set('kelasId', $this->kelasA->id)->set('mapelId', $this->mtk->id)
            ->assertSee('Siswa A1')->assertSee('Siswa A2')->assertDontSee('Siswa B1')
            ->set("nilai.{$this->a1->id}.{$this->uh->id}", '80')->set("nilai.{$this->a1->id}.{$this->uts->id}", '90,5')->set("nilai.{$this->a1->id}.{$this->uas->id}", '70')
            ->call('simpan')->assertNotified('3 nilai tersimpan.');

        $this->assertSame('90.50', $this->nilaiSimpan($this->a1->id, $this->mtk->id, $this->uts->id));
        $this->assertSame($this->guruMtk->id, \App\Models\Score::withoutGlobalScopes()->value('recorded_by'));
    }

    public function test_nilai_akhir_muncul_setelah_semua_komponen_tersimpan(): void
    {
        $this->masuk($this->guruMtk);

        $k = Livewire::test(InputNilai::class)->set('kelasId', $this->kelasA->id)->set('mapelId', $this->mtk->id)
            ->set("nilai.{$this->a1->id}.{$this->uh->id}", '80')->set("nilai.{$this->a1->id}.{$this->uts->id}", '90')
            ->call('simpan');
        $this->assertNull(collect($k->instance()->baris())->firstWhere(fn ($r) => $r['student']->id === $this->a1->id)['akhir'], 'UAS masih kosong');

        $k->set("nilai.{$this->a1->id}.{$this->uas->id}", '70')->call('simpan');
        $baris = collect($k->instance()->baris())->firstWhere(fn ($r) => $r['student']->id === $this->a1->id);
        $this->assertSame([79, 'C', true], [$baris['bulat'], $baris['predikat'], $baris['tuntas']]);
        $k->assertSee('79');
    }

    public function test_isian_kosong_dimuat_kembali_sebagai_kosong_dan_nilai_tersimpan_dimuat(): void
    {
        $this->nilai($this->akunAdmin, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => '85.5']]);
        $this->masuk($this->guruMtk);

        Livewire::test(InputNilai::class)->set('kelasId', $this->kelasA->id)->set('mapelId', $this->mtk->id)
            ->assertSet("nilai.{$this->a1->id}.{$this->uh->id}", '85.5')->assertSet("nilai.{$this->a1->id}.{$this->uts->id}", null)->assertSet("nilai.{$this->a2->id}.{$this->uh->id}", null);
    }

    public function test_nilai_tidak_sah_dari_formulir_tidak_menyimpan_satu_pun(): void
    {
        $this->masuk($this->guruMtk);

        Livewire::test(InputNilai::class)->set('kelasId', $this->kelasA->id)->set('mapelId', $this->mtk->id)
            ->set("nilai.{$this->a1->id}.{$this->uh->id}", '80')->set("nilai.{$this->a2->id}.{$this->uh->id}", '150')
            ->call('simpan')->assertNotified('Nilai tidak tersimpan');

        $this->assertSame(0, $this->jumlahSkor());
    }

    public function test_guru_mengubah_mata_pelajaran_dari_browser_tidak_bisa_menilai_milik_guru_lain(): void
    {
        $this->masuk($this->guruMtk);

        Livewire::test(InputNilai::class)->set('kelasId', $this->kelasA->id)->set('mapelId', $this->ipa->id)
            ->assertDontSee('Siswa A1')->set("nilai.{$this->a1->id}.{$this->uh->id}", '99')
            ->call('simpan')->assertNotified('Pilih semester, kelas, dan mata pelajaran yang berhak Anda nilai.');

        $this->assertSame(0, $this->jumlahSkor());
    }

    public function test_guru_mengubah_kelas_dari_browser_tidak_bisa_menilai_kelas_yang_bukan_miliknya(): void
    {
        $this->masuk($this->guruIpa); // Bu IPA hanya mengampu IPA di 4A

        Livewire::test(InputNilai::class)->set('kelasId', $this->kelasB->id)->set('mapelId', $this->mtk->id)
            ->assertDontSee('Siswa B1')->call('simpan')->assertNotified('Pilih semester, kelas, dan mata pelajaran yang berhak Anda nilai.');

        $this->assertSame(0, $this->jumlahSkor());
    }

    public function test_guru_mengubah_semester_dari_browser_tidak_bisa_menilai_semester_tidak_aktif(): void
    {
        $sem2 = $this->semester($this->s, $this->y, 2, '2027-01-01', '2027-06-30');
        $this->masuk($this->guruMtk);

        Livewire::test(InputNilai::class)->set('semesterId', $sem2->id)->set('kelasId', $this->kelasA->id)->set('mapelId', $this->mtk->id)
            ->set("nilai.{$this->a1->id}.{$this->uh->id}", '99')->call('simpan')->assertNotified('Pilih semester, kelas, dan mata pelajaran yang berhak Anda nilai.');

        $this->assertSame(0, $this->jumlahSkor());
    }

    public function test_admin_boleh_memilih_semester_tidak_aktif_dan_semua_mata_pelajaran(): void
    {
        $sem2 = $this->semester($this->s, $this->y, 2, '2027-01-01', '2027-06-30');
        $this->masuk($this->akunAdmin);

        $k = Livewire::test(InputNilai::class);
        $this->assertCount(2, $k->instance()->opsiSemester());
        $this->assertCount(3, $k->set('kelasId', $this->kelasA->id)->instance()->opsiMapel());

        $k->set('semesterId', $sem2->id)->set('kelasId', $this->kelasA->id)->set('mapelId', $this->ipa->id)
            ->set("nilai.{$this->a1->id}.{$this->uh->id}", '77')->call('simpan')->assertNotified('1 nilai tersimpan.');
        $this->assertSame('77.00', $this->nilaiSimpan($this->a1->id, $this->ipa->id, $this->uh->id));
    }

    public function test_tanpa_komponen_aktif_halaman_menjelaskan_dan_tidak_menyimpan(): void
    {
        foreach ([$this->uh, $this->uts, $this->uas] as $k) {
            $this->untuk($this->s, fn () => $k->update(['active' => false]));
        }
        $this->masuk($this->guruMtk);

        Livewire::test(InputNilai::class)->set('kelasId', $this->kelasA->id)->set('mapelId', $this->mtk->id)->assertSee('Belum ada komponen penilaian aktif');
    }

    // ---------- rekap nilai ----------

    private function isiBeberapa(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);
        $this->isiPenuh($this->a1, $this->ipa, $this->kelasA, [60, 60, 60]);
        $this->isiPenuh($this->a1, $this->ind, $this->kelasA, [90, 90, 90], $this->waliA);
    }

    public function test_wali_kelas_melihat_semua_mata_pelajaran_kelasnya_dan_rata_rata(): void
    {
        $this->isiBeberapa();
        $this->masuk($this->waliA);

        $k = Livewire::test(RekapNilai::class)->set('mode', 'siswa')->set('kelasId', $this->kelasA->id)->set('siswaId', $this->a1->id);
        $r = $k->instance()->ringkasanSiswa();

        $this->assertCount(3, $r['mapel']);
        $this->assertSame(76.33, $r['rata']);
        $this->assertTrue($r['lengkap']);
        $k->assertSee('Matematika')->assertSee('IPA')->assertSee('Bahasa Indonesia')->assertSee('Rata-rata');
    }

    public function test_guru_mata_pelajaran_hanya_melihat_mata_pelajarannya_dan_tanpa_rata_rata(): void
    {
        $this->isiBeberapa();
        $this->masuk($this->guruMtk);

        $k = Livewire::test(RekapNilai::class)->set('mode', 'siswa')->set('kelasId', $this->kelasA->id)->set('siswaId', $this->a1->id);
        $r = $k->instance()->ringkasanSiswa();

        $this->assertSame(['Matematika'], collect($r['mapel'])->map(fn ($m) => $m['subject']->name)->all());
        $this->assertNull($r['rata'], 'rata-rata semua mata pelajaran tidak boleh bocor ke guru satu mata pelajaran');
        $this->assertFalse($r['penuh']);
        $k->assertSee('Matematika')->assertDontSee('Bahasa Indonesia')->assertDontSee('IPA')->assertSee('hanya tampil bila seluruh mata pelajaran');
    }

    public function test_rekap_per_mata_pelajaran_menampilkan_nilai_dan_riwayat(): void
    {
        $this->actingAs($this->akunAdmin);
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);
        $this->masuk($this->akunKepala);

        $k = Livewire::test(RekapNilai::class)->set('mode', 'kelas')->set('kelasId', $this->kelasA->id)->set('mapelId', $this->mtk->id);
        $baris = collect($k->instance()->barisKelas())->keyBy(fn ($r) => $r['student']->name);

        $this->assertSame(79, $baris['Siswa A1']['bulat']);
        $this->assertCount(3, $k->instance()->riwayat());
        $k->assertSee('Siswa A1')->assertSee('Riwayat perubahan nilai')->assertSee('Ulangan Harian');
    }

    public function test_guru_mengubah_mata_pelajaran_atau_kelas_di_rekap_tidak_bisa_mengintip(): void
    {
        $this->isiBeberapa();
        $this->masuk($this->guruMtk);

        $k = Livewire::test(RekapNilai::class)->set('mode', 'kelas')->set('kelasId', $this->kelasA->id)->set('mapelId', $this->ipa->id);
        $this->assertSame([], $k->instance()->barisKelas());
        $this->assertSame([], $k->instance()->riwayat());
        $k->assertDontSee('Siswa A1');

        $k->set('kelasId', $this->kelasB->id)->set('mapelId', $this->ind->id);
        $this->assertSame([], $k->instance()->barisKelas());
    }

    public function test_wali_kelas_lain_tidak_bisa_melihat_rekap_kelas_ini(): void
    {
        $this->isiBeberapa();
        $this->masuk($this->waliB);

        $k = Livewire::test(RekapNilai::class)->set('mode', 'siswa')->set('kelasId', $this->kelasA->id)->set('siswaId', $this->a1->id);

        $this->assertSame([], $k->instance()->ringkasanSiswa()['mapel']);
        $this->assertSame([$this->kelasB->id => '4B'], $k->instance()->opsiKelas());
    }

    public function test_siswa_yang_bukan_di_kelas_terpilih_tidak_bisa_diintip(): void
    {
        $this->isiPenuh($this->b1, $this->mtk, $this->kelasB, [90, 90, 90]);
        $this->masuk($this->waliA); // wali 4A memilih kelasnya sendiri tetapi memasukkan id siswa 4B

        $k = Livewire::test(RekapNilai::class)->set('mode', 'siswa')->set('kelasId', $this->kelasA->id)->set('siswaId', $this->b1->id);

        $this->assertSame([], $k->instance()->ringkasanSiswa()['mapel']);
    }

    // ---------- akses halaman ----------

    public function test_hak_akses_halaman_penilaian(): void
    {
        $harapan = [ // [input-nilai, rekap-nilai, komponen]
            Peran::ADMIN_SEKOLAH => [200, 200, 200], Peran::KEPALA_SEKOLAH => [403, 200, 200], Peran::STAF_TU => [403, 403, 200],
            Peran::WALI_KELAS => [200, 200, 200], Peran::GURU_MAPEL => [200, 200, 200], Peran::GURU_BK => [403, 403, 200], Peran::STAF_KEUANGAN => [403, 403, 200],
        ];
        foreach ($harapan as $peran => $kode) {
            $akun = $peran === Peran::ADMIN_SEKOLAH ? $this->adminDari($this->s) : $this->penggunaSekolah($this->s, $peran);
            foreach (['input-nilai', 'rekap-nilai', 'assessment-components'] as $i => $halaman) {
                $this->flushSession();
                auth()->forgetGuards();
                $this->assertSame($kode[$i], $this->actingAs($akun)->get("/sekolah/{$this->s->slug}/$halaman")->getStatusCode(), "$peran di $halaman");
            }
        }
    }
}
