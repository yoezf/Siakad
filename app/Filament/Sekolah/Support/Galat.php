<?php

namespace App\Filament\Sekolah\Support;

use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/** Aturan data yang dijaga di model dilaporkan ke pengguna sebagai notifikasi yang jelas, bukan galat umum. */
final class Galat
{
    public static function pesan(ValidationException $e): string
    {
        return collect($e->errors())->flatten()->implode(' ');
    }

    public static function beritahu(ValidationException $e, string $judul = 'Tidak bisa disimpan'): void
    {
        Notification::make()->danger()->persistent()->title($judul)->body(self::pesan($e))->send();
    }
}
