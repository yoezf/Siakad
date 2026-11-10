<?php

namespace App\Filament\Widgets;

use App\Models\AiAnalysis;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\Peran;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ringkasan lintas sekolah untuk Super Admin (tim platform). Penyaring sekolah dimatikan sengaja: hanya jumlah, tanpa isi data sekolah. */
class RingkasanPlatform extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Ringkasan platform';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole(Peran::SUPER_ADMIN) ?? false;
    }

    protected function getStats(): array
    {
        $aktif = School::where('status', '!=', 'nonaktif')->count();
        $disahkan = ReportCard::withoutGlobalScopes()->where('status', 'disahkan')->count();

        return [
            Stat::make('Sekolah aktif', $aktif)->description(School::count().' terdaftar')->icon('heroicon-o-building-library'),
            Stat::make('Siswa di semua sekolah', Student::withoutGlobalScopes()->where('status', 'aktif')->count())->description('Data tiap sekolah terpisah')->icon('heroicon-o-academic-cap'),
            Stat::make('Pengguna sekolah', User::whereNotNull('school_id')->count())->description('Admin, guru, orang tua, siswa')->icon('heroicon-o-users'),
            Stat::make('Rapor disahkan', $disahkan)->description('Terkunci dan berversi')->icon('heroicon-o-document-check'),
            Stat::make('Sekolah dengan Analisis AI', School::all()->filter->hasFeature('analisis_ai')->count())->description(AiAnalysis::withoutGlobalScopes()->count().' analisis tercatat')->icon('heroicon-o-sparkles'),
        ];
    }
}
