<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AdminOnly;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use RuntimeException;

/**
 * Pantalla "Manual de uso" del panel: la guía paso a paso del sistema.
 *
 * El texto vive en resources/manual/manual-de-usuario.md (Markdown) y se convierte a HTML en cada
 * visita. Solo la ven las cuentas con rol de administrador (ver User::isSiteAdmin()): para el
 * resto del personal el enlace no aparece en el menú y la dirección responde 403.
 */
class UserManual extends Page
{
    // Solo para administradores.
    use AdminOnly;

    protected string $view = 'filament.pages.user-manual';

    // URL en castellano: /admin/manual.
    protected static ?string $slug = 'manual';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Manual de uso';

    protected static ?string $title = 'Manual de uso';

    // En el menú va después del QR de ingreso (que usa el orden 2) y antes del grupo "Admin del sitio".
    protected static ?int $navigationSort = 3;

    /**
     * Botones del encabezado de la pantalla.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Ofrece "Imprimir", que abre el diálogo de impresión del navegador (desde ahí también se
     *   guarda como PDF). Al imprimir sale solo el manual (ver filament/admin/styles.blade.php).
     * - No guarda nada.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        // Acciones del encabezado.
        return [
            // Imprime el manual o lo guarda como PDF.
            Action::make('imprimir')
                // Texto del botón.
                ->label('Imprimir')
                // Ícono de impresora.
                ->icon(Heroicon::OutlinedPrinter)
                // Gris: no es una acción principal.
                ->color('gray')
                // No va al servidor: abre el diálogo de impresión en el navegador.
                ->alpineClickHandler('window.print()'),
        ];
    }

    /**
     * Datos que recibe la vista: el manual convertido a HTML.
     *
     * Precondiciones:
     * - Existe resources/manual/manual-de-usuario.md.
     *
     * Postcondiciones:
     * - Devuelve "manual": el HTML del manual, con el índice armado.
     * - No guarda nada.
     *
     * @return array{manual: string}
     */
    protected function getViewData(): array
    {
        // Entrega el HTML a la vista.
        return [
            // Manual listo para mostrar.
            'manual' => $this->renderManual(),
        ];
    }

    /**
     * Convierte el archivo Markdown del manual en HTML.
     *
     * Precondiciones:
     * - Existe resources/manual/manual-de-usuario.md y se puede leer.
     *
     * Postcondiciones:
     * - Devuelve el HTML del manual sin su título principal (la pantalla ya muestra "Manual de uso").
     * - Cada título lleva un id derivado de su texto ("Dar de baja un cliente" => "dar-de-baja-un-cliente"),
     *   así los enlaces internos del manual y el índice saltan a la sección.
     * - Donde el archivo dice [TOC] queda el índice, con los títulos de nivel 2 y 3.
     * - El HTML escrito a mano dentro del archivo se descarta y los enlaces inseguros
     *   (javascript:, etc.) se anulan.
     * - Si el archivo no existe, lanza la excepción.
     * - No modifica nada.
     *
     * @return string HTML del manual.
     *
     * @throws RuntimeException Si falta el archivo del manual.
     */
    private function renderManual(): string
    {
        // Ruta del archivo fuente.
        $path = resource_path('manual/manual-de-usuario.md');

        // Sin el archivo no hay nada que mostrar: es un error de despliegue.
        if (! is_file($path)) {
            // Corta con un mensaje claro.
            throw new RuntimeException("Falta el archivo del manual: {$path}");
        }

        // Texto del manual sin la primera línea de título "# ...", que repetiría el de la pantalla.
        $markdown = preg_replace('/\A#\s.*\R+/', '', (string) file_get_contents($path));

        // Convierte a HTML (Markdown estilo GitHub: tablas, listas, citas).
        return str($markdown)->markdown(
            [
                // Descarta el HTML crudo del archivo.
                'html_input' => 'strip',
                // Anula enlaces con esquemas peligrosos.
                'allow_unsafe_links' => false,
                // Ids de los títulos, para poder enlazarlos.
                'heading_permalink' => [
                    // Sin símbolo visible al lado del título.
                    'symbol' => '',
                    // El id va en el propio título, no en un enlace aparte.
                    'apply_id_to_heading' => true,
                    // Ids sin prefijo: "#cobros", no "#content-cobros".
                    'id_prefix' => '',
                    'fragment_prefix' => '',
                ],
                // Índice automático.
                'table_of_contents' => [
                    // Va donde el archivo tiene el marcador.
                    'position' => 'placeholder',
                    'placeholder' => '[TOC]',
                    // Secciones y casos de uso (títulos ## y ###).
                    'min_heading_level' => 2,
                    'max_heading_level' => 3,
                    // Clase para darle estilo propio.
                    'html_class' => 'orions-manual-indice',
                ],
            ],
            // Extensiones que agregan los ids y el índice.
            [new HeadingPermalinkExtension, new TableOfContentsExtension],
        )->toString();
    }
}
