<?php

namespace App\Filament\Pages;

use App\Enums\SiteSectionEnum;
use Filament\Actions\Action;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

use function Filament\Support\original_request;

/**
 * Pantalla "Próximamente" de las secciones de la página pública que todavía no se administran.
 *
 * Es una sola pantalla para todas las secciones de SiteSectionEnum: la URL dice cuál se está
 * mirando (/admin/sitio/portada, /admin/sitio/horarios, ...). Cuando una sección tenga su pantalla
 * real, se saca su caso del enum y se la suma al mismo grupo del menú.
 */
class ComingSoon extends Page
{
    // Base de la URL: /admin/sitio; el tramo de la sección lo agrega getRoutePath().
    protected static ?string $slug = 'sitio';

    // Sección de la página pública que se está mirando.
    public SiteSectionEnum $section;

    /**
     * Guarda qué sección se pidió en la URL.
     *
     * Precondiciones:
     * - La URL es /admin/sitio/{section}. Livewire convierte ese tramo en un caso de
     *   SiteSectionEnum antes de llamar a este método; si no coincide con ninguno, responde 404
     *   y este método no se ejecuta.
     *
     * Postcondiciones:
     * - La pantalla queda mostrando esa sección.
     *
     * @param  SiteSectionEnum  $section  Sección pedida (por ejemplo Portada para "portada").
     */
    public function mount(SiteSectionEnum $section): void
    {
        // Guarda la sección para el título y el cartel.
        $this->section = $section;
    }

    /**
     * Ruta de la pantalla dentro del panel, con el tramo variable de la sección.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve "/sitio/{section}": Filament registra una sola ruta para todas las secciones.
     *
     * @param  Panel  $panel  Panel donde se registra la ruta.
     * @return string Ruta relativa al panel.
     */
    public static function getRoutePath(Panel $panel): string
    {
        // Base de la URL más el parámetro que recibe mount().
        return '/'.static::getSlug($panel).'/{section}';
    }

    /**
     * Enlaces del menú lateral: uno por cada sección, dentro del grupo "Admin del sitio".
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve un enlace por cada caso de SiteSectionEnum, en el orden del enum y después de
     *   Planes y Anuncios (que usan los órdenes 1 y 2 en el mismo grupo).
     * - Cada enlace abre esta pantalla con su sección y se marca como activo solo en esa sección.
     *
     * @return array<NavigationItem> Los enlaces, listos para el menú.
     */
    public static function getNavigationItems(): array
    {
        // Enlaces armados.
        $items = [];

        // Recorre las secciones en orden de importancia.
        foreach (SiteSectionEnum::cases() as $position => $section) {
            // Enlace con el nombre de la sección.
            $items[] = NavigationItem::make($section->getLabel())
                // Clave única del enlace dentro del menú.
                ->key(static::class.':'.$section->value)
                // Grupo del menú que reúne lo de la página pública.
                ->group(SiteSectionEnum::NAVIGATION_GROUP)
                // Ícono de la sección.
                ->icon($section->getIcon())
                // Orden: Planes es 1 y Anuncios 2; las secciones siguen desde 3.
                ->sort($position + 3)
                // Activo solo cuando se está mirando esta sección (el tramo de la URL llega ya convertido en la sección).
                ->isActiveWhen(fn (): bool => original_request()->routeIs(static::getRouteName())
                    && original_request()->route('section') === $section)
                // Destino: esta pantalla con la sección en la URL.
                ->url(static::getUrl(['section' => $section->value]));
        }

        // Devuelve todos los enlaces.
        return $items;
    }

    /**
     * Título de la pantalla: el nombre de la sección.
     *
     * Precondiciones:
     * - mount() ya guardó la sección.
     *
     * Postcondiciones:
     * - Devuelve el nombre de la sección (por ejemplo "Portada"), que también va a la pestaña del navegador.
     *
     * @return string Título a mostrar.
     */
    public function getTitle(): string
    {
        // Usa el nombre legible de la sección.
        return $this->section->getLabel();
    }

    /**
     * Contenido de la pantalla: el cartel "Próximamente" de la sección.
     *
     * Precondiciones:
     * - mount() ya guardó la sección.
     *
     * Postcondiciones:
     * - Muestra el ícono de la sección, "Próximamente", qué se va a poder cambiar y un botón que
     *   abre la página pública en otra pestaña.
     * - No guarda nada.
     *
     * @param  Schema  $schema  Esquema vacío que entrega Filament.
     * @return Schema El esquema con el cartel.
     */
    public function content(Schema $schema): Schema
    {
        // Devuelve el esquema con un único componente.
        return $schema->components([
            // Cartel centrado.
            EmptyState::make('Próximamente')
                // Ícono de la sección.
                ->icon($this->section->getIcon())
                // Qué se va a poder hacer acá.
                ->description($this->section->getDescription())
                // Botón al pie del cartel.
                ->footer([
                    // Abre la página pública para ver cómo está hoy la sección.
                    Action::make('verSitio')
                        // Texto del botón.
                        ->label('Ver el sitio')
                        // Ícono de "abrir afuera".
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        // Botón gris: no es una acción principal.
                        ->color('gray')
                        // Página pública del gimnasio.
                        ->url(route('inicio'))
                        // En otra pestaña, para no salir del panel.
                        ->openUrlInNewTab(),
                ]),
        ]);
    }
}
