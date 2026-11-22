<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /** Carpeta del disco "public" donde viven los archivos de la sección Comunidad. */
    private const string DIRECTORY = 'site/comunidad';

    /** Archivos que trae el proyecto para el contenido inicial (en database/seeders/images/comunidad). */
    private const array FILES = ['video-comunidad.mp4', 'video-comunidad.webp', 'barra-olimpica.webp', 'kettlebell.webp'];

    /**
     * Carga como contenido inicial de la sección Comunidad lo que la página pública mostraba fijo
     * antes de que la sección se editara desde el panel. Así, al desplegar, la sección no queda vacía.
     *
     * Precondiciones:
     * - Existe la tabla site_contents (migración create_site_contents_table).
     *
     * Postcondiciones:
     * - Si la sección Comunidad ya tenía contenido, no toca nada.
     * - Si no, copia el video, su imagen fija y las dos fotos al disco "public" (carpeta
     *   site/comunidad) y crea la fila con los textos y esas rutas.
     * - Si algún archivo no se puede copiar, ese dato queda vacío (no se muestra) y el resto se
     *   carga igual: nunca corta el despliegue.
     */
    public function up(): void
    {
        // Ya cargada (por ejemplo, desde el panel): no se pisa.
        if (DB::table('site_contents')->where('section', 'comunidad')->exists()) {
            return;
        }

        // Copia los archivos y anota la ruta de cada uno (null si no se pudo).
        $paths = [];
        foreach (self::FILES as $file) {
            // Origen dentro del proyecto y destino en el disco público.
            $source = database_path('seeders/images/comunidad/'.$file);
            $target = self::DIRECTORY.'/'.$file;
            // Copia solo si el origen existe y el disco deja escribir.
            $paths[$file] = is_file($source) && Storage::disk('public')->put($target, (string) file_get_contents($source)) ? $target : null;
        }

        // Fotos que se pudieron copiar, con su descripción.
        $fotos = array_values(array_filter([
            $paths['barra-olimpica.webp'] ? ['path' => $paths['barra-olimpica.webp'], 'alt' => 'Barra olímpica cargada sobre el piso del gimnasio'] : null,
            $paths['kettlebell.webp'] ? ['path' => $paths['kettlebell.webp'], 'alt' => 'Kettlebell junto a una barra con discos'] : null,
        ]));

        // Contenido que la página mostraba fijo.
        DB::table('site_contents')->insert([
            'section' => 'comunidad',
            'data' => json_encode([
                'etiqueta' => 'La comunidad',
                'titulo' => 'Nadie entrena solo',
                'texto' => 'Orions es un lugar para venir a trabajar: barras, discos, kettlebells y profes que corrigen. Cada uno a su ritmo y con su objetivo, pero nunca solo. Lo que se construye acá, se construye entre todos.',
                'video_path' => $paths['video-comunidad.mp4'],
                'poster_path' => $paths['video-comunidad.webp'],
                'frase' => 'Venís por el físico.',
                'frase_destacada' => 'Te quedás por la gente.',
                'fotos' => $fotos,
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
