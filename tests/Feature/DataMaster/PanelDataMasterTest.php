<?php

namespace Tests\Feature\DataMaster;

use App\Filament\Sekolah\Resources\Guardians\Pages\ManageGuardians;
use App\Filament\Sekolah\Resources\Staff\Pages\ManageStaff;
use App\Filament\Sekolah\Resources\Students\Pages\ManageStudents;
use App\Filament\Sekolah\Resources\Subjects\Pages\ManageSubjects;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Support\Peran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuatXlsx;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class PanelDataMasterTest extends TestCase
{
    use BuatXlsx, SiapkanSekolah;

    private const PRIBADI = ['siswa', 'guru-staf', 'orang-tua'];

    private const MASTER = ['mata-pelajaran', 'kelas', 'tahun-ajaran', 'semester'];

    private function kodeHalaman(string $peran, string $slug, string $halaman, $sekolah): int
    {
        $u = $peran === Peran::ADMIN_SEKOLAH ? $this->adminDari($sekolah) : $this->penggunaSekolah($sekolah, $peran);

        // Sesi bersih tiap permintaan: berganti pengguna dalam satu uji membuat sesi lama tidak cocok dan pengguna dikeluarkan.
        $this->flushSession();
        auth()->forgetGuards();

        return $this->actingAs($u)->get("/sekolah/{$slug}/{$halaman}")->getStatusCode();
    }

    // ---------- akses minimum ----------

    public function test_data_pribadi_hanya_untuk_peran_yang_perlu(): void
    {
        $s = $this->sekolah('SD A');
        $diizinkan = [Peran::ADMIN_SEKOLAH, Peran::KEPALA_SEKOLAH, Peran::STAF_TU];
        $ditolak = [Peran::WALI_KELAS, Peran::GURU_MAPEL, Peran::GURU_BK, Peran::STAF_KEUANGAN];

        foreach (self::PRIBADI as $h) {
            foreach ($diizinkan as $p) {
                $this->assertSame(200, $this->kodeHalaman($p, $s->slug, $h, $s), "$p seharusnya boleh membuka $h");
            }
            foreach ($ditolak as $p) {
                // Wali kelas dan guru mapel boleh membuka halaman Siswa (daftarnya dibatasi pada kelas mereka; lihat AksesGuruTest).
                $boleh = $h === 'siswa' && in_array($p, [Peran::WALI_KELAS, Peran::GURU_MAPEL], true);
                $this->assertSame($boleh ? 200 : 403, $this->kodeHalaman($p, $s->slug, $h, $s), "$p di $h");
            }
        }
    }

    public function test_mata_pelajaran_dan_kelas_terbuka_untuk_semua_staf(): void
    {
        $s = $this->sekolah('SD A');
        foreach (self::MASTER as $h) {
            foreach (Peran::PANEL_SEKOLAH as $p) {
                $this->assertSame(200, $this->kodeHalaman($p, $s->slug, $h, $s), "$p seharusnya boleh membuka $h");
            }
        }
    }

    public function test_kepala_sekolah_melihat_tetapi_tidak_bisa_membuat_atau_mengimpor(): void
    {
        $s = $this->sekolah('SD A');
        $this->masukPanel($this->penggunaSekolah($s, Peran::KEPALA_SEKOLAH), 'sekolah', $s);

        Livewire::test(ManageStudents::class)->assertActionHidden('create')->assertActionHidden('impor')->assertActionHidden('templat');
        try {
            Livewire::test(ManageStudents::class)->callAction('create', ['name' => 'Penyusup', 'gender' => 'L', 'status' => 'aktif']);
        } catch (\Throwable) {
            // Filament menolak memanggil aksi yang tersembunyi; yang dinilai adalah isi basis data
        }

        $this->assertSame(0, Student::withoutGlobalScopes()->count(), 'kepala sekolah berhasil membuat siswa lewat aksi langsung');
    }

    public function test_guru_tidak_bisa_memanggil_halaman_data_pribadi_sama_sekali(): void
    {
        $s = $this->sekolah('SD A');
        $this->masukPanel($this->penggunaSekolah($s, Peran::GURU_MAPEL), 'sekolah', $s);

        foreach ([ManageStudents::class, ManageStaff::class, ManageGuardians::class] as $halaman) {
            try {
                Livewire::test($halaman)->callAction('create', ['name' => 'X', 'gender' => 'L', 'status' => 'aktif', 'kind' => 'guru', 'relationship' => 'wali']);
            } catch (\Throwable) {
                // ditolak dengan pengecualian juga benar; yang dinilai adalah data di basis data
            }
        }
        $this->assertSame(0, Student::withoutGlobalScopes()->count() + Staff::withoutGlobalScopes()->count() + Guardian::withoutGlobalScopes()->count());
    }

    public function test_staf_keuangan_tidak_bisa_mengubah_mata_pelajaran(): void
    {
        $s = $this->sekolah('SD A');
        $this->masukPanel($this->penggunaSekolah($s, Peran::STAF_KEUANGAN), 'sekolah', $s);

        Livewire::test(ManageSubjects::class)->assertActionHidden('create');
        try {
            Livewire::test(ManageSubjects::class)->callAction('create', ['code' => 'MTK', 'name' => 'Matematika']);
        } catch (\Throwable) {
        }
        $this->assertSame(0, Subject::withoutGlobalScopes()->count());
    }

    public function test_tidak_ada_tombol_hapus_pada_data_master(): void
    {
        $s = $this->sekolah('SD A');
        $this->untuk($s, fn () => Student::create(['name' => 'A', 'gender' => 'L']));
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageStudents::class)->assertTableActionDoesNotExist('delete');
        $this->assertSame(1, $this->untuk($s, fn () => Student::count()));
    }

    // ---------- formulir ----------

    public function test_staf_tu_menambah_siswa_dan_data_masuk_sekolah_aktif(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->masukPanel($this->penggunaSekolah($a, Peran::STAF_TU), 'sekolah', $a);

        Livewire::test(ManageStudents::class)
            ->callAction('create', ['name' => 'Budi', 'nisn' => '0012345678', 'gender' => 'L', 'status' => 'aktif'])
            ->assertHasNoActionErrors();

        $s = Student::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($a->id, $s->school_id);
        $this->assertNotSame($b->id, $s->school_id);
    }

    public function test_nisn_kembar_dan_salah_format_ditolak_di_formulir(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->untuk($a, fn () => Student::create(['name' => 'Ada', 'nisn' => '0012345678', 'gender' => 'L']));
        $this->untuk($b, fn () => Student::create(['name' => 'DiB', 'nisn' => '0099999999', 'gender' => 'L']));
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageStudents::class)
            ->callAction('create', ['name' => 'Kembar', 'nisn' => '0012345678', 'gender' => 'L', 'status' => 'aktif'])->assertHasActionErrors(['nisn']);
        Livewire::test(ManageStudents::class)
            ->callAction('create', ['name' => 'Pendek', 'nisn' => '123', 'gender' => 'L', 'status' => 'aktif'])->assertHasActionErrors(['nisn']);
        Livewire::test(ManageStudents::class) // NISN milik sekolah B tidak mengganggu sekolah A
            ->callAction('create', ['name' => 'Sah', 'nisn' => '0099999999', 'gender' => 'P', 'status' => 'aktif'])->assertHasNoActionErrors();
    }

    public function test_orang_tua_ditautkan_ke_beberapa_anak_lewat_formulir(): void
    {
        $a = $this->sekolah('SD A');
        $x = $this->untuk($a, fn () => Student::create(['name' => 'X', 'gender' => 'L']));
        $y = $this->untuk($a, fn () => Student::create(['name' => 'Y', 'gender' => 'P']));
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageGuardians::class)
            ->callAction('create', ['name' => 'Bapak Rudi', 'relationship' => 'ayah', 'students' => [$x->id, $y->id]])
            ->assertHasNoActionErrors();

        $g = Guardian::withoutGlobalScopes()->firstOrFail();
        $this->assertSame([$x->id, $y->id], $g->students()->withoutGlobalScopes()->orderBy('students.id')->pluck('students.id')->all());
    }

    public function test_formulir_tidak_bisa_menautkan_anak_sekolah_lain(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $anakB = $this->untuk($b, fn () => Student::create(['name' => 'Anak B', 'gender' => 'L']));
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        try {
            Livewire::test(ManageGuardians::class)
                ->callAction('create', ['name' => 'Ortu A', 'relationship' => 'ibu', 'students' => [$anakB->id]]);
        } catch (\Throwable) {
            // ditolak oleh validasi formulir atau oleh model; yang dinilai adalah tidak adanya tautan
        }
        $this->assertSame(0, DB::table('guardian_student')->count(), 'tautan lintas sekolah terbentuk');
    }

    // ---------- impor lewat antarmuka ----------

    private function taruBerkas(string $isi): string
    {
        Storage::fake('local');
        $path = 'impor-sementara/uji.xlsx';
        Storage::disk('local')->put($path, $isi);

        return $path;
    }

    public function test_admin_mengimpor_siswa_dari_excel_lewat_panel(): void
    {
        $a = $this->sekolah('SD A');
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);
        $path = $this->taruBerkas(file_get_contents($this->xlsx([$this->headerSiswa(), ['Budi', '0012345678', '1', 'L', 'Bandung', '2015-03-21'], ['Siti', '', '', 'P', '', '']])));

        Livewire::test(ManageStudents::class)->callAction('impor', ['berkas' => [$path]])->assertHasNoActionErrors();

        $this->assertSame(2, Student::withoutGlobalScopes()->where('school_id', $a->id)->count());
        Storage::disk('local')->assertMissing($path); // berkas sementara dihapus
    }

    public function test_impor_bermasalah_tidak_menyimpan_apa_pun_dan_berkas_tetap_dihapus(): void
    {
        $a = $this->sekolah('SD A');
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);
        $path = $this->taruBerkas(file_get_contents($this->xlsx([$this->headerSiswa(), ['Budi', '', '', 'L', '', ''], ['', '', '', 'P', '', '']])));

        Livewire::test(ManageStudents::class)->callAction('impor', ['berkas' => [$path]])->assertNotified();

        $this->assertSame(0, Student::withoutGlobalScopes()->count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_impor_masuk_ke_sekolah_aktif_bukan_sekolah_lain(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);
        $path = $this->taruBerkas(file_get_contents($this->xlsx([$this->headerSiswa(), ['Budi', '', '', 'L', '', '']])));

        Livewire::test(ManageStudents::class)->callAction('impor', ['berkas' => [$path]]);

        $this->assertSame(1, Student::withoutGlobalScopes()->where('school_id', $a->id)->count());
        $this->assertSame(0, Student::withoutGlobalScopes()->where('school_id', $b->id)->count());
    }

    public function test_admin_mengunduh_templat_excel(): void
    {
        $a = $this->sekolah('SD A');
        $this->masukPanel($this->adminDari($a), 'sekolah', $a);

        Livewire::test(ManageStudents::class)->callAction('templat')->assertFileDownloaded('templat-siswa.xlsx');
        Livewire::test(ManageStaff::class)->callAction('templat')->assertFileDownloaded('templat-guru-dan-tendik.xlsx');
    }
}
