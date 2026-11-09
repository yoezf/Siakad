<?php

namespace Tests\Feature\Kehadiran;

use App\Filament\Sekolah\Pages\PresensiGuru;
use App\Filament\Sekolah\Pages\PresensiSiswa;
use App\Filament\Sekolah\Pages\ProfilSekolah;
use App\Filament\Sekolah\Pages\RekapKehadiran;
use App\Models\StaffAttendance;
use App\Models\StudentAttendance;
use App\Modules\Kehadiran\CatatKehadiranSiswa;
use App\Support\Peran;
use Livewire\Livewire;

class PanelKehadiranTest extends KehadiranTestCase
{
    private function masuk($akun): void
    {
        $this->masukPanel($akun, 'sekolah', $this->s);
    }

    private function jumlah(): int
    {
        return StudentAttendance::withoutGlobalScopes()->count();
    }

    private function statusDi(int $siswaId, ?int $entriId = null): ?string
    {
        return StudentAttendance::withoutGlobalScopes()->where('student_id', $siswaId)->where('schedule_entry_id', $entriId)->value('status');
    }

    // ---------- catat kehadiran harian ----------

    public function test_admin_membuka_halaman_dan_semua_siswa_kelas_tampil_dengan_status_hadir(): void
    {
        $this->masuk($this->akunAdmin);

        Livewire::test(PresensiSiswa::class)
            ->set('kelasId', $this->kelasA->id)
            ->assertSee('Siswa A1')->assertSee('Siswa A2')->assertDontSee('Siswa B1')
            ->assertSet("status.{$this->a1->id}", 'H')->assertSet("status.{$this->a2->id}", 'H')
            ->assertSet('tanggal', self::KAMIS);
    }

    public function test_admin_menyimpan_kehadiran_dan_memuatnya_kembali(): void
    {
        $this->masuk($this->akunAdmin);

        $k = Livewire::test(PresensiSiswa::class)->set('kelasId', $this->kelasA->id)->set('tanggal', self::SENIN)
            ->set("status.{$this->a2->id}", 'S')->set("catatan.{$this->a2->id}", 'demam')
            ->call('simpan')->assertNotified();

        $this->assertSame('H', $this->statusDi($this->a1->id));
        $this->assertSame('S', $this->statusDi($this->a2->id));
        $this->assertSame('demam', StudentAttendance::withoutGlobalScopes()->where('student_id', $this->a2->id)->value('note'));

        // pindah tanggal lalu kembali: nilai tersimpan dimuat ulang, bukan kembali ke bawaan
        $k->set('tanggal', self::SELASA)->assertSet("status.{$this->a2->id}", 'H')
            ->set('tanggal', self::SENIN)->assertSet("status.{$this->a2->id}", 'S')->assertSet("catatan.{$this->a2->id}", 'demam');
    }

    public function test_tandai_semua_hadir_mengembalikan_seluruh_status(): void
    {
        $this->masuk($this->akunAdmin);

        Livewire::test(PresensiSiswa::class)->set('kelasId', $this->kelasA->id)
            ->set("status.{$this->a1->id}", 'A')->set("status.{$this->a2->id}", 'S')
            ->call('semuaHadir')->assertSet("status.{$this->a1->id}", 'H')->assertSet("status.{$this->a2->id}", 'H');
    }

    public function test_tanggal_masa_depan_tidak_tersimpan_dan_dijelaskan(): void
    {
        $this->masuk($this->akunAdmin);

        Livewire::test(PresensiSiswa::class)->set('kelasId', $this->kelasA->id)->set('tanggal', self::JUMAT)
            ->call('simpan')->assertNotified('Kehadiran tidak tersimpan');

        $this->assertSame(0, $this->jumlah());
    }

