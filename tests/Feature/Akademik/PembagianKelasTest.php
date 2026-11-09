<?php

namespace Tests\Feature\Akademik;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\TeachingAssignment;
use App\Support\Peran;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class PembagianKelasTest extends TestCase
{
    use SiapkanSekolah;

    private function tempatkan($s, $siswa, $kelas)
    {
        return $this->untuk($s, fn () => Enrollment::tempatkan($siswa, $kelas));
    }

    private function gagalValidasi(callable $f, ?string $kunci = null): void
    {
        try {
            $f();
            $this->fail('Seharusnya ditolak dengan ValidationException');
        } catch (ValidationException $e) {
            if ($kunci) {
                $this->assertArrayHasKey($kunci, $e->errors());
            }
            $this->addToAssertionCount(1);
        }
    }

    // ---------- penempatan siswa ----------

    public function test_siswa_ditempatkan_dan_tahun_ajaran_mengikuti_kelas(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $e = $this->tempatkan($s, $this->siswaDi($s), $this->kelas($s, $y));

        $e->refresh();
        $this->assertTrue($e->isActive());
        $this->assertSame($y->id, $e->academic_year_id);
        $this->assertTrue($e->joined_at->isToday());
        $this->assertNull($e->left_at);
    }

    public function test_siswa_tidak_aktif_tidak_bisa_ditempatkan(): void
    {
        $s = $this->sekolah('SD A');
        $kelas = $this->kelas($s, $this->tahunAjaran($s));
        $siswa = $this->siswaDi($s, 'Lulus', ['status' => 'lulus']);

        $this->gagalValidasi(fn () => $this->tempatkan($s, $siswa, $kelas), 'student_id');
    }

    public function test_satu_siswa_tidak_bisa_punya_dua_kelas_pada_tahun_yang_sama(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $siswa = $this->siswaDi($s);
        $this->tempatkan($s, $siswa, $this->kelas($s, $y, '4A'));

        $this->gagalValidasi(fn () => $this->tempatkan($s, $siswa, $this->kelas($s, $y, '4B')), 'student_id');
        $this->assertSame(1, $this->untuk($s, fn () => Enrollment::count()));
    }

    public function test_basis_data_sendiri_menolak_dua_penempatan_berlaku_pada_satu_tahun(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $siswa = $this->siswaDi($s);
        $a = $this->kelas($s, $y, '4A');
        $b = $this->kelas($s, $y, '4B');
        $this->tempatkan($s, $siswa, $a);

        $this->expectException(QueryException::class); // SQL langsung melewati semua kode aplikasi
        DB::table('enrollments')->insert(['school_id' => $s->id, 'student_id' => $siswa->id, 'classroom_id' => $b->id,
            'academic_year_id' => $y->id, 'aktif' => 1, 'joined_at' => today()]);
    }

    public function test_naik_kelas_adalah_penempatan_di_tahun_berikutnya_dan_riwayat_lama_tetap(): void
    {
        $s = $this->sekolah('SD A');
        $y1 = $this->tahunAjaran($s, '2025/2026');
        $y2 = $this->tahunAjaran($s, '2026/2027', '2026-07-01', '2027-06-30');
        $siswa = $this->siswaDi($s);
        $this->tempatkan($s, $siswa, $this->kelas($s, $y1, '4A', 4));
        $this->tempatkan($s, $siswa, $this->kelas($s, $y2, '5A', 5)); // sah: tahun ajaran berbeda

        $baris = $this->untuk($s, fn () => Enrollment::where('student_id', $siswa->id)->orderBy('academic_year_id')->get());
        $this->assertCount(2, $baris);
        $this->assertTrue($baris[0]->isActive() && $baris[1]->isActive()); // masing-masing berlaku pada tahunnya
    }

    // ---------- pindah kelas ----------

    public function test_pindah_kelas_menutup_baris_lama_dan_membuka_baris_baru(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $siswa = $this->siswaDi($s);
        $lama = $this->tempatkan($s, $siswa, $a = $this->kelas($s, $y, '4A'));
        $b = $this->kelas($s, $y, '4B');

        $baru = $this->untuk($s, fn () => $lama->pindahkan($b, 'orang tua meminta'));

        $lama->refresh();
        $this->assertFalse($lama->isActive());
        $this->assertTrue($lama->left_at->isToday());
        $this->assertSame($a->id, $lama->classroom_id); // riwayat tidak ditimpa
        $this->assertTrue($baru->fresh()->isActive());
        $this->assertSame($b->id, $baru->classroom_id);
        $this->assertSame('orang tua meminta', $baru->note);
        $this->assertSame(2, $this->untuk($s, fn () => Enrollment::where('student_id', $siswa->id)->count()));
    }

    public function test_pindah_hanya_dalam_tahun_ajaran_yang_sama(): void
    {
        $s = $this->sekolah('SD A');
        $y1 = $this->tahunAjaran($s, '2025/2026');
        $y2 = $this->tahunAjaran($s, '2026/2027', '2026-07-01', '2027-06-30');
        $lama = $this->tempatkan($s, $this->siswaDi($s), $this->kelas($s, $y1, '4A'));
        $lain = $this->kelas($s, $y2, '5A', 5);

        $this->gagalValidasi(fn () => $this->untuk($s, fn () => $lama->pindahkan($lain)), 'classroom_id');
        $this->assertTrue($lama->fresh()->isActive(), 'penempatan lama harus utuh bila pindah gagal');
    }

    public function test_pindah_ke_kelas_yang_sama_atau_dari_baris_tertutup_ditolak(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $a = $this->kelas($s, $y, '4A');
        $b = $this->kelas($s, $y, '4B');
        $lama = $this->tempatkan($s, $this->siswaDi($s), $a);

        $this->gagalValidasi(fn () => $this->untuk($s, fn () => $lama->pindahkan($a)), 'classroom_id');

        $this->untuk($s, fn () => $lama->pindahkan($b));
        $this->gagalValidasi(fn () => $this->untuk($s, fn () => $lama->fresh()->pindahkan($b)), 'classroom_id'); // sudah ditutup
    }

    public function test_riwayat_penempatan_tidak_bisa_ditulis_ulang(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $a = $this->kelas($s, $y, '4A');
        $b = $this->kelas($s, $y, '4B');
        $e = $this->tempatkan($s, $this->siswaDi($s), $a);

        $this->expectException(LogicException::class);
        $this->untuk($s, fn () => $e->update(['classroom_id' => $b->id]));
    }

    public function test_siswa_lulus_pindah_atau_keluar_otomatis_dilepas_dari_kelas(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        foreach (['lulus', 'pindah', 'keluar'] as $status) {
            $siswa = $this->siswaDi($s, "Siswa $status");
            $e = $this->tempatkan($s, $siswa, $this->kelas($s, $y, "K-$status"));

            $this->untuk($s, fn () => $siswa->update(['status' => $status]));

            $this->assertFalse($e->fresh()->isActive(), $status);
            $this->assertTrue($e->fresh()->left_at->isToday(), $status);
        }
    }

    // ---------- lintas sekolah ----------

    public function test_siswa_dan_kelas_sekolah_lain_tidak_bisa_dipakai(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $kelasA = $this->kelas($a, $this->tahunAjaran($a));
        $siswaB = $this->siswaDi($b);
        $kelasB = $this->kelas($b, $this->tahunAjaran($b));
        $siswaA = $this->siswaDi($a);

        $this->gagalValidasi(fn () => $this->untuk($a, fn () => Enrollment::create(['student_id' => $siswaB->id, 'classroom_id' => $kelasA->id])), 'student_id');
        $this->gagalValidasi(fn () => $this->untuk($a, fn () => Enrollment::create(['student_id' => $siswaA->id, 'classroom_id' => $kelasB->id])), 'classroom_id');
        $this->assertSame(0, Enrollment::withoutGlobalScopes()->count());
    }

    public function test_basis_data_menolak_penempatan_lintas_sekolah_lewat_sql_langsung(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $kelasA = $this->kelas($a, $yA = $this->tahunAjaran($a));
        $siswaB = $this->siswaDi($b);

        foreach ([$a->id, $b->id] as $sekolah) {
            try {
                DB::table('enrollments')->insert(['school_id' => $sekolah, 'student_id' => $siswaB->id, 'classroom_id' => $kelasA->id,
                    'academic_year_id' => $yA->id, 'aktif' => 1, 'joined_at' => today()]);
                $this->fail("Penempatan lintas sekolah lolos (school_id=$sekolah)");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_penempatan_sekolah_lain_tidak_terbaca(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->tempatkan($a, $this->siswaDi($a), $this->kelas($a, $this->tahunAjaran($a)));

        $this->actingAs($this->adminDari($b));
        $this->assertSame(0, Enrollment::count());
    }

    // ---------- wali kelas ----------

    public function test_wali_kelas_harus_guru_aktif_di_sekolah_sendiri(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $y = $this->tahunAjaran($a);
        $tendik = $this->guruDi($a, 'Pak TU', null, 'tendik');
        $nonaktif = $this->guruDi($a, 'Bu Pensiun', null, 'guru', 'nonaktif');
        $guruB = $this->guruDi($b, 'Guru B');

        foreach ([$tendik, $nonaktif, $guruB] as $bukan) {
            $this->gagalValidasi(fn () => $this->kelas($a, $y, '4A', 4, $bukan->id), 'homeroom_staff_id');
        }
        $guru = $this->guruDi($a);
        $this->assertSame($guru->id, $this->kelas($a, $y, '4A', 4, $guru->id)->homeroom_staff_id);
    }

    public function test_satu_guru_hanya_wali_satu_kelas_per_tahun_tetapi_boleh_di_tahun_lain(): void
    {
        $s = $this->sekolah('SD A');
        $y1 = $this->tahunAjaran($s, '2025/2026');
        $y2 = $this->tahunAjaran($s, '2026/2027', '2026-07-01', '2027-06-30');
        $guru = $this->guruDi($s);
        $this->kelas($s, $y1, '4A', 4, $guru->id);
        $this->kelas($s, $y2, '5A', 5, $guru->id); // tahun lain: sah

        $this->expectException(QueryException::class);
        $this->kelas($s, $y1, '4B', 4, $guru->id);
    }

    public function test_basis_data_menolak_wali_kelas_dari_sekolah_lain_lewat_sql_langsung(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $kelasA = $this->kelas($a, $this->tahunAjaran($a));
        $guruB = $this->guruDi($b);

        $this->expectException(QueryException::class);
        DB::table('classrooms')->where('id', $kelasA->id)->update(['homeroom_staff_id' => $guruB->id]);
    }

    public function test_tahun_ajaran_kelas_tidak_bisa_diubah(): void
    {
        $s = $this->sekolah('SD A');
        $y1 = $this->tahunAjaran($s, '2025/2026');
        $y2 = $this->tahunAjaran($s, '2026/2027', '2026-07-01', '2027-06-30');
        $kelas = $this->kelas($s, $y1);

        $this->gagalValidasi(fn () => $this->untuk($s, fn () => $kelas->update(['academic_year_id' => $y2->id])), 'academic_year_id');
    }

    // ---------- penugasan guru mata pelajaran ----------

    public function test_penugasan_guru_dan_penggantian_guru(): void
    {
        $s = $this->sekolah('SD A');
        $kelas = $this->kelas($s, $this->tahunAjaran($s));
        $mtk = $this->mapel($s);
        $a = $this->guruDi($s, 'Bu A');
        $b = $this->guruDi($s, 'Pak B');

        $p = $this->untuk($s, fn () => TeachingAssignment::create(['classroom_id' => $kelas->id, 'subject_id' => $mtk->id, 'staff_id' => $a->id]));
        $this->untuk($s, fn () => $p->update(['staff_id' => $b->id])); // ganti guru: sah

        $this->assertSame($b->id, $p->fresh()->staff_id);
    }

    public function test_satu_mata_pelajaran_hanya_satu_guru_per_kelas(): void
    {
        $s = $this->sekolah('SD A');
        $kelas = $this->kelas($s, $this->tahunAjaran($s));
        $mtk = $this->mapel($s);
        $guruA = $this->guruDi($s, 'A');
        $guruB = $this->guruDi($s, 'B');
        $this->untuk($s, fn () => TeachingAssignment::create(['classroom_id' => $kelas->id, 'subject_id' => $mtk->id, 'staff_id' => $guruA->id]));

        $this->expectException(QueryException::class);
        $this->untuk($s, fn () => TeachingAssignment::create(['classroom_id' => $kelas->id, 'subject_id' => $mtk->id, 'staff_id' => $guruB->id]));
    }

    public function test_pengampu_harus_guru_aktif_dan_data_harus_sesekolah(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $kelasA = $this->kelas($a, $this->tahunAjaran($a));
        $mtkA = $this->mapel($a);
        $kelasB = $this->kelas($b, $this->tahunAjaran($b));
        $mtkB = $this->mapel($b);
        $buat = fn ($kelas, $mapel, $staf) => fn () => $this->untuk($a, fn () => TeachingAssignment::create(['classroom_id' => $kelas->id, 'subject_id' => $mapel->id, 'staff_id' => $staf->id]));

        $this->gagalValidasi($buat($kelasA, $mtkA, $this->guruDi($a, 'Tendik', null, 'tendik')), 'staff_id');
        $this->gagalValidasi($buat($kelasA, $mtkA, $this->guruDi($a, 'Nonaktif', null, 'guru', 'nonaktif')), 'staff_id');
        $this->gagalValidasi($buat($kelasA, $mtkA, $this->guruDi($b, 'Guru B')), 'staff_id');
        $this->gagalValidasi($buat($kelasB, $mtkA, $this->guruDi($a, 'Guru A')), 'classroom_id');
        $this->gagalValidasi($buat($kelasA, $mtkB, $this->guruDi($a, 'Guru A2')), 'subject_id');
        $this->assertSame(0, TeachingAssignment::withoutGlobalScopes()->count());
    }

    public function test_basis_data_menolak_penugasan_lintas_sekolah_lewat_sql_langsung(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $kelasA = $this->kelas($a, $this->tahunAjaran($a));
        $mtkA = $this->mapel($a);
        $guruB = $this->guruDi($b);

        $this->expectException(QueryException::class);
        DB::table('teaching_assignments')->insert(['school_id' => $a->id, 'classroom_id' => $kelasA->id, 'subject_id' => $mtkA->id, 'staff_id' => $guruB->id]);
    }

    public function test_kelas_dan_mata_pelajaran_penugasan_tidak_bisa_dipindah(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $a = $this->kelas($s, $y, '4A');
        $b = $this->kelas($s, $y, '4B');
        $mtk = $this->mapel($s);
        $guru = $this->guruDi($s);
        $p = $this->untuk($s, fn () => TeachingAssignment::create(['classroom_id' => $a->id, 'subject_id' => $mtk->id, 'staff_id' => $guru->id]));

        $this->expectException(LogicException::class);
        $this->untuk($s, fn () => $p->update(['classroom_id' => $b->id]));
    }

    // ---------- tahun berjalan dan kelas milik guru ----------

    public function test_tahun_berjalan_mengikuti_semester_aktif_bila_ada_selain_itu_tahun_terbaru(): void
    {
        $s = $this->sekolah('SD A');
        $lama = $this->tahunAjaran($s, '2024/2025', '2024-07-01', '2025-06-30');
        $baru = $this->tahunAjaran($s, '2025/2026');
        $this->assertSame($baru->id, AcademicYear::tahunBerjalanId($s->id)); // belum ada semester aktif: terbaru

        $sem = $this->semester($s, $lama, 1, '2024-07-01', '2024-12-31');
        $this->untuk($s, fn () => $sem->activate());
        $this->assertSame($lama->id, AcademicYear::tahunBerjalanId($s->id)); // semester aktif menentukan
    }

    public function test_id_kelas_saya_mencakup_kelas_yang_diwalikan_dan_diampu_pada_tahun_berjalan_saja(): void
    {
        $s = $this->sekolah('SD A');
        $lama = $this->tahunAjaran($s, '2024/2025', '2024-07-01', '2025-06-30');
        $baru = $this->tahunAjaran($s, '2025/2026');
        $akun = $this->penggunaSekolah($s, Peran::WALI_KELAS);
        $guru = $this->guruDi($s, 'Bu Sari', $akun);
        $diwalikan = $this->kelas($s, $baru, '4A', 4, $guru->id);
        $diampu = $this->kelas($s, $baru, '4B');
        $lainnya = $this->kelas($s, $baru, '4C');
        $tahunLalu = $this->kelas($s, $lama, '3A', 3);
        $mtk = $this->mapel($s);
        $this->untuk($s, fn () => TeachingAssignment::create(['classroom_id' => $diampu->id, 'subject_id' => $mtk->id, 'staff_id' => $guru->id]));
        $this->untuk($s, fn () => TeachingAssignment::create(['classroom_id' => $tahunLalu->id, 'subject_id' => $mtk->id, 'staff_id' => $guru->id]));

        $ids = $akun->idKelasSaya();
        sort($ids);
        $this->assertSame([$diwalikan->id, $diampu->id], $ids);
        $this->assertNotContains($lainnya->id, $ids);
        $this->assertNotContains($tahunLalu->id, $ids);
    }

    public function test_pengguna_tanpa_catatan_guru_atau_guru_nonaktif_tidak_punya_kelas(): void
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $tanpaStaf = $this->penggunaSekolah($s, Peran::GURU_MAPEL);
        $akun = $this->penggunaSekolah($s, Peran::WALI_KELAS);
        $guru = $this->guruDi($s, 'Bu Sari', $akun);
        $this->kelas($s, $y, '4A', 4, $guru->id);

        $this->assertSame([], $tanpaStaf->idKelasSaya());
        $this->assertCount(1, $akun->idKelasSaya());

        $this->untuk($s, fn () => $guru->update(['status' => 'nonaktif']));
        $this->assertSame([], $akun->idKelasSaya(), 'guru nonaktif tidak boleh tetap punya akses');
    }
}
