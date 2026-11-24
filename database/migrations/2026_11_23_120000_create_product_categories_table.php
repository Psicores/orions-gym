<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea las categorías de productos del kiosco (Bebidas, Suplementos, Ropa...).
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - product_categories: nombre único y orden (el de los botones de la pantalla de venta).
     */
    public function up(): void
    {
        // Categorías del kiosco.
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            // Nombre del botón de la categoría; no se repite.
            $table->string('nombre', 60)->unique();
            // Posición en la pantalla de venta (menor primero).
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Borra la tabla de categorías.
     */
    public function down(): void
    {
        // Borra la tabla.
        Schema::dropIfExists('product_categories');
    }
};
