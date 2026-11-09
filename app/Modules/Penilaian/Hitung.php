<?php

namespace App\Modules\Penilaian;

use Illuminate\Support\Collection;

/** Perhitungan nilai murni (tanpa basis data) agar mudah diuji: nilai akhir, ketuntasan, dan predikat. */
final class Hitung
{
    /**
     * Nilai akhir = rata-rata berbobot semua komponen aktif, dinormalkan terhadap jumlah bobot, dibulatkan 2 desimal.
     * Hanya dihitung bila SEMUA komponen sudah punya nilai; bila ada yang kosong hasilnya null (belum lengkap), bukan
     * perkiraan. Nilai kosong tidak pernah dianggap nol.
     *
     * @param  Collection<int, object{id:int, weight:int}>  $komponen  komponen aktif
     * @param  array<int, float|string|null>  $nilai  id komponen => nilai
     */
    public static function akhir(Collection $komponen, array $nilai): ?float
    {
        $jumlahBobot = $komponen->sum('weight');
        if ($komponen->isEmpty() || $jumlahBobot <= 0) {
            return null;
        }
        $total = 0.0;
        foreach ($komponen as $k) {
            $v = $nilai[$k->id] ?? null;
            if ($v === null || $v === '') {
                return null;
            }
            $total += (float) $v * $k->weight;
        }

        return round($total / $jumlahBobot, 2);
    }

    /** Tuntas dinilai dari nilai BULAT yang tampil di rapor, supaya angka yang dilihat orang tua dan statusnya selalu sejalan. */
    public static function tuntas(?float $akhir, int $kkm): ?bool
    {
        return $akhir === null ? null : self::bulat($akhir) >= $kkm;
    }

    /** Nilai bulat untuk rapor (pembulatan biasa: 84,5 menjadi 85). */
    public static function bulat(?float $akhir): ?int
    {
        return $akhir === null ? null : (int) round($akhir, 0, PHP_ROUND_HALF_UP);
    }

    /** D bila di bawah KKM (belum tuntas); selain itu A untuk 90 ke atas, B untuk 80 ke atas, dan C sisanya. */
    public static function predikat(?float $akhir, int $kkm): ?string
    {
        if ($akhir === null) {
            return null;
        }
        $n = self::bulat($akhir);

        return match (true) {
            $n < $kkm => 'D',
            $n >= 90 => 'A',
            $n >= 80 => 'B',
            default => 'C',
        };
    }
}
