<?php

namespace Database\Seeders;

use App\Enums\SiteSectionEnum;
use App\Models\SiteContent;
use Illuminate\Database\Seeder;

/**
 * Contenido de ejemplo de la página pública (horarios, dirección y opiniones), para ver cómo queda
 * en desarrollo. Son datos inventados: nunca se cargan en producción salvo con SEED_DEMO_DATA=true.
 *
 * Lo carga DatabaseSeeder junto con los demás datos de ejemplo. Se borra con "php artisan demo:remove".
 */
class DemoSiteContentSeeder extends Seeder
{
    /**
     * Contenido de ejemplo por sección (valor de SiteSectionEnum => datos).
     *
     * @var array<string, array<string, mixed>>
     */
    public const array CONTENT = [
        'horarios' => [
            'horarios' => [
                ['dias' => 'Lunes a viernes', 'horas' => '06:00 a 23:00'],
                ['dias' => 'Sábados', 'horas' => '10:00 a 13:00 · 19:00 a 21:00'],
                ['dias' => 'Domingos y feriados', 'horas' => 'Cerrado'],
            ],
            'direccion' => 'Dirección de ejemplo 123',
            'mapa_url' => null,
        ],
        'opiniones' => [
            'opiniones' => [
                ['autor' => 'Socio de ejemplo 1', 'texto' => 'El mejor ambiente para entrenar. Los profes están siempre encima de la técnica.', 'estrellas' => 5],
                ['autor' => 'Socio de ejemplo 2', 'texto' => 'Buen equipamiento, buena música y cero careta. Vengo hace un año y no lo cambio.', 'estrellas' => 5],
                ['autor' => 'Socio de ejemplo 3', 'texto' => 'Arranqué de cero y me acompañaron en cada paso. Se nota la comunidad.', 'estrellas' => 5],
                ['autor' => 'Socio de ejemplo 4', 'texto' => 'Horarios amplios y planes claros. Entrenar acá es otra cosa.', 'estrellas' => 4],
                ['autor' => 'Socio de ejemplo 5', 'texto' => 'Lugar amplio, limpio y con hierro de verdad. Recomendadísimo.', 'estrellas' => 5],
            ],
        ],
    ];

    /**
     * Carga el contenido de ejemplo de las secciones que todavía no tienen nada.
     *
     * Precondiciones:
     * - Existe la tabla site_contents.
     *
     * Postcondiciones:
     * - Cada sección de CONTENT que no tenía fila queda con su contenido de ejemplo.
     * - Una sección que ya tenía contenido (por ejemplo, cargado desde el panel) no se toca, así
     *   que correrlo varias veces no duplica ni pisa nada.
     */
    public function run(): void
    {
        // Recorre las secciones de ejemplo.
        foreach (self::CONTENT as $section => $data) {
            // La crea solo si no existe.
            SiteContent::query()->firstOrCreate(['section' => SiteSectionEnum::from($section)], ['data' => $data]);
        }
    }
}
