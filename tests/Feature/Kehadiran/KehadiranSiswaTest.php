<?php

namespace Tests\Feature\Kehadiran;

use App\Models\ScheduleEntry;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Modules\Kehadiran\CatatKehadiranSiswa;
use App\Support\Peran;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Activity;

class KehadiranSiswaTest extends KehadiranTestCase
{
    private function catat($oleh, $kelas, string $tanggal, array $baris, $entri = null): int
    {
        return $this->untuk($this->s, fn () => CatatKehadiranSiswa::handle($oleh, $kelas, $tanggal, $entri, $baris));
    }

    private function semua(string $status = 'H'): array
    {
        return [$this->a1->id => ['status' => $status], $this->a2->id => ['status' => $status]];
    }

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

    private function jumlah(): int
    {
        return StudentAttendance::withoutGlobalScopes()->count();
    }

    // ---------- siapa boleh mencatat ----------

    public function test_admin_mencatat_kehadiran_harian_dan_pencatatnya_tersimpan(): void
    {
        $n = $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, [
            $this->a1->id => ['status' => 'H'],
            $this->a2->id => ['status' => 'S', 'note' => 'demam'],
        ]);

        $this->assertSame(2, $n);
        $s = StudentAttendance::withoutGlobalScopes()->where('student_id', $this->a2->id)->firstOrFail();
        $this->assertSame('S', $s->status);
        $this->assertSame('demam', $s->note);
        $this->assertSame($this->akunAdmin->id, $s->recorded_by);
        $this->assertSame($this->s->id, $s->school_id);
        $this->assertNull($s->schedule_entry_id);
    }

    public function test_wali_kelas_hanya_boleh_mencatat_kelas_yang_diwalikannya(): void
    {
        $this->assertSame(2, $this->catat($this->waliA, $this->kelasA, self::SENIN, $this->semua()));

        $this->expectException(AuthorizationException::class);
        $this->catat($this->waliA, $this->kelasB, self::SENIN, [$this->b1->id => ['status' => 'H']]);
    }

    public function test_pencatatan_oleh_yang_tidak_berhak_ditolak_dan_tidak_menyimpan_apa_pun(): void
    {
        $pihak = [
            'guru mapel (harian)' => $this->guruMtk,
            'kepala sekolah' => $this->akunKepala,
            'guru BK' => $this->penggunaSekolah($this->s, Peran::GURU_BK),
            'staf keuangan' => $this->penggunaSekolah($this->s, Peran::STAF_KEUANGAN),
            'wali kelas B' => $this->waliB,
        ];
        foreach ($pihak as $nama => $akun) {
            try {
                $this->catat($akun, $this->kelasA, self::SENIN, $this->semua());
                $this->fail("$nama berhasil mencatat");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, $this->jumlah());
    }

    public function test_admin_sekolah_lain_tidak_bisa_mencatat_kelas_ini(): void
    {
        $b = $this->sekolah('SD B');

        $this->expectException(AuthorizationException::class);
        $this->catat($this->adminDari($b), $this->kelasA, self::SENIN, $this->semua());
    }

    public function test_wali_kelas_nonaktif_tidak_bisa_mencatat(): void
    {
        $this->untuk($this->s, fn () => $this->stafWaliA->update(['status' => 'nonaktif']));

        $this->expectException(AuthorizationException::class);
        $this->catat($this->waliA, $this->kelasA, self::SENIN, $this->semua());
    }

    // ---------- per pelajaran ----------

    public function test_guru_pengampu_mencatat_kehadiran_pada_pelajarannya(): void
    {
        $n = $this->catat($this->guruMtk, $this->kelasA, self::SENIN, $this->semua(), $this->entriSenin);

        $this->assertSame(2, $n);
        $this->assertSame($this->entriSenin->id, StudentAttendance::withoutGlobalScopes()->first()->schedule_entry_id);
    }

    public function test_hanya_guru_pengampu_dan_admin_yang_boleh_mencatat_per_pelajaran(): void
    {
        $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, $this->semua(), $this->entriSenin); // admin: boleh
        $this->catat($this->akunTu, $this->kelasA, self::SENIN, $this->semua(), $this->entriSenin);     // TU: boleh (memperbarui)

        foreach ([$this->waliA, $this->waliB] as $bukanPengampu) { // wali kelas yang bukan pengampu pelajaran itu
            try {
                $this->catat($bukanPengampu, $this->kelasA, self::SENIN, $this->semua(), $this->entriSenin);
                $this->fail('bukan pengampu berhasil mencatat');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_hari_pelajaran_harus_sesuai_jadwal(): void
    {
        $this->gagalValidasi(fn () => $this->catat($this->guruMtk, $this->kelasA, self::SELASA, $this->semua(), $this->entriSenin),
            'schedule_entry_id', 'dijadwalkan hari Senin, bukan hari Selasa');
        $this->assertSame(0, $this->jumlah());
    }

    public function test_pelajaran_kelas_lain_ditolak(): void
    {
        $this->expectException(ValidationException::class);
        $this->catat($this->akunAdmin, $this->kelasB, self::SENIN, [$this->b1->id => ['status' => 'H']], $this->entriSenin);
    }

    public function test_kehadiran_harian_dan_per_pelajaran_pada_hari_yang_sama_berdampingan(): void
    {
        $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, $this->semua('H'));
        $this->catat($this->guruMtk, $this->kelasA, self::SENIN, $this->semua('T'), $this->entriSenin);

        $this->assertSame(4, $this->jumlah());
        $this->assertSame('H', StudentAttendance::withoutGlobalScopes()->where('student_id', $this->a1->id)->whereNull('schedule_entry_id')->value('status'));
        $this->assertSame('T', StudentAttendance::withoutGlobalScopes()->where('student_id', $this->a1->id)->whereNotNull('schedule_entry_id')->value('status'));
    }

    // ---------- mengubah catatan ----------

    public function test_menyimpan_ulang_memperbarui_catatan_yang_sama_dan_tercatat_di_audit(): void
    {
        $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, $this->semua('H'));
        $this->catat($this->akunTu, $this->kelasA, self::SENIN, [$this->a1->id => ['status' => 'A', 'note' => 'tanpa kabar'], $this->a2->id => ['status' => 'H']]);

        $this->assertSame(2, $this->jumlah(), 'tidak boleh membuat catatan ganda');
        $a1 = StudentAttendance::withoutGlobalScopes()->where('student_id', $this->a1->id)->firstOrFail();
        $this->assertSame('A', $a1->status);
        $this->assertSame($this->akunTu->id, $a1->recorded_by, 'pencatat terakhir yang tersimpan');
        $this->assertTrue(Activity::where('log_name', 'kehadiran_siswa')->where('subject_id', $a1->id)->where('event', 'updated')->exists());
        $this->assertTrue(Activity::where('log_name', 'kehadiran_siswa')->where('subject_id', $a1->id)->where('event', 'created')->exists());
    }

    public function test_siswa_tanggal_kelas_dan_pelajaran_pada_catatan_tidak_bisa_diubah(): void
    {
        $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, $this->semua());
        $a = StudentAttendance::withoutGlobalScopes()->firstOrFail();

        foreach (['date' => self::SELASA, 'student_id' => $this->b1->id, 'classroom_id' => $this->kelasB->id] as $kolom => $nilai) {
            try {
                $this->untuk($this->s, fn () => $a->fresh()->update([$kolom => $nilai]));
                $this->fail("$kolom berhasil diubah");
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---------- semua atau tidak sama sekali ----------

    public function test_satu_status_salah_membatalkan_seluruh_penyimpanan(): void
    {
        $this->gagalValidasi(fn () => $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, [
            $this->a1->id => ['status' => 'H'],
            $this->a2->id => ['status' => 'X'],
        ]), 'status');

        $this->assertSame(0, $this->jumlah(), 'siswa pertama tidak boleh tersimpan bila siswa kedua bermasalah');
    }

    public function test_tidak_ada_siswa_atau_tanggal_tidak_valid_ditolak(): void
    {
        $this->gagalValidasi(fn () => $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, []), 'student_id');
        foreach (['2026-13-40', 'kemarin', '09-11-2026', '2026-11-9', ''] as $salah) {
            $this->gagalValidasi(fn () => $this->catat($this->akunAdmin, $this->kelasA, $salah, $this->semua()), 'date');
        }
    }

    public function test_tanggal_masa_depan_dan_di_luar_tahun_ajaran_ditolak(): void
    {
        $this->gagalValidasi(fn () => $this->catat($this->akunAdmin, $this->kelasA, self::JUMAT, $this->semua()), 'date', 'belum tiba');
        $this->gagalValidasi(fn () => $this->catat($this->akunAdmin, $this->kelasA, '2026-06-30', $this->semua()), 'date', 'di luar tahun ajaran');
        $this->assertSame(2, $this->catat($this->akunAdmin, $this->kelasA, self::KAMIS, $this->semua()), 'hari ini harus boleh');
    }

    // ---------- terdaftar pada tanggal itu ----------

    public function test_siswa_hanya_bisa_dicatat_pada_tanggal_ia_terdaftar_di_kelas(): void
    {
        $baru = $this->siswaDi($this->s, 'Siswa Baru');
        $this->daftarkan($baru, $this->kelasA, self::SELASA); // masuk Selasa

        $this->gagalValidasi(fn () => $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, [$baru->id => ['status' => 'H']]), 'student_id', 'tidak terdaftar di kelas 4A');
        $this->assertSame(1, $this->catat($this->akunAdmin, $this->kelasA, self::SELASA, [$baru->id => ['status' => 'H']]));
    }

    public function test_siswa_yang_sudah_keluar_kelas_tidak_bisa_dicatat_setelah_tanggal_keluar(): void
    {
        $e = StudentAttendance::class; // hanya agar tahu modelnya dipakai
        $penempatan = \App\Models\Enrollment::withoutGlobalScopes()->where('student_id', $this->a2->id)->firstOrFail();
        DB::table('enrollments')->where('id', $penempatan->id)->update(['aktif' => null, 'left_at' => self::SELASA]);

        $this->assertSame(1, $this->catat($this->akunAdmin, $this->kelasA, self::SELASA, [$this->a2->id => ['status' => 'H']]), 'tanggal keluar masih sah');
        $this->gagalValidasi(fn () => $this->catat($this->akunAdmin, $this->kelasA, self::RABU, [$this->a2->id => ['status' => 'H']]), 'student_id');
    }

    public function test_siswa_kelas_lain_dalam_daftar_ditolak(): void
    {
        $this->gagalValidasi(fn () => $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, [$this->a1->id => ['status' => 'H'], $this->b1->id => ['status' => 'H']]), 'student_id', 'tidak terdaftar');
        $this->assertSame(0, $this->jumlah());
    }

    public function test_siswa_pindah_kelas_pada_hari_yang_sama_tidak_dicatat_dua_kali(): void
    {
        // a1 pindah dari 4A ke 4B pada hari Selasa; kehadiran Selasa sudah dicatat oleh 4A.
        $this->catat($this->akunAdmin, $this->kelasA, self::SELASA, [$this->a1->id => ['status' => 'H']]);
        $lama = \App\Models\Enrollment::withoutGlobalScopes()->where('student_id', $this->a1->id)->firstOrFail();
        DB::table('enrollments')->where('id', $lama->id)->update(['aktif' => null, 'left_at' => self::SELASA]);
        $baru = $this->daftarkan($this->a1, $this->kelasB, self::SELASA);

        $this->gagalValidasi(fn () => $this->catat($this->akunAdmin, $this->kelasB, self::SELASA, [$this->a1->id => ['status' => 'S']]), 'student_id', 'kelas lain');
        $this->assertSame('H', StudentAttendance::withoutGlobalScopes()->where('student_id', $this->a1->id)->value('status'));
        $this->assertNotNull($baru);
    }

    // ---------- batas data ----------

    public function test_catatan_dirapikan_dan_dibatasi_255_karakter(): void
    {
        $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, [
            $this->a1->id => ['status' => 'H', 'note' => '   '],
            $this->a2->id => ['status' => 'S', 'note' => str_repeat('x', 400)],
        ]);

        $this->assertNull(StudentAttendance::withoutGlobalScopes()->where('student_id', $this->a1->id)->value('note'));
        $this->assertSame(255, mb_strlen(StudentAttendance::withoutGlobalScopes()->where('student_id', $this->a2->id)->value('note')));
    }

    public function test_siswa_dan_kelas_sekolah_lain_tidak_bisa_dicatat(): void
    {
        $b = $this->sekolah('SD B');
        $siswaB = $this->siswaDi($b, 'Siswa SD B');

        $this->gagalValidasi(fn () => $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, [$siswaB->id => ['status' => 'H']]), 'student_id');
        $this->assertSame(0, $this->jumlah());
    }

    public function test_kehadiran_sekolah_lain_tidak_terbaca(): void
    {
        $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, $this->semua());
        $b = $this->sekolah('SD B');

        $this->actingAs($this->adminDari($b));
        $this->assertSame(0, StudentAttendance::count());
    }

    // ---------- penjaga di basis data ----------

    public function test_basis_data_menolak_catatan_ganda_harian_lewat_sql_langsung(): void
    {
        $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, $this->semua());
        $baris = ['school_id' => $this->s->id, 'student_id' => $this->a1->id, 'classroom_id' => $this->kelasA->id, 'date' => self::SENIN, 'status' => 'A'];

        $this->expectException(QueryException::class); // entry_key = 0 menjaga keunikan walau schedule_entry_id kosong
        DB::table('student_attendances')->insert($baris);
    }

    public function test_basis_data_menolak_catatan_ganda_per_pelajaran_tetapi_menerima_pelajaran_lain(): void
    {
        $this->catat($this->guruMtk, $this->kelasA, self::SENIN, $this->semua(), $this->entriSenin);
        $baris = ['school_id' => $this->s->id, 'student_id' => $this->a1->id, 'classroom_id' => $this->kelasA->id, 'date' => self::SENIN, 'status' => 'A'];

        try {
            DB::table('student_attendances')->insert($baris + ['schedule_entry_id' => $this->entriSenin->id]);
            $this->fail('catatan ganda per pelajaran lolos');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(0, DB::table('student_attendances')->where('student_id', $this->a1->id)->whereNull('schedule_entry_id')->count()); // tidak terpengaruh
    }

    public function test_basis_data_menolak_siswa_atau_kelas_lintas_sekolah_lewat_sql_langsung(): void
    {
        $b = $this->sekolah('SD B');
        $siswaB = $this->siswaDi($b, 'Siswa SD B');

        $this->expectException(QueryException::class);
        DB::table('student_attendances')->insert(['school_id' => $this->s->id, 'student_id' => $siswaB->id, 'classroom_id' => $this->kelasA->id, 'date' => self::SENIN, 'status' => 'H']);
    }

    // ---------- jadwal yang sudah dipakai kehadiran ----------

    public function test_hari_dan_mata_pelajaran_jadwal_terkunci_setelah_ada_kehadiran(): void
    {
        $this->catat($this->guruMtk, $this->kelasA, self::SENIN, $this->semua(), $this->entriSenin);

        $this->gagalValidasi(fn () => $this->untuk($this->s, fn () => $this->entriSenin->fresh()->update(['day' => 3])), 'day', 'sudah punya data kehadiran');
        $jam2 = $this->jam($this->s, 2, '08:00', '08:45');
        $this->untuk($this->s, fn () => $this->entriSenin->fresh()->update(['time_slot_id' => $jam2->id])); // jam boleh dipindah
        $this->assertSame($jam2->id, $this->entriSenin->fresh()->time_slot_id);
    }

    public function test_jadwal_yang_dipakai_kehadiran_tidak_bisa_dihapus(): void
    {
        $this->catat($this->guruMtk, $this->kelasA, self::SENIN, $this->semua(), $this->entriSenin);

        $this->expectException(QueryException::class);
        DB::table('schedule_entries')->where('id', $this->entriSenin->id)->delete();
    }

    public function test_jadwal_tanpa_kehadiran_masih_bebas_diubah_dan_dihapus(): void
    {
        $this->untuk($this->s, fn () => $this->entriSenin->update(['day' => 3]));
        $this->assertSame(3, $this->entriSenin->fresh()->day);
        $this->untuk($this->s, fn () => $this->entriSenin->delete());
        $this->assertSame(0, ScheduleEntry::withoutGlobalScopes()->count());
    }

    public function test_siswa_tidak_pernah_dihapus_jadi_kehadiran_tetap_terhubung(): void
    {
        $this->catat($this->akunAdmin, $this->kelasA, self::SENIN, $this->semua());

        $this->expectException(QueryException::class);
        DB::table('students')->where('id', $this->a1->id)->delete();
    }
}
