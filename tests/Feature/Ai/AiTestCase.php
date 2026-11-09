<?php

namespace Tests\Feature\Ai;

use App\Models\AiAnalysis;
use App\Models\User;
use App\Modules\Ai\BuatAnalisis;
use App\Support\Peran;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

/** Dasar uji modul AI: sekolah dengan fitur analisis_ai menyala, disk dan antrean palsu, kunci Gemini palsu. Gemini asli tidak pernah dipanggil. */
abstract class AiTestCase extends TestCase
{
    use SiapkanSekolah { setUp as private setUpSekolah; }

    protected const KUNCI = 'kunci-uji-rahasia-12345';

    protected $s;

    protected User $admin;

    protected User $kepala;

    protected User $guru;

    protected function setUp(): void
    {
        $this->setUpSekolah();
        Storage::fake(AiAnalysis::DISK);
        Queue::fake();
        config(['services.gemini.key' => self::KUNCI, 'services.gemini.model' => 'gemini-2.5-flash']);

        $this->s = $this->sekolah('SD A');
        $this->s->update(['features' => ['analisis_ai']]);
        $this->admin = $this->adminDari($this->s);
        $this->kepala = $this->penggunaSekolah($this->s, Peran::KEPALA_SEKOLAH, 'Kepala');
        $this->guru = $this->penggunaSekolah($this->s, Peran::GURU_MAPEL, 'Guru');
    }

    protected function foto(int $lebar = 800, int $tinggi = 600, string $nama = 'foto.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nama, $lebar, $tinggi);
    }

    protected function persetujuan(array $ubah = []): array
    {
        return $ubah + ['subject_name' => 'Budi Santoso', 'consent_name' => 'Budi Santoso', 'consent_relation' => 'diri_sendiri', 'setuju' => true];
    }

    protected function buat(?User $akun = null, string $jenis = 'palmistry', ?UploadedFile $foto = null, ?array $persetujuan = null, $sekolah = null): AiAnalysis
    {
        return $this->untuk($sekolah ?? $this->s, fn () => BuatAnalisis::handle($akun ?? $this->admin, $jenis, $foto ?? $this->foto(), $persetujuan ?? $this->persetujuan()));
    }

    /** Respons mentah palmistry yang sah; $ubah menimpa kolom tingkat atas. */
    protected function palmMentah(array $ubah = []): array
    {
        $b = fn (string $s = 'terlihat_jelas', string $d = 'Garis tampak panjang dan melengkung.', string $i = 'Dalam tradisi palmistri dipercaya melambangkan vitalitas.') => ['status' => $s, 'deskripsi_visual' => $d, 'interpretasi_tradisi' => $i];

        return $ubah + ['gambar_valid' => true, 'sisi_tangan' => 'kanan', 'keterbacaan_gambar' => ['tingkat' => 'baik', 'alasan' => 'Cahaya cukup.'],
            'garis_kehidupan' => $b(), 'garis_kepala' => $b('terlihat_samar'), 'garis_hati' => $b(), 'garis_nasib' => $b('tidak_terlihat', '', ''),
            'bentuk_telapak' => $b(), 'bentuk_jari' => $b(), 'ringkasan_indonesia' => 'Telapak tangan kanan dengan tiga garis utama yang terlihat.', 'catatan_keterbatasan' => ['Foto diambil dari atas.']];
    }

    protected function wajahMentah(array $ubah = []): array
    {
        $b = fn (string $d = 'Berbentuk oval dengan garis rahang halus.', string $i = 'Dalam tradisi fisiognomi, bentuk oval dianggap simbol keseimbangan.') => ['status' => 'terlihat_jelas', 'deskripsi_visual' => $d, 'interpretasi_tradisi' => $i];

        return $ubah + ['wajah_terdeteksi' => true, 'jumlah_wajah' => 1, 'keterbacaan_gambar' => ['tingkat' => 'baik', 'alasan' => 'Wajah menghadap depan.'],
            'bentuk_wajah' => $b(), 'mata' => $b('Mata berbentuk almond.'), 'hidung' => $b('Hidung dengan pangkal sedang.'), 'bibir' => $b('Bibir penuh.'), 'alis' => $b('Alis melengkung.'),
            'dagu' => $b('Dagu bulat.'), 'proporsi_wajah' => $b('Tiga bagian wajah tampak seimbang.'), 'ringkasan_indonesia' => 'Wajah oval dengan proporsi seimbang.', 'catatan_keterbatasan' => []];
    }

    /** Bungkus hasil mentah menjadi respons Gemini. */
    protected function responsGemini(array $mentah): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($mentah)]]], 'finishReason' => 'STOP']]];
    }
}
