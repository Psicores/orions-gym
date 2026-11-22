<?php

namespace App\Models;

use App\Enums\SiteSectionEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Contenido editable de una sección de la página pública (horarios, opiniones, etc.).
 *
 * Hay a lo sumo una fila por sección. Lo que no está cargado se muestra vacío y la página pública
 * oculta lo que no tiene datos: así nunca se publica un dato inventado.
 */
#[Fillable(['section', 'data'])]
class SiteContent extends Model
{
    /** Comienzo que tiene que tener la dirección de un mapa para mostrarse (mapa "insertado" de Google Maps). */
    public const string MAP_EMBED_PREFIX = 'https://www.google.com/maps/embed';

    /**
     * Tipos de las columnas.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // La sección como caso del enum.
            'section' => SiteSectionEnum::class,
            // El contenido como arreglo.
            'data' => 'array',
        ];
    }

    /**
     * Devuelve el contenido de una sección, completo con los valores vacíos que falten.
     *
     * Precondiciones:
     * - Existe la tabla site_contents.
     *
     * Postcondiciones:
     * - Devuelve un arreglo con todas las claves de $section->emptyContent(): las que están
     *   cargadas con su valor y las demás vacías. Las claves que no son de esa sección se descartan.
     * - Si la sección no tiene fila, devuelve $section->emptyContent().
     * - No modifica nada.
     *
     * @param  SiteSectionEnum  $section  Sección pedida.
     * @return array<string, mixed> Contenido de la sección.
     */
    public static function dataFor(SiteSectionEnum $section): array
    {
        // Lo guardado (null si la sección nunca se cargó).
        $stored = static::query()->where('section', $section)->value('data');
        // Forma completa de la sección, con todo vacío.
        $empty = $section->emptyContent();

        // Lo guardado sobre lo vacío, solo con las claves que corresponden a la sección.
        return array_replace($empty, array_intersect_key(is_array($stored) ? $stored : [], $empty));
    }

    /**
     * Indica si una dirección se puede mostrar como mapa en la página pública.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve true solo si $url empieza con MAP_EMBED_PREFIX (el mapa "insertado" que da
     *   Google Maps en Compartir > Insertar un mapa). Es lo único que deja cargar la política de
     *   contenido del sitio en un marco.
     * - No modifica nada.
     *
     * @param  string|null  $url  Dirección cargada.
     * @return bool true si se puede mostrar.
     */
    public static function isAllowedMapUrl(?string $url): bool
    {
        // Tiene que ser texto y empezar exactamente como un mapa insertado de Google.
        return is_string($url) && str_starts_with($url, self::MAP_EMBED_PREFIX);
    }
}
