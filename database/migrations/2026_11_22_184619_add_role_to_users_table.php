<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el rol a las cuentas del personal y marca como administrador a la del .env.
     *
     * Precondiciones:
     * - Existe la tabla users.
     *
     * Postcondiciones:
     * - users.role existe, es texto y vale "profe" por defecto (ver UserRoleEnum).
     * - La cuenta cuyo email coincide con ADMIN_EMAIL (sin distinguir mayúsculas ni espacios)
     *   queda como "admin".
     * - Si ninguna coincide (ADMIN_EMAIL vacío o distinto), la cuenta más antigua queda como
     *   "admin" y se anota en el log: el panel nunca queda sin administrador.
     * - Sin cuentas, solo se agrega la columna.
     */
    public function up(): void
    {
        // Columna nueva: toda cuenta existente o nueva arranca como profe.
        Schema::table('users', function (Blueprint $table) {
            // Rol de la cuenta.
            $table->string('role', 20)->default('profe')->after('email');
        });

        // Email del administrador según .env, normalizado.
        $adminEmail = mb_strtolower(trim((string) config('app.admin.email')));

        // Marca como admin la cuenta del .env, si existe.
        $promoted = $adminEmail !== '' && DB::table('users')->whereRaw('lower(trim(email)) = ?', [$adminEmail])->update(['role' => 'admin']) > 0;

        // Sin coincidencia, el administrador pasa a ser la cuenta más antigua.
        if (! $promoted) {
            // Cuenta más antigua (null si no hay ninguna).
            $oldest = DB::table('users')->orderBy('id')->value('id');

            // Solo si hay alguna cuenta.
            if ($oldest !== null) {
                // La marca como admin.
                DB::table('users')->where('id', $oldest)->update(['role' => 'admin']);
                // Deja constancia para revisar.
                Log::warning('Ninguna cuenta coincide con ADMIN_EMAIL: se marcó como administrador la más antigua', ['user_id' => $oldest]);
            }
        }
    }

    /**
     * Quita la columna (se pierden los roles asignados).
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Borra el rol.
            $table->dropColumn('role');
        });
    }
};
