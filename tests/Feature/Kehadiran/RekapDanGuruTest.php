<?php

namespace Tests\Feature\Kehadiran;

use App\Models\Enrollment;
use App\Models\StaffAttendance;
use App\Models\StudentAttendance;
use App\Modules\Kehadiran\CatatKehadiranGuru;
use App\Modules\Kehadiran\CatatKehadiranSiswa;
use App\Modules\Kehadiran\Rekap;
use App\Support\Peran;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Activity;

class RekapDanGuruTest extends KehadiranTestCase
{
    private function catat($kelas, string $tanggal, array $baris, $entri = null): void
    {
        $this->untuk($this->s, fn () => CatatKehadiranSiswa::handle($this->akunAdmin, $kelas, $tanggal, $entri, $baris));
    }

    /** a1: H, T, I   a2: S, A, H   (Senin-Rabu); ditambah satu catatan per pelajaran yang TIDAK boleh ikut rekap. */
    private function isiPekan(): void
    {
        $data = [
            self::SENIN => ['H', 'S'], self::SELASA => ['T', 'A'], self::RABU => ['I', 'H'],
        ];
        foreach ($data as $tgl => [$s1, $s2]) {
            $this->catat($this->kelasA, $tgl, [$this->a1->id => ['status' => $s1], $this->a2->id => ['status' => $s2]]);
        }
        $this->catat($this->kelasA, self::SENIN, [$this->a1->id => ['status' => 'A'], $this->a2->id => ['status' => 'A']], $this->entriSenin);
    }

    private function rekap($kelas, string $dari, string $sampai): array
    {
        return $this->untuk($this->s, fn () => collect(Rekap::kelas($kelas, Carbon::parse($dari), Carbon::parse($sampai)))->keyBy(fn ($r) => $r['student']->name)->all());
    }

    // ---------- rekap kelas ----------

    public function test_rekap_menghitung_status_total_dan_persen_dengan_terlambat_dihitung_hadir(): void
    {
        $this->isiPekan();
        $r = $this->rekap($this->kelasA, self::SENIN, self::RABU);

        $a1 = $r['Siswa A1'];
        $this->assertSame([1, 0, 1, 0, 1], [$a1['H'], $a1['S'], $a1['I'], $a1['A'], $a1['T']]);
        $this->assertSame(3, $a1['total']);
        $this->assertSame(67, $a1['persen']); // (hadir 1 + terlambat 1) / 3

        $a2 = $r['Siswa A2'];
        $this->assertSame([1, 1, 0, 1, 0], [$a2['H'], $a2['S'], $a2['I'], $a2['A'], $a2['T']]);
        $this->assertSame(33, $a2['persen']);
    }

    public function test_kehadiran_per_pelajaran_tidak_dicampur_ke_rekap_harian(): void
    {
        $this->isiPekan(); // sudah memuat catatan alpa per pelajaran pada hari Senin

        $this->assertSame(0, $this->rekap($this->kelasA, self::SENIN, self::SENIN)['Siswa A1']['A']);
        $this->assertSame(1, $this->rekap($this->kelasA, self::SENIN, self::SENIN)['Siswa A1']['H']);
    }

    public function test_rekap_menurut_rentang_hari_bulan_dan_di_luar_rentang(): void
    {
        $this->isiPekan();

        $hari = $this->rekap($this->kelasA, self::SENIN, self::SENIN);
        $this->assertSame([1, 100], [$hari['Siswa A1']['total'], $hari['Siswa A1']['persen']]);
        $this->assertSame([1, 0], [$hari['Siswa A2']['total'], $hari['Siswa A2']['persen']]);

        $bulan = $this->rekap($this->kelasA, '2026-11-01', '2026-11-30');
        $this->assertSame(3, $bulan['Siswa A1']['total']);

        $kosong = $this->rekap($this->kelasA, '2026-10-01', '2026-10-31');
        $this->assertSame([0, null], [$kosong['Siswa A1']['total'], $kosong['Siswa A1']['persen']], 'tanpa data: nol, bukan 0%');
    }

