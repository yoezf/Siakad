<?php

namespace App\Filament\Sekolah\Widgets;

use App\Modules\Laporan\DasborSekolah;
use Filament\Widgets\Widget;

/** Kartu dasbor sekolah (KartuSorot). Angka dari DasborSekolah, dibatasi pada sekolah yang sedang dibuka. */
class KartuSorot extends Widget
{
    protected static ?int $sort = -11;

    protected static bool $isLazy = false;

    protected string $view = 'filament.sekolah.widgets.kartu-sorot';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    public static function canView(): bool
    {
        return auth()->user()?->can('lihat-data-pribadi') ?? false;
    }
}
