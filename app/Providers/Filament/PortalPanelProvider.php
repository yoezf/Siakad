<?php

namespace App\Providers\Filament;

use App\Filament\Portal\Pages\Beranda;
use App\Models\School;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** Portal orang tua dan siswa: panel terpisah dari panel Sekolah, hanya berisi halaman yang membaca data anak sendiri. */
class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->login()
            ->brandLogo(fn () => view('components.brand-logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn () => asset('favicon.svg'))
            ->defaultAvatarProvider(\App\Support\AvatarInisial::class)
            ->defaultThemeMode(\Filament\Enums\ThemeMode::Light)
            ->topNavigation()
            ->maxContentWidth(\Filament\Support\Enums\Width::SevenExtraLarge)
            ->tenant(School::class, slugAttribute: 'slug')
            ->colors(['primary' => '#6366f1'])
            ->pages([Beranda::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
