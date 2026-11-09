<?php

namespace Tests\Feature\Rapor;

use App\Models\Score;
use App\Modules\Kehadiran\CatatKehadiranSiswa;
use App\Modules\Rapor\BukaKunciRapor;
use App\Modules\Rapor\CatatanWali;
use App\Support\Peran;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/** Nilai yang sudah disahkan tidak boleh berubah lewat proses biasa; satu-satunya jalan adalah buka kunci dengan alasan. */
class KunciRaporTest extends RaporTestCase
{
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

    private function catat($tanggal, $status, $oleh = null, $entri = null, $kelas = null): void
    {
        $this->untuk($this->s, fn () => CatatKehadiranSiswa::handle($oleh ?? $this->akunAdmin, $kelas ?? $this->kelasA, $tanggal, $entri, [$this->a1->id => ['status' => $status]]));
    }

    private function nilaiMtkUh(): string
    {
        return (string) Score::withoutGlobalScopes()->where('student_id', $this->a1->id)->where('subject_id', $this->mtk->id)->where('component_id', $this->uh->id)->value('value');
    }

    // ---------- yang terkunci setelah disahkan ----------

    public function test_nilai_siswa_terkunci_setelah_rapor_disahkan(): void
    {
        $this->siapSahkan();
        $this->sahkan();

        foreach ([$this->guruMtk, $this->akunAdmin] as $oleh) { // bahkan admin sekolah tidak bisa melewati kunci
            $this->gagalValidasi(fn () => $this->nilai($oleh, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 99]]), 'value', 'terkunci');
        }
        $this->assertSame('80.00', $this->nilaiMtkUh());
    }

    public function test_nilai_terkunci_juga_melalui_model_langsung_dan_untuk_komponen_yang_masih_kosong(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        $skor = Score::withoutGlobalScopes()->where('student_id', $this->a1->id)->firstOrFail();
        $komponenBaru = $this->komponen('Tugas', 10, 4);

        $this->gagalValidasi(fn () => $this->untuk($this->s, fn () => $skor->update(['value' => 1])), 'value', 'terkunci');
        $this->gagalValidasi(fn () => $this->untuk($this->s, fn () => Score::create(['semester_id' => $this->sem->id, 'student_id' => $this->a1->id,
            'classroom_id' => $this->kelasA->id, 'subject_id' => $this->mtk->id, 'component_id' => $komponenBaru->id, 'value' => 50])), 'value', 'terkunci');
    }

    public function test_menyimpan_nilai_yang_tidak_berubah_tetap_lolos_dan_siswa_lain_tidak_terkunci(): void
    {
        $this->siapSahkan();
        $this->sahkan();

        $this->assertSame(0, $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 80]]), 'tidak berubah: bukan pelanggaran kunci');
        $this->assertSame(1, $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a2->id => [$this->uh->id => 55]]), 'siswa lain tidak ikut terkunci');
    }

    public function test_semua_atau_tidak_sama_sekali_berlaku_bila_ada_siswa_terkunci_dalam_daftar(): void
    {
        $this->siapSahkan();
        $this->sahkan();

        $this->gagalValidasi(fn () => $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [
            $this->a2->id => [$this->uh->id => 55],   // boleh
            $this->a1->id => [$this->uh->id => 99],   // terkunci
        ]), 'value');

        $this->assertNull(Score::withoutGlobalScopes()->where('student_id', $this->a2->id)->where('subject_id', $this->mtk->id)->first(), 'nilai siswa lain ikut dibatalkan');
    }

    public function test_kehadiran_harian_dalam_semester_terkunci_tetapi_per_pelajaran_dan_di_luar_semester_tidak(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        $entri = $this->jadwal($this->s, $this->kelasA, $this->mtk, $this->jam($this->s, 1, '07:00', '07:45'), 1); // Senin
        $sem2 = $this->semester($this->s, $this->y, 2, '2027-01-01', '2027-06-30');

        $this->gagalValidasi(fn () => $this->catat('2026-11-09', 'A'), 'date', 'terkunci');    // mengubah catatan yang ada
        $this->gagalValidasi(fn () => $this->catat('2026-11-02', 'H'), 'date', 'terkunci');    // catatan baru dalam semester itu (Senin)
        $this->catat('2026-11-09', 'A', $this->guruMtk, $entri);                                // per pelajaran: tidak ikut rapor, tidak terkunci
        $this->assertSame('A', \App\Models\StudentAttendance::withoutGlobalScopes()->where('schedule_entry_id', $entri->id)->value('status'));
        $this->assertNotNull($sem2);
    }

    public function test_kehadiran_semester_lain_tidak_terkunci(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        $this->untuk($this->s, fn () => $this->sem->update(['end_date' => '2026-11-12'])); // semester berakhir hari ini
        \Carbon\Carbon::setTestNow('2026-11-13 09:00:00');
        $this->untuk($this->s, fn () => $this->semester($this->s, $this->y, 2, '2026-11-13', '2027-06-30'));
        DB::table('enrollments')->update(['joined_at' => '2026-07-20']);

        $this->catat('2026-11-13', 'H'); // Jumat, semester 2: tidak terkunci

        $this->assertSame('H', \App\Models\StudentAttendance::withoutGlobalScopes()->whereDate('date', '2026-11-13')->value('status'));
    }

    public function test_catatan_wali_terkunci_setelah_disahkan(): void
    {
        $this->siapSahkan();
        $this->sahkan();

        $this->gagalValidasi(fn () => $this->untuk($this->s, fn () => CatatanWali::simpan($this->waliA, $this->sem, $this->a1, 'Diubah diam-diam')), 'homeroom_note', 'terkunci');
        $this->gagalValidasi(fn () => $this->untuk($this->s, fn () => $this->rapor()->update(['homeroom_note' => 'Lewat model'])), 'homeroom_note', 'terkunci');
        $this->assertSame('Siswa rajin dan santun.', $this->rapor()->homeroom_note);
    }

    public function test_rapor_siswa_lain_tidak_ikut_terkunci(): void
    {
        $this->siapSahkan();
        $this->sahkan();

        $this->assertSame(1, $this->nilai($this->guruIpa, $this->kelasA, $this->ipa, [$this->a2->id => [$this->uh->id => 70]]));
        $this->untuk($this->s, fn () => CatatanWali::simpan($this->waliA, $this->sem, $this->a2, 'Catatan A2'));
    }

    // ---------- buka kunci ----------

    public function test_buka_kunci_dengan_alasan_mengubah_status_dan_mencatat_siapa_kapan_mengapa(): void
    {
        $this->siapSahkan();
        $this->sahkan();

        $r = $this->bukaKunci('Ada kesalahan input nilai IPA', $this->akunKepala);

        $this->assertSame(['revisi', 'Ada kesalahan input nilai IPA', $this->akunKepala->id], [$r->status, $r->unlock_reason, $r->unlocked_by]);
        $this->assertNotNull($r->unlocked_at);
        $this->assertSame(1, $r->current_version, 'versi yang berlaku tetap 1 sampai disahkan ulang');
        $this->assertSame(1, $this->jumlahVersi());
        $this->assertTrue(Activity::where('log_name', 'rapor')->where('subject_id', $r->id)->where('event', 'updated')->exists());
    }

    public function test_alasan_wajib_dan_dibatasi(): void
    {
        $this->siapSahkan();
        $this->sahkan();

        foreach (['', '   ', 'singkat', str_repeat('x', 256)] as $salah) {
            $this->gagalValidasi(fn () => $this->bukaKunci($salah), 'alasan');
        }
        $this->assertSame('disahkan', $this->rapor()->status, 'rapor tetap terkunci bila alasan tidak sah');
        $this->assertSame('revisi', $this->bukaKunci(str_repeat('x', 255))->status, '255 karakter masih sah');
    }

    public function test_hanya_kepala_dan_admin_yang_boleh_membuka_kunci(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        $tidak = [
            'wali kelas A' => $this->waliA, 'guru MTK' => $this->guruMtk, 'staf TU' => $this->akunTu,
            'guru BK' => $this->penggunaSekolah($this->s, Peran::GURU_BK), 'admin sekolah lain' => $this->adminDari($this->sekolah('SD B')),
        ];
        foreach ($tidak as $nama => $akun) {
            try {
                $this->bukaKunci('Alasan yang cukup panjang', $akun);
                $this->fail("$nama berhasil membuka kunci");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('disahkan', $this->rapor()->status);
        $this->assertSame('revisi', $this->bukaKunci('Alasan yang cukup panjang', $this->akunAdmin)->status);
    }

    public function test_rapor_yang_belum_disahkan_tidak_punya_kunci_untuk_dibuka(): void
    {
        $this->untuk($this->s, fn () => CatatanWali::simpan($this->waliA, $this->sem, $this->a1, 'Catatan'));

        $this->gagalValidasi(fn () => $this->bukaKunci('Alasan yang cukup panjang'), 'rapor', 'tidak sedang disahkan');
    }

    public function test_kunci_tidak_bisa_dibuka_dua_kali_berturut_turut(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        $this->bukaKunci();

        $this->gagalValidasi(fn () => $this->bukaKunci('Alasan kedua yang cukup panjang'), 'rapor');
    }

    public function test_setelah_buka_kunci_nilai_kehadiran_dan_catatan_bisa_direvisi(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        $this->bukaKunci();

        $this->assertSame(1, $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 95]]));
        $this->catat('2026-11-09', 'S');
        $this->untuk($this->s, fn () => CatatanWali::simpan($this->waliA, $this->sem, $this->a1, 'Catatan revisi'));

        $this->assertSame('95.00', $this->nilaiMtkUh());
        $this->assertSame('Catatan revisi', $this->rapor()->homeroom_note);
    }

    // ---------- revisi menjadi versi baru ----------

    public function test_pengesahan_ulang_membuat_versi_2_dan_versi_1_tetap_utuh(): void
    {
        $this->siapSahkan();
        $v1 = $this->sahkan();
        $this->bukaKunci('Nilai UH Matematika salah ketik');
        $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 100]]);

        $v2 = $this->sahkan($this->akunAdmin);

        $rapor = $this->rapor();
        $this->assertSame(['disahkan', 2], [$rapor->status, $rapor->current_version]);
        $this->assertSame([2, 'Nilai UH Matematika salah ketik', $this->akunAdmin->id], [$v2->version, $v2->revision_reason, $v2->approved_by]);
        $this->assertNull($v1->fresh()->revision_reason);

        $mtk1 = collect($v1->fresh()->snapshot['mapel'])->firstWhere('nama', 'Matematika');
        $mtk2 = collect($v2->snapshot['mapel'])->firstWhere('nama', 'Matematika');
        $this->assertSame('80.00', $mtk1['nilai']['Ulangan Harian'], 'versi 1 tetap memuat nilai lama');
        $this->assertSame('100.00', $mtk2['nilai']['Ulangan Harian']);
        // MTK versi 2: (100*30 + 90*30 + 70*40) / 100 = 85
        $this->assertSame(['76.33', 79, 85], [$v1->fresh()->snapshot['rata'], $mtk1['bulat'], $mtk2['bulat']]);
        $this->assertTrue($v1->fresh()->utuh() && $v2->fresh()->utuh());
        $this->assertTrue($this->utuhRapor());
    }

    public function test_pengesahan_ulang_terkunci_kembali(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        $this->bukaKunci();
        $this->sahkan();

        $this->gagalValidasi(fn () => $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 1]]), 'value', 'terkunci');
    }

    public function test_beberapa_kali_revisi_menghasilkan_versi_berurutan_dengan_alasan_masing_masing(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        foreach ([2 => 'Revisi pertama karena salah ketik', 3 => 'Revisi kedua karena tambahan nilai'] as $versi => $alasan) {
            $this->bukaKunci($alasan);
            $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 80 + $versi]]);
            $this->assertSame($versi, $this->sahkan()->version);
        }

        $v = $this->untuk($this->s, fn () => $this->rapor()->versions()->get());
        $this->assertSame([1, 2, 3], $v->pluck('version')->all());
        $this->assertSame([null, 'Revisi pertama karena salah ketik', 'Revisi kedua karena tambahan nilai'], $v->pluck('revision_reason')->all());
        $this->assertTrue($this->utuhRapor());
    }

    public function test_pengesahan_ulang_tetap_wajib_lengkap(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        $this->bukaKunci();
        $this->komponen('Tugas', 10, 4); // komponen baru: semua nilai jadi belum lengkap

        $this->gagalValidasi(fn () => $this->sahkan(), 'rapor', 'belum bisa disahkan');
        $this->assertSame('revisi', $this->rapor()->status, 'tetap dalam revisi, tidak terkunci setengah jalan');
        $this->assertSame(1, $this->jumlahVersi());
    }

    public function test_revisi_mengambil_pengaturan_dan_kelas_terbaru_tetapi_versi_lama_tidak_berubah(): void
    {
        $this->siapSahkan();
        $v1 = $this->sahkan();
        $this->bukaKunci();
        $this->untuk($this->s, fn () => $this->s->update(['settings' => ['kepala_sekolah_nama' => 'Kepala Baru', 'rapor_judul' => 'JUDUL BARU']]));

        $v2 = $this->sahkan();

        $this->assertSame('Kepala Baru', $v2->snapshot['kepala']['nama']);
        $this->assertSame('Drs. Kepala Sekolah', $v1->fresh()->snapshot['kepala']['nama']);
    }
}
