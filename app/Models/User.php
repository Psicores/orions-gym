<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
// Implementa los contratos de Filament para la verificación en dos pasos con una app de autenticación
// (Google Authenticator, Authy, etc.) y sus códigos de recuperación. Los traits guardan el secreto y
// los códigos cifrados en app_authentication_secret y app_authentication_recovery_codes, y los
// ocultan al convertir el usuario a array o JSON.
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Decide si el usuario puede entrar a un panel de Filament.
     *
     * Filament la llama al iniciar sesión, en cada request al panel y al pedir o resetear la
     * contraseña. Hoy los usuarios de la tabla users son el personal del gimnasio (dueño y
     * profes); los socios son el modelo Client y no inician sesión.
     *
     * Precondiciones:
     * - $this es un usuario existente cuyas credenciales ya fueron validadas por Filament.
     * - $panel es un panel registrado en la aplicación.
     *
     * Postcondiciones:
     * - Devuelve true solo si el panel es "admin" y el usuario tiene el email verificado.
     * - Devuelve false en cualquier otro caso: Filament rechaza el login, o responde
     *   403 (Prohibido) si ya tenía la sesión iniciada.
     * - No modifica nada: es una consulta pura.
     *
     * @param  Panel  $panel  Panel al que el usuario intenta entrar.
     * @return bool true si tiene acceso, false si no.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Permite el acceso solo si es el panel "admin" Y el usuario verificó su email.
        return $panel->getId() === 'admin' && $this->hasVerifiedEmail();
    }
}
