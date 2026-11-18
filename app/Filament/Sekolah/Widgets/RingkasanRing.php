<?php

namespace App\Filament\Sekolah\Widgets;

use App\Modules\Laporan\DasborSekolah;
use Filament\Widgets\Widget;

/** Kartu dasbor sekolah (RingkasanRing). Angka dari DasborSekolah, dibatasi pada sekolah yang sedang dibuka. */
class RingkasanRing extends Widget
{
    protected static ?int $sort = -12;

    protected static bool $isLazy = false;

    protected string $view = 'filament.sekolah.widgets.ringkasan-ring';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public static function canView(): bool
    {
        return auth()->user()?->can('lihat-data-pribadi') ?? false;
    }
}
