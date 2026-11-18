<?php

namespace App\Providers\Filament;

use App\Filament\Sekolah\Pages\ProfilSekolah;
use App\Models\School;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Sekolah\Pages\Dasbor;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SekolahPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('sekolah')
            ->path('sekolah')
            ->login()
            ->brandLogo(fn () => view('components.brand-logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn () => asset('favicon.svg'))
            ->defaultAvatarProvider(\App\Support\AvatarInisial::class)
            ->defaultThemeMode(\Filament\Enums\ThemeMode::Light)
            ->sidebarCollapsibleOnDesktop()
            ->tenant(School::class, slugAttribute: 'slug')
            ->tenantProfile(ProfilSekolah::class)
            ->colors([
                'primary' => '#6366f1',
            ])
            ->discoverResources(in: app_path('Filament/Sekolah/Resources'), for: 'App\Filament\Sekolah\Resources')
            ->discoverPages(in: app_path('Filament/Sekolah/Pages'), for: 'App\Filament\Sekolah\Pages')
            ->pages([
                Dasbor::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Sekolah/Widgets'), for: 'App\Filament\Sekolah\Widgets')
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
