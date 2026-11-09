<?php

namespace Tests\Feature\Akademik;

use App\Filament\Sekolah\Resources\Students\Pages\ManageStudents;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Support\Peran;
use Livewire\Livewire;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

/** Wali kelas dan guru mata pelajaran hanya melihat siswa di kelas mereka pada tahun ajaran berjalan. */
class AksesGuruTest extends TestCase
{
    // setUp milik trait tertimpa setUp kelas ini, jadi dipanggil lewat alias agar peran tetap terisi.
    use SiapkanSekolah { setUp as private setUpSekolah; }

    private $s;

    private $y;

    private $a1;

    private $a2;

    private $b1;

    private $c1;

    private $kelasA;

    private $kelasB;

    private $waliA;     // akun wali kelas 4A

    private $guruMtkB;  // akun guru mapel yang mengampu 4B

    private $mtk;

    private $stafMtkB;

    protected function setUp(): void
    {
        $this->setUpSekolah();
        $this->s = $this->sekolah('SD A');
        $this->y = $this->tahunAjaran($this->s);
        $this->kelasA = $this->kelas($this->s, $this->y, '4A');
        $this->kelasB = $this->kelas($this->s, $this->y, '4B');
        $this->a1 = $this->siswaDi($this->s, 'Siswa A1');
        $this->a2 = $this->siswaDi($this->s, 'Siswa A2');
        $this->b1 = $this->siswaDi($this->s, 'Siswa B1');
        $this->c1 = $this->siswaDi($this->s, 'Siswa C1 tanpa kelas');
        $this->untuk($this->s, function () {
            Enrollment::tempatkan($this->a1, $this->kelasA);
            Enrollment::tempatkan($this->a2, $this->kelasA);
            Enrollment::tempatkan($this->b1, $this->kelasB);
        });

        $this->waliA = $this->penggunaSekolah($this->s, Peran::WALI_KELAS, 'Wali A');
        $stafWali = $this->guruDi($this->s, 'Bu Wali', $this->waliA);
        $this->untuk($this->s, fn () => $this->kelasA->update(['homeroom_staff_id' => $stafWali->id]));

        $this->guruMtkB = $this->penggunaSekolah($this->s, Peran::GURU_MAPEL, 'Guru MTK');
        $this->stafMtkB = $this->guruDi($this->s, 'Pak MTK', $this->guruMtkB);
        $this->mtk = $this->mapel($this->s);
        $this->untuk($this->s, fn () => TeachingAssignment::create(['classroom_id' => $this->kelasB->id, 'subject_id' => $this->mtk->id, 'staff_id' => $this->stafMtkB->id]));
    }

    private function lihat($akun): array
    {
        $this->masukPanel($akun, 'sekolah', $this->s);
        $komponen = Livewire::test(ManageStudents::class);

        return Student::withoutGlobalScopes()->where('school_id', $this->s->id)->pluck('name', 'id')
            ->filter(fn ($nama, $id) => $this->terlihat($komponen, $id))->values()->all();
    }

