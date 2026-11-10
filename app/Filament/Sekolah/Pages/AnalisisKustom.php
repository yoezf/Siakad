<?php

namespace App\Filament\Sekolah\Pages;

use App\Filament\Sekolah\Support\HalamanAnalisis;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/** Mengunggah foto lalu menganalisisnya menurut prompt tersimpan (lihat Prompt Kustom). */
class AnalisisKustom extends HalamanAnalisis
{
    protected static string $jenis = 'custom';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Analisis dengan Prompt';

    protected static ?string $title = 'Analisis dengan Prompt';

    protected static ?string $slug = 'analisis-prompt';
}
