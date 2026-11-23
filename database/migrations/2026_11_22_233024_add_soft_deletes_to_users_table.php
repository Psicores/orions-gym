<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite dar de baja cuentas del personal sin borrarlas.
     *
     * Precondiciones:
     * - Existe la tabla users.
     *
     * Postcondiciones:
     * - users.deleted_at existe y es null para todas las cuentas. Una cuenta dada de baja no
     *   puede entrar, pero sigue figurando en los pagos que cobró (payments no deja borrarla).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Fecha de baja (null mientras la cuenta está activa).
            $table->softDeletes();
        });
    }

    /**
     * Quita la columna: las cuentas dadas de baja vuelven a quedar activas.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Borra la fecha de baja.
            $table->dropSoftDeletes();
        });
    }
};
