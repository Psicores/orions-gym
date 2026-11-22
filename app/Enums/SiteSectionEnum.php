<?php

namespace App\Enums;

use App\Filament\Pages\SiteCommunity;
use App\Filament\Pages\SiteHero;
use App\Filament\Pages\SiteReviews;
use App\Filament\Pages\SiteSchedule;
use App\Filament\Pages\SiteSectionPage;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Secciones de la página pública (el home) que se van a poder administrar desde el panel.
 *
 * Cada una se edita en su pantalla del menú "Admin del sitio" (ver editPage()) y su contenido se
 * guarda en site_contents (ver App\Models\SiteContent). El valor de texto de cada caso es la clave
 * de la sección en esa tabla y el nombre de su carpeta de archivos (site/{valor}).
 *
 * Los casos están en orden de importancia, que es el orden en que aparecen en el menú. Los planes
 * también son parte de ese menú, pero ya tienen su pantalla propia (ver PlanResource) y por eso no
 * figuran acá.
 */
enum SiteSectionEnum: string implements HasDescription, HasIcon, HasLabel
{
    // Nombre del grupo del menú lateral que reúne todo lo que administra la página pública.
    public const string NAVIGATION_GROUP = 'Admin del sitio';

    // Lo primero que se ve al entrar: video de fondo, título y texto de bienvenida.
    case Portada = 'portada';
    // Días y horas de apertura, dirección y mapa.
    case Horarios = 'horarios';
    // Sección "Comunidad": texto y video.
    case Comunidad = 'comunidad';
    // Reseñas de socios.
    case Opiniones = 'opiniones';

    /**
     * Devuelve el nombre de la sección para mostrar en el menú y como título de su pantalla.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve un texto no vacío, distinto para cada sección.
     * - No modifica nada.
     *
     * @return string Nombre legible de la sección (por ejemplo "Portada").
     */
    public function getLabel(): string
    {
        // Elige el texto según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Nombre de la portada.
            self::Portada => 'Portada',
            // Nombre de horarios y ubicación (van juntos en el home).
            self::Horarios => 'Horarios y ubicación',
            // Nombre de la sección Comunidad.
            self::Comunidad => 'Comunidad',
            // Nombre de las reseñas.
            self::Opiniones => 'Opiniones',
        };
    }

    /**
     * Devuelve qué se cambia en la sección; es el subtítulo de su pantalla.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve una oración no vacía, distinta para cada sección.
     * - No modifica nada.
     *
     * @return string Explicación corta.
     */
    public function getDescription(): string
    {
        // Elige el texto según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Portada.
            self::Portada => 'Lo primero que se ve al entrar: video de fondo, título, texto de bienvenida y cómo aparece el sitio en Google.',
            // Horarios y ubicación.
            self::Horarios => 'Los días y horarios de apertura, la dirección y el mapa.',
            // Comunidad.
            self::Comunidad => 'El título, el texto, el video y las fotos de la sección Comunidad.',
            // Opiniones.
            self::Opiniones => 'Las opiniones de socios que se muestran en el inicio.',
        };
    }

    /**
     * Devuelve el ícono que acompaña a la sección en el menú y en su pantalla.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve un caso de Heroicon, distinto para cada sección.
     * - No modifica nada.
     *
     * @return Heroicon Ícono de Heroicons incluido en Filament.
     */
    public function getIcon(): Heroicon
    {
        // Elige el ícono según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Cinta de película: la portada es un video.
            self::Portada => Heroicon::OutlinedFilm,
            // Reloj.
            self::Horarios => Heroicon::OutlinedClock,
            // Grupo de personas.
            self::Comunidad => Heroicon::OutlinedUserGroup,
            // Estrella de reseña.
            self::Opiniones => Heroicon::OutlinedStar,
        };
    }

    /**
     * Devuelve la forma del contenido de la sección con todo vacío.
     *
     * Es lo que se muestra mientras nadie cargó nada: la página pública oculta lo vacío, así que
     * nunca aparece un dato inventado. También fija qué claves acepta cada sección (ver
     * SiteContent::dataFor()).
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Horarios: "horarios" (lista de ['dias' => texto, 'horas' => texto]), "direccion" y
     *   "mapa_url" (texto o null).
     * - Opiniones: "opiniones" (lista de ['autor' => texto, 'texto' => texto, 'estrellas' => 1 a 5]).
     * - Comunidad: "etiqueta", "titulo", "texto", "frase", "frase_destacada" (texto o null),
     *   "video_path" y "poster_path" (ruta en el disco "public" o null) y "fotos" (lista de
     *   ['path' => ruta, 'alt' => descripción], hasta dos).
     * - Portada: "etiqueta", "titulo", "titulo_destacado", "texto", "titulo_pagina" y
     *   "descripcion" (texto o null), "video_path" y "poster_path" (ruta en el disco "public" o null).
     * - No modifica nada.
     *
     * @return array<string, mixed> Contenido vacío de la sección.
     */
    public function emptyContent(): array
    {
        // Forma de cada sección; match exige cubrir todos los casos.
        return match ($this) {
            // Franjas horarias, dirección y mapa.
            self::Horarios => ['horarios' => [], 'direccion' => null, 'mapa_url' => null],
            // Reseñas.
            self::Opiniones => ['opiniones' => []],
            // Textos, video y fotos.
            self::Comunidad => [
                'etiqueta' => null, 'titulo' => null, 'texto' => null,
                'video_path' => null, 'poster_path' => null, 'frase' => null, 'frase_destacada' => null,
                'fotos' => [],
            ],
            // Textos, fondo y datos para buscadores.
            self::Portada => [
                'etiqueta' => null, 'titulo' => null, 'titulo_destacado' => null, 'texto' => null,
                'video_path' => null, 'poster_path' => null, 'titulo_pagina' => null, 'descripcion' => null,
            ],
        };
    }

    /**
     * Devuelve la pantalla del panel que edita esta sección.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve la clase de la pantalla (una SiteSectionPage).
     * - No modifica nada.
     *
     * @return class-string<SiteSectionPage> Clase de la pantalla.
     */
    public function editPage(): string
    {
        // Una pantalla por sección.
        return match ($this) {
            // Horarios, dirección y mapa.
            self::Horarios => SiteSchedule::class,
            // Reseñas de socios.
            self::Opiniones => SiteReviews::class,
            // Textos, video y fotos de Comunidad.
            self::Comunidad => SiteCommunity::class,
            // Video de fondo, textos y datos para buscadores.
            self::Portada => SiteHero::class,
        };
    }

    /**
     * Devuelve el lugar de la sección en el menú "Admin del sitio".
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve el orden de la sección entre los casos del enum, empezando en 3 (Planes es el 1 y
     *   Anuncios el 2).
     * - No modifica nada.
     *
     * @return int Orden en el menú.
     */
    public function navigationSort(): int
    {
        // Posición del caso más 3.
        return (int) array_search($this, self::cases(), true) + 3;
    }
}
