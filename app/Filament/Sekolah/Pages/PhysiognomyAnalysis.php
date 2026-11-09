<?php

namespace App\Filament\Sekolah\Pages;

use App\Filament\Sekolah\Support\HalamanAnalisis;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PhysiognomyAnalysis extends HalamanAnalisis
{
    protected static string $jenis = 'physiognomy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceSmile;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Physiognomy Analysis';

    protected static ?string $title = 'Physiognomy Analysis';

    protected static ?string $slug = 'analisis-physiognomy';
}
