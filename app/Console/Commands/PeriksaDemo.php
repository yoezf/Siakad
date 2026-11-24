<?php

namespace App\Console\Commands;

use App\Models\AiPrompt;
use App\Models\Announcement;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Modules\Ai\GeminiGagal;
use App\Modules\Ai\GeminiService;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('demo:periksa {--ai : Uji panggilan nyata ke Gemini (memakai kuota sangat kecil)}')]
#[Description('Memeriksa kesiapan demo: database, data, akun, fitur, dan (opsional) Gemini. Tidak mengubah apa pun.')]
class PeriksaDemo extends Command
{
    private int $gagal = 0;

    private int $peringatan = 0;

    public function handle(): int
    {
        $this->info('Pemeriksaan kesiapan demo');
        $this->newLine();

        $ada = $this->cek('Database terhubung dan data demo ada', fn () => School::count() >= 2 ? School::pluck('name')->implode(', ') : throw new \RuntimeException('Belum ada data demo. Jalankan: php artisan demo:isi'));
        if (! $ada) {
            return $this->akhir();
        }

        $sd = School::where('slug', 'sd-harapan-bangsa')->first();
        $smp = School::where('slug', 'smp-cendekia-nusantara')->first();
        $this->cek('Dua sekolah demo (SD dan SMP) terdaftar dan aktif', fn () => $sd?->isActive() && $smp?->isActive() ? 'ya' : throw new \RuntimeException('SD atau SMP tidak ditemukan atau nonaktif'));

        $sandi = \Database\Seeders\DemoSeeder::SANDI;
        $this->cek('Akun demo bisa masuk dengan kata sandi demo', function () use ($sandi) {
            $email = ['super@demo.test', 'admin@harapanbangsa.demo', 'kepala@harapanbangsa.demo', 'wali6a@harapanbangsa.demo', 'gurumtk@harapanbangsa.demo', 'ortu@harapanbangsa.demo',
                'siswa@harapanbangsa.demo', 'admin@cendekia.demo', 'ortu@cendekia.demo'];
            $salah = collect($email)->filter(fn ($e) => ! ($u = User::where('email', $e)->first()) || ! Hash::check($sandi, $u->password));

            return $salah->isEmpty() ? count($email).' akun diperiksa' : throw new \RuntimeException('Bermasalah: '.$salah->implode(', ').' (jalankan demo:isi untuk mengembalikan)');
        });

        if ($sd) {
            CurrentSchool::set($sd->id);
            $this->cek('SD: 36 siswa, rapor disahkan dan satu dalam revisi', function () {
                $per = ReportCard::all()->groupBy('status')->map->count();

                return Student::count() === 36 && ($per['disahkan'] ?? 0) >= 12 && ($per['revisi'] ?? 0) >= 1 ? '36 siswa, '.($per['disahkan'] ?? 0).' disahkan, '.($per['revisi'] ?? 0).' revisi' : throw new \RuntimeException('Data SD tidak sesuai skenario demo');
            });
            $this->cek('SD: pengumuman terbit untuk portal', fn () => Announcement::where('status', 'terbit')->count() >= 4 ? 'ya' : throw new \RuntimeException('Pengumuman kurang'));
            $this->cek('SD: fitur Analisis AI menyala dan ada prompt contoh', fn () => $sd->hasFeature('analisis_ai') && AiPrompt::count() >= 3 ? AiPrompt::count().' prompt' : throw new \RuntimeException('Fitur analisis_ai mati atau prompt contoh hilang'));
            $this->cek('SD: Absensi wajah menyala dan berkas model wajah ada', function () use ($sd) {
                $model = ['tiny_face_detector_model.bin', 'face_landmark_68_model.bin', 'face_recognition_model.bin'];
                $lengkap = collect($model)->every(fn ($f) => is_file(public_path("vendor/face-api/model/$f")));

                return $sd->hasFeature('absensi_wajah') && $lengkap ? 'ya' : throw new \RuntimeException('Fitur absensi_wajah mati atau berkas model hilang (public/vendor/face-api/model)');
            });
            CurrentSchool::set(null);
        }
        if ($smp) {
            $this->cek('SMP: fitur Analisis AI dan Absensi wajah mati (sengaja, untuk menunjukkan kontrol per sekolah)', fn () => ! $smp->hasFeature('analisis_ai') && ! $smp->hasFeature('absensi_wajah') ? 'mati' : throw new \RuntimeException('Fitur menyala di SMP'));
        }

        $this->cek('Akun uji lemah (admin@admin.test) tidak ada', fn () => ! User::where('email', 'admin@admin.test')->exists() ? 'tidak ada' : throw new \RuntimeException('Masih ada akun dengan kata sandi sederhana. Hapus sebelum demo di jaringan umum.'), peringatan: true);

        // ---- Analisis AI ----
        $this->newLine();
        $this->line('Analisis AI');
        $kunci = (string) config('services.gemini.key');
        $this->cek('Kunci Gemini terisi di server', fn () => $kunci !== '' ? strlen($kunci).' karakter (isi tidak ditampilkan)' : throw new \RuntimeException('GEMINI_API_KEY kosong di .env'), peringatan: true);
        $this->cek('Model Gemini yang dipakai', fn () => config('services.gemini.model'), peringatan: true);
        $this->cek('Penyimpanan foto privat dapat ditulis', function () {
            Storage::disk('local')->put('analisis-ai/.periksa', 'ok');
            Storage::disk('local')->delete('analisis-ai/.periksa');

            return 'ya';
        }, peringatan: true);
        $this->cek('Pekerja antrean: tidak ada job menumpuk atau gagal', function () {
            $m = DB::table('jobs')->count();
            $g = DB::table('failed_jobs')->count();

            return $m === 0 && $g === 0 ? 'bersih' : throw new \RuntimeException("$m job menunggu, $g gagal. Setel QUEUE_CONNECTION=sync di .env untuk demo lokal, lalu php artisan queue:clear");
        }, peringatan: true);
        $this->cek('Jenis antrean', fn () => config('queue.default') === 'sync' ? 'sync: analisis langsung diproses saat diunggah' : config('queue.default').': butuh pekerja antrean yang berjalan; untuk demo lokal pakai QUEUE_CONNECTION=sync', peringatan: true);

        if ($this->option('ai')) {
            $this->cek('Panggilan nyata ke Gemini berhasil', function () {
                $im = imagecreatetruecolor(400, 300);
                imagefill($im, 0, 0, imagecolorallocate($im, 240, 240, 240));
                imagefilledellipse($im, 200, 150, 160, 160, imagecolorallocate($im, 200, 60, 60));
                ob_start();
                imagejpeg($im, null, 80);
                $jpeg = (string) ob_get_clean();
                $t = microtime(true);
                try {
                    app(GeminiService::class)->analisis('custom', $jpeg, 'image/jpeg', 'Sebutkan bentuk dan warna yang terlihat di gambar.');
                } catch (GeminiGagal $e) {
                    throw new \RuntimeException($e->getMessage());
                }

                return sprintf('berhasil dalam %.1f detik', microtime(true) - $t);
            }, peringatan: true);
        } else {
            $this->line('  (lewati uji Gemini nyata; tambahkan --ai untuk mencobanya)');
        }

        return $this->akhir();
    }

    /** Menjalankan satu pemeriksaan dan mencetak hasilnya. Mengembalikan true bila lolos. */
    private function cek(string $nama, callable $f, bool $peringatan = false): bool
    {
        try {
            $hasil = $f();
            $this->line(sprintf('  <info>OK</info>   %s%s', $nama, $hasil !== null && $hasil !== '' ? '  ['.$hasil.']' : ''));

            return true;
        } catch (Throwable $e) {
            $peringatan ? $this->peringatan++ : $this->gagal++;
            $this->line(sprintf('  %s %s  [%s]', $peringatan ? '<comment>PERLU</comment>' : '<error>GAGAL</error>', $nama, $e->getMessage()));

            return false;
        }
    }

    private function akhir(): int
    {
        $this->newLine();
        if ($this->gagal > 0) {
            $this->error("{$this->gagal} pemeriksaan wajib gagal. Perbaiki sebelum demo.");

            return self::FAILURE;
        }
        $this->info($this->peringatan > 0 ? "Siap, dengan {$this->peringatan} hal yang perlu diperhatikan (lihat PERLU di atas)." : 'Siap demo.');

        return self::SUCCESS;
    }
}
