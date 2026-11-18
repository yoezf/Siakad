<?php

namespace App\Filament\Sekolah\Widgets;

use App\Models\StudentAttendance;
use Filament\Widgets\ChartWidget;

/** Kehadiran harian siswa pada 7 hari terakhir yang tercatat: hadir (H, T) dan tidak hadir (S, I, A). Dibatasi pada sekolah yang sedang dibuka. */
class GrafikKehadiran extends ChartWidget
{
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    protected static bool $isLazy = false;

    protected ?string $heading = 'Kehadiran siswa, 7 hari terakhir';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->can('lihat-data-pribadi') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $tanggal = StudentAttendance::whereNull('schedule_entry_id')->select('date')->distinct()->orderByDesc('date')->limit(7)->pluck('date')->reverse()->values();
        $per = StudentAttendance::whereNull('schedule_entry_id')->whereIn('date', $tanggal->map->toDateString()->all())->get(['date', 'status'])
            ->groupBy(fn ($b) => $b->date->toDateString());
        $hadir = $tidak = [];
        foreach ($tanggal as $t) {
            $baris = $per->get($t->toDateString(), collect());
            $hadir[] = $baris->whereIn('status', ['H', 'T'])->count();
            $tidak[] = $baris->whereIn('status', ['S', 'I', 'A'])->count();
        }

        return [
            'labels' => $tanggal->map->format('d/m')->all(),
            'datasets' => [
                ['label' => 'Hadir', 'data' => $hadir, 'backgroundColor' => '#6366f1', 'borderRadius' => 6, 'maxBarThickness' => 22],
                ['label' => 'Tidak hadir', 'data' => $tidak, 'backgroundColor' => '#2dd4bf', 'borderRadius' => 6, 'maxBarThickness' => 22],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['position' => 'bottom']], 'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]];
    }
}
