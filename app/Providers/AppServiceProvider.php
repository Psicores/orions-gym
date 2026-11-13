<?php

namespace App\Providers;

use App\Http\Middleware\EnsureMemberHasOwnPassword;
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
     */
    public function boot(): void
    {
        // En producción el sitio solo se usa por HTTPS.
        if ($this->app->isProduction()) {
            // Fuerza https:// en las URL generadas (formularios, redirecciones, assets).
            URL::forceHttps();
        }

        // Livewire vuelve a aplicar este middleware en cada acción de las pantallas que lo usan.
        Livewire::addPersistentMiddleware([EnsureMemberHasOwnPassword::class]);
    }
}
