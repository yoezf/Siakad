<?php

namespace Tests\Feature\Pengumuman;

use App\Filament\Portal\Pages\Beranda;
use App\Filament\Sekolah\Resources\Announcements\Pages\ManageAnnouncements;
use App\Models\Announcement;
use App\Modules\Pengumuman\Daftar;
use App\Support\Peran;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Portal\PortalTestCase;

class PengumumanTest extends PortalTestCase
{
    private function buat(string $judul, string $sasaran = 'semua', string $status = 'terbit', $kelas = null, $sekolah = null): Announcement
    {
        return $this->untuk($sekolah ?? $this->s, fn () => Announcement::create(['title' => $judul, 'body' => "Isi $judul", 'audience' => $sasaran,
            'status' => $status, 'classroom_id' => $kelas?->id]));
    }

    private function judul($akun): array
    {
        return Daftar::untuk($akun)->pluck('title')->all();
    }

    // ---------- sasaran ----------

    public function test_pengumuman_untuk_semua_dibaca_orang_tua_dan_siswa(): void
    {
        $this->buat('Libur Natal');

        $this->assertSame(['Libur Natal'], $this->judul($this->ortu1));
        $this->assertSame(['Libur Natal'], $this->judul($this->akunSiswaA1));
    }

    public function test_sasaran_orang_tua_dan_siswa_dipisah(): void
    {
        $this->buat('Rapat orang tua', 'orang_tua');
        $this->buat('Lomba siswa', 'siswa');

        $this->assertSame(['Rapat orang tua'], $this->judul($this->ortu1));
        $this->assertSame(['Lomba siswa'], $this->judul($this->akunSiswaA1));
    }

    public function test_sasaran_kelas_hanya_untuk_orang_tua_dan_siswa_kelas_itu(): void
    {
        $this->buat('Study tour 4A', 'kelas', 'terbit', $this->kelasA);
        $this->buat('Karya wisata 4B', 'kelas', 'terbit', $this->kelasB);

        $this->assertSame(['Study tour 4A'], $this->judul($this->ortu1), 'anak di 4A');
        $this->assertSame(['Study tour 4A'], $this->judul($this->akunSiswaA1));
        $this->assertEqualsCanonicalizing(['Study tour 4A', 'Karya wisata 4B'], $this->judul($this->ortu2), 'anak di 4A dan 4B');
    }

    public function test_hanya_yang_terbit_terlihat_dan_terbaru_di_atas(): void
    {
        $this->buat('Draf rahasia', 'semua', 'draf');
        $this->buat('Sudah arsip', 'semua', 'arsip');
        $this->buat('Pertama');
        \Carbon\Carbon::setTestNow('2026-11-13 09:00:00');
        $this->buat('Kedua');

        $this->assertSame(['Kedua', 'Pertama'], $this->judul($this->ortu1));
    }

    public function test_pengumuman_sekolah_lain_tidak_terlihat(): void
    {
        $b = $this->sekolah('SD B');
        $this->buat('Milik SD B', 'semua', 'terbit', null, $b);

        $this->assertSame([], $this->judul($this->ortu1));
    }

    public function test_siswa_yang_sudah_pindah_kelas_mengikuti_kelas_barunya(): void
    {
        $this->buat('Study tour 4A', 'kelas', 'terbit', $this->kelasA);
        $this->buat('Karya wisata 4B', 'kelas', 'terbit', $this->kelasB);
        $this->pindahkanSiswa($this->a1, $this->kelasA, $this->kelasB);

        $this->assertSame(['Karya wisata 4B'], $this->judul($this->ortu1));
    }

    public function test_akun_tanpa_peran_portal_tidak_melihat_pengumuman(): void
    {
        $this->buat('Untuk semua');

        $this->assertSame([], $this->judul($this->waliA));
        $this->assertSame([], $this->judul($this->akunAdmin));
    }

    // ---------- aturan data ----------

