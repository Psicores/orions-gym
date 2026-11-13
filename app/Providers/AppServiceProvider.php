<?php

namespace App\Providers;

use App\Http\Middleware\EnsureMemberHasOwnPassword;
use App\Http\Middleware\EnsureMemberSessionIsCurrent;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Ajustes de seguridad que valen para toda la aplicación.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - En producción, todos los enlaces que genera la aplicación usan https://.
     * - Las acciones de Livewire (que viajan por /livewire/update y no por la ruta de la
     *   pantalla) también exigen que el socio tenga contraseña propia en las pantallas que lo piden.
     * - Todos los campos de fecha del panel (con o sin hora) abren el calendario propio de
     *   Orions en vez del nativo del navegador: semana de lunes a domingo, fecha escrita como
     *   31/12/2026 (más la hora, si el campo la pide) y, en los de solo fecha, se cierra al elegir el día.
     */
    public function boot(): void
    {
        // En producción el sitio solo se usa por HTTPS.
        if ($this->app->isProduction()) {
            // Fuerza https:// en las URL generadas (formularios, redirecciones, assets).
            URL::forceHttps();
        }

        // Livewire vuelve a aplicar estos middlewares en cada acción de las pantallas que los usan.
        Livewire::addPersistentMiddleware([EnsureMemberHasOwnPassword::class, EnsureMemberSessionIsCurrent::class]);

        // Configuración común de todos los campos de fecha del panel (DatePicker hereda de DateTimePicker).
        DateTimePicker::configureUsing(function (DateTimePicker $picker): void {
            // Usa el calendario de Filament (con los estilos de Orions) en vez del nativo del navegador.
            $picker->native(false)
                // La semana arranca el lunes.
                ->firstDayOfWeek(1)
                // Fecha como se escribe acá: día/mes/año, con hora y minutos si el campo los pide.
                ->displayFormat(fn (DateTimePicker $component): string => $component->hasTime() ? 'd/m/Y H:i' : 'd/m/Y')
                // En los campos de solo fecha, elegir el día cierra el calendario.
                ->closeOnDateSelection(fn (DateTimePicker $component): bool => ! $component->hasTime());
        });
    }
}
