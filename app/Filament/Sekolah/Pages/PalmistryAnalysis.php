<?php

namespace App\Filament\Sekolah\Pages;

use App\Filament\Sekolah\Support\HalamanAnalisis;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PalmistryAnalysis extends HalamanAnalisis
{
    protected static string $jenis = 'palmistry';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Palmistry Analysis';

    protected static ?string $title = 'Palmistry Analysis';

    protected static ?string $slug = 'analisis-palmistry';
}
