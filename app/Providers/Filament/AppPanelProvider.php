<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Register;
use App\Filament\Pages\HouseMembers;
use App\Filament\Pages\MonthlyBills;
use App\Filament\Pages\Tenancy\EditHouse;
use App\Filament\Pages\Tenancy\RegisterHouse;
use App\Http\Middleware\EnsureActiveHouseMembership;
use App\Http\Middleware\RedirectToEmailVerification;
use App\Models\House;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->viteTheme('resources/css/filament/app/theme.css')
            ->brandName('DiddyVisor')
            ->brandLogo(fn () => view('filament.components.brand'))
            ->brandLogoHeight('3rem')
            ->darkMode(false)
            ->font('Inter', provider: LocalFontProvider::class)
            ->favicon(asset('images/diddy/favicon.ico'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.components.head-meta'))
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_START, fn () => view('filament.components.auth-mascot'))
            ->login()
            ->registration(Register::class)
            ->passwordReset()
            ->emailVerification()
            ->tenant(House::class)
            ->tenantRegistration(RegisterHouse::class)
            ->tenantProfile(EditHouse::class)
            ->tenantMiddleware([EnsureActiveHouseMembership::class], isPersistent: true)
            ->colors([
                'primary' => Color::hex('#C92332'),
                'gray' => Color::hex('#765C4E'),
                'info' => Color::hex('#765C4E'),
                'success' => Color::hex('#247A46'),
                'warning' => Color::hex('#F2C14E'),
                'danger' => Color::hex('#B4471F'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                MonthlyBills::class,
                HouseMembers::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
                RedirectToEmailVerification::class,
            ]);
    }
}
