<?php

namespace Tests\Feature\Rapor;

use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\ReportCardVersion;
use App\Modules\Rapor\Akses;
use App\Modules\Rapor\CatatanWali;
use App\Modules\Rapor\Sidik;
use App\Support\Peran;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class RaporServiceTest extends RaporTestCase
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

    // ---------- penyusunan dan pemeriksaan ----------

    public function test_susun_rapor_lengkap_memuat_nilai_kehadiran_dan_identitas(): void
    {
        $this->siapSahkan();
        $h = $this->susun();
        $s = $h['snapshot'];

        $this->assertSame([], $h['masalah']);
        $this->assertSame('Siswa A1', $s['siswa']['nama']);
        $this->assertSame(['4A', 4, 'Bu Wali A'], [$s['kelas']['nama'], $s['kelas']['tingkat'], $s['kelas']['wali']]);
        $this->assertSame(['2026/2027', 'Ganjil'], [$s['semester']['tahun'], $s['semester']['label']]);
        $this->assertSame(['Ulangan Harian', 'UTS', 'UAS'], array_column($s['komponen'], 'nama'));
        $this->assertSame([30, 30, 40], array_column($s['komponen'], 'bobot'));
        $this->assertSame(['Bahasa Indonesia', 'IPA', 'Matematika'], array_column($s['mapel'], 'nama'));
        $mtk = collect($s['mapel'])->firstWhere('nama', 'Matematika');
        $this->assertSame(['79.00', 79, 'C', true], [$mtk['akhir'], $mtk['bulat'], $mtk['predikat'], $mtk['tuntas']]);
        $this->assertSame(['80.00', '90.00', '70.00'], array_values($mtk['nilai']));
        $ipa = collect($s['mapel'])->firstWhere('nama', 'IPA');
        $this->assertSame([60, 'D', false], [$ipa['bulat'], $ipa['predikat'], $ipa['tuntas']]);
        $this->assertSame(['76.33', true], [$s['rata'], $s['lengkap']]);
        $this->assertSame([1, 1, 1, 0, 0, 3, 67], [$s['kehadiran']['H'], $s['kehadiran']['T'], $s['kehadiran']['S'], $s['kehadiran']['I'], $s['kehadiran']['A'], $s['kehadiran']['total'], $s['kehadiran']['persen']]);
        $this->assertSame('Siswa rajin dan santun.', $s['catatan']);
        $this->assertSame(['Drs. Kepala Sekolah', '1970010119900101'], [$s['kepala']['nama'], $s['kepala']['nip']]);
        $this->assertSame(['LAPORAN HASIL BELAJAR SISWA', 'Bandung', true], [$s['format']['judul'], $s['format']['kota'], $s['format']['tampil_kehadiran']]);
    }

    public function test_nilai_belum_lengkap_menghalangi_pengesahan_dan_disebut_per_mata_pelajaran(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]); // IPA dan IND belum dinilai

        $m = $this->susun()['masalah'];

        $this->assertContains('Nilai IPA belum lengkap.', $m);
        $this->assertContains('Nilai Bahasa Indonesia belum lengkap.', $m);
        $this->assertNotContains('Nilai Matematika belum lengkap.', $m);
    }

    public function test_satu_komponen_kosong_membuat_mata_pelajaran_belum_lengkap(): void
    {
        $this->nilai($this->akunAdmin, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 100, $this->uts->id => 100]]); // UAS kosong

        $this->assertContains('Nilai Matematika belum lengkap.', $this->susun()['masalah']);
    }

    public function test_tanpa_komponen_aktif_atau_tanpa_mata_pelajaran_tidak_bisa_disahkan(): void
    {
        foreach ([$this->uh, $this->uts, $this->uas] as $k) {
            $this->untuk($this->s, fn () => $k->update(['active' => false]));
        }
        $this->assertContains('Belum ada komponen penilaian aktif.', $this->susun()['masalah']);

        $kelasKosong = $this->kelas($this->s, $this->y, '4C'); // tanpa penugasan guru
        $siswa = $this->siswaDi($this->s, 'Siswa C1');
        $this->untuk($this->s, fn () => Enrollment::tempatkan($siswa, $kelasKosong));
        $this->assertContains('Belum ada mata pelajaran yang diampu untuk kelas siswa ini.', $this->susun($siswa)['masalah']);
    }

    public function test_siswa_tanpa_kelas_pada_tahun_ajaran_itu_tidak_bisa_diproses(): void
    {
        $lepas = $this->siswaDi($this->s, 'Siswa Lepas');

        $h = $this->susun($lepas);

        $this->assertNull($h['kelas']);
        $this->assertNull($h['snapshot']);
        $this->assertStringContainsString('tidak terdaftar di kelas', $h['masalah'][0]);
        $this->gagalValidasi(fn () => $this->sahkan(null, $lepas), 'rapor');
    }

    public function test_peringatan_tidak_menghalangi_dan_hilang_saat_dipenuhi(): void
    {
        $this->lengkapi();
        $this->untuk($this->s, fn () => $this->s->update(['settings' => ['kepala_sekolah_nama' => '']]));

        $h = $this->susun();
        $this->assertSame([], $h['masalah']);
        $this->assertCount(3, $h['peringatan']);
        $this->assertStringContainsString('kehadiran', $h['peringatan'][0]);

        $this->kehadiranA1();
        $this->untuk($this->s, fn () => CatatanWali::simpan($this->waliA, $this->sem, $this->a1, 'Baik.'));
        $this->untuk($this->s, fn () => $this->s->update(['settings' => ['kepala_sekolah_nama' => 'Kepala']]));
        $this->assertSame([], $this->susun()['peringatan']);
    }

    public function test_snapshot_hanya_berisi_tipe_sederhana(): void
    {
        $this->siapSahkan();

        $this->hanyaTipeSederhana($this->susun()['snapshot']);
    }

    public function test_siswa_yang_pindah_kelas_memakai_kelas_terakhir(): void
    {
        $this->siapSahkan();
        $this->pindahkanSiswa($this->a1, $this->kelasA, $this->kelasB);

        $this->assertSame('4B', $this->susun()['snapshot']['kelas']['nama']);
    }

    // ---------- pengesahan ----------

    public function test_pengesahan_membuat_versi_1_menguncikan_dan_mencatat_pengesah(): void
    {
        $this->siapSahkan();

        $v = $this->sahkan($this->akunKepala);

        $rapor = $this->rapor();
        $this->assertSame(['disahkan', 1], [$rapor->status, $rapor->current_version]);
        $this->assertSame($this->kelasA->id, $rapor->classroom_id);
        $this->assertSame([1, null, $this->akunKepala->id], [$v->version, $v->revision_reason, $v->approved_by]);
        $this->assertSame(['Kepala', 1], [$v->snapshot['pengesahan']['oleh'], $v->snapshot['pengesahan']['versi']]);
        $this->assertSame(1, $this->jumlahVersi());
    }

    public function test_sidik_stabil_setelah_disimpan_dan_dimuat_dari_basis_data(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();

        $dimuat = ReportCardVersion::withoutGlobalScopes()->findOrFail($v->id);

        $this->assertSame(64, strlen($dimuat->checksum));
        $this->assertTrue($dimuat->utuh(), 'roundtrip JSON MySQL tidak boleh mengubah sidik');
        $this->assertSame($v->checksum, Sidik::hitung($dimuat->snapshot));
    }

    public function test_perubahan_snapshot_lewat_sql_langsung_terdeteksi_oleh_sidik(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();
        $diubah = $v->snapshot;
        $diubah['mapel'][0]['akhir'] = '100.00';
        DB::table('report_card_versions')->where('id', $v->id)->update(['snapshot' => json_encode($diubah)]);

        $this->assertFalse(ReportCardVersion::withoutGlobalScopes()->findOrFail($v->id)->utuh());
    }

    public function test_sidik_tidak_bergantung_pada_urutan_kunci(): void
    {
        $a = ['b' => 1, 'a' => ['y' => 'x', 'x' => null], 'daftar' => [3, 1, 2]];
        $b = ['a' => ['x' => null, 'y' => 'x'], 'daftar' => [3, 1, 2], 'b' => 1];

        $this->assertSame(Sidik::hitung($a), Sidik::hitung($b));
        $this->assertNotSame(Sidik::hitung($a), Sidik::hitung(array_replace($a, ['b' => 2])), 'nilai yang berbeda harus menghasilkan sidik berbeda');
        $this->assertNotSame(Sidik::hitung(['daftar' => [3, 1, 2]]), Sidik::hitung(['daftar' => [1, 2, 3]]), 'urutan daftar berarti');
    }

    public function test_pengesahan_ditolak_bila_belum_lengkap_dan_tidak_meninggalkan_jejak(): void
    {
        $this->isiPenuh($this->a1, $this->mtk, $this->kelasA, [80, 90, 70]);

        $this->gagalValidasi(fn () => $this->sahkan(), 'rapor', 'belum bisa disahkan');

        $this->assertSame(0, $this->jumlahVersi());
        $this->assertNull($this->rapor(), 'transaksi dibatalkan, tidak boleh ada kepala rapor tersisa');
    }

    public function test_rapor_yang_sudah_disahkan_tidak_bisa_disahkan_lagi_tanpa_buka_kunci(): void
    {
        $this->siapSahkan();
        $this->sahkan();

        $this->gagalValidasi(fn () => $this->sahkan(), 'rapor', 'sudah disahkan (versi 1)');
        $this->assertSame(1, $this->jumlahVersi());
    }

    public function test_hanya_kepala_dan_admin_yang_boleh_mengesahkan(): void
    {
        $this->siapSahkan();
        $tidak = [
            'wali kelas A' => $this->waliA, 'guru MTK' => $this->guruMtk, 'staf TU' => $this->akunTu,
            'guru BK' => $this->penggunaSekolah($this->s, Peran::GURU_BK), 'staf keuangan' => $this->penggunaSekolah($this->s, Peran::STAF_KEUANGAN),
            'admin sekolah lain' => $this->adminDari($this->sekolah('SD B')),
        ];
        foreach ($tidak as $nama => $akun) {
            try {
                $this->sahkan($akun);
                $this->fail("$nama berhasil mengesahkan");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, $this->jumlahVersi());
        $this->assertSame(1, $this->sahkan($this->akunAdmin)->version, 'admin sekolah boleh');
    }

    public function test_status_dan_versi_tidak_bisa_diisi_massal(): void
    {
        $r = new ReportCard(['semester_id' => $this->sem->id, 'student_id' => $this->a1->id, 'classroom_id' => $this->kelasA->id,
            'status' => 'disahkan', 'current_version' => 9, 'unlock_reason' => 'x']);

        $this->assertSame(['draf', 0, null], [$r->status, $r->current_version, $r->unlock_reason]);
    }

    public function test_status_tidak_sah_dan_perubahan_semester_atau_siswa_ditolak(): void
    {
        $this->siapSahkan();
        $this->sahkan();
        $r = $this->rapor();

        $this->gagalValidasi(fn () => $this->untuk($this->s, fn () => $r->forceFill(['status' => 'rahasia'])->save()), 'status');
        try {
            $this->untuk($this->s, fn () => $r->fresh()->forceFill(['student_id' => $this->a2->id])->save());
            $this->fail('siswa pada rapor berhasil diganti');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }
    }

    // ---------- kekekalan versi ----------

    public function test_versi_tidak_bisa_diubah_atau_dihapus(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();

        foreach ([
            fn () => $this->untuk($this->s, fn () => $v->fresh()->update(['revision_reason' => 'diam-diam'])),
            fn () => $this->untuk($this->s, fn () => $v->fresh()->delete()),
            fn () => $this->untuk($this->s, fn () => $v->fresh()->forceFill(['snapshot' => ['x' => 1]])->save()),
        ] as $coba) {
            try {
                $coba();
                $this->fail('versi rapor berhasil diubah atau dihapus');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertTrue($v->fresh()->utuh());
    }

    public function test_basis_data_menolak_menghapus_kepala_rapor_dan_siswa_yang_punya_versi(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();

        foreach ([['report_cards', $v->report_card_id], ['students', $this->a1->id]] as [$tabel, $id]) {
            try {
                DB::table($tabel)->where('id', $id)->delete();
                $this->fail("$tabel yang punya versi berhasil dihapus");
            } catch (\Illuminate\Database\QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_integritas_rapor_terdeteksi_bila_versi_dihapus_atau_diubah_lewat_sql_langsung(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();
        $this->assertTrue($this->utuhRapor());

        // baris versi bisa dihapus lewat SQL langsung (tanpa trigger), tetapi tidak lolos dari pemeriksaan integritas
        DB::table('report_card_versions')->where('id', $v->id)->delete();

        $this->assertFalse($this->utuhRapor(), 'nomor versi berlaku 1 tetapi tidak ada salinannya');
    }

    public function test_integritas_rapor_gagal_bila_isi_salinan_diubah(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();
        $diubah = $v->snapshot;
        $diubah['rata'] = '99.00';
        DB::table('report_card_versions')->where('id', $v->id)->update(['snapshot' => json_encode($diubah)]);

        $this->assertFalse($this->utuhRapor());
    }

    public function test_pengaturan_atau_kelas_yang_berubah_setelah_pengesahan_tidak_mengubah_salinan(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();
        $awal = $v->fresh()->snapshot;

        $this->untuk($this->s, fn () => $this->s->update(['name' => 'Nama Sekolah Baru', 'settings' => ['kepala_sekolah_nama' => 'Kepala Baru', 'rapor_judul' => 'JUDUL BARU', 'kkm' => 99]]));
        $this->untuk($this->s, fn () => $this->kelasA->update(['name' => '4A-Baru']));
        $this->untuk($this->s, fn () => $this->mtk->update(['name' => 'Matematika Baru', 'kkm' => 99]));

        $setelah = $v->fresh();
        $this->assertSame($awal, $setelah->snapshot);
        $this->assertTrue($setelah->utuh());
        $this->assertSame(['SD A', 'Drs. Kepala Sekolah', 'LAPORAN HASIL BELAJAR SISWA', '4A'], [$setelah->snapshot['sekolah']['nama'], $setelah->snapshot['kepala']['nama'], $setelah->snapshot['format']['judul'], $setelah->snapshot['kelas']['nama']]);
    }

    // ---------- catatan wali ----------

    public function test_wali_kelas_menyimpan_catatan_kelasnya_dan_admin_boleh_semua(): void
    {
        $this->untuk($this->s, fn () => CatatanWali::simpan($this->waliA, $this->sem, $this->a1, '  Perlu belajar lebih giat.  '));

        $r = $this->rapor();
        $this->assertSame(['draf', 'Perlu belajar lebih giat.', $this->kelasA->id], [$r->status, $r->homeroom_note, $r->classroom_id]);

        $this->untuk($this->s, fn () => CatatanWali::simpan($this->akunAdmin, $this->sem, $this->a1, 'Diubah admin.'));
        $this->assertSame('Diubah admin.', $this->rapor()->homeroom_note);
        $this->untuk($this->s, fn () => CatatanWali::simpan($this->waliA, $this->sem, $this->a1, '   '));
        $this->assertNull($this->rapor()->homeroom_note, 'kosong disimpan sebagai NULL');
    }

    public function test_catatan_oleh_yang_tidak_berhak_ditolak(): void
    {
        $tidak = [
            'wali kelas B (kelas lain)' => [$this->waliB, $this->a1], 'guru mapel' => [$this->guruMtk, $this->a1], 'kepala sekolah' => [$this->akunKepala, $this->a1],
            'staf TU' => [$this->akunTu, $this->a1], 'wali A untuk siswa kelas B' => [$this->waliA, $this->b1],
        ];
        foreach ($tidak as $nama => [$akun, $siswa]) {
            try {
                $this->untuk($this->s, fn () => CatatanWali::simpan($akun, $this->sem, $siswa, 'Siluman'));
                $this->fail("$nama berhasil menulis catatan");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, ReportCard::withoutGlobalScopes()->count());
    }

    public function test_catatan_terlalu_panjang_ditolak(): void
    {
        $this->gagalValidasi(fn () => $this->untuk($this->s, fn () => CatatanWali::simpan($this->waliA, $this->sem, $this->a1, str_repeat('x', 1001))), 'homeroom_note');
        $this->untuk($this->s, fn () => CatatanWali::simpan($this->waliA, $this->sem, $this->a1, str_repeat('x', 1000)));
        $this->assertSame(1000, mb_strlen($this->rapor()->homeroom_note));
    }

    // ---------- hak akses ----------

    public function test_hak_rapor_per_peran(): void
    {
        $bk = $this->penggunaSekolah($this->s, Peran::GURU_BK);
        $b = $this->sekolah('SD B');
        // [lihat 4A, susun 4A, sahkan, buka kunci, lihat 4B]
        $harapan = [
            'admin' => [$this->akunAdmin, true, true, true, true, true],
            'kepala' => [$this->akunKepala, true, false, true, true, true],
            'wali A' => [$this->waliA, true, true, false, false, false],
            'wali B' => [$this->waliB, false, false, false, false, true],
            'guru MTK' => [$this->guruMtk, false, false, false, false, false],
            'staf TU' => [$this->akunTu, false, false, false, false, false],
            'guru BK' => [$bk, false, false, false, false, false],
            'admin sekolah lain' => [$this->adminDari($b), false, false, false, false, false],
        ];
        foreach ($harapan as $nama => [$akun, $lihat, $susun, $sahkan, $buka, $lihatB]) {
            $this->assertSame($lihat, Akses::bisaMelihat($akun, $this->kelasA), "$nama melihat 4A");
            $this->assertSame($susun, Akses::bisaMenyusun($akun, $this->kelasA), "$nama menyusun 4A");
            $this->assertSame($sahkan, Akses::bisaMengesahkan($akun, $this->kelasA), "$nama mengesahkan");
            $this->assertSame($buka, Akses::bisaBukaKunci($akun, $this->kelasA), "$nama membuka kunci");
            $this->assertSame($lihatB, Akses::bisaMelihat($akun, $this->kelasB), "$nama melihat 4B");
        }
    }
}
