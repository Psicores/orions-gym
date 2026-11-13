<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('staff:disable-two-factor {email : Email de la cuenta del personal}')]
#[Description('Desactiva la verificación en dos pasos de una cuenta del personal (por ejemplo, si perdió el celular y los códigos de recuperación)')]
class DisableStaffTwoFactor extends Command
{
    /**
     * Desactiva el segundo factor de una cuenta del personal, para que pueda volver a entrar
     * solo con su contraseña y activarlo de nuevo desde "Perfil".
     *
     * Precondiciones:
     * - Se corre desde la consola del servidor (quien la corre ya tiene acceso al servidor).
     * - {email} es el email de una cuenta de la tabla users.
     *
     * Postcondiciones:
     * - Si la cuenta existe: se borran su secreto de la app de autenticación y sus códigos de
     *   recuperación, informa que quedó desactivado y termina con éxito. Su contraseña no cambia.
     * - Si la cuenta no tenía el segundo factor activo, lo informa y no cambia nada.
     * - Si no hay ninguna cuenta con ese email, muestra el error y termina con falla.
     *
     * @return int Command::SUCCESS o Command::FAILURE.
     */
    public function handle(): int
    {
        // Busca la cuenta por email.
        $user = User::query()->where('email', $this->argument('email'))->first();

        // Sin cuenta no hay nada que desactivar.
        if ($user === null) {
            // Avisa y termina con falla.
            $this->error('No hay ninguna cuenta del personal con ese email.');

            return self::FAILURE;
        }

        // Ya estaba desactivado.
        if (blank($user->getAppAuthenticationSecret())) {
            // Avisa y termina sin cambios.
            $this->info('Esa cuenta no tiene la verificación en dos pasos activada.');

            return self::SUCCESS;
        }

        // Borra el secreto de la app de autenticación.
        $user->saveAppAuthenticationSecret(null);
        // Borra los códigos de recuperación.
        $user->saveAppAuthenticationRecoveryCodes(null);

        // Confirma.
        $this->info("Verificación en dos pasos desactivada para {$user->email}. Puede volver a activarla desde su perfil.");

        return self::SUCCESS;
    }
}
