<?php

namespace Tests\Feature\Akademik;

use App\Filament\Sekolah\Resources\Classrooms\Pages\ManageClassrooms;
use App\Filament\Sekolah\Resources\Enrollments\Pages\ManageEnrollments;
use App\Filament\Sekolah\Resources\TeachingAssignments\Pages\ManageTeachingAssignments;
use App\Models\Enrollment;
use App\Models\TeachingAssignment;
use App\Support\Peran;
use Livewire\Livewire;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class PanelPembagianKelasTest extends TestCase
{
    use SiapkanSekolah;

    private function jumlahBerlaku($s): int
    {
        return Enrollment::withoutGlobalScopes()->where('school_id', $s->id)->where('aktif', 1)->count();
    }

    // ---------- tempatkan siswa ----------

    public function test_admin_menempatkan_beberapa_siswa_sekaligus(): void
    {
        $s = $this->sekolah('SD A');
        $kelas = $this->kelas($s, $this->tahunAjaran($s));
        $a = $this->siswaDi($s, 'A');
        $b = $this->siswaDi($s, 'B');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageEnrollments::class)
            ->callAction('tempatkan', ['classroom_id' => $kelas->id, 'student_ids' => [$a->id, $b->id]])
            ->assertHasNoActionErrors()->assertNotified();

        $this->assertSame(2, $this->jumlahBerlaku($s));
    }

    public function test_penempatan_massal_semua_atau_tidak_sama_sekali(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $a4 = $this->kelas($s, $y, '4A');
        $b4 = $this->kelas($s, $y, '4B');
        $sudah = $this->siswaDi($s, 'Sudah Punya Kelas');
        $belum = $this->siswaDi($s, 'Belum Punya Kelas');
        $this->untuk($s, fn () => Enrollment::tempatkan($sudah, $a4));
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        try {
            Livewire::test(ManageEnrollments::class)->callAction('tempatkan', ['classroom_id' => $b4->id, 'student_ids' => [$sudah->id, $belum->id]]);
        } catch (\Throwable) {
            // ditolak oleh validasi pilihan atau oleh aturan penempatan; yang dinilai adalah basis data
        }

        $this->assertSame(1, $this->jumlahBerlaku($s), 'tidak boleh ada yang ditempatkan bila satu siswa bermasalah');
        $this->assertSame($a4->id, Enrollment::withoutGlobalScopes()->where('student_id', $sudah->id)->value('classroom_id'));
    }

    public function test_siswa_sekolah_lain_tidak_bisa_ikut_ditempatkan(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $kelas = $this->kelas($a, $this->tahunAjaran($a));
        $milikA = $this->siswaDi($a, 'Milik A');
        $milikB = $this->siswaDi($b, 'Milik B');
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        try {
            Livewire::test(ManageEnrollments::class)->callAction('tempatkan', ['classroom_id' => $kelas->id, 'student_ids' => [$milikA->id, $milikB->id]]);
        } catch (\Throwable) {
        }

        $this->assertSame(0, Enrollment::withoutGlobalScopes()->count(), 'siswa sekolah lain tidak boleh membuat penempatan terbentuk');
    }

    public function test_siswa_yang_tidak_aktif_tidak_bisa_ditempatkan_lewat_panel(): void
    {
        $s = $this->sekolah('SD A');
        $kelas = $this->kelas($s, $this->tahunAjaran($s));
        $lulus = $this->siswaDi($s, 'Alumni', ['status' => 'lulus']);
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        try {
            Livewire::test(ManageEnrollments::class)->callAction('tempatkan', ['classroom_id' => $kelas->id, 'student_ids' => [$lulus->id]]);
        } catch (\Throwable) {
        }
        $this->assertSame(0, Enrollment::withoutGlobalScopes()->count());
    }

    // ---------- pindahkan ----------

    public function test_admin_memindahkan_siswa_dan_riwayat_tersimpan(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $a = $this->kelas($s, $y, '4A');
        $b = $this->kelas($s, $y, '4B');
        $lama = $this->untuk($s, fn () => Enrollment::tempatkan($this->siswaDi($s), $a));
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageEnrollments::class)
            ->callTableAction('pindahkan', $lama, ['classroom_id' => $b->id, 'note' => 'pindah rombel'])
            ->assertHasNoTableActionErrors()->assertNotified();

        $this->assertFalse($lama->fresh()->isActive());
        $this->assertSame(2, Enrollment::withoutGlobalScopes()->count());
        $this->assertSame($b->id, Enrollment::withoutGlobalScopes()->where('aktif', 1)->value('classroom_id'));
    }

    public function test_pilihan_pindah_hanya_kelas_tahun_yang_sama(): void
    {
        $s = $this->sekolah('SD A');
        $y1 = $this->tahunAjaran($s, '2025/2026');
        $y2 = $this->tahunAjaran($s, '2026/2027', '2026-07-01', '2027-06-30');
        $lama = $this->untuk($s, fn () => Enrollment::tempatkan($this->siswaDi($s), $this->kelas($s, $y1, '4A')));
        $kelasTahunLain = $this->kelas($s, $y2, '5A', 5);
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        try {
            Livewire::test(ManageEnrollments::class)->callTableAction('pindahkan', $lama, ['classroom_id' => $kelasTahunLain->id])->assertHasTableActionErrors(['classroom_id']);
        } catch (\Throwable) {
        }
        $this->assertTrue($lama->fresh()->isActive());
        $this->assertSame(1, Enrollment::withoutGlobalScopes()->count());
    }

    public function test_baris_yang_sudah_ditutup_tidak_punya_tombol_pindahkan(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $lama = $this->untuk($s, fn () => Enrollment::tempatkan($this->siswaDi($s), $this->kelas($s, $y, '4A')));
        $this->untuk($s, fn () => $lama->pindahkan($this->kelas($s, $y, '4B')));
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        // Filter bawaan "hanya yang berlaku" menyembunyikan baris tertutup; dilepas agar barisnya tampil.
        Livewire::test(ManageEnrollments::class)->removeTableFilters()->assertCanSeeTableRecords([$lama->fresh()])->assertTableActionHidden('pindahkan', $lama->fresh());
    }

    // ---------- akses ----------

    public function test_hak_akses_halaman_pembagian_kelas(): void
    {
        $s = $this->sekolah('SD A');
        foreach ([Peran::ADMIN_SEKOLAH => 200, Peran::KEPALA_SEKOLAH => 200, Peran::STAF_TU => 200, Peran::WALI_KELAS => 403, Peran::GURU_MAPEL => 403, Peran::GURU_BK => 403, Peran::STAF_KEUANGAN => 403] as $peran => $harapan) {
            $akun = $peran === Peran::ADMIN_SEKOLAH ? $this->adminDari($s) : $this->penggunaSekolah($s, $peran);
            $this->flushSession();
            auth()->forgetGuards();
            $this->assertSame($harapan, $this->actingAs($akun)->get("/sekolah/{$s->slug}/enrollments")->getStatusCode(), $peran);
        }
    }

    public function test_kepala_sekolah_melihat_tetapi_tidak_bisa_menempatkan_atau_memindahkan(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $a = $this->kelas($s, $y, '4A');
        $b = $this->kelas($s, $y, '4B');
        $siswa = $this->siswaDi($s);
        $baris = $this->untuk($s, fn () => Enrollment::tempatkan($siswa, $a));
        $lain = $this->siswaDi($s, 'Lain');
        $this->masukPanel($this->penggunaSekolah($s, Peran::KEPALA_SEKOLAH), 'sekolah', $s);

        Livewire::test(ManageEnrollments::class)->assertActionHidden('tempatkan')->assertTableActionHidden('pindahkan', $baris);
        try {
            Livewire::test(ManageEnrollments::class)->callAction('tempatkan', ['classroom_id' => $b->id, 'student_ids' => [$lain->id]]);
        } catch (\Throwable) {
        }
        try {
            Livewire::test(ManageEnrollments::class)->callTableAction('pindahkan', $baris, ['classroom_id' => $b->id]);
        } catch (\Throwable) {
        }

        $this->assertSame(1, Enrollment::withoutGlobalScopes()->count());
        $this->assertSame($a->id, $baris->fresh()->classroom_id);
        $this->assertTrue($baris->fresh()->isActive());
    }

    public function test_staf_tu_boleh_menempatkan_siswa(): void
    {
        $s = $this->sekolah('SD A');
        $kelas = $this->kelas($s, $this->tahunAjaran($s));
        $siswa = $this->siswaDi($s);
        $this->masukPanel($this->penggunaSekolah($s, Peran::STAF_TU), 'sekolah', $s);

        Livewire::test(ManageEnrollments::class)->callAction('tempatkan', ['classroom_id' => $kelas->id, 'student_ids' => [$siswa->id]])->assertHasNoActionErrors();

        $this->assertSame(1, $this->jumlahBerlaku($s));
    }

    // ---------- wali kelas lewat formulir kelas ----------

    public function test_admin_menunjuk_wali_kelas_dan_jumlah_siswa_tampil(): void
    {
        $s = $this->sekolah('SD A');
        $kelas = $this->kelas($s, $this->tahunAjaran($s));
        $guru = $this->guruDi($s, 'Bu Sari');
        $this->untuk($s, fn () => Enrollment::tempatkan($this->siswaDi($s), $kelas));
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageClassrooms::class)
            ->callTableAction('edit', $kelas, ['name' => '4A', 'grade' => 4, 'homeroom_staff_id' => $guru->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame($guru->id, $kelas->fresh()->homeroom_staff_id);
        $this->get("/sekolah/{$s->slug}/classrooms")->assertSee('Bu Sari');
    }

    public function test_guru_tidak_bisa_menjadi_wali_dua_kelas_pada_tahun_yang_sama_dan_tendik_tidak_bisa_dipilih(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $guru = $this->guruDi($s, 'Bu Sari');
        $tendik = $this->guruDi($s, 'Pak TU', null, 'tendik');
        $this->kelas($s, $y, '4A', 4, $guru->id);
        $b = $this->kelas($s, $y, '4B');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageClassrooms::class)
            ->callTableAction('edit', $b, ['name' => '4B', 'grade' => 4, 'homeroom_staff_id' => $guru->id])->assertHasTableActionErrors(['homeroom_staff_id']);
        Livewire::test(ManageClassrooms::class)
            ->callTableAction('edit', $b, ['name' => '4B', 'grade' => 4, 'homeroom_staff_id' => $tendik->id])->assertHasTableActionErrors(['homeroom_staff_id']);

        $this->assertNull($b->fresh()->homeroom_staff_id);
    }

    // ---------- penugasan guru lewat panel ----------

    public function test_admin_membuat_dan_mengganti_penugasan_guru(): void
    {
        $s = $this->sekolah('SD A');
        $kelas = $this->kelas($s, $this->tahunAjaran($s));
        $mtk = $this->mapel($s);
        $a = $this->guruDi($s, 'Bu A');
        $b = $this->guruDi($s, 'Pak B');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageTeachingAssignments::class)
            ->callAction('create', ['classroom_id' => $kelas->id, 'subject_id' => $mtk->id, 'staff_id' => $a->id])->assertHasNoActionErrors();
        $p = TeachingAssignment::withoutGlobalScopes()->firstOrFail();

        Livewire::test(ManageTeachingAssignments::class)
            ->callTableAction('edit', $p, ['staff_id' => $b->id])->assertHasNoTableActionErrors();

        $this->assertSame($b->id, $p->fresh()->staff_id);
        $this->assertSame(1, TeachingAssignment::withoutGlobalScopes()->count());
    }

    public function test_penugasan_ganda_pada_kelas_dan_mata_pelajaran_yang_sama_ditolak(): void
    {
        $s = $this->sekolah('SD A');
        $kelas = $this->kelas($s, $this->tahunAjaran($s));
        $mtk = $this->mapel($s);
        $a = $this->guruDi($s, 'Bu A');
        $b = $this->guruDi($s, 'Pak B');
        $this->untuk($s, fn () => TeachingAssignment::create(['classroom_id' => $kelas->id, 'subject_id' => $mtk->id, 'staff_id' => $a->id]));
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageTeachingAssignments::class)
            ->callAction('create', ['classroom_id' => $kelas->id, 'subject_id' => $mtk->id, 'staff_id' => $b->id])->assertHasActionErrors(['subject_id']);
    }

    public function test_tendik_tidak_bisa_dipilih_sebagai_pengampu(): void
    {
        $s = $this->sekolah('SD A');
        $kelas = $this->kelas($s, $this->tahunAjaran($s));
        $mtk = $this->mapel($s);
        $tendik = $this->guruDi($s, 'Pak TU', null, 'tendik');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageTeachingAssignments::class)
            ->callAction('create', ['classroom_id' => $kelas->id, 'subject_id' => $mtk->id, 'staff_id' => $tendik->id])->assertHasActionErrors(['staff_id']);
        $this->assertSame(0, TeachingAssignment::withoutGlobalScopes()->count());
    }

    public function test_semua_staf_boleh_melihat_penugasan_tetapi_hanya_admin_dan_tu_mengubah(): void
    {
        $s = $this->sekolah('SD A');
        foreach (Peran::PANEL_SEKOLAH as $peran) {
            $akun = $peran === Peran::ADMIN_SEKOLAH ? $this->adminDari($s) : $this->penggunaSekolah($s, $peran);
            $this->flushSession();
            auth()->forgetGuards();
            $this->assertSame(200, $this->actingAs($akun)->get("/sekolah/{$s->slug}/teaching-assignments")->getStatusCode(), $peran);
        }

        $this->masukPanel($this->penggunaSekolah($s, Peran::GURU_MAPEL), 'sekolah', $s);
        Livewire::test(ManageTeachingAssignments::class)->assertActionHidden('create');
    }

    public function test_kolom_kelas_pada_daftar_siswa_menampilkan_kelas_tahun_berjalan(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $this->untuk($s, fn () => Enrollment::tempatkan($this->siswaDi($s, 'Budi Berkelas'), $this->kelas($s, $y, 'Kelas-Unik-9Z')));
        $this->siswaDi($s, 'Siti Tanpa Kelas');

        $this->actingAs($this->adminDari($s))->get("/sekolah/{$s->slug}/students")
            ->assertOk()->assertSee('Budi Berkelas')->assertSee('Kelas-Unik-9Z')->assertSee('Siti Tanpa Kelas');
    }
}
