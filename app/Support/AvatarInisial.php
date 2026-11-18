<?php

namespace App\Support;

use App\Models\School;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar inisial berupa SVG di dalam halaman (data URI), bukan layanan luar: sesuai warna tema, tanpa mengirim nama pengguna ke pihak lain.
 * Sekolah (tenant) tampil putih karena berada di sidebar berwarna; pengguna tampil indigo.
 */
class AvatarInisial implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $nama = trim((string) Filament::getNameForDefaultAvatar($record));
        $inisial = collect(preg_split('/\s+/u', $nama, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $k) => preg_replace('/^[^\p{L}\p{N}]+/u', '', $k))->filter()->take(2)
            ->map(fn (string $k) => mb_strtoupper(mb_substr($k, 0, 1)))->implode('') ?: '?';
        [$latar, $teks] = $record instanceof School ? ['#ffffff', '#4338ca'] : ['#6366f1', '#ffffff'];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="'.$latar.'"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="26" font-weight="700" fill="'.$teks.'">'.e($inisial).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