    public function test_siswa_tanpa_catatan_tampil_nol_dan_urutan_nama_wajar(): void
    {
        $adi = $this->siswaDi($this->s, 'adi tanpa catatan');
        $this->daftarkan($adi, $this->kelasA);

        $urut = $this->untuk($this->s, fn () => array_map(fn ($r) => $r['student']->name, Rekap::kelas($this->kelasA, Carbon::parse(self::SENIN), Carbon::parse(self::SENIN))));

        $this->assertSame(['adi tanpa catatan', 'Siswa A1', 'Siswa A2'], $urut);
        $this->assertSame(0, $this->rekap($this->kelasA, self::SENIN, self::SENIN)['adi tanpa catatan']['total']);
    }

    public function test_siswa_yang_sudah_keluar_hanya_muncul_pada_periode_ia_masih_terdaftar(): void
    {
        $penempatan = Enrollment::withoutGlobalScopes()->where('student_id', $this->a2->id)->firstOrFail();
        DB::table('enrollments')->where('id', $penempatan->id)->update(['aktif' => null, 'left_at' => self::SELASA]);

        $this->assertArrayHasKey('Siswa A2', $this->rekap($this->kelasA, self::SENIN, self::KAMIS));
        $this->assertArrayHasKey('Siswa A2', $this->rekap($this->kelasA, self::SELASA, self::SELASA)); // hari keluar masih dihitung
        $this->assertArrayNotHasKey('Siswa A2', $this->rekap($this->kelasA, self::RABU, self::KAMIS));
    }

    public function test_rekap_satu_siswa_menggabungkan_semua_kelasnya_untuk_rapor(): void
    {
        $this->catat($this->kelasA, self::SENIN, [$this->a1->id => ['status' => 'H']]);
        $lama = Enrollment::withoutGlobalScopes()->where('student_id', $this->a1->id)->firstOrFail();
        DB::table('enrollments')->where('id', $lama->id)->update(['aktif' => null, 'left_at' => self::SENIN]);
        $this->daftarkan($this->a1, $this->kelasB, self::SELASA);
        $this->catat($this->kelasB, self::SELASA, [$this->a1->id => ['status' => 'S']]);

        $r = $this->untuk($this->s, fn () => Rekap::siswa($this->a1, Carbon::parse(self::SENIN), Carbon::parse(self::RABU)));

        $this->assertSame([1, 1, 2, 50], [$r['H'], $r['S'], $r['total'], $r['persen']]);
    }

    public function test_rekap_sekolah_lain_tidak_bocor(): void
    {
        $this->isiPekan();
        $b = $this->sekolah('SD B');
        $kelasB = $this->kelas($b, $this->tahunAjaran($b, '2026/2027', '2026-07-01', '2027-06-30'), '4A');

        $this->assertSame([], $this->untuk($b, fn () => Rekap::kelas($kelasB, Carbon::parse(self::SENIN), Carbon::parse(self::RABU))));
    }

    // ---------- siapa boleh melihat ----------

    public function test_hak_melihat_rekap_per_peran(): void
    {
        $guruBk = $this->penggunaSekolah($this->s, Peran::GURU_BK);
        $keuangan = $this->penggunaSekolah($this->s, Peran::STAF_KEUANGAN);
        $matriks = [
            'admin' => [$this->akunAdmin, true, true],
            'TU' => [$this->akunTu, true, true],
            'kepala' => [$this->akunKepala, true, true],
            'wali A' => [$this->waliA, true, false],
            'wali B' => [$this->waliB, false, true],
            'guru MTK (mengampu 4A)' => [$this->guruMtk, true, false],
            'guru BK' => [$guruBk, false, false],
            'staf keuangan' => [$keuangan, false, false],
        ];
        foreach ($matriks as $nama => [$akun, $kelasA, $kelasB]) {
            $this->assertSame($kelasA, Rekap::bisaMelihat($akun, $this->kelasA), "$nama melihat 4A");
            $this->assertSame($kelasB, Rekap::bisaMelihat($akun, $this->kelasB), "$nama melihat 4B");
        }
    }

