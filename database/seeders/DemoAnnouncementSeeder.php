<?php

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Anuncios de ejemplo para desarrollo: cubren todas las combinaciones (texto e imagen, solo texto
 * con formato, solo imagen, con y sin fechas) y todas las situaciones (visible, programado,
 * finalizado y apagado).
 *
 * Lo carga DatabaseSeeder junto con todo lo demás (salvo con SEED_DEMO_DATA=false). A mano:
 * php artisan db:seed --class=DemoAnnouncementSeeder
 */
class DemoAnnouncementSeeder extends Seeder
{
    /**
     * Carga los anuncios de ejemplo.
     *
     * Precondiciones:
     * - Existe la tabla announcements.
     * - Para ver las imágenes en el navegador existe el enlace public/storage (DatabaseSeeder
     *   intenta crearlo; a mano es php artisan storage:link).
     *
     * Postcondiciones:
     * - Existen los seis anuncios de ejemplo: tres visibles (texto e imagen; solo texto con
     *   formato y fecha de fin; solo imagen), uno programado, uno finalizado y uno apagado.
     *   Las fechas son relativas a hoy, así las situaciones valen sin importar cuándo se cargue.
     * - Los dos banners quedan en el disco "public", carpeta "announcements".
     * - Un anuncio cuyo título ya existía no se toca, así que correrlo varias veces no duplica
     *   nada ni pisa cambios hechos desde el panel (por ejemplo, haberlo apagado).
     * - Si no puede copiar un banner, ese anuncio se carga sin imagen: nunca corta la carga.
     */
    public function run(): void
    {
        // Copia al disco público los dos banners que vienen con el proyecto.
        $bannerGym = $this->publishBanner('demo-banner-gimnasio.jpg');
        $bannerEntreno = $this->publishBanner('demo-banner-entrenamiento.jpg');

        // Anuncios de ejemplo; el título es la clave para no duplicarlos.
        $announcements = [
            // Visible, completo: imagen y texto, sin fechas (se muestra hasta que lo apaguen).
            [
                'titulo' => 'Llegaron las barras nuevas',
                'contenido' => '<p>Sumamos <strong>cuatro barras olímpicas</strong> y dos racks. Ya los podés usar en el sector de fuerza.</p>',
                'imagen_path' => $bannerGym,
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
            ],
            // Visible, solo texto con todo el formato del editor y baja automática en 5 días.
            [
                'titulo' => 'Feriado del lunes: horario especial',
                'contenido' => '<p>El <strong>lunes</strong> abrimos con horario reducido:</p>'
                    .'<ul><li>Mañana: de 9 a 13</li><li>Tarde: <em>cerrado</em></li></ul>'
                    .'<p>El martes volvemos al <u>horario habitual</u>. Cualquier duda, <a href="'.url('/').'">mirá los horarios en el sitio</a>.</p>'
                    .'<ol><li>Reservá tu lugar en recepción.</li><li>Traé tu toalla.</li></ol>',
                'imagen_path' => null,
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(5),
            ],
            // Visible, solo imagen (banner): sin texto.
            [
                'titulo' => 'Torneo interno de fuerza',
                'contenido' => null,
                'imagen_path' => $bannerEntreno,
                'is_active' => true,
                'starts_at' => now()->subDays(2),
                'ends_at' => null,
            ],
            // Programado: todavía no empezó, los socios no lo ven.
            [
                'titulo' => 'Promo de verano: traé un amigo',
                'contenido' => '<p>Durante enero, tu amigo entrena <strong>una semana gratis</strong>.</p>',
                'imagen_path' => null,
                'is_active' => true,
                'starts_at' => now()->addDays(3),
                'ends_at' => now()->addDays(33),
            ],
            // Finalizado: pasó su fecha de fin y se dio de baja solo.
            [
                'titulo' => 'Corte de agua el martes',
                'contenido' => '<p>El martes por la mañana no hubo duchas por un arreglo en el edificio.</p>',
                'imagen_path' => null,
                'is_active' => true,
                'starts_at' => now()->subDays(10),
                'ends_at' => now()->subDay(),
            ],
            // Apagado: dado de baja a mano, aunque no tiene fecha de fin.
            [
                'titulo' => 'Clase abierta de movilidad',
                'contenido' => '<p>Se suspendió. Avisamos cuando haya nueva fecha.</p>',
                'imagen_path' => null,
                'is_active' => false,
                'starts_at' => null,
                'ends_at' => null,
            ],
        ];

        // Crea los anuncios que falten.
        foreach ($announcements as $announcement) {
            // Busca por título; si ya existe lo deja como está, si no lo crea.
            Announcement::query()->firstOrCreate(['titulo' => $announcement['titulo']], $announcement);
        }
    }

    /**
     * Copia un banner de ejemplo al disco público, donde lo lee el sitio.
     *
     * Los banners vienen ya achicados dentro del proyecto (database/seeders/images), así el
     * seeder no necesita procesar imágenes en el servidor.
     *
     * Precondiciones:
     * - $file es el nombre de un archivo de database/seeders/images.
     *
     * Postcondiciones:
     * - En el disco "public" existe announcements/$file (si ya estaba, no lo vuelve a copiar) y
     *   devuelve esa ruta, lista para guardar en imagen_path.
     * - Si el archivo de origen falta o no se puede escribir, devuelve null y no lanza nada.
     *
     * @param  string  $file  Nombre del banner.
     * @return string|null Ruta dentro del disco "public", o null si no se pudo copiar.
     */
    private function publishBanner(string $file): ?string
    {
        // Ruta del banner dentro del proyecto.
        $source = database_path('seeders/images/'.$file);
        // Ruta de destino dentro del disco público.
        $target = 'announcements/'.$file;

        // Si ya está copiado, alcanza con devolver la ruta.
        if (Storage::disk('public')->exists($target)) {
            // Lista para usar.
            return $target;
        }

        // Sin el archivo de origen no hay imagen: el anuncio se carga igual, sin banner.
        if (! is_file($source)) {
            // Sin imagen.
            return null;
        }

        // Lo copia; si el disco no deja escribir, put() devuelve false.
        return Storage::disk('public')->put($target, (string) file_get_contents($source)) ? $target : null;
    }
}
