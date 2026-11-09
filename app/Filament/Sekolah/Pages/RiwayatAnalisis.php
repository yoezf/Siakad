<?php

namespace App\Filament\Sekolah\Pages;

use App\Filament\Sekolah\Support\KelolaAnalisisAi;
use App\Models\AiAnalysis;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** Semua analisis sekolah ini: lihat hasil, hapus foto saja, atau hapus foto beserta hasilnya. */
class RiwayatAnalisis extends Page
{
    use KelolaAnalisisAi;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Analisis AI';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Riwayat Analisis';

    protected static ?string $title = 'Riwayat Analisis';

    protected static ?string $slug = 'riwayat-analisis-ai';

    protected string $view = 'filament.sekolah.pages.ai-riwayat';

    public string $filterJenis = '';

    public string $filterStatus = '';

    public static function canAccess(): bool
    {
        return static::bisaMengakses();
    }

    /** @return Collection<int, AiAnalysis> */
    public function daftar(): Collection
    {
        return AiAnalysis::with('author')
            ->when(array_key_exists($this->filterJenis, AiAnalysis::TYPE), fn ($q) => $q->where('type', $this->filterJenis))
            ->when(array_key_exists($this->filterStatus, AiAnalysis::STATUS), fn ($q) => $q->where('status', $this->filterStatus))
            ->latest('id')->limit(100)->get();
    }

    public function pilih(int $id): void
    {
        $this->analisisId = $id;
    }
}
