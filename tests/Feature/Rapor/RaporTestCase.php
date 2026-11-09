<?php

namespace Tests\Feature\Rapor;

use App\Models\ReportCard;
use App\Models\ReportCardVersion;
use App\Modules\Kehadiran\CatatKehadiranSiswa;
use App\Modules\Rapor\BukaKunciRapor;
use App\Modules\Rapor\CatatanWali;
use App\Modules\Rapor\SahkanRapor;
use App\Modules\Rapor\Susun;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Penilaian\NilaiTestCase;

/**
 * Dasar uji rapor, memakai fondasi penilaian. Siswa A1 di 4A punya tiga mata pelajaran:
 * Matematika (Pak MTK), IPA (Bu IPA), Bahasa Indonesia (Bu Wali A). Hasil lengkap A1:
 *   MTK 80/90/70 = 79 (C)   IPA 60/60/60 = 60 (D, belum tuntas)   IND 90/90/90 = 90 (A)   rata-rata 76,33.
 * Kehadiran A1 (Senin H, Selasa T, Rabu S) = hadir 1, terlambat 1, sakit 1, tercatat 3, 67%.
 */
abstract class RaporTestCase extends NilaiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // penempatan dimundurkan agar kehadiran tanggal lampau sah
        DB::table('enrollments')->update(['joined_at' => '2026-07-20']);
        $this->untuk($this->s, fn () => $this->s->update(['settings' => ['kepala_sekolah_nama' => 'Drs. Kepala Sekolah', 'kepala_sekolah_nip' => '1970010119900101',
            'rapor_kota' => 'Bandung', 'rapor_judul' => 'LAPORAN HASIL BELAJAR SISWA', 'rapor_tampil_kehadiran' => true]]));
    }

    /** Mengisi seluruh nilai A1 (atau siswa lain) lewat layanan resmi. */
    protected function lengkapi($siswa = null, ?array $skor = null): void
    {
        $siswa ??= $this->a1;
        $skor ??= ['mtk' => [80, 90, 70], 'ipa' => [60, 60, 60], 'ind' => [90, 90, 90]];
        $this->isiPenuh($siswa, $this->mtk, $this->kelasA, $skor['mtk']);
        $this->isiPenuh($siswa, $this->ipa, $this->kelasA, $skor['ipa']);
        $this->isiPenuh($siswa, $this->ind, $this->kelasA, $skor['ind']);
    }

    protected function kehadiranA1(): void
    {
        foreach (['2026-11-09' => 'H', '2026-11-10' => 'T', '2026-11-11' => 'S'] as $tgl => $status) {
            $this->untuk($this->s, fn () => CatatKehadiranSiswa::handle($this->akunAdmin, $this->kelasA, $tgl, null, [$this->a1->id => ['status' => $status]]));
        }
    }

    protected function siapSahkan($siswa = null): void
    {
        $this->lengkapi($siswa);
        $this->kehadiranA1();
        $this->untuk($this->s, fn () => CatatanWali::simpan($this->waliA, $this->sem, $siswa ?? $this->a1, 'Siswa rajin dan santun.'));
    }

    protected function susun($siswa = null): array
    {
        return $this->untuk($this->s, fn () => Susun::untuk($this->sem, $siswa ?? $this->a1));
    }

    protected function sahkan($oleh = null, $siswa = null, $semester = null): ReportCardVersion
    {
        return $this->untuk($this->s, fn () => SahkanRapor::handle($oleh ?? $this->akunKepala, $semester ?? $this->sem, $siswa ?? $this->a1));
    }

    protected function bukaKunci(string $alasan = 'Ada kesalahan input nilai IPA', $oleh = null, $siswa = null): ReportCard
    {
        $rapor = ReportCard::withoutGlobalScopes()->where('student_id', ($siswa ?? $this->a1)->id)->firstOrFail();

        return $this->untuk($this->s, fn () => BukaKunciRapor::handle($oleh ?? $this->akunKepala, $rapor, $alasan));
    }

    protected function rapor($siswa = null): ?ReportCard
    {
        return ReportCard::withoutGlobalScopes()->where('student_id', ($siswa ?? $this->a1)->id)->first();
    }

    /** Pemeriksaan integritas rapor A1 di dalam konteks sekolah (versi dimuat lewat penyaring sekolah). */
    protected function utuhRapor($siswa = null): bool
    {
        return $this->untuk($this->s, fn () => $this->rapor($siswa)->utuh());
    }

    protected function jumlahVersi(): int
    {
        return ReportCardVersion::withoutGlobalScopes()->count();
    }

    /** Semua nilai dalam snapshot harus teks, bilangan bulat, boolean, atau null (lihat Sidik). */
    protected function hanyaTipeSederhana(mixed $n, string $jalur = 'snapshot'): void
    {
        if (is_array($n)) {
            foreach ($n as $k => $v) {
                $this->hanyaTipeSederhana($v, "$jalur.$k");
            }

            return;
        }
        $this->assertTrue($n === null || is_string($n) || is_int($n) || is_bool($n), "$jalur bertipe ".gettype($n).', padahal hanya teks, bilangan bulat, boolean, dan null yang boleh');
    }
}