    public function test_aturan_isian(): void
    {
        foreach ([
            ['title' => 'ab', 'body' => 'x'],
            ['title' => 'Judul sah', 'body' => '   '],
            ['title' => str_repeat('a', 151), 'body' => 'x'],
            ['title' => 'Judul sah', 'body' => str_repeat('a', 5001)],
            ['title' => 'Judul sah', 'body' => 'x', 'audience' => 'rahasia'],
            ['title' => 'Judul sah', 'body' => 'x', 'status' => 'tayang'],
            ['title' => 'Judul sah', 'body' => 'x', 'audience' => 'kelas'], // tanpa kelas
        ] as $data) {
            try {
                $this->untuk($this->s, fn () => Announcement::create($data));
                $this->fail('seharusnya ditolak: '.json_encode($data));
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, Announcement::withoutGlobalScopes()->count());
    }

    public function test_kelas_sekolah_lain_ditolak(): void
    {
        $b = $this->sekolah('SD B');
        $kelasB = $this->kelas($b, $this->tahunAjaran($b, '2026/2027', '2026-07-01', '2027-06-30'), '4A');

        $this->expectException(ValidationException::class);
        $this->untuk($this->s, fn () => Announcement::create(['title' => 'Judul sah', 'body' => 'x', 'audience' => 'kelas', 'classroom_id' => $kelasB->id]));
    }

    public function test_kelas_dikosongkan_bila_sasaran_bukan_kelas(): void
    {
        $a = $this->buat('Judul sah', 'semua', 'terbit', $this->kelasA);

        $this->assertNull($a->fresh()->classroom_id);
    }

    public function test_waktu_terbit_diisi_sekali_dan_tidak_berubah_saat_diarsipkan(): void
    {
        $a = $this->buat('Judul sah', 'semua', 'draf');
        $this->assertNull($a->published_at);

        $this->untuk($this->s, fn () => $a->update(['status' => 'terbit']));
        $waktu = $a->fresh()->published_at;
        $this->assertNotNull($waktu);

        \Carbon\Carbon::setTestNow('2026-12-01 09:00:00');
        $this->untuk($this->s, fn () => $a->fresh()->update(['status' => 'arsip']));
        $this->assertEquals($waktu, $a->fresh()->published_at);
    }

    public function test_mengubah_isi_pengumuman_terbit_tidak_menggeser_waktu_terbit(): void
    {
        $a = $this->buat('Judul sah');
        $waktu = $a->fresh()->published_at;

        \Carbon\Carbon::setTestNow('2026-12-01 09:00:00');
        $this->untuk($this->s, fn () => $a->fresh()->update(['body' => 'Isi diperbaiki']));

        $this->assertEquals($waktu, $a->fresh()->published_at);
    }

    public function test_pengumuman_terbit_tidak_kembali_menjadi_draf(): void
    {
        $a = $this->buat('Judul sah');

        $this->expectException(ValidationException::class);
        $this->untuk($this->s, fn () => $a->update(['status' => 'draf']));
    }

    public function test_pembuat_tercatat(): void
    {
        $this->actingAs($this->akunKepala);

        $this->assertSame($this->akunKepala->id, $this->buat('Judul sah')->created_by);
    }

    // ---------- halaman kelola (panel sekolah) ----------

    public function test_hak_akses_menu_pengumuman(): void
    {
        $harapan = [Peran::ADMIN_SEKOLAH => 200, Peran::KEPALA_SEKOLAH => 200, Peran::STAF_TU => 200,
            Peran::WALI_KELAS => 403, Peran::GURU_MAPEL => 403, Peran::STAF_KEUANGAN => 403, Peran::GURU_BK => 403];
        foreach ($harapan as $peran => $kode) {
            $akun = $peran === Peran::ADMIN_SEKOLAH ? $this->adminDari($this->s) : $this->penggunaSekolah($this->s, $peran);
            $this->flushSession();
            auth()->forgetGuards();
            $this->assertSame($kode, $this->actingAs($akun)->get("/sekolah/{$this->s->slug}/announcements")->getStatusCode(), $peran);
        }
    }

    public function test_kepala_sekolah_membuat_pengumuman_lewat_halaman_dan_staf_tu_tidak_bisa(): void
    {
        $this->masukPanel($this->akunKepala, 'sekolah', $this->s);
        Livewire::test(ManageAnnouncements::class)->callAction('create', ['title' => 'Libur Semester', 'body' => 'Libur mulai 20 Desember.', 'audience' => 'kelas',
            'classroom_id' => $this->kelasA->id, 'status' => 'terbit'])->assertHasNoActionErrors();

        $a = Announcement::first();
        $this->assertSame(['Libur Semester', 'kelas', $this->kelasA->id, $this->akunKepala->id], [$a->title, $a->audience, $a->classroom_id, $a->created_by]);

        $this->masukPanel($this->akunTu, 'sekolah', $this->s);
        Livewire::test(ManageAnnouncements::class)->assertActionHidden('create')->assertSee('Libur Semester');
    }

    public function test_pengumuman_sekolah_lain_tidak_tampil_di_halaman_kelola(): void
    {
        $b = $this->sekolah('SD B');
        $this->buat('Milik SD B', 'semua', 'terbit', null, $b);
        $this->buat('Milik SD A');

        $this->masukPanel($this->akunAdmin, 'sekolah', $this->s);
        Livewire::test(ManageAnnouncements::class)->assertSee('Milik SD A')->assertDontSee('Milik SD B');
    }

    // ---------- portal ----------

    public function test_portal_menampilkan_pengumuman_dan_tab_hanya_membaca(): void
    {
        $this->buat('Libur Natal');
        $this->buat('Hanya 4B', 'kelas', 'terbit', $this->kelasB);
        $this->masukPortal($this->ortu1);

        Livewire::test(Beranda::class)->call('pilihTab', 'pengumuman')->assertSee('Libur Natal')->assertSee('Isi Libur Natal')->assertDontSee('Hanya 4B');
    }

    public function test_portal_tanpa_pengumuman_diberi_penjelasan(): void
    {
        $this->masukPortal($this->ortu1);

        Livewire::test(Beranda::class)->call('pilihTab', 'pengumuman')->assertSee('Belum ada pengumuman.');
    }

    public function test_isi_html_pada_pengumuman_dilepas(): void
    {
        $this->buat('<b>Judul</b>', 'semua', 'terbit');
        $this->untuk($this->s, fn () => Announcement::first()->update(['body' => '<script>alert(1)</script>']));
        $this->masukPortal($this->ortu1);

        $k = Livewire::test(Beranda::class)->call('pilihTab', 'pengumuman');
        $k->assertDontSeeHtml('<script>alert(1)</script>')->assertSeeHtml('&lt;script&gt;');
    }
}
