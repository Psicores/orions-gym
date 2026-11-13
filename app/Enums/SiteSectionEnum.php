<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Secciones de la página pública (el home) que se van a poder administrar desde el panel.
 *
 * Todavía no se administra ninguna: cada caso es un enlace del menú "Admin del sitio" que abre la
 * pantalla "Próximamente" (ver App\Filament\Pages\ComingSoon). El valor de texto de cada caso es el
 * tramo de la URL: /admin/sitio/{valor}.
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
}
