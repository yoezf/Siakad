<?php

namespace Tests\Feature\Demo;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\ReportCard;
use App\Models\ScheduleEntry;
use App\Models\School;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Pengumuman\Daftar;
use App\Modules\Portal\Anak;
use App\Modules\Rapor\Susun;
use App\Support\Peran;
use App\Support\Tenancy\CurrentSchool;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

/** Data demo harus utuh, konsisten, dan terpisah antar sekolah; bila tidak, presentasi gagal di depan penonton. */
class DemoTest extends TestCase
{
    // setUp milik trait tertimpa setUp kelas ini, jadi dipanggil lewat alias agar peran tetap terisi.
    use SiapkanSekolah { setUp as private setUpSekolah; }

    private DemoSeeder $demo;

    protected function setUp(): void
    {
        $this->setUpSekolah();
        $this->demo = new DemoSeeder;
        $this->demo->run();
    }

    /** Mengisi data demo makan waktu sekitar satu menit, jadi seluruh pemeriksaan berbagi satu kali pengisian. */
    public function test_data_demo_utuh_konsisten_dan_terpisah_antar_sekolah(): void
    {
        $this->periksa_isi_data_kedua_sekolah_lengkap();
        $this->periksa_status_rapor_sesuai_skenario_dan_semua_salinan_utuh();
        $this->periksa_akun_setiap_peran_masuk_ke_panel_yang_benar_dan_sandinya_sama();
        $this->periksa_orang_tua_dan_siswa_demo_hanya_melihat_data_miliknya();
        $this->periksa_data_sd_tidak_terlihat_dari_sekolah_smp();
    }

    private function sekolah_(string $nama): School
    {
        return School::where('name', $nama)->firstOrFail();
    }

    private function periksa_isi_data_kedua_sekolah_lengkap(): void
    {
        $sd = $this->sekolah_('SD Harapan Bangsa');
        $smp = $this->sekolah_('SMP Cendekia Nusantara');

        $this->untuk($sd, function () {
            $this->assertSame(['4A', '5A', '6A'], Classroom::orderBy('name')->pluck('name')->all());
            $this->assertSame(36, Student::count());
            $this->assertGreaterThan(0, ScheduleEntry::count());
            foreach (Classroom::all() as $k) {
                $this->assertGreaterThan(20, ScheduleEntry::where('classroom_id', $k->id)->count(), "jadwal {$k->name} terisi");
            }
            $this->assertSame(5, Announcement::count());
        });
        $this->untuk($smp, function () {
            $this->assertSame(['7A', '8A'], Classroom::orderBy('name')->pluck('name')->all());
            $this->assertSame(20, Student::count());
            $this->assertSame(2, Announcement::count());
        });
        $this->assertSame(['lengkap', 'lengkap'], [$sd->plan, $smp->plan]);
        $this->assertSame([70, 75], [(int) $sd->setting('kkm'), (int) $smp->setting('kkm')]);
    }

    private function periksa_status_rapor_sesuai_skenario_dan_semua_salinan_utuh(): void
    {
        $sd = $this->sekolah_('SD Harapan Bangsa');
        $this->untuk($sd, function () {
            $per = ReportCard::all()->groupBy('status')->map->count();
            $this->assertSame(12, $per['disahkan'], '3 di 4A + 9 di 6A');
            $this->assertSame(1, $per['revisi']);
            foreach (ReportCard::all() as $r) {
                $this->assertTrue($r->utuh(), "rapor {$r->id} utuh");
            }
            $sem = Semester::where('aktif', 1)->firstOrFail();
            $kelas = fn ($n) => Classroom::where('name', $n)->firstOrFail();
            $siswa = fn ($n) => Student::whereIn('id', \App\Models\Enrollment::where('classroom_id', $kelas($n)->id)->select('student_id'))->orderBy('id')->get();

            $tanpaMasalah = fn ($s) => Susun::untuk($sem, $s)['masalah'] === [];
            $this->assertTrue($tanpaMasalah($siswa('6A')[10]), 'siap sahkan');
            $this->assertFalse($tanpaMasalah($siswa('6A')[11]), 'kurang satu nilai');
            foreach ($siswa('5A') as $s) {
                $this->assertFalse($tanpaMasalah($s), "5A belum ada UAS: {$s->name}");
            }
            foreach ($siswa('4A') as $s) {
                $this->assertTrue($tanpaMasalah($s), "4A lengkap: {$s->name}");
            }
        });
    }

