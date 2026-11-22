<?php

namespace App\Enums;

use App\Filament\Pages\SiteSchedule;
use App\Filament\Pages\SiteSectionPage;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Secciones de la página pública (el home) que se van a poder administrar desde el panel.
 *
 * Las que ya tienen pantalla propia la devuelven en editPage(); las demás son un enlace del menú
 * "Admin del sitio" que abre la pantalla "Próximamente" (ver App\Filament\Pages\ComingSoon). El
 * valor de texto de cada caso es el tramo de la URL de esa pantalla: /admin/sitio/{valor}.
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
     * Devuelve qué se va a poder cambiar de la sección cuando su pantalla esté lista.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve una oración no vacía, distinta para cada sección.
     * - No modifica nada.
     *
     * @return string Explicación corta para la pantalla "Próximamente".
     */
    public function getDescription(): string
    {
        // Elige el texto según el caso actual; match exige cubrir todos los casos.
        return match ($this) {
            // Qué se va a administrar de la portada.
            self::Portada => 'Desde acá vas a poder cambiar el video de fondo, el título y el texto de bienvenida del inicio.',
            // Qué se va a administrar de horarios y ubicación.
            self::Horarios => 'Desde acá vas a poder cambiar los días y horarios de apertura, la dirección y el mapa.',
            // Qué se va a administrar de Comunidad.
            self::Comunidad => 'Desde acá vas a poder cambiar el título, el texto y el video de la sección Comunidad.',
            // Qué se va a administrar de las reseñas.
            self::Opiniones => 'Desde acá vas a poder elegir qué opiniones de socios se muestran en el inicio.',
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
     * - Portada y Comunidad: todavía sin claves (sus textos siguen en la vista hasta que tengan pantalla).
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
            // Sin contenido editable todavía.
            self::Portada, self::Comunidad => [],
        };
    }

    /**
     * Devuelve la pantalla del panel que edita esta sección, si ya la tiene.
     *
     * Precondiciones:
     * - Ninguna: se llama sobre cualquier caso del enum.
     *
     * Postcondiciones:
     * - Devuelve la clase de la pantalla (una SiteSectionPage) o null si la sección todavía se
     *   muestra como "Próximamente" (ver App\Filament\Pages\ComingSoon).
     * - No modifica nada.
     *
     * @return class-string<SiteSectionPage>|null Clase de la pantalla, o null.
     */
    public function editPage(): ?string
    {
        // Secciones que ya tienen pantalla propia; las demás siguen en "Próximamente".
        return match ($this) {
            // Horarios, dirección y mapa.
            self::Horarios => SiteSchedule::class,
            // Todavía sin pantalla.
            self::Portada, self::Comunidad, self::Opiniones => null,
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
     *   Anuncios el 2). Es el mismo tenga o no pantalla propia.
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