    private function terlihat($komponen, int $id): bool
    {
        try {
            $komponen->assertCanSeeTableRecords([Student::withoutGlobalScopes()->find($id)]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function test_wali_kelas_hanya_melihat_siswa_kelasnya(): void
    {
        $this->assertEqualsCanonicalizing(['Siswa A1', 'Siswa A2'], $this->lihat($this->waliA));
    }

    public function test_guru_mapel_hanya_melihat_siswa_kelas_yang_diampunya(): void
    {
        $this->assertSame(['Siswa B1'], $this->lihat($this->guruMtkB));
    }

    public function test_halaman_http_guru_menampilkan_siswa_kelasnya_saja(): void
    {
        $this->actingAs($this->waliA)->get("/sekolah/{$this->s->slug}/students")
            ->assertOk()->assertSee('Siswa A1')->assertSee('Siswa A2')
            ->assertDontSee('Siswa B1')->assertDontSee('Siswa C1');
    }

    public function test_guru_tanpa_kelas_atau_tanpa_catatan_guru_tidak_melihat_siapa_pun(): void
    {
        $tanpaKelas = $this->penggunaSekolah($this->s, Peran::GURU_MAPEL, 'Guru Kosong');
        $this->guruDi($this->s, 'Bu Kosong', $tanpaKelas);
        $tanpaStaf = $this->penggunaSekolah($this->s, Peran::WALI_KELAS, 'Tanpa Staf');

        $this->assertSame([], $this->lihat($tanpaKelas));
        $this->assertSame([], $this->lihat($tanpaStaf));
    }

    public function test_admin_kepala_dan_tu_melihat_semua_siswa(): void
    {
        foreach ([$this->adminDari($this->s), $this->penggunaSekolah($this->s, Peran::KEPALA_SEKOLAH), $this->penggunaSekolah($this->s, Peran::STAF_TU)] as $akun) {
            $this->assertCount(4, $this->lihat($akun));
        }
    }

    // ---------- kebijakan per siswa ----------

    public function test_policy_per_siswa_membatasi_guru_pada_kelasnya(): void
    {
        $this->assertTrue($this->waliA->can('view', $this->a1));
        $this->assertFalse($this->waliA->can('view', $this->b1));
        $this->assertFalse($this->waliA->can('view', $this->c1));
        $this->assertTrue($this->guruMtkB->can('view', $this->b1));
        $this->assertFalse($this->guruMtkB->can('view', $this->a1));
    }

    public function test_guru_tidak_bisa_membuat_mengubah_atau_mengimpor_siswa(): void
    {
        foreach ([$this->waliA, $this->guruMtkB] as $akun) {
            $this->assertFalse($akun->can('create', Student::class));
            $this->assertFalse($akun->can('update', $this->a1));
            $this->assertFalse($akun->can('update', $this->b1));
        }
        $this->masukPanel($this->waliA, 'sekolah', $this->s);
        Livewire::test(ManageStudents::class)
            ->assertActionHidden('create')->assertActionHidden('impor')->assertTableActionHidden('edit', $this->a1);
    }

    public function test_guru_tidak_bisa_mengubah_siswa_walau_memanggil_aksi_langsung(): void
    {
        $this->masukPanel($this->waliA, 'sekolah', $this->s);
        try {
            Livewire::test(ManageStudents::class)->callTableAction('edit', $this->a1, ['name' => 'Diubah Guru', 'gender' => 'L', 'status' => 'aktif']);
        } catch (\Throwable) {
            // aksi tersembunyi ditolak; yang dinilai adalah isi basis data
        }
        $this->assertSame('Siswa A1', Student::withoutGlobalScopes()->find($this->a1->id)->name);
    }

    public function test_guru_tetap_tidak_melihat_data_guru_dan_orang_tua(): void
    {
        foreach (['staff', 'guardians', 'enrollments'] as $halaman) {
            $this->flushSession();
            auth()->forgetGuards();
            $this->actingAs($this->waliA)->get("/sekolah/{$this->s->slug}/{$halaman}")->assertForbidden();
        }
    }

    // ---------- akses mengikuti penugasan ----------

    public function test_akses_hilang_saat_guru_diganti_pada_penugasan(): void
    {
        $this->assertSame(['Siswa B1'], $this->lihat($this->guruMtkB));

        $pengganti = $this->guruDi($this->s, 'Guru Pengganti');
        $this->untuk($this->s, fn () => TeachingAssignment::first()->update(['staff_id' => $pengganti->id]));

        $this->assertSame([], $this->lihat($this->guruMtkB), 'guru yang digantikan tidak boleh tetap melihat kelas itu');
    }

    public function test_siswa_pindah_kelas_ikut_pindah_hak_lihat(): void
    {
        $penempatan = $this->untuk($this->s, fn () => Enrollment::where('student_id', $this->a1->id)->firstOrFail());
        $this->untuk($this->s, fn () => $penempatan->pindahkan($this->kelasB));

        $this->assertSame(['Siswa A2'], $this->lihat($this->waliA));
        $this->assertEqualsCanonicalizing(['Siswa A1', 'Siswa B1'], $this->lihat($this->guruMtkB));
    }

    public function test_siswa_yang_lulus_tidak_lagi_terlihat_oleh_guru(): void
    {
        $this->untuk($this->s, fn () => $this->a1->update(['status' => 'lulus']));

        $this->assertSame(['Siswa A2'], $this->lihat($this->waliA));
    }

    public function test_guru_nonaktif_kehilangan_akses(): void
    {
        $this->untuk($this->s, fn () => $this->stafMtkB->update(['status' => 'nonaktif']));

        $this->assertSame([], $this->lihat($this->guruMtkB));
    }

    public function test_kelas_tahun_lalu_tidak_memberi_akses_pada_tahun_berjalan(): void
    {
        $lalu = $this->tahunAjaran($this->s, '2024/2025', '2024-07-01', '2025-06-30');
        $kelasLalu = $this->kelas($this->s, $lalu, '3A', 3);
        $siswaLama = $this->siswaDi($this->s, 'Siswa Tahun Lalu');
        $this->untuk($this->s, fn () => Enrollment::tempatkan($siswaLama, $kelasLalu));
        $akun = $this->penggunaSekolah($this->s, Peran::WALI_KELAS, 'Wali Lama');
        $staf = $this->guruDi($this->s, 'Bu Lama', $akun);
        $this->untuk($this->s, fn () => $kelasLalu->update(['homeroom_staff_id' => $staf->id]));

        $this->assertSame([], $this->lihat($akun)); // tahun berjalan 2025/2026 (terbaru), bukan 2024/2025
    }

    public function test_guru_sekolah_lain_tidak_melihat_siswa_sekolah_ini(): void
    {
        $b = $this->sekolah('SD B');
        $akunB = $this->penggunaSekolah($b, Peran::WALI_KELAS, 'Wali SD B');
        $this->guruDi($b, 'Bu B', $akunB);

        $this->assertFalse($akunB->can('view', $this->a1));
        $this->masukPanel($akunB, 'sekolah', $b);
        Livewire::test(ManageStudents::class)->assertCanNotSeeTableRecords([$this->a1, $this->b1]);
    }
}