    public function test_status_dari_browser_yang_tidak_sah_tidak_tersimpan(): void
    {
        $this->masuk($this->akunAdmin);

        Livewire::test(PresensiSiswa::class)->set('kelasId', $this->kelasA->id)->set('tanggal', self::SENIN)
            ->set("status.{$this->a1->id}", 'H')->set("status.{$this->a2->id}", '<script>')
            ->call('simpan')->assertNotified('Kehadiran tidak tersimpan');

        $this->assertSame(0, $this->jumlah(), 'semua dibatalkan, termasuk siswa yang statusnya sah');
    }

    // ---------- wali kelas ----------

    public function test_wali_kelas_hanya_melihat_kelasnya_di_pilihan(): void
    {
        $this->masuk($this->waliA);

        $k = Livewire::test(PresensiSiswa::class);
        $this->assertSame([$this->kelasA->id => '4A'], $k->instance()->opsiKelas());
        $k->assertSet('kelasId', $this->kelasA->id)->assertSee('Siswa A1')->assertDontSee('Siswa B1');
    }

    public function test_wali_kelas_mengubah_kelas_dari_browser_tidak_bisa_mengintip_atau_menyimpan(): void
    {
        $this->masuk($this->waliA);

        Livewire::test(PresensiSiswa::class)->set('kelasId', $this->kelasB->id)->set('tanggal', self::SENIN)
            ->assertDontSee('Siswa B1')->assertSee('Tidak ada siswa yang bisa dicatat')
            ->call('simpan')->assertNotified('Pilih kelas yang berhak Anda catat.');

        $this->assertSame(0, $this->jumlah());
    }

    public function test_wali_kelas_menyimpan_kehadiran_kelasnya_sendiri(): void
    {
        $this->masuk($this->waliA);

        Livewire::test(PresensiSiswa::class)->set('tanggal', self::SENIN)->set("status.{$this->a1->id}", 'I')->call('simpan')->assertNotified();

        $this->assertSame('I', $this->statusDi($this->a1->id));
        $this->assertSame($this->waliA->id, StudentAttendance::withoutGlobalScopes()->value('recorded_by'));
    }

    // ---------- per pelajaran ----------

    public function test_guru_pengampu_mencatat_per_pelajaran_pada_hari_yang_dijadwalkan(): void
    {
        $this->masuk($this->guruMtk);

        $k = Livewire::test(PresensiSiswa::class)->set('jenis', 'pelajaran')->set('tanggal', self::SENIN);
        $this->assertSame([$this->kelasA->id => '4A'], $k->instance()->opsiKelas());
        $this->assertCount(1, $k->instance()->opsiEntri());

        $k->set('entriId', $this->entriSenin->id)->assertSee('Siswa A1')
            ->set("status.{$this->a2->id}", 'T')->call('simpan')->assertNotified();

        $this->assertSame('T', $this->statusDi($this->a2->id, $this->entriSenin->id));
        $this->assertNull($this->statusDi($this->a2->id), 'tidak boleh menyentuh kehadiran harian');
    }

    public function test_pada_hari_tanpa_pelajaran_tidak_ada_pilihan_pelajaran(): void
    {
        $this->masuk($this->guruMtk);

        $k = Livewire::test(PresensiSiswa::class)->set('jenis', 'pelajaran')->set('tanggal', self::SELASA);
        $this->assertCount(0, $k->instance()->opsiEntri());
        $k->assertSee('Pilih pelajaran yang dijadwalkan');
    }

    public function test_guru_mapel_tidak_punya_pilihan_kelas_untuk_kehadiran_harian(): void
    {
        $this->masuk($this->guruMtk);

        $this->assertSame([], Livewire::test(PresensiSiswa::class)->set('jenis', 'harian')->instance()->opsiKelas());
    }

