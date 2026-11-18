<?php

namespace App\Filament\Sekolah\Widgets;

use App\Models\Announcement;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/** Empat pengumuman terbit terbaru. Dibatasi pada sekolah yang sedang dibuka; hanya untuk yang boleh melihat pengumuman. */
class PapanPengumuman extends Widget
{
    protected static ?int $sort = -6;

    protected static bool $isLazy = false;

    protected string $view = 'filament.sekolah.widgets.papan-pengumuman';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    public static function canView(): bool
    {
        return auth()->user()?->can('lihat-pengumuman') ?? false;
    }

    /** @return Collection<int, Announcement> */
    public function daftar(): Collection
    {
        return Announcement::where('status', 'terbit')->orderByDesc('published_at')->orderByDesc('id')->limit(4)->get();
    }
}
