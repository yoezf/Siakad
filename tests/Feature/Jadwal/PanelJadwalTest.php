<?php

namespace Tests\Feature\Jadwal;

use App\Filament\Sekolah\Pages\JadwalMingguan;
use App\Filament\Sekolah\Resources\ScheduleEntries\Pages\ManageScheduleEntries;
use App\Filament\Sekolah\Resources\TimeSlots\Pages\ManageTimeSlots;
use App\Models\ScheduleEntry;
use App\Models\TimeSlot;
use App\Support\Peran;
use Livewire\Livewire;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class PanelJadwalTest extends TestCase
{
    use SiapkanSekolah;

    private function dasar(): array
    {
        $s = $this->sekolah('SD A');
        $y = $this->tahunAjaran($s);
        $a = $this->kelas($s, $y, '4A');
        $b = $this->kelas($s, $y, '4B');
        $sari = $this->guruDi($s, 'Bu Sari');
        $budi = $this->guruDi($s, 'Pak Budi');
        $mtk = $this->mapel($s, 'MTK', 'Matematika');
        $ipa = $this->mapel($s, 'IPA', 'Ilmu Alam');
        $this->penugasan($s, $a, $mtk, $sari);
        $this->penugasan($s, $a, $ipa, $budi);
        $this->penugasan($s, $b, $mtk, $sari);
        $j1 = $this->jam($s, 1, '07:00', '07:45');
        $j2 = $this->jam($s, 2, '07:45', '08:30');

        return compact('s', 'y', 'a', 'b', 'sari', 'budi', 'mtk', 'ipa', 'j1', 'j2');
    }

    private function admin(array $d): void
    {
        $this->masukPanel($this->adminDari($d['s']), 'sekolah', $d['s']);
    }

    private function jumlahJadwal(): int
    {
        return ScheduleEntry::withoutGlobalScopes()->count();
    }

    // ---------- jam pelajaran ----------

    public function test_admin_menambah_jam_pelajaran_lewat_formulir(): void
    {
        $s = $this->sekolah('SD A');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageTimeSlots::class)
            ->callAction('create', ['number' => 1, 'name' => 'Jam ke-1', 'start_time' => '07:00', 'end_time' => '07:45', 'is_break' => false])
            ->assertHasNoActionErrors();

        $t = TimeSlot::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($s->id, $t->school_id);
        $this->assertSame('07:00–07:45', $t->rentang());
    }

    public function test_jam_bertabrakan_dilaporkan_sebagai_notifikasi_dan_tidak_tersimpan(): void
    {
        $s = $this->sekolah('SD A');
        $this->jam($s, 1, '07:00', '07:45');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageTimeSlots::class)
            ->callAction('create', ['number' => 2, 'name' => 'Jam ke-2', 'start_time' => '07:30', 'end_time' => '08:15'])
            ->assertNotified('Tidak bisa disimpan');

        $this->assertSame(1, TimeSlot::withoutGlobalScopes()->count());
    }

    public function test_urutan_kembar_ditolak_di_formulir(): void
    {
        $s = $this->sekolah('SD A');
        $this->jam($s, 1, '07:00', '07:45');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageTimeSlots::class)
            ->callAction('create', ['number' => 1, 'name' => 'Lain', 'start_time' => '09:00', 'end_time' => '09:45'])
            ->assertHasActionErrors(['number']);
    }

    public function test_jam_selesai_sebelum_mulai_ditolak_di_formulir(): void
    {
        $s = $this->sekolah('SD A');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);

        Livewire::test(ManageTimeSlots::class)
            ->callAction('create', ['number' => 1, 'name' => 'Jam ke-1', 'start_time' => '08:00', 'end_time' => '07:00'])
            ->assertHasActionErrors(['end_time']);
    }

    public function test_jam_yang_dipakai_jadwal_tidak_bisa_dihapus_tetapi_yang_bebas_bisa(): void
    {
        $d = $this->dasar();
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $this->admin($d);

        Livewire::test(ManageTimeSlots::class)->callTableAction('delete', $d['j1'])->assertNotified('Jam pelajaran ini masih dipakai jadwal.');
        $this->assertTrue(TimeSlot::withoutGlobalScopes()->whereKey($d['j1']->id)->exists());

        Livewire::test(ManageTimeSlots::class)->callTableAction('delete', $d['j2']);
        $this->assertFalse(TimeSlot::withoutGlobalScopes()->whereKey($d['j2']->id)->exists());
    }

    public function test_jam_pelajaran_bisa_diubah_lewat_formulir(): void
    {
        $d = $this->dasar();
        $this->admin($d);

        Livewire::test(ManageTimeSlots::class)
            ->callTableAction('edit', $d['j2'], ['number' => 2, 'name' => 'Jam ke-2 baru', 'start_time' => '07:50', 'end_time' => '08:35', 'is_break' => false])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Jam ke-2 baru', $d['j2']->fresh()->name);
        $this->assertSame('07:50:00', $d['j2']->fresh()->start_time);
    }

    // ---------- jadwal pelajaran ----------

    public function test_admin_menambah_pelajaran_dan_guru_terisi_dari_penugasan(): void
    {
        $d = $this->dasar();
        $this->admin($d);

        Livewire::test(ManageScheduleEntries::class)
            ->callAction('create', ['classroom_id' => $d['a']->id, 'subject_id' => $d['mtk']->id, 'day' => 1, 'time_slot_id' => $d['j1']->id])
            ->assertHasNoActionErrors();

        $e = ScheduleEntry::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($d['sari']->id, $e->staff_id);
        $this->assertSame($d['s']->id, $e->school_id);
    }

    public function test_bentrok_kelas_dilaporkan_jelas_dan_tidak_tersimpan(): void
    {
        $d = $this->dasar();
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $this->admin($d);

        Livewire::test(ManageScheduleEntries::class)
            ->callAction('create', ['classroom_id' => $d['a']->id, 'subject_id' => $d['ipa']->id, 'day' => 1, 'time_slot_id' => $d['j1']->id])
            ->assertNotified('Jadwal bentrok atau tidak sah');

        $this->assertSame(1, $this->jumlahJadwal());
    }

    public function test_bentrok_guru_antar_kelas_dilaporkan_dan_tidak_tersimpan(): void
    {
        $d = $this->dasar();
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $this->admin($d);

        Livewire::test(ManageScheduleEntries::class)
            ->callAction('create', ['classroom_id' => $d['b']->id, 'subject_id' => $d['mtk']->id, 'day' => 1, 'time_slot_id' => $d['j1']->id])
            ->assertNotified('Jadwal bentrok atau tidak sah');

        $this->assertSame(1, $this->jumlahJadwal(), 'Bu Sari tidak boleh mengajar dua kelas pada jam yang sama');
    }

    public function test_mata_pelajaran_tanpa_penugasan_tidak_bisa_dipilih(): void
    {
        $d = $this->dasar();
        $fisika = $this->mapel($d['s'], 'FIS', 'Fisika'); // tidak punya guru pengampu di 4B
        $this->admin($d);

        Livewire::test(ManageScheduleEntries::class)
            ->callAction('create', ['classroom_id' => $d['b']->id, 'subject_id' => $fisika->id, 'day' => 1, 'time_slot_id' => $d['j1']->id])
            ->assertHasActionErrors(['subject_id']);
        $this->assertSame(0, $this->jumlahJadwal());
    }

    public function test_istirahat_tidak_muncul_sebagai_pilihan_jam_pelajaran(): void
    {
        $d = $this->dasar();
        $istirahat = $this->jam($d['s'], 3, '09:00', '09:15', true, 'Istirahat');
        $this->admin($d);

        Livewire::test(ManageScheduleEntries::class)
            ->callAction('create', ['classroom_id' => $d['a']->id, 'subject_id' => $d['mtk']->id, 'day' => 1, 'time_slot_id' => $istirahat->id])
            ->assertHasActionErrors(['time_slot_id']);
        $this->assertSame(0, $this->jumlahJadwal());
    }

    public function test_admin_memindahkan_pelajaran_dan_bentrok_saat_memindah_ditolak(): void
    {
        $d = $this->dasar();
        $mtk = $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $ipa = $this->jadwal($d['s'], $d['a'], $d['ipa'], $d['j2'], 1);
        $this->admin($d);

        Livewire::test(ManageScheduleEntries::class)
            ->callTableAction('edit', $mtk, ['subject_id' => $d['mtk']->id, 'day' => 2, 'time_slot_id' => $d['j1']->id])
            ->assertHasNoTableActionErrors();
        $this->assertSame(2, $mtk->fresh()->day);

        Livewire::test(ManageScheduleEntries::class)
            ->callTableAction('edit', $ipa, ['subject_id' => $d['ipa']->id, 'day' => 2, 'time_slot_id' => $d['j1']->id]) // 4A hari 2 jam 1 sudah terisi MTK
            ->assertNotified('Jadwal tidak bisa diubah');
        $this->assertSame(1, $ipa->fresh()->day, 'jadwal semula harus utuh');
    }

    public function test_admin_menghapus_pelajaran_dari_jadwal(): void
    {
        $d = $this->dasar();
        $e = $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $this->admin($d);

        Livewire::test(ManageScheduleEntries::class)->callTableAction('delete', $e);

        $this->assertSame(0, $this->jumlahJadwal());
    }

    // ---------- hak akses ----------

    public function test_semua_staf_boleh_melihat_jadwal_dan_jam_pelajaran(): void
    {
        $d = $this->dasar();
        foreach (Peran::PANEL_SEKOLAH as $peran) {
            $akun = $peran === Peran::ADMIN_SEKOLAH ? $this->adminDari($d['s']) : $this->penggunaSekolah($d['s'], $peran);
            foreach (['schedule-entries', 'time-slots', 'jadwal-mingguan'] as $halaman) {
                $this->flushSession();
                auth()->forgetGuards();
                $this->assertSame(200, $this->actingAs($akun)->get("/sekolah/{$d['s']->slug}/{$halaman}")->getStatusCode(), "$peran di $halaman");
            }
        }
    }

    public function test_hanya_admin_dan_tu_yang_mengubah_jadwal(): void
    {
        $d = $this->dasar();
        $e = $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);

        foreach ([Peran::GURU_MAPEL, Peran::WALI_KELAS, Peran::KEPALA_SEKOLAH, Peran::STAF_KEUANGAN] as $peran) {
            $this->masukPanel($this->penggunaSekolah($d['s'], $peran), 'sekolah', $d['s']);
            Livewire::test(ManageScheduleEntries::class)->assertActionHidden('create')->assertTableActionHidden('edit', $e)->assertTableActionHidden('delete', $e);
            Livewire::test(ManageTimeSlots::class)->assertActionHidden('create')->assertTableActionHidden('delete', $d['j1']);
        }
        $this->masukPanel($this->penggunaSekolah($d['s'], Peran::STAF_TU), 'sekolah', $d['s']);
        Livewire::test(ManageScheduleEntries::class)->assertActionVisible('create')->assertTableActionVisible('edit', $e);
    }

    public function test_guru_tidak_bisa_mengubah_jadwal_walau_memanggil_aksi_langsung(): void
    {
        $d = $this->dasar();
        $e = $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $this->masukPanel($this->penggunaSekolah($d['s'], Peran::GURU_MAPEL), 'sekolah', $d['s']);

        foreach ([
            fn () => Livewire::test(ManageScheduleEntries::class)->callAction('create', ['classroom_id' => $d['b']->id, 'subject_id' => $d['mtk']->id, 'day' => 3, 'time_slot_id' => $d['j2']->id]),
            fn () => Livewire::test(ManageScheduleEntries::class)->callTableAction('edit', $e, ['subject_id' => $d['mtk']->id, 'day' => 5, 'time_slot_id' => $d['j2']->id]),
            fn () => Livewire::test(ManageScheduleEntries::class)->callTableAction('delete', $e),
            fn () => Livewire::test(ManageTimeSlots::class)->callAction('create', ['number' => 9, 'name' => 'X', 'start_time' => '12:00', 'end_time' => '12:45']),
            fn () => Livewire::test(ManageTimeSlots::class)->callTableAction('delete', $d['j2']),
        ] as $coba) {
            try {
                $coba();
            } catch (\Throwable) {
                // aksi tersembunyi ditolak; yang dinilai adalah isi basis data
            }
        }

        $this->assertSame(1, $this->jumlahJadwal());
        $this->assertSame(1, $e->fresh()->day);
        $this->assertSame(2, TimeSlot::withoutGlobalScopes()->count());
    }

    public function test_jadwal_sekolah_lain_tidak_tampil_di_halaman(): void
    {
        $d = $this->dasar();
        $b = $this->sekolah('SD B');
        $kelasB = $this->kelas($b, $this->tahunAjaran($b), '9Z-Rahasia');
        $mapelB = $this->mapel($b, 'RHS', 'Pelajaran Rahasia B');
        $this->penugasan($b, $kelasB, $mapelB, $this->guruDi($b, 'Guru Rahasia'));
        $this->jadwal($b, $kelasB, $mapelB, $this->jam($b, 1, '07:00', '07:45'), 1);
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);

        $this->actingAs($this->adminDari($d['s']))->get("/sekolah/{$d['s']->slug}/schedule-entries")
            ->assertOk()->assertSee('Matematika')->assertDontSee('Pelajaran Rahasia B')->assertDontSee('9Z-Rahasia');
    }

    // ---------- tampilan mingguan ----------

    public function test_tampilan_mingguan_per_kelas_menampilkan_pelajaran_dan_guru(): void
    {
        $d = $this->dasar();
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $this->jadwal($d['s'], $d['a'], $d['ipa'], $d['j2'], 2);
        $this->masukPanel($this->adminDari($d['s']), 'sekolah', $d['s']);

        Livewire::test(JadwalMingguan::class)
            ->set('mode', 'kelas')->set('pilihan', $d['a']->id)
            ->assertSee('Matematika')->assertSee('Bu Sari')->assertSee('Ilmu Alam')->assertSee('Pak Budi')
            ->assertSee('Senin')->assertSee('Selasa')->assertSee('07:00–07:45');
    }

    public function test_tampilan_mingguan_per_guru_menampilkan_kelas_yang_diajar(): void
    {
        $d = $this->dasar();
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $this->jadwal($d['s'], $d['b'], $d['mtk'], $d['j2'], 1);
        $this->masukPanel($this->adminDari($d['s']), 'sekolah', $d['s']);

        Livewire::test(JadwalMingguan::class)
            ->set('mode', 'guru')->set('pilihan', $d['sari']->id)
            ->assertSee('Kelas 4A')->assertSee('Kelas 4B')->assertSee('Matematika');
    }

    public function test_guru_langsung_melihat_jadwal_mengajarnya_sendiri(): void
    {
        $d = $this->dasar();
        $akun = $this->penggunaSekolah($d['s'], Peran::GURU_MAPEL, 'Akun Bu Sari');
        $this->untuk($d['s'], fn () => $d['sari']->update(['user_id' => $akun->id]));
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $this->jadwal($d['s'], $d['a'], $d['ipa'], $d['j2'], 1); // milik Pak Budi
        $this->masukPanel($akun, 'sekolah', $d['s']);

        $komponen = Livewire::test(JadwalMingguan::class);
        $komponen->assertSet('mode', 'guru')->assertSet('pilihan', $d['sari']->id)->assertSee('Matematika')->assertDontSee('Ilmu Alam');
    }

    public function test_istirahat_dan_keadaan_kosong_tampil_dengan_benar(): void
    {
        $s = $this->sekolah('SD A');
        $this->masukPanel($this->adminDari($s), 'sekolah', $s);
        Livewire::test(JadwalMingguan::class)->assertSee('Belum ada jam pelajaran');

        $this->jam($s, 1, '07:00', '07:45');
        $this->jam($s, 2, '07:45', '08:00', true, 'Istirahat Pagi');
        Livewire::test(JadwalMingguan::class)->assertSee('Istirahat Pagi')->assertSee('Istirahat');
    }

    public function test_sabtu_hanya_muncul_bila_dipakai(): void
    {
        $d = $this->dasar();
        $this->jadwal($d['s'], $d['a'], $d['mtk'], $d['j1'], 1);
        $this->masukPanel($this->adminDari($d['s']), 'sekolah', $d['s']);

        Livewire::test(JadwalMingguan::class)->set('mode', 'kelas')->set('pilihan', $d['a']->id)->assertSee('Jumat')->assertDontSee('Sabtu');

        $this->jadwal($d['s'], $d['a'], $d['ipa'], $d['j1'], 6);
        Livewire::test(JadwalMingguan::class)->set('mode', 'kelas')->set('pilihan', $d['a']->id)->assertSee('Sabtu');
    }
}
