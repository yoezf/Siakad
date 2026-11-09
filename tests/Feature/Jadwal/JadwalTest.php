<?php

namespace Tests\Feature\Jadwal;

use App\Models\ScheduleEntry;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class JadwalTest extends TestCase
{
    use SiapkanSekolah;

    /** Sekolah dengan dua kelas, dua guru, dua mata pelajaran, dua jam, dan penugasan lengkap. */
    private function dasar(): array
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $a = $this->kelas($s, $y, '4A');
        $b = $this->kelas($s, $y, '4B');
        $sari = $this->guruDi($s, 'Bu Sari');
        $budi = $this->guruDi($s, 'Pak Budi');
        $mtk = $this->mapel($s, 'MTK', 'Matematika');
        $ipa = $this->mapel($s, 'IPA', 'IPA');
        $j1 = $this->jam($s, 1, '07:00', '07:45');
        $j2 = $this->jam($s, 2, '07:45', '08:30');

        return compact('s', 'y', 'a', 'b', 'sari', 'budi', 'mtk', 'ipa', 'j1', 'j2');
    }

    private function gagalValidasi(callable $f, string $kunci, ?string $isiPesan = null): void
    {
        try {
            $f();
            $this->fail('Seharusnya ditolak dengan ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($kunci, $e->errors());
            if ($isiPesan) {
                $this->assertStringContainsString($isiPesan, $e->errors()[$kunci][0]);
            }
        }
    }

    // ---------- jam pelajaran ----------

    public function test_jam_pelajaran_dinormalkan_dan_bisa_berurutan_tanpa_celah(): void
    {
        $s = $this->sekolah('SD A');
        $j1 = $this->jam($s, 1, '07:00', '07:45');
        $j2 = $this->jam($s, 2, '07:45', '08:30'); // mulai tepat saat jam sebelumnya selesai: sah

        $this->assertSame('07:00:00', $j1->fresh()->start_time);
        $this->assertSame('07:00–07:45', $j1->fresh()->rentang());
        $this->assertSame('Jam ke-2 (07:45–08:30)', $j2->fresh()->label());
    }

    public function test_jam_selesai_harus_setelah_mulai_dan_format_harus_sah(): void
    {
        $s = $this->sekolah('SD A');
        $this->gagalValidasi(fn () => $this->jam($s, 1, '08:00', '07:00'), 'end_time');
        $this->gagalValidasi(fn () => $this->jam($s, 1, '07:00', '07:00'), 'end_time');
        $this->gagalValidasi(fn () => $this->jam($s, 1, '25:00', '26:00'), 'start_time');
        $this->gagalValidasi(fn () => $this->jam($s, 1, 'pagi', '07:00'), 'start_time');
        $this->assertSame(0, TimeSlot::withoutGlobalScopes()->count());
    }

    public function test_jam_pelajaran_tidak_boleh_bertabrakan(): void
    {
        $s = $this->sekolah('SD A');
        $this->jam($s, 1, '07:00', '07:45');

        $this->gagalValidasi(fn () => $this->jam($s, 2, '07:30', '08:15'), 'start_time', 'Bertabrakan dengan Jam ke-1');
        $this->gagalValidasi(fn () => $this->jam($s, 2, '06:30', '07:15'), 'start_time');
        $this->gagalValidasi(fn () => $this->jam($s, 2, '07:10', '07:20'), 'start_time'); // di dalam jam lain
    }

    public function test_urutan_jam_unik_per_sekolah_dan_boleh_sama_antar_sekolah(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->jam($a, 1, '07:00', '07:45');
        $this->jam($b, 1, '07:00', '07:45'); // sekolah lain: urutan dan jam yang sama sah

        $this->expectException(QueryException::class);
        $this->jam($a, 1, '09:00', '09:45');
    }

    public function test_jam_sekolah_lain_tidak_terlihat_dan_tidak_memengaruhi_tabrakan(): void
    {
        $a = $this->sekolah('SD A');
        $b = $this->sekolah('SD B');
        $this->jam($b, 1, '07:00', '07:45');
        $this->jam($a, 1, '07:00', '07:45'); // tidak bertabrakan dengan sekolah B

        $this->actingAs($this->adminDari($a));
        $this->assertSame(1, TimeSlot::count());
    }

    // ---------- pelajaran di jadwal ----------

    public function test_guru_pada_jadwal_diambil_dari_penugasan(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);

        $e = $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);

        $this->assertSame($d['sari']->id, $e->fresh()->staff_id);
    }

    public function test_pelajaran_tanpa_guru_pengampu_ditolak(): void
    {
        $d = $this->dasar(); // belum ada penugasan

        $this->gagalValidasi(fn () => $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1']), 'subject_id', 'belum punya guru pengampu');
    }

    public function test_istirahat_hari_dan_data_sekolah_lain_ditolak(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $istirahat = $this->jam($d['s'], 3, '09:00', '09:15', true, 'Istirahat');
        $b = $this->sekolah('SD B');
        $jamB = $this->jam($b, 1, '07:00', '07:45');
        $kelasB = $this->kelas($b, $this->tahunAjaran($b), '4A');

        $this->gagalValidasi(fn () => $this->jadwal($d['s'], $d['a'], $d['mtk'], $istirahat), 'time_slot_id', 'istirahat');
        $this->gagalValidasi(fn () => $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 9), 'day');
        $this->gagalValidasi(fn () => $this->jadwal($d['s'], $d['a'], $d['mtk'], $jamB), 'time_slot_id');
        $this->gagalValidasi(fn () => $this->jadwal($d['s'], $kelasB, $d['mtk'], $d['j1']), 'classroom_id');
        $this->assertSame(0, ScheduleEntry::withoutGlobalScopes()->count());
    }

    // ---------- bentrok ----------

    public function test_kelas_tidak_bisa_punya_dua_pelajaran_pada_jam_yang_sama(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $this->penugasan($d['s'], $d['a'], $d['ipa'], $d['budi']);
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);

        $this->gagalValidasi(fn () => $this->jadwal($d['s'], $d['a'], $d['ipa'], $d['j1'], 1), 'time_slot_id', 'Kelas 4A sudah punya pelajaran Matematika');
        $this->assertSame(1, ScheduleEntry::withoutGlobalScopes()->count());
    }

    public function test_guru_tidak_bisa_mengajar_dua_kelas_pada_jam_yang_sama(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $this->penugasan($d['s'], $d['b'], $d['mtk'], $d['sari']);
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);

        $this->gagalValidasi(fn () => $this->jadwal($d['s'], $d['b'], $d['mtk'], $d['j1'], 1), 'subject_id', 'Bu Sari sudah mengajar di kelas 4A');
    }

    public function test_hari_atau_jam_berbeda_dan_guru_berbeda_tidak_dianggap_bentrok(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $this->penugasan($d['s'], $d['b'], $d['mtk'], $d['sari']);
        $this->penugasan($d['s'], $d['b'], $d['ipa'], $d['budi']);
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);

        $this->jadwal($d['s'], $d['b'], $d['mtk'], $d['j1'], 2);  // hari lain
        $this->jadwal($d['s'], $d['b'], $d['mtk'], $d['j2'], 1);  // jam lain
        $this->jadwal($d['s'], $d['b'], $d['ipa'], $d['j1'], 1);  // guru lain pada jam yang sama dengan kelas lain

        $this->assertSame(4, ScheduleEntry::withoutGlobalScopes()->count());
    }

    public function test_basis_data_sendiri_menolak_bentrok_lewat_sql_langsung(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $this->penugasan($d['s'], $d['b'], $d['mtk'], $d['sari']);
        $this->penugasan($d['s'], $d['a'], $d['ipa'], $d['budi']);
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $baris = fn ($kelas, $mapel, $guru) => ['school_id' => $d['s']->id, 'classroom_id' => $kelas->id, 'subject_id' => $mapel->id,
            'staff_id' => $guru->id, 'time_slot_id' => $d['j1']->id, 'day' => 1];

        foreach ([
            'kelas ganda' => $baris($d['a'], $d['ipa'], $d['budi']),
            'guru ganda' => $baris($d['b'], $d['mtk'], $d['sari']),
        ] as $nama => $data) {
            try {
                DB::table('schedule_entries')->insert($data);
                $this->fail("SQL langsung lolos untuk $nama");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(1, ScheduleEntry::withoutGlobalScopes()->count());
    }

    public function test_basis_data_menolak_guru_yang_bukan_pengampu_dan_data_lintas_sekolah(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $b = $this->sekolah('SD B');
        $jamB = $this->jam($b, 1, '07:00', '07:45');

        $salah = [
            'guru bukan pengampu' => ['classroom_id' => $d['a']->id, 'subject_id' => $d['mtk']->id, 'staff_id' => $d['budi']->id, 'time_slot_id' => $d['j1']->id],
            'jam sekolah lain' => ['classroom_id' => $d['a']->id, 'subject_id' => $d['mtk']->id, 'staff_id' => $d['sari']->id, 'time_slot_id' => $jamB->id],
            'mapel tanpa penugasan' => ['classroom_id' => $d['a']->id, 'subject_id' => $d['ipa']->id, 'staff_id' => $d['sari']->id, 'time_slot_id' => $d['j1']->id],
        ];
        foreach ($salah as $nama => $data) {
            try {
                DB::table('schedule_entries')->insert(['school_id' => $d['s']->id, 'day' => 1] + $data);
                $this->fail("SQL langsung lolos untuk $nama");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---------- mengubah jadwal ----------

    public function test_pelajaran_dipindah_ke_jam_kosong_dan_perubahan_tercatat(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $e = $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);

        $this->untuk($d['s'], fn () => $e->update(['time_slot_id' => $d['j2']->id, 'day' => 3]));

        $this->assertSame(3, $e->fresh()->day);
        $this->assertSame($d['j2']->id, $e->fresh()->time_slot_id);
        $this->assertTrue(Activity::where('log_name', 'jadwal')->where('subject_id', $e->id)->where('event', 'updated')->exists());
    }

    public function test_menyimpan_tanpa_mengubah_waktu_tidak_dianggap_bentrok_dengan_dirinya_sendiri(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $e = $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);

        $this->untuk($d['s'], fn () => $e->update(['day' => 1, 'time_slot_id' => $d['j1']->id]));
        $this->assertSame(1, $e->fresh()->day);
    }

    public function test_pindah_ke_jam_yang_bentrok_ditolak_dan_jadwal_semula_utuh(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $this->penugasan($d['s'], $d['a'], $d['ipa'], $d['budi']);
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $ipa = $this->jadwal($d['s'], $d['a'], $d['ipa'], $d['j2'], 1);

        $this->gagalValidasi(fn () => $this->untuk($d['s'], fn () => $ipa->update(['time_slot_id' => $d['j1']->id])), 'time_slot_id');
        $this->assertSame($d['j2']->id, $ipa->fresh()->time_slot_id);
    }

    public function test_mengganti_mata_pelajaran_mengganti_guru_mengikuti_penugasan(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $this->penugasan($d['s'], $d['a'], $d['ipa'], $d['budi']);
        $e = $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);

        $this->untuk($d['s'], fn () => $e->update(['subject_id' => $d['ipa']->id]));

        $this->assertSame($d['budi']->id, $e->fresh()->staff_id);
    }

    public function test_kelas_pada_jadwal_tidak_bisa_diubah(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $this->penugasan($d['s'], $d['b'], $d['mtk'], $d['sari']);
        $e = $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);

        $this->expectException(LogicException::class);
        $this->untuk($d['s'], fn () => $e->update(['classroom_id' => $d['b']->id]));
    }

    public function test_pelajaran_boleh_dihapus_dan_tercatat(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $e = $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);

        $this->untuk($d['s'], fn () => $e->delete());

        $this->assertSame(0, ScheduleEntry::withoutGlobalScopes()->count());
        $this->assertTrue(Activity::where('log_name', 'jadwal')->where('event', 'created')->exists());
    }

    // ---------- guru diganti pada penugasan ----------

    public function test_jadwal_ikut_guru_baru_saat_penugasan_diganti(): void
    {
        $d = $this->dasar();
        $p = $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $e = $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);

        $this->untuk($d['s'], fn () => $p->update(['staff_id' => $d['budi']->id]));

        $this->assertSame($d['budi']->id, $e->fresh()->staff_id, 'jadwal harus ikut guru pengganti');
    }

    public function test_penggantian_guru_ditolak_bila_guru_baru_bentrok_dengan_jadwal(): void
    {
        $d = $this->dasar();
        $p = $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $this->penugasan($d['s'], $d['b'], $d['ipa'], $d['budi']);
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $this->jadwal($d['s'], $d['b'], $d['ipa'], $d['j1'], 1); // Pak Budi sudah mengajar 4B pada jam yang sama

        $this->gagalValidasi(fn () => $this->untuk($d['s'], fn () => $p->update(['staff_id' => $d['budi']->id])), 'staff_id', 'Guru pengganti sudah mengajar');
        $this->assertSame($d['sari']->id, $p->fresh()->staff_id);
    }

    public function test_basis_data_menolak_penggantian_guru_yang_bentrok_lewat_sql_langsung(): void
    {
        $d = $this->dasar();
        $p = $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $this->penugasan($d['s'], $d['b'], $d['ipa'], $d['budi']);
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $this->jadwal($d['s'], $d['b'], $d['ipa'], $d['j1'], 1);

        $this->expectException(QueryException::class);
        DB::table('teaching_assignments')->where('id', $p->id)->update(['staff_id' => $d['budi']->id]);
    }

    public function test_penugasan_dan_jam_yang_dipakai_jadwal_tidak_bisa_dihapus(): void
    {
        $d = $this->dasar();
        $p = $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $bebas = $this->jam($d['s'], 3, '09:00', '09:45');

        foreach ([['teaching_assignments', $p->id], ['time_slots', $d['j1']->id]] as [$tabel, $id]) {
            try {
                DB::table($tabel)->where('id', $id)->delete();
                $this->fail("$tabel yang dipakai jadwal berhasil dihapus");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(1, DB::table('time_slots')->where('id', $bebas->id)->delete(), 'jam yang tidak dipakai boleh dihapus');
    }

    // ---------- isolasi ----------

    public function test_jadwal_sekolah_lain_tidak_terbaca(): void
    {
        $d = $this->dasar();
        $this->penugasan($d['s'], $d['a'], $d['mtk'], $d['sari']);
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $b = $this->sekolah('SD B');

        $this->actingAs($this->adminDari($b));
        $this->assertSame(0, ScheduleEntry::count());
        $this->assertSame(0, TimeSlot::count());
    }
}
