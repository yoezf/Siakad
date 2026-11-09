<?php

namespace App\Modules\Rapor;

/**
 * Sidik (checksum) salinan rapor. Dihitung dari bentuk kanonik (kunci diurutkan) supaya tidak berubah walau MySQL menata ulang
 * kunci JSON. Karena itu snapshot hanya boleh berisi teks, bilangan bulat, boolean, dan null: bilangan pecahan berubah
 * bentuknya setelah disimpan (79.0 menjadi 79), jadi nilai dan persentase disimpan sebagai teks.
 */
final class Sidik
{
    public static function hitung(array $snapshot): string
    {
        return hash('sha256', json_encode(self::kanonik($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function kanonik(mixed $nilai): mixed
    {
        if (! is_array($nilai)) {
            return $nilai;
        }
        $hasil = array_map([self::class, 'kanonik'], $nilai);
        if (! array_is_list($hasil)) {
            ksort($hasil);
        }

        return $hasil;
    }
}
