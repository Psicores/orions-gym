<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /** Carpeta del disco "public" donde viven los archivos de la portada. */
    private const string DIRECTORY = 'site/portada';

    /**
     * Carga como contenido inicial de la portada lo que la página pública mostraba fijo antes de
     * que se editara desde el panel. Así, al desplegar, la portada se ve igual que antes.
     *
     * Precondiciones:
     * - Existe la tabla site_contents (migración create_site_contents_table).
     *
     * Postcondiciones:
     * - Si la portada ya tenía contenido, no toca nada.
     * - Si no, copia el video de fondo y su imagen fija al disco "public" (carpeta site/portada)
     *   y crea la fila con los textos, el título de la página y la descripción para buscadores.
     * - Si algún archivo no se puede copiar, ese dato queda vacío y el resto se carga igual.
     */
    public function up(): void
    {
        // Ya cargada (por ejemplo, desde el panel): no se pisa.
        if (DB::table('site_contents')->where('section', 'portada')->exists()) {
            return;
        }

        // Copia un archivo del proyecto al disco público y devuelve su ruta (null si no pudo).
        $copy = function (string $file): ?string {
            // Origen dentro del proyecto y destino en el disco público.
            $source = database_path('seeders/images/portada/'.$file);
            $target = self::DIRECTORY.'/'.$file;

            // Copia solo si el origen existe y el disco deja escribir.
            return is_file($source) && Storage::disk('public')->put($target, (string) file_get_contents($source)) ? $target : null;
        };

        // Contenido que la página mostraba fijo.
        DB::table('site_contents')->insert([
            'section' => 'portada',
            'data' => json_encode([
                'etiqueta' => 'Gimnasio · Fuerza · Comunidad',
                'titulo' => 'Fierros',
                'titulo_destacado' => 'y constancia',
                'texto' => 'Entrenamiento serio, sin vueltas. Vení a levantar pesado con gente que empuja para el mismo lado.',
                'video_path' => $copy('video-portada.mp4'),
                'poster_path' => $copy('video-portada.webp'),
                'titulo_pagina' => 'Orions · Gimnasio',
                'descripcion' => 'Orions: gimnasio de fuerza y comunidad. Conocé los planes, los horarios y dónde encontrarnos.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * No se deshace: el contenido pudo haberse editado desde el panel después.
     */
    public function down(): void
    {
        //
    }
};