    public function test_guru_tidak_bisa_memakai_pelajaran_milik_guru_lain_dengan_mengubah_nilai_dari_browser(): void
    {
        $lain = $this->penggunaSekolah($this->s, Peran::GURU_MAPEL, 'Guru Lain');
        $stafLain = $this->guruDi($this->s, 'Bu Lain', $lain);
        $ipa = $this->mapel($this->s, 'IPA', 'IPA');
        $this->penugasan($this->s, $this->kelasA, $ipa, $stafLain);
        $jam2 = $this->jam($this->s, 2, '08:00', '08:45');
        $milikLain = $this->jadwal($this->s, $this->kelasA, $ipa, $jam2, 1); // Senin, diajar Bu Lain
        $this->masuk($this->guruMtk);

        Livewire::test(PresensiSiswa::class)->set('jenis', 'pelajaran')->set('tanggal', self::SENIN)->set('kelasId', $this->kelasA->id)
            ->set('entriId', $milikLain->id)->assertDontSee('Siswa A1')->call('simpan')->assertNotified('Pilih kelas yang berhak Anda catat.');

        $this->assertSame(0, $this->jumlah());
    }

    // ---------- akses halaman ----------

    public function test_hak_akses_halaman_kehadiran_siswa(): void
    {
        $semua = ['presensi-siswa', 'rekap-kehadiran'];
        $harapan = [
            Peran::ADMIN_SEKOLAH => [200, 200], Peran::STAF_TU => [200, 200], Peran::KEPALA_SEKOLAH => [403, 200],
            Peran::WALI_KELAS => [200, 200], Peran::GURU_MAPEL => [200, 200], Peran::GURU_BK => [403, 403], Peran::STAF_KEUANGAN => [403, 403],
        ];
        foreach ($harapan as $peran => $kode) {
            $akun = $peran === Peran::ADMIN_SEKOLAH ? $this->adminDari($this->s) : $this->penggunaSekolah($this->s, $peran);
            foreach ($semua as $i => $halaman) {
                $this->flushSession();
                auth()->forgetGuards();
                $this->assertSame($kode[$i], $this->actingAs($akun)->get("/sekolah/{$this->s->slug}/$halaman")->getStatusCode(), "$peran di $halaman");
            }
        }
    }

    // ---------- rekap ----------

    public function test_halaman_rekap_menampilkan_hitungan_dan_persen(): void
    {
        $this->untuk($this->s, fn () => CatatKehadiranSiswa::handle($this->akunAdmin, $this->kelasA, self::SENIN, null,
            [$this->a1->id => ['status' => 'H'], $this->a2->id => ['status' => 'A']]));
        $this->masuk($this->akunKepala);

        $k = Livewire::test(RekapKehadiran::class)->set('kelasId', $this->kelasA->id)->set('periode', 'hari')->set('tanggal', self::SENIN);
        $baris = collect($k->instance()->baris())->keyBy(fn ($r) => $r['student']->name);
        $this->assertSame(100, $baris['Siswa A1']['persen']);
        $this->assertSame(0, $baris['Siswa A2']['persen']);
        $k->assertSee('Siswa A1')->assertSee('100%')->assertSee('0%');
    }

    public function test_rekap_bulan_dan_semester_bisa_dipilih(): void
    {
        $this->untuk($this->s, fn () => CatatKehadiranSiswa::handle($this->akunAdmin, $this->kelasA, self::SENIN, null, [$this->a1->id => ['status' => 'H']]));
        $sem = $this->semester($this->s, $this->y, 1, '2026-07-01', '2026-12-31');
        $this->masuk($this->akunAdmin);

        $k = Livewire::test(RekapKehadiran::class)->set('kelasId', $this->kelasA->id);
        $this->assertSame(1, collect($k->set('periode', 'bulan')->set('bulan', '2026-11')->instance()->baris())->firstWhere(fn ($r) => $r['student']->id === $this->a1->id)['total']);
        $this->assertSame(0, collect($k->set('bulan', '2026-10')->instance()->baris())->firstWhere(fn ($r) => $r['student']->id === $this->a1->id)['total']);
        $this->assertSame(1, collect($k->set('periode', 'semester')->set('semesterId', $sem->id)->instance()->baris())->firstWhere(fn ($r) => $r['student']->id === $this->a1->id)['total']);
    }

