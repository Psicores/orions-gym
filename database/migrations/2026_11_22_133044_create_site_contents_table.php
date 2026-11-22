<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla con el contenido editable de la página pública: una fila por sección.
     *
     * Precondiciones:
     * - No existe la tabla site_contents.
     *
     * Postcondiciones:
     * - Existe site_contents con "section" (valor de SiteSectionEnum, único: una fila por
     *   sección) y "data" (JSON con los textos y listas de esa sección).
     * - La tabla queda vacía: sin filas, la página pública oculta las secciones sin datos.
     */
    public function up(): void
    {
        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();
            // Sección del sitio (portada, horarios, comunidad, opiniones): una fila por cada una.
            $table->string('section', 30)->unique();
            // Contenido de la sección; su forma depende de la sección (ver SiteSectionEnum::emptyContent()).
            $table->json('data');
            $table->timestamps();
        });
    }

    /**
     * Borra la tabla y todo el contenido cargado desde el panel.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_contents');
    }
};
