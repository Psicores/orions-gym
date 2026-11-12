<?php

namespace App\Providers\Filament;

use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Nombre que muestra el panel (arriba a la izquierda y en el título de la pestaña).
            ->brandName('Orions')
            ->login()
            // Paleta de Orions.
            ->colors([
                // Color principal (botones, enlaces, pestaña activa): verde Orions #598F8B,
                // con su variante clara (#7AAEA9) en 400 y la oscura (#3E6662) en 700.
                'primary' => [
                    50 => '#F1F7F6',
                    100 => '#DCEBE9',
                    200 => '#BBD7D4',
                    300 => '#9AC3BF',
                    400 => '#7AAEA9',
                    500 => '#598F8B',
                    600 => '#4A7A76',
                    700 => '#3E6662',
                    800 => '#325350',
                    900 => '#284240',
                    950 => '#162827',
                ],
                // Color de aviso (quien debe, cuotas vencidas): naranja "desert" #D9772F, el contraste de la paleta.
                'warning' => [
                    50 => '#FDF4EC',
                    100 => '#FAE4D0',
                    200 => '#F4C7A1',
                    300 => '#EDA871',
                    400 => '#E48F4C',
                    500 => '#D9772F',
                    600 => '#BC6223',
                    700 => '#9A4F1D',
                    800 => '#7A3F1A',
                    900 => '#5F3216',
                    950 => '#371B0A',
                ],
                // Grises del panel: en modo oscuro las superficies usan "carbon" 800 (#232625, en 900)
                // y el fondo "carbon" 900 (#161817, en 950).
                'gray' => [
                    50 => '#F6F7F7',
                    100 => '#ECEEED',
                    200 => '#D9DCDB',
                    300 => '#BCC1BF',
                    400 => '#979D9B',
                    500 => '#747A78',
                    600 => '#575C5A',
                    700 => '#3D4140',
                    800 => '#2E3231',
                    900 => '#232625',
                    950 => '#161817',
                ],
            ])
            // Arranca en modo claro; cada usuario puede cambiarlo desde su menú.
            ->defaultThemeMode(ThemeMode::Light)
            // Estilos propios del panel (por ejemplo la cabecera de color de los modales), después de los de Filament.
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): View => view('filament.admin.styles'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Sin widgets genéricos: el inicio muestra solo los de app/Filament/Widgets (contadores y "Para cobrar").
            ->widgets([])
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