    public function test_wali_kelas_hanya_bisa_merekap_kelasnya_dan_nilai_dari_browser_tidak_dipercaya(): void
    {
        $this->untuk($this->s, fn () => CatatKehadiranSiswa::handle($this->akunAdmin, $this->kelasB, self::SENIN, null, [$this->b1->id => ['status' => 'H']]));
        $this->masuk($this->waliA);

        $k = Livewire::test(RekapKehadiran::class);
        $this->assertSame([$this->kelasA->id => '4A'], $k->instance()->opsiKelas());
        $k->set('kelasId', $this->kelasB->id)->set('periode', 'hari')->set('tanggal', self::SENIN)->assertDontSee('Siswa B1');
        $this->assertSame([], $k->instance()->baris());
    }

    // ---------- kehadiran guru ----------

    public function test_admin_mencatat_kehadiran_guru_lewat_halaman(): void
    {
        $this->masuk($this->akunAdmin);

        Livewire::test(PresensiGuru::class)->set('tanggal', self::SENIN)
            ->set("status.{$this->stafGuruMtk->id}", 'S')->call('simpan')->assertNotified();

        $this->assertSame('S', StaffAttendance::withoutGlobalScopes()->where('staff_id', $this->stafGuruMtk->id)->value('status'));
        $this->assertSame('H', StaffAttendance::withoutGlobalScopes()->where('staff_id', $this->stafWaliA->id)->value('status'));
    }

    public function test_kepala_sekolah_hanya_melihat_rekap_dan_tidak_bisa_menyimpan(): void
    {
        $this->masuk($this->akunKepala);

        Livewire::test(PresensiGuru::class)->assertDontSee('Simpan kehadiran')->assertSee('Rekap bulanan')
            ->set("status.{$this->stafGuruMtk->id}", 'A')->call('simpan')->assertNotified('Anda tidak berhak mencatat kehadiran guru.');

        $this->assertSame(0, StaffAttendance::withoutGlobalScopes()->count());
    }

    public function test_hak_akses_halaman_kehadiran_guru(): void
    {
        $harapan = [Peran::ADMIN_SEKOLAH => 200, Peran::STAF_TU => 200, Peran::KEPALA_SEKOLAH => 200,
            Peran::WALI_KELAS => 403, Peran::GURU_MAPEL => 403, Peran::GURU_BK => 403, Peran::STAF_KEUANGAN => 403];
        foreach ($harapan as $peran => $kode) {
            $akun = $peran === Peran::ADMIN_SEKOLAH ? $this->adminDari($this->s) : $this->penggunaSekolah($this->s, $peran);
            $this->flushSession();
            auth()->forgetGuards();
            $this->assertSame($kode, $this->actingAs($akun)->get("/sekolah/{$this->s->slug}/presensi-guru")->getStatusCode(), $peran);
        }
    }

    public function test_menu_dan_halaman_kehadiran_guru_hilang_saat_sekolah_mematikannya(): void
    {
        $this->masuk($this->akunAdmin);
        Livewire::test(ProfilSekolah::class)->fillForm([
            'name' => 'SD A', 'levels' => ['sd'],
            'settings' => ['kurikulum' => 'Kurikulum Merdeka', 'kkm' => 70, 'peringkat_aktif' => false, 'absensi_guru' => false],
        ])->call('save')->assertHasNoFormErrors();

        $this->assertFalse($this->s->fresh()->setting('absensi_guru'));
        $this->flushSession();
        auth()->forgetGuards();
        $this->actingAs($this->akunAdmin->fresh())->get("/sekolah/{$this->s->slug}/presensi-guru")->assertForbidden();
        $this->assertFalse(PresensiGuru::shouldRegisterNavigation());
    }

    public function test_sekolah_baru_memakai_absensi_guru_secara_bawaan(): void
    {
        $this->assertTrue($this->sekolah('SD Lain')->setting('absensi_guru'));
    }
}
