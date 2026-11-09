<?php

namespace Tests\Feature\Ai;

use App\Models\AiAnalysis;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class HapusDanAksesTest extends AiTestCase
{
    public function test_hapus_foto_menghilangkan_berkas_tetapi_hasil_tetap(): void
    {
        $a = $this->buat();
        $this->untuk($this->s, fn () => $a->fresh()->forceFill(['status' => 'selesai', 'result' => ['x' => 1], 'summary' => 'ringkas'])->save());
        $jalur = $a->image_path;

        $this->untuk($this->s, fn () => $a->fresh()->hapusFoto());

        Storage::disk(AiAnalysis::DISK)->assertMissing($jalur);
        $baru = AiAnalysis::withoutGlobalScopes()->find($a->id);
        $this->assertNull($baru->image_path);
        $this->assertFalse($baru->adaFoto());
        $this->assertSame('ringkas', $baru->summary);
        $this->assertSame(['x' => 1], $baru->result);
    }

    public function test_hapus_analisis_menghilangkan_foto_dan_hasil(): void
    {
        $a = $this->buat();
        $jalur = $a->image_path;

        $this->untuk($this->s, fn () => $a->fresh()->delete());

        Storage::disk(AiAnalysis::DISK)->assertMissing($jalur);
        $this->assertNull(AiAnalysis::withoutGlobalScopes()->find($a->id));
    }

    public function test_kebijakan_akses_per_peran(): void
    {
        $a = $this->buat();
        $kepala = $this->kepala;
        $cek = fn ($akun, $aksi, $arg = null) => Gate::forUser($akun)->allows($aksi, $arg ?? $a);

        $this->assertTrue($cek($this->admin, 'viewAny', AiAnalysis::class));
        $this->assertTrue($cek($this->admin, 'view'));
        $this->assertTrue($cek($this->admin, 'create', AiAnalysis::class));
        $this->assertTrue($cek($this->admin, 'delete'));
        foreach ([$kepala, $this->guru] as $akun) {
            foreach (['view', 'delete'] as $aksi) {
                $this->assertFalse($cek($akun, $aksi), "{$akun->name} tidak boleh $aksi");
            }
            $this->assertFalse($cek($akun, 'viewAny', AiAnalysis::class));
            $this->assertFalse($cek($akun, 'create', AiAnalysis::class));
        }
        $this->assertFalse($cek($this->admin, 'update'), 'hasil tidak disunting');
        $this->assertFalse($cek($this->admin, 'deleteAny', AiAnalysis::class), 'tidak ada hapus massal');
    }

    public function test_admin_sekolah_lain_tidak_melihat_atau_menghapus(): void
    {
        $a = $this->buat();
        $b = $this->sekolah('SD B');
        $b->update(['features' => ['analisis_ai']]);
        $adminB = $this->adminDari($b);

        $this->assertFalse(Gate::forUser($adminB)->allows('view', $a));
        $this->assertFalse(Gate::forUser($adminB)->allows('delete', $a));
        $this->assertSame(0, $this->untuk($b, fn () => AiAnalysis::count()), 'penyaring sekolah menyembunyikan data sekolah lain');
    }

    public function test_fitur_dimatikan_menutup_semua_akses_termasuk_hapus(): void
    {
        $a = $this->buat();
        $this->s->update(['features' => []]);
        $admin = $this->admin->fresh();

        foreach (['view', 'delete'] as $aksi) {
            $this->assertFalse(Gate::forUser($admin)->allows($aksi, $a), $aksi);
        }
        $this->assertFalse(Gate::forUser($admin)->allows('viewAny', AiAnalysis::class));
    }

    public function test_log_audit_tidak_memuat_nama_foto_atau_hasil(): void
    {
        $a = $this->buat();
        $this->untuk($this->s, fn () => $a->fresh()->forceFill(['status' => 'selesai', 'result' => ['rahasia' => 'isi hasil'], 'summary' => 'rahasia ringkasan'])->save());

        $log = json_encode(Activity::where('log_name', 'analisis_ai')->get()->map->only(['description', 'attribute_changes', 'properties'])->all());

        $this->assertNotSame('[]', $log);
        $this->assertStringNotContainsString('Budi Santoso', $log);
        $this->assertStringNotContainsString('rahasia', $log);
        $this->assertStringNotContainsString('.jpg', $log);
    }

    public function test_aturan_data_model(): void
    {
        foreach ([['type' => 'tarot'], ['status' => 'aneh'], ['consent_relation' => 'teman'], ['subject_name' => 'x']] as $ubah) {
            try {
                $m = new AiAnalysis(['type' => 'palmistry', 'subject_name' => 'Budi', 'consent_name' => 'Budi', 'consent_relation' => 'diri_sendiri']);
                $m->forceFill(['consented_at' => now(), 'consent_version' => 'v', 'school_id' => $this->s->id] + $ubah);
                $m->validasiDulu();
                $this->fail('seharusnya ditolak: '.json_encode($ubah));
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->expectException(ValidationException::class);
        $tanpa = new AiAnalysis(['type' => 'palmistry', 'subject_name' => 'Budi', 'consent_name' => 'Budi', 'consent_relation' => 'diri_sendiri']);
        $tanpa->validasiDulu(); // tanpa waktu persetujuan
    }
}
