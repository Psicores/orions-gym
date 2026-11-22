<?php

namespace App\Models;

use App\Enums\SiteSectionEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Devuelve la dirección pública de un archivo subido para una sección (video, foto...).
     *
     * Precondiciones:
     * - $path es una ruta dentro del disco "public" (como las guarda el panel) o null.
     *
     * Postcondiciones:
     * - Devuelve la URL del archivo en el disco "public" (/storage/...), o null si $path está vacío.
     * - No verifica que el archivo exista. No modifica nada.
     *
     * @param  string|null  $path  Ruta guardada.
     * @return string|null URL para usar en src, o null.
     */
    public static function mediaUrl(?string $path): ?string
    {
        // Sin archivo, sin dirección; si no, la del disco público.
        return filled($path) ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Saca la dirección del mapa de lo que pegó el usuario.
     *
     * Google Maps da, en Compartir > Insertar un mapa, un código <iframe src="..."> entero. Esto
     * permite pegar ese código o solo la dirección.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Si $input trae un <iframe> con src, devuelve ese src (con los &amp; convertidos en &).
     * - Si no, devuelve $input sin espacios en las puntas.
     * - Si $input está vacío, devuelve null.
     * - No valida que sea un mapa de Google (ver isAllowedMapUrl()).
     *
     * @param  string|null  $input  Código o dirección pegada.
     * @return string|null La dirección del mapa, o null.
     */
    public static function extractMapUrl(?string $input): ?string
    {
        // Sin espacios en las puntas.
        $input = trim((string) $input);

        // Vacío: sin mapa.
        if ($input === '') {
            // Nada que guardar.
            return null;
        }

        // Con el código entero, toma el src del iframe.
        if (preg_match('/<iframe[^>]*\ssrc=["\']([^"\']+)["\']/i', $input, $matches) === 1) {
            // La dirección, con las entidades HTML convertidas.
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        // Ya era la dirección.
        return $input;
    }
}
