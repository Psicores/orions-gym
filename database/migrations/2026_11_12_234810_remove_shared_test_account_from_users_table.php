<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Borra la cuenta de prueba compartida del personal: su contraseña estuvo escrita en el código
     * del proyecto, así que cualquiera que lo haya leído podía entrar al panel /admin.
     *
     * Precondiciones:
     * - Existe la tabla users.
     *
     * Postcondiciones:
     * - Si hay otra cuenta del personal, la cuenta de prueba deja de existir.
     * - Si es la única cuenta (el panel quedaría sin nadie que pueda entrar) o tiene cobros
     *   registrados a su nombre, no se borra: en ese caso se le anula la contraseña, así nadie
     *   puede entrar con ella y los cobros conservan quién los registró.
     */
    public function up(): void
    {
        // Email de la cuenta de prueba.
        $email = 'pandu@pandu.com';
        // Busca la cuenta.
        $account = DB::table('users')->where('email', $email)->first();

        // Si no existe, no hay nada que hacer.
        if ($account === null) {
            return;
        }

        // ¿Es la única cuenta del personal?
        $isOnlyAccount = DB::table('users')->where('email', '!=', $email)->doesntExist();
        // ¿Registró cobros? (la clave foránea de payments impide borrarla).
        $hasPayments = DB::table('payments')->where('registrado_por_user_id', $account->id)->exists();

        // Si es la única cuenta se deja como está: sin ella nadie podría entrar al panel.
        if ($isOnlyAccount) {
            return;
        }

        // Con cobros a su nombre no se puede borrar: se le anula el acceso.
        if ($hasPayments) {
            // Sin email verificado no entra al panel; la contraseña pasa a ser un valor imposible de adivinar.
            DB::table('users')->where('id', $account->id)->update([
                'password' => bcrypt(bin2hex(random_bytes(32))),
                'email_verified_at' => null,
                'remember_token' => null,
            ]);

            return;
        }

        // Caso normal: se borra la cuenta (sus sesiones abiertas dejan de valer al no existir el usuario).
        DB::table('users')->where('id', $account->id)->delete();
    }

    /**
     * No se puede deshacer: la cuenta no se vuelve a crear.
     */
    public function down(): void
    {
        //
    }
};