    public function test_pengguna_sekolah_lain_tidak_bisa_melihat_rekap_kelas_ini(): void
    {
        $b = $this->sekolah('SD B');

        $this->assertFalse(Rekap::bisaMelihat($this->adminDari($b), $this->kelasA));
    }

    // ---------- kehadiran guru dan tenaga kependidikan ----------

    private function catatGuru($oleh, string $tanggal, array $baris): int
    {
        return $this->untuk($this->s, fn () => CatatKehadiranGuru::handle($oleh, $tanggal, $baris));
    }

    public function test_admin_dan_tu_mencatat_kehadiran_guru_dan_tendik(): void
    {
        $tendik = $this->guruDi($this->s, 'Pak TU Sekolah', null, 'tendik');

        $n = $this->catatGuru($this->akunAdmin, self::SENIN, [
            $this->stafGuruMtk->id => ['status' => 'H'],
            $tendik->id => ['status' => 'S', 'note' => 'sakit'],
        ]);
        $this->catatGuru($this->akunTu, self::SELASA, [$this->stafGuruMtk->id => ['status' => 'T']]);

        $this->assertSame(2, $n);
        $this->assertSame('S', StaffAttendance::withoutGlobalScopes()->where('staff_id', $tendik->id)->value('status'));
        $this->assertSame($this->akunAdmin->id, StaffAttendance::withoutGlobalScopes()->where('staff_id', $tendik->id)->value('recorded_by'));
        $this->assertSame(3, StaffAttendance::withoutGlobalScopes()->count());
    }