    private function periksa_akun_setiap_peran_masuk_ke_panel_yang_benar_dan_sandinya_sama(): void
    {
        $staf = [Peran::ADMIN_SEKOLAH, Peran::KEPALA_SEKOLAH, Peran::WALI_KELAS, Peran::GURU_MAPEL, Peran::STAF_TU];
        $panelSekolah = Filament::getPanel('sekolah');
        $panelPortal = Filament::getPanel('portal');
        $dilihat = [];
        foreach ($this->demo->daftarAkun() as $a) {
            $u = User::where('email', $a['email'])->firstOrFail();
            $this->assertTrue(password_verify(DemoSeeder::SANDI, $u->password), "sandi {$a['email']}");
            if ($u->hasRole(Peran::SUPER_ADMIN)) {
                $this->assertTrue($u->canAccessPanel(Filament::getPanel('admin')));

                continue;
            }
            $peran = $u->roles->first()->name;
            $dilihat[$peran] = true;
            $this->assertSame(in_array($peran, $staf, true), $u->canAccessPanel($panelSekolah), "{$a['email']} panel sekolah");
            $this->assertSame(in_array($peran, Peran::PORTAL, true), $u->canAccessPanel($panelPortal), "{$a['email']} portal");
        }
        $this->assertEqualsCanonicalizing([...$staf, Peran::ORANG_TUA, Peran::SISWA], array_keys($dilihat), 'setiap peran punya akun demo');
    }

    private function periksa_orang_tua_dan_siswa_demo_hanya_melihat_data_miliknya(): void
    {
        $sd = $this->sekolah_('SD Harapan Bangsa');
        $ortu = User::where('email', 'ortu@harapanbangsa.demo')->firstOrFail();
        $siswa = User::where('email', 'siswa@harapanbangsa.demo')->firstOrFail();
        $ortuSmp = User::where('email', 'ortu@cendekia.demo')->firstOrFail();

        $this->assertCount(2, Anak::untuk($ortu));
        $this->assertCount(1, Anak::untuk($siswa));
        $this->assertSame(1, Anak::untuk($ortuSmp)->count());
        $this->assertSame([], array_intersect(Anak::untuk($ortu)->pluck('id')->all(), Anak::untuk($ortuSmp)->pluck('id')->all()));

        $judul = Daftar::untuk($ortu)->pluck('title')->all();
        $this->assertContains('Libur Semester Ganjil', $judul);
        $this->assertContains('Try Out Kelas 6', $judul);
        $this->assertNotContains('Lomba Menggambar Antar Sekolah', $judul, 'khusus siswa');
        $this->assertNotContains('Jadwal Ujian Akhir Semester (draf)', $judul, 'draf');
        $this->assertNotContains('Pekan Ujian Tengah Semester', $judul, 'milik SMP');
        $this->assertContains('Lomba Menggambar Antar Sekolah', Daftar::untuk($siswa)->pluck('title')->all());
        $this->assertNotNull($sd);
    }

    private function periksa_data_sd_tidak_terlihat_dari_sekolah_smp(): void
    {
        $smp = $this->sekolah_('SMP Cendekia Nusantara');

        $this->untuk($smp, function () {
            $this->assertSame(0, Student::where('name', 'like', '%Ratna%')->count());
            $this->assertSame(20, Student::count());
            $this->assertSame(0, ReportCard::where('status', 'revisi')->count());
            $this->assertSame(6, ReportCard::where('status', 'disahkan')->count());
        });
        CurrentSchool::set(null);
    }
}
