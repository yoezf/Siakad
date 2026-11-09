<?php

namespace App\Filament\Sekolah\Pages;

use App\Filament\Sekolah\Support\KelolaAnalisisAi;
use App\Models\AiAnalysis;
use App\Modules\Ai\Skema;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class StatistikAnalisis extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Analisis AI';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Statistik Analisis';

    protected static ?string $title = 'Statistik Analisis';

    protected static ?string $slug = 'statistik-analisis-ai';

    protected string $view = 'filament.sekolah.pages.ai-statistik';

    public static function canAccess(): bool
    {
        return KelolaAnalisisAi::bisaMengakses();
    }

    /** @return array{total: int, jenis: array<string, int>, status: array<string, int>, berhasil: ?int, keterbacaan: array<string, int>, hariIni: int, batas: int, harian: array<string, int>} */
    public function data(): array
    {
        $per = fn (string $kolom, array $kunci) => collect($kunci)->mapWithKeys(fn ($l, $k) => [$k => AiAnalysis::where($kolom, $k)->count()])->all();
        $status = $per('status', AiAnalysis::STATUS);
        $selesai = $status['selesai'] + $status['gagal'];
        $harian = AiAnalysis::where('created_at', '>=', now()->subDays(13)->startOfDay())->get(['created_at'])->groupBy(fn ($a) => $a->created_at->format('Y-m-d'))->map->count();

        return [
            'total' => AiAnalysis::count(),
            'jenis' => $per('type', AiAnalysis::TYPE),
            'status' => $status,
            'berhasil' => $selesai > 0 ? (int) round($status['selesai'] / $selesai * 100) : null,
            'keterbacaan' => $per('readability', Skema::KETERBACAAN),
            'hariIni' => AiAnalysis::whereDate('created_at', today())->count(),
            'batas' => (int) config('ai.batas_harian'),
            'harian' => collect(range(13, 0))->mapWithKeys(fn ($i) => [now()->subDays($i)->format('Y-m-d') => (int) ($harian[now()->subDays($i)->format('Y-m-d')] ?? 0)])->all(),
        ];
    }
}
