<?php

namespace Tests\Feature\Kehadiran;

use App\Filament\Sekolah\Pages\AbsenWajah;
use App\Filament\Sekolah\Pages\DaftarWajah;
use App\Models\FaceTemplate;
use App\Models\StudentAttendance;
use App\Modules\Wajah\AbsensiWajah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/** Absensi wajah: data wajah (bukan foto), hak akses, fitur per sekolah, pencatatan hadir, dan halaman. Pencocokan di peramban tidak bisa diuji di sini. */
class AbsensiWajahTest extends KehadiranTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->s->update(['features' => ['absensi_wajah']]);
        $this->akunAdmin = $this->akunAdmin->fresh();
        RateLimiter::clear('absen-wajah:'.$this->akunAdmin->id);
        RateLimiter::clear('absen-wajah:'.$this->waliA->id);
    }

    /** @return list<list<float>> */
    private function vektor(int $n = 5, float $nilai = 0.1): array
    {
        return array_fill(0, $n, array_fill(0, 128, $nilai));
    }

    private function daftar($oleh, $siswa, ?array $vektor = null): FaceTemplate
    {
        return $this->untuk($this->s, fn () => AbsensiWajah::daftarkan($oleh, $siswa, $vektor ?? $this->vektor()));
    }

    private function tolak(callable $f, string $kelas = AuthorizationException::class): void
    {
        try {
            $f();
            $this->fail('Seharusnya ditolak');
        } catch (\Throwable $e) {
            $this->assertInstanceOf($kelas, $e);
        }
    }

    // ---------- data wajah ----------

    public function test_admin_mendaftarkan_wajah_dan_hanya_data_numerik_terenkripsi_yang_tersimpan(): void
    {
        $t = $this->daftar($this->akunAdmin, $this->a1);

        $this->assertSame(5, $t->samples);
        $this->assertSame($this->akunAdmin->id, $t->created_by);
        $mentah = DB::table('face_templates')->where('id', $t->id)->value('descriptors');
        $this->assertStringNotContainsString('0.1', $mentah, 'harus terenkripsi, bukan JSON polos');
        $this->assertSame(0.1, $this->untuk($this->s, fn () => FaceTemplate::find($t->id))->descriptors[0][0]);
        $this->assertArrayNotHasKey('descriptors', $this->untuk($this->s, fn () => FaceTemplate::find($t->id))->toArray(), 'vektor tidak ikut ke serialisasi');
    }

    public function test_mendaftar_ulang_mengganti_data_lama_bukan_menambah(): void
    {
        $this->daftar($this->akunAdmin, $this->a1);
        $this->daftar($this->akunAdmin, $this->a1, $this->vektor(3, 0.2));

        $this->assertSame(1, FaceTemplate::withoutGlobalScopes()->count());
        $this->assertSame(3, FaceTemplate::withoutGlobalScopes()->first()->samples);
    }

    public function test_vektor_tidak_sah_ditolak(): void
    {
        $kasus = ['kosong' => [], 'kurang dimensi' => [array_fill(0, 127, 0.1)], 'terlalu banyak sampel' => $this->vektor(11), 'bukan angka' => [array_fill(0, 128, 'x')],
            'di luar rentang' => [array_fill(0, 128, 9.0)], 'bukan larik' => ['abc']];
        foreach ($kasus as $nama => $v) {
            $this->tolak(fn () => $this->daftar($this->akunAdmin, $this->a1, $v), ValidationException::class);
        }
        $this->assertSame(0, FaceTemplate::withoutGlobalScopes()->count());
    }

    public function test_hanya_admin_sekolah_yang_boleh_mendaftarkan_dan_menghapus(): void
    {
        foreach ([$this->akunTu, $this->akunKepala, $this->waliA, $this->guruMtk] as $akun) {
            $this->tolak(fn () => $this->daftar($akun, $this->a1));
        }
        $this->daftar($this->akunAdmin, $this->a1);
        $this->tolak(fn () => $this->untuk($this->s, fn () => AbsensiWajah::hapus($this->akunTu, $this->a1)));
        $this->assertSame(1, FaceTemplate::withoutGlobalScopes()->count());

        $this->untuk($this->s, fn () => AbsensiWajah::hapus($this->akunAdmin, $this->a1));
        $this->assertSame(0, FaceTemplate::withoutGlobalScopes()->count());
    }

    public function test_fitur_mati_menolak_semuanya(): void
    {
        $this->s->update(['features' => []]);
        $this->akunAdmin = $this->akunAdmin->fresh();

        $this->tolak(fn () => $this->daftar($this->akunAdmin, $this->a1));
        $this->assertFalse(Gate::forUser($this->akunAdmin)->allows('viewAny', FaceTemplate::class));
        $this->assertFalse(DaftarWajah::canAccess() || AbsenWajah::canAccess());
    }

    public function test_siswa_sekolah_lain_dan_siswa_tidak_aktif_ditolak(): void
    {
        $b = $this->sekolah('SD B');
        $asing = $this->siswaDi($b, 'Siswa Asing');
        $this->tolak(fn () => $this->daftar($this->akunAdmin, $asing));

        $this->untuk($this->s, fn () => $this->a1->update(['status' => 'pindah']));
        $this->tolak(fn () => $this->daftar($this->akunAdmin, $this->a1->fresh()), ValidationException::class);
    }

    public function test_catatan_aktivitas_tidak_memuat_vektor(): void
    {
        $this->daftar($this->akunAdmin, $this->a1);

        $log = Activity::where('log_name', 'data_wajah')->latest('id')->firstOrFail();
        $this->assertStringNotContainsString('descriptors', json_encode($log->properties));
        $this->assertStringNotContainsString('0.1', json_encode($log->properties));
    }

    // ---------- data untuk pemindaian ----------

    public function test_data_kelas_hanya_siswa_kelas_itu_yang_punya_data_wajah(): void
    {
        $this->daftar($this->akunAdmin, $this->a1);
        $this->daftar($this->akunAdmin, $this->b1); // kelas lain
        // a2 belum didaftarkan

        $d = $this->untuk($this->s, fn () => AbsensiWajah::dataKelas($this->akunAdmin, $this->kelasA));

        $this->assertSame([$this->a1->id], array_column($d, 'id'));
        $this->assertCount(5, $d[0]['vektor']);
    }

    public function test_wali_hanya_memindai_kelasnya_sendiri(): void
    {
        $this->daftar($this->akunAdmin, $this->a1);

        $this->assertCount(1, $this->untuk($this->s, fn () => AbsensiWajah::dataKelas($this->waliA, $this->kelasA)));
        $this->tolak(fn () => $this->untuk($this->s, fn () => AbsensiWajah::dataKelas($this->waliA, $this->kelasB)));
        $this->tolak(fn () => $this->untuk($this->s, fn () => AbsensiWajah::dataKelas($this->akunKepala, $this->kelasA)), AuthorizationException::class);
    }

    // ---------- mencatat hadir ----------

    public function test_mencatat_hadir_sekali_dan_pemanggilan_ulang_tidak_menggandakan(): void
    {
        $this->assertSame('dicatat', $this->untuk($this->s, fn () => AbsensiWajah::catatHadir($this->waliA, $this->kelasA, $this->a1->id)));
        $this->assertSame('sudah', $this->untuk($this->s, fn () => AbsensiWajah::catatHadir($this->waliA, $this->kelasA, $this->a1->id)));

        $this->assertSame(1, StudentAttendance::withoutGlobalScopes()->count());
        $a = StudentAttendance::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('H', $a->status);
        $this->assertSame(AbsensiWajah::CATATAN, $a->note);
        $this->assertSame($this->waliA->id, $a->recorded_by);
        $this->assertSame(self::KAMIS, $a->date->toDateString());
    }

    public function test_status_yang_sudah_ada_tidak_ditimpa(): void
    {
        $this->untuk($this->s, fn () => \App\Modules\Kehadiran\CatatKehadiranSiswa::handle($this->waliA, $this->kelasA, self::KAMIS, null, [$this->a1->id => ['status' => 'S', 'note' => 'demam']]));

        $this->assertSame('sudah', $this->untuk($this->s, fn () => AbsensiWajah::catatHadir($this->waliA, $this->kelasA, $this->a1->id)));

        $a = StudentAttendance::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('S', $a->status);
        $this->assertSame('demam', $a->note);
    }

    public function test_pencatatan_ditolak_untuk_yang_tidak_berhak_atau_siswa_di_luar_kelas(): void
    {
        $this->tolak(fn () => $this->untuk($this->s, fn () => AbsensiWajah::catatHadir($this->waliB, $this->kelasA, $this->a1->id)));
        $this->tolak(fn () => $this->untuk($this->s, fn () => AbsensiWajah::catatHadir($this->akunKepala, $this->kelasA, $this->a1->id)));
        $this->tolak(fn () => $this->untuk($this->s, fn () => AbsensiWajah::catatHadir($this->waliA, $this->kelasA, $this->b1->id)), ValidationException::class);

        $this->assertSame(0, StudentAttendance::withoutGlobalScopes()->count());
    }

    public function test_pencatatan_dibatasi_lajunya(): void
    {
        $this->untuk($this->s, function () {
            for ($i = 0; $i < 120; $i++) {
                AbsensiWajah::catatHadir($this->waliA, $this->kelasA, $this->a1->id);
            }
        });

        $this->tolak(fn () => $this->untuk($this->s, fn () => AbsensiWajah::catatHadir($this->waliA, $this->kelasA, $this->a1->id)), ValidationException::class);
    }

    // ---------- halaman ----------

    public function test_hak_akses_halaman(): void
    {
        $this->masukPanel($this->akunAdmin, 'sekolah', $this->s);
        $this->assertTrue(DaftarWajah::canAccess());
        $this->assertTrue(AbsenWajah::canAccess());

        $this->masukPanel($this->waliA, 'sekolah', $this->s);
        $this->assertFalse(DaftarWajah::canAccess(), 'wali kelas tidak boleh mendaftarkan wajah');
        $this->assertTrue(AbsenWajah::canAccess());

        $this->masukPanel($this->akunKepala, 'sekolah', $this->s);
        $this->assertFalse(AbsenWajah::canAccess());
    }

    public function test_halaman_daftar_menyimpan_dan_menghapus_wajah(): void
    {
        $this->masukPanel($this->akunAdmin, 'sekolah', $this->s);

        $k = Livewire::test(DaftarWajah::class)->set('kelasId', $this->kelasA->id)->assertSee('Siswa A1')->assertDontSee('Siswa B1');
        $k->call('simpanWajah', $this->a1->id, $this->vektor())->assertReturned(['ok' => true]);
        $this->assertSame(1, FaceTemplate::withoutGlobalScopes()->count());
        $k->assertSee('Terdaftar');

        $k->call('simpanWajah', $this->a1->id, [[1, 2]])->assertReturned(fn ($r) => $r['ok'] === false);
        $k->call('hapusWajah', $this->a1->id);
        $this->assertSame(0, FaceTemplate::withoutGlobalScopes()->count());
    }

    public function test_halaman_absen_memberi_data_dan_mencatat_hadir(): void
    {
        $this->daftar($this->akunAdmin, $this->a1);
        $this->masukPanel($this->waliA, 'sekolah', $this->s);

        $k = Livewire::test(AbsenWajah::class)->assertSee('Absen dengan wajah');
        $k->call('dataKelas', $this->kelasA->id)->assertReturned(fn ($d) => count($d) === 1 && $d[0]['id'] === $this->a1->id);
        $k->call('catatHadir', $this->kelasA->id, $this->a1->id)->assertReturned(['hasil' => 'dicatat']);
        $k->call('catatHadir', $this->kelasA->id, $this->a1->id)->assertReturned(['hasil' => 'sudah']);
        $k->call('catatHadir', $this->kelasB->id, $this->b1->id)->assertReturned(fn ($r) => $r['hasil'] === 'galat');
        $this->assertSame(1, StudentAttendance::withoutGlobalScopes()->count());
    }
}