    public function test_kepala_guru_wali_dan_peran_lain_tidak_bisa_mencatat_kehadiran_guru(): void
    {
        foreach ([$this->akunKepala, $this->waliA, $this->guruMtk, $this->penggunaSekolah($this->s, Peran::STAF_KEUANGAN)] as $akun) {
            try {
                $this->catatGuru($akun, self::SENIN, [$this->stafGuruMtk->id => ['status' => 'H']]);
                $this->fail('berhasil mencatat kehadiran guru');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, StaffAttendance::withoutGlobalScopes()->count());
    }

    public function test_sekolah_dapat_mematikan_absensi_guru(): void
    {
        $this->assertTrue($this->s->setting('absensi_guru'), 'bawaannya aktif');
        $this->untuk($this->s, fn () => $this->s->update(['settings' => ['absensi_guru' => false]]));
        $admin = $this->akunAdmin->fresh();

        try {
            $this->catatGuru($admin, self::SENIN, [$this->stafGuruMtk->id => ['status' => 'H']]);
            $this->fail('seharusnya ditolak saat absensi guru dimatikan');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tidak diaktifkan', $e->errors()['staff_id'][0]);
        }
        $this->assertSame(0, StaffAttendance::withoutGlobalScopes()->count());
    }

    public function test_pegawai_nonaktif_atau_sekolah_lain_ditolak_dan_semua_dibatalkan(): void
    {
        $nonaktif = $this->guruDi($this->s, 'Sudah Pensiun', null, 'guru', 'nonaktif');
        $b = $this->sekolah('SD B');
        $guruB = $this->guruDi($b, 'Guru SD B');

        foreach ([$nonaktif, $guruB] as $bukan) {
            try {
                $this->catatGuru($this->akunAdmin, self::SENIN, [$this->stafGuruMtk->id => ['status' => 'H'], $bukan->id => ['status' => 'H']]);
                $this->fail('pegawai tidak sah lolos');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, StaffAttendance::withoutGlobalScopes()->count(), 'guru pertama tidak boleh tersimpan');
    }

    public function test_kehadiran_guru_diperbarui_tanpa_duplikat_dan_tercatat_di_audit(): void
    {
        $this->catatGuru($this->akunAdmin, self::SENIN, [$this->stafGuruMtk->id => ['status' => 'H']]);
        $this->catatGuru($this->akunTu, self::SENIN, [$this->stafGuruMtk->id => ['status' => 'A', 'note' => 'tanpa kabar']]);

        $this->assertSame(1, StaffAttendance::withoutGlobalScopes()->count());
        $a = StaffAttendance::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('A', $a->status);
        $this->assertSame($this->akunTu->id, $a->recorded_by);
        $this->assertTrue(Activity::where('log_name', 'kehadiran_pegawai')->where('subject_id', $a->id)->where('event', 'updated')->exists());
    }

    public function test_tanggal_masa_depan_status_salah_dan_format_tanggal_ditolak(): void
    {
        $baris = [$this->stafGuruMtk->id => ['status' => 'H']];
        foreach ([[self::JUMAT, $baris, 'date'], [self::SENIN, [$this->stafGuruMtk->id => ['status' => 'Z']], 'status'], ['10-11-2026', $baris, 'date']] as [$tgl, $b, $kunci]) {
            try {
                $this->catatGuru($this->akunAdmin, $tgl, $b);
                $this->fail("lolos: $tgl");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($kunci, $e->errors());
            }
        }
        $this->assertSame(0, StaffAttendance::withoutGlobalScopes()->count());
    }

    public function test_pegawai_dan_tanggal_pada_catatan_guru_tidak_bisa_diubah(): void
    {
        $this->catatGuru($this->akunAdmin, self::SENIN, [$this->stafGuruMtk->id => ['status' => 'H']]);
        $a = StaffAttendance::withoutGlobalScopes()->firstOrFail();

        $this->expectException(LogicException::class);
        $this->untuk($this->s, fn () => $a->fresh()->update(['date' => self::SELASA]));
    }

    public function test_basis_data_menolak_catatan_guru_ganda_dan_lintas_sekolah(): void
    {
        $this->catatGuru($this->akunAdmin, self::SENIN, [$this->stafGuruMtk->id => ['status' => 'H']]);
        $b = $this->sekolah('SD B');
        $guruB = $this->guruDi($b, 'Guru SD B');

        foreach ([
            'ganda' => ['school_id' => $this->s->id, 'staff_id' => $this->stafGuruMtk->id, 'date' => self::SENIN, 'status' => 'A'],
            'lintas sekolah' => ['school_id' => $this->s->id, 'staff_id' => $guruB->id, 'date' => self::SENIN, 'status' => 'H'],
        ] as $nama => $data) {
            try {
                DB::table('staff_attendances')->insert($data);
                $this->fail("SQL langsung lolos untuk $nama");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_rekap_bulanan_guru_menghitung_semua_pegawai_aktif(): void
    {
        $tendik = $this->guruDi($this->s, 'Pak Tendik', null, 'tendik');
        $this->catatGuru($this->akunAdmin, self::SENIN, [$this->stafGuruMtk->id => ['status' => 'H'], $tendik->id => ['status' => 'S']]);
        $this->catatGuru($this->akunAdmin, self::SELASA, [$this->stafGuruMtk->id => ['status' => 'T'], $tendik->id => ['status' => 'H']]);

        $r = $this->untuk($this->s, fn () => collect(Rekap::pegawai(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')))->keyBy(fn ($x) => $x['staff']->name));

        $this->assertSame([2, 100], [$r['Pak MTK']['total'], $r['Pak MTK']['persen']]); // hadir + terlambat
        $this->assertSame([2, 50], [$r['Pak Tendik']['total'], $r['Pak Tendik']['persen']]);
        $this->assertSame([0, null], [$r['Bu Wali A']['total'], $r['Bu Wali A']['persen']]);
    }

    public function test_kehadiran_guru_sekolah_lain_tidak_terbaca(): void
    {
        $this->catatGuru($this->akunAdmin, self::SENIN, [$this->stafGuruMtk->id => ['status' => 'H']]);
        $b = $this->sekolah('SD B');

        $this->actingAs($this->adminDari($b));
        $this->assertSame(0, StaffAttendance::count());
        $this->assertSame(1, StudentAttendance::withoutGlobalScopes()->count() + StaffAttendance::withoutGlobalScopes()->count());
    }
}
