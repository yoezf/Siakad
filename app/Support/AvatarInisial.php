<?php

namespace App\Support;

use App\Models\School;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar berupa SVG di dalam halaman (data URI), bukan layanan luar: sesuai warna tema, tanpa mengirim nama ke pihak lain.
 * Pengguna tampil sebagai ikon orang putih di lingkaran indigo; sekolah (tenant) tampil putih dengan inisial karena berada di atas sidebar berwarna.
 */
class AvatarInisial implements AvatarProvider
{
    private const ORANG = 'M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z';

    public function get(Model|Authenticatable $record): string
    {
        if (! $record instanceof School) {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#6366f1"/>'
                .'<g transform="translate(14 14) scale(1.5)" fill="#ffffff"><path fill-rule="evenodd" clip-rule="evenodd" d="'.self::ORANG.'"/></g></svg>';

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        }
        $nama = trim((string) Filament::getNameForDefaultAvatar($record));
        $inisial = collect(preg_split('/\s+/u', $nama, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $k) => preg_replace('/^[^\p{L}\p{N}]+/u', '', $k))->filter()->take(2)
            ->map(fn (string $k) => mb_strtoupper(mb_substr($k, 0, 1)))->implode('') ?: '?';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#ffffff"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="26" font-weight="700" fill="#4338ca">'.e($inisial).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
