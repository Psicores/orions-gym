<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Contraseña del portal del socio, hasheada. Vacía = todavía no la eligió y entra con su DNI.
            $table->string('password')->nullable()->after('email');
            // Token de "recordarme": mantiene la sesión del portal abierta en el celular.
            $table->rememberToken()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Saca las dos columnas del portal.
            $table->dropColumn(['password', 'remember_token']);
        });
    }
};
