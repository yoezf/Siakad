<?php

namespace App\Filament\Sekolah\Widgets;

use App\Models\Student;
use Filament\Widgets\ChartWidget;

/** Siswa aktif menurut jenis kelamin. Dibatasi pada sekolah yang sedang dibuka. */
class KomposisiSiswa extends ChartWidget
{
    protected static ?int $sort = -8;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected static bool $isLazy = false;

    protected ?string $heading = 'Siswa menurut jenis kelamin';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->can('lihat-data-pribadi') ?? false;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $n = Student::where('status', 'aktif')->selectRaw('gender, COUNT(*) as jumlah')->groupBy('gender')->pluck('jumlah', 'gender');

        return [
            'labels' => ['Laki-laki ('.(int) $n->get('L', 0).')', 'Perempuan ('.(int) $n->get('P', 0).')'],
            'datasets' => [['data' => [(int) $n->get('L', 0), (int) $n->get('P', 0)], 'backgroundColor' => ['#6366f1', '#2dd4bf'], 'borderWidth' => 0]],
        ];
    }

    protected function getOptions(): array
    {
        return ['cutout' => '68%', 'plugins' => ['legend' => ['position' => 'bottom']], 'scales' => ['x' => ['display' => false], 'y' => ['display' => false]]];
    }
}
