<?php

namespace Tests\Feature\DataMaster;

use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Support\Peran;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class DataMasterTest extends TestCase
{
    use SiapkanSekolah;

    private function siswa($s, string $nama = 'Budi', array $lain = []): Student
    {
        return $this->untuk($s, fn () => Student::create(['name' => $nama, 'gender' => 'L'] + $lain));
    }

    // ---------- isolasi ----------

    public function test_data_master_sekolah_lain_tidak_terbaca(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $y = $this->tahunAjaran($a);
        $this->siswa($a);
        $this->untuk($a, fn () => Staff::create(['name' => 'Guru A']));
        $this->untuk($a, fn () => Subject::create(['code' => 'mtk', 'name' => 'Matematika']));
        $this->untuk($a, fn () => Classroom::create(['academic_year_id' => $y->id, 'name' => '4A', 'grade' => 4]));
        $this->untuk($a, fn () => Guardian::create(['name' => 'Ortu A']));

        $this->actingAs($this->adminDari($b));

        foreach ([Student::class, Staff::class, Subject::class, Classroom::class, Guardian::class] as $model) {
            $this->assertSame(0, $model::count(), $model);
        }
    }

    public function test_halaman_siswa_hanya_menampilkan_siswa_sekolah_sendiri(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->siswa($a, 'Siswa Milik A');
        $this->siswa($b, 'Siswa Milik B');

        $this->actingAs($this->adminDari($a))->get("/sekolah/{$a->slug}/siswa")
            ->assertOk()->assertSee('Siswa Milik A')->assertDontSee('Siswa Milik B');
    }

    // ---------- aturan data siswa ----------

    public function test_nisn_dan_nis_unik_per_sekolah_dan_boleh_sama_antar_sekolah(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->siswa($a, 'A', ['nisn' => '0012345678', 'nis' => '1']);
        $this->siswa($b, 'B', ['nisn' => '0012345678', 'nis' => '1']); // sah

        $this->expectException(QueryException::class);
        $this->siswa($a, 'Kembar', ['nisn' => '0012345678']);
    }

    public function test_banyak_siswa_tanpa_nisn_diizinkan(): void
    {
        $a = $this->sekolah('SD A');
        $this->siswa($a, 'A');
        $this->siswa($a, 'B', ['nisn' => '']); // kosong = NULL, tidak dianggap kembar

        $this->assertSame(2, $this->untuk($a, fn () => Student::whereNull('nisn')->count()));
    }

    public function test_siswa_tidak_valid_ditolak_model(): void
    {
        $a = $this->sekolah('SD A');
        foreach ([['nisn' => '123'], ['gender' => 'X'], ['status' => 'hilang'], ['name' => '  '], ['birth_date' => '2999-01-01']] as $salah) {
            try {
                $this->untuk($a, fn () => Student::create(array_replace(['name' => 'Budi', 'gender' => 'L'], $salah)));
                $this->fail('Seharusnya ditolak: '.json_encode($salah));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, $this->untuk($a, fn () => Student::count()));
    }

    public function test_status_siswa_berubah_tanpa_menghapus_identitas(): void
    {
        $a = $this->sekolah('SD A');
        $s = $this->siswa($a);
        $this->untuk($a, fn () => $s->update(['status' => 'lulus']));

        $this->assertSame('lulus', $this->untuk($a, fn () => Student::find($s->id)->status));
        $this->assertSame(1, $this->untuk($a, fn () => Student::count()));
    }

    // ---------- akun yang ditautkan ----------

    public function test_staf_hanya_boleh_ditautkan_ke_akun_sekolah_sendiri(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $guruA = $this->penggunaSekolah($a, Peran::GURU_MAPEL);
        $guruB = $this->penggunaSekolah($b, Peran::GURU_MAPEL);

        $staf = $this->untuk($a, fn () => Staff::create(['name' => 'Bu Sari', 'user_id' => $guruA->id]));
        $this->assertSame($guruA->id, $staf->user_id);

        $this->expectException(ValidationException::class);
        $this->untuk($a, fn () => Staff::create(['name' => 'Penyusup', 'user_id' => $guruB->id]));
    }

    public function test_satu_akun_tidak_bisa_dipakai_dua_staf(): void
    {
        $a = $this->sekolah('SD A');
        $u = $this->penggunaSekolah($a, Peran::GURU_MAPEL);
        $this->untuk($a, fn () => Staff::create(['name' => 'A', 'user_id' => $u->id]));

        $this->expectException(QueryException::class);
        $this->untuk($a, fn () => Staff::create(['name' => 'B', 'user_id' => $u->id]));
    }

    public function test_jenis_dan_nip_staf(): void
    {
        $a = $this->sekolah('SD A');
        $this->untuk($a, fn () => Staff::create(['name' => 'A', 'nip' => '111', 'kind' => 'tendik']));
        $this->assertSame('guru', $this->untuk($a, fn () => Staff::create(['name' => 'B']))->kind); // bawaan

        try {
            $this->untuk($a, fn () => Staff::create(['name' => 'C', 'kind' => 'kepala']));
            $this->fail('Jenis tidak dikenal harus ditolak');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(QueryException::class);
        $this->untuk($a, fn () => Staff::create(['name' => 'D', 'nip' => '111']));
    }

    // ---------- orang tua dan anak ----------

    public function test_satu_orang_tua_banyak_anak_dan_satu_anak_banyak_orang_tua(): void
    {
        $a = $this->sekolah('SD A');
        $anak1 = $this->siswa($a, 'Anak 1');
        $anak2 = $this->siswa($a, 'Anak 2');
        $ayah = $this->untuk($a, fn () => Guardian::create(['name' => 'Ayah', 'relationship' => 'ayah']));
        $ibu = $this->untuk($a, fn () => Guardian::create(['name' => 'Ibu', 'relationship' => 'ibu']));

        $this->untuk($a, fn () => $ayah->linkStudents([$anak1->id, $anak2->id]));
        $this->untuk($a, fn () => $ibu->linkStudents([$anak1->id]));

        $this->assertSame(2, $this->untuk($a, fn () => $ayah->students()->count()));
        $this->assertSame(2, $this->untuk($a, fn () => $anak1->guardians()->count()));
        $this->assertSame(1, $this->untuk($a, fn () => $anak2->guardians()->count()));
    }

    public function test_menautkan_ulang_mengganti_daftar_anak(): void
    {
        $a = $this->sekolah('SD A');
        $x = $this->siswa($a, 'X');
        $y = $this->siswa($a, 'Y');
        $g = $this->untuk($a, fn () => Guardian::create(['name' => 'Ortu']));

        $this->untuk($a, fn () => $g->linkStudents([$x->id]));
        $this->untuk($a, fn () => $g->linkStudents([$y->id]));

        $this->assertSame(['Y'], $this->untuk($a, fn () => $g->students()->pluck('name')->all()));
    }

    public function test_orang_tua_tidak_bisa_ditautkan_ke_siswa_sekolah_lain(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $anakB = $this->siswa($b, 'Anak B');
        $ortuA = $this->untuk($a, fn () => Guardian::create(['name' => 'Ortu A']));

        try {
            $this->untuk($a, fn () => $ortuA->linkStudents([$anakB->id]));
            $this->fail('Seharusnya ditolak oleh model');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(0, DB::table('guardian_student')->count());
    }

    public function test_basis_data_sendiri_menolak_tautan_lintas_sekolah_walau_kode_dilewati(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $anakB = $this->siswa($b, 'Anak B');
        $ortuA = $this->untuk($a, fn () => Guardian::create(['name' => 'Ortu A']));

        // SQL langsung, melewati seluruh kode aplikasi: kunci asing gabungan tetap menolak.
        foreach ([$a->id, $b->id] as $sekolah) {
            try {
                DB::table('guardian_student')->insert(['school_id' => $sekolah, 'guardian_id' => $ortuA->id, 'student_id' => $anakB->id]);
                $this->fail("Tautan lintas sekolah lolos dengan school_id={$sekolah}");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---------- kelas dan mata pelajaran ----------

    public function test_kode_mata_pelajaran_huruf_besar_dan_unik_per_sekolah(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->assertSame('MTK', $this->untuk($a, fn () => Subject::create(['code' => ' mtk ', 'name' => 'Matematika']))->code);
        $this->untuk($b, fn () => Subject::create(['code' => 'MTK', 'name' => 'Matematika'])); // sekolah lain boleh sama

        $this->expectException(QueryException::class);
        $this->untuk($a, fn () => Subject::create(['code' => 'mtk', 'name' => 'Lagi']));
    }

    public function test_kelas_unik_per_tahun_ajaran_dan_tingkat_dibatasi(): void
    {
        $a = $this->sekolah('SD A');
        $y1 = $this->tahunAjaran($a, '2025/2026');
        $y2 = $this->tahunAjaran($a, '2026/2027', '2026-07-01', '2027-06-30');
        $this->untuk($a, fn () => Classroom::create(['academic_year_id' => $y1->id, 'name' => '4A', 'grade' => 4]));
        $this->untuk($a, fn () => Classroom::create(['academic_year_id' => $y2->id, 'name' => '4A', 'grade' => 4])); // tahun lain sah

        try {
            $this->untuk($a, fn () => Classroom::create(['academic_year_id' => $y1->id, 'name' => '4B', 'grade' => 13]));
            $this->fail('Tingkat 13 harus ditolak');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(QueryException::class);
        $this->untuk($a, fn () => Classroom::create(['academic_year_id' => $y1->id, 'name' => '4A', 'grade' => 4]));
    }

    public function test_kelas_tidak_bisa_memakai_tahun_ajaran_sekolah_lain(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $tahunB = $this->tahunAjaran($b);

        $this->expectException(ValidationException::class);
        $this->untuk($a, fn () => Classroom::create(['academic_year_id' => $tahunB->id, 'name' => '4A', 'grade' => 4]));
    }
}
