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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            // Título del anuncio (lo único obligatorio).
            $table->string('titulo');
            // Texto con formato (HTML del editor). Vacío si el anuncio es solo una imagen.
            $table->text('contenido')->nullable();
            // Ruta de la imagen o banner dentro del disco público. Vacía si es solo texto.
            $table->string('imagen_path')->nullable();
            // Interruptor manual: apagado, no se muestra aunque las fechas lo permitan.
            $table->boolean('is_active')->default(true)->index();
            // Desde cuándo se muestra. Vacío = desde que se crea.
            $table->dateTime('starts_at')->nullable();
            // Hasta cuándo se muestra (baja automática). Vacío = sin fin.
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
