<?php

namespace App\Filament\Pages;

use App\Enums\SiteSectionEnum;
use App\Models\SiteContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Base de las pantallas del panel que editan una sección de la página pública (horarios,
 * opiniones, etc.). Cada sección define sus campos; la carga, el guardado y el lugar en el menú
 * son comunes a todas.
 *
 * El contenido se guarda en site_contents (ver App\Models\SiteContent).
 *
 * @property-read Schema $form
 */
abstract class SiteSectionPage extends Page
{
    protected string $view = 'filament.pages.site-section';

    protected static string|UnitEnum|null $navigationGroup = SiteSectionEnum::NAVIGATION_GROUP;

    /**
     * Contenido del formulario mientras se edita.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Sección de la página pública que edita esta pantalla.
     *
     * @return SiteSectionEnum La sección.
     */
    abstract public static function section(): SiteSectionEnum;

    /**
     * Campos del formulario de la sección.
     *
     * @return array<Component> Componentes del formulario, con los nombres de las claves de la sección.
     */
    abstract protected function fields(): array;

    /**
     * Prepara el contenido de la sección antes de guardarlo (recorta textos, saca filas vacías...).
     *
     * Precondiciones:
     * - $data es el estado del formulario ya validado.
     *
     * Postcondiciones:
     * - Devuelve el contenido listo para guardar. Por defecto, el mismo $data.
     *
     * @param  array<string, mixed>  $data  Estado del formulario.
     * @return array<string, mixed> Contenido a guardar.
     */
    protected function prepareForSaving(array $data): array
    {
        // Sin cambios, salvo que la sección lo redefina.
        return $data;
    }

    /**
     * Nombre de la pantalla en el menú: el de la sección.
     *
     * @return string Nombre legible.
     */
    public static function getNavigationLabel(): string
    {
        // El mismo que usaba la pantalla "Próximamente".
        return static::section()->getLabel();
    }

    /**
     * Ícono de la pantalla en el menú: el de la sección.
     *
     * @return string|BackedEnum|null Ícono.
     */
    public static function getNavigationIcon(): string|BackedEnum|null
    {
        // El mismo que usaba la pantalla "Próximamente".
        return static::section()->getIcon();
    }

    /**
     * Lugar en el menú: el que le corresponde a la sección dentro de "Admin del sitio".
     *
     * @return int|null Orden.
     */
    public static function getNavigationSort(): ?int
    {
        // Mismo orden que las secciones que siguen en "Próximamente".
        return static::section()->navigationSort();
    }

    /**
     * Título de la pantalla: el nombre de la sección.
     *
     * @return string Título.
     */
    public function getTitle(): string
    {
        // Por ejemplo "Horarios y ubicación".
        return static::section()->getLabel();
    }

    /**
     * Carga en el formulario lo que la sección tiene guardado.
     *
     * Precondiciones:
     * - Existe la tabla site_contents.
     *
     * Postcondiciones:
     * - El formulario muestra el contenido guardado de la sección, o todo vacío si nunca se cargó.
     */
    public function mount(): void
    {
        // Contenido actual, completo con vacíos (ver SiteContent::dataFor()).
        $this->form->fill(SiteContent::dataFor(static::section()));
    }

    /**
     * Arma el formulario: los campos de la sección y los botones de guardar y ver el sitio.
     *
     * @param  Schema  $schema  Esquema vacío que entrega Filament.
     * @return Schema El formulario.
     */
    public function form(Schema $schema): Schema
    {
        // Formulario con los campos de la sección y su pie de botones.
        return $schema
            ->components([
                Form::make($this->fields())
                    // Al enviarlo (botón o Ctrl+S) llama a save().
                    ->livewireSubmitHandler('save')
                    // Botones al pie.
                    ->footer([
                        Actions::make([
                            // Guarda los cambios.
                            Action::make('save')
                                ->label('Guardar')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                            // Abre la página pública para ver cómo quedó.
                            Action::make('verSitio')
                                ->label('Ver en el sitio')
                                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                ->color('gray')
                                ->url(route('inicio'))
                                ->openUrlInNewTab(),
                        ]),
                    ]),
            ])
            // El estado del formulario vive en $data.
            ->statePath('data');
    }

    /**
     * Guarda el contenido de la sección.
     *
     * Precondiciones:
     * - El formulario tiene los datos que cargó el usuario.
     *
     * Postcondiciones:
     * - Si los datos no son válidos, muestra los errores y no guarda nada.
     * - Si son válidos, la sección queda con ese contenido (se crea su fila si no existía) y la
     *   página pública lo muestra desde el próximo pedido.
     * - Muestra el aviso "Cambios guardados".
     */
    public function save(): void
    {
        // Valida y devuelve el estado (con las transformaciones de cada campo).
        $data = $this->prepareForSaving($this->form->getState());

        // Una fila por sección: la crea o la reemplaza.
        SiteContent::query()->updateOrCreate(['section' => static::section()], ['data' => $data]);

        // Confirmación.
        Notification::make()
            ->success()
            ->title('Cambios guardados')
            ->body('Ya se ven en el sitio.')
            ->send();
    }
}
