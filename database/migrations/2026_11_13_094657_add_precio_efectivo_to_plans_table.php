<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega a los planes el precio con descuento por pago en efectivo.
     *
     * Precondiciones:
     * - Existe la tabla plans con la columna precio.
     *
     * Postcondiciones:
     * - plans tiene la columna precio_efectivo, decimal(10,2), vacía (null) en los planes existentes.
     * - Un plan sin precio_efectivo se sigue cobrando al precio de lista también en efectivo.
     */
    public function up(): void
    {
        // Modifica la tabla de planes.
        Schema::table('plans', function (Blueprint $table) {
            // Precio pagando en efectivo; opcional, ubicado después del precio de lista.
            $table->decimal('precio_efectivo', 10, 2)->nullable()->after('precio');
        });
    }

    /**
     * Quita el precio en efectivo de los planes.
     *
     * Precondiciones:
     * - Existe la columna plans.precio_efectivo.
     *
     * Postcondiciones:
     * - La columna deja de existir; se pierden los precios en efectivo cargados.
     */
    public function down(): void
    {
        // Modifica la tabla de planes.
        Schema::table('plans', function (Blueprint $table) {
            // Elimina la columna.
            $table->dropColumn('precio_efectivo');
        });
    }
};
