<?php

namespace App\Filament\Pages;

use App\Enums\SiteSectionEnum;
use App\Filament\Concerns\AdminOnly;
use App\Models\SiteContent;
use App\Support\ImageOptimizer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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
 * El contenido se guarda en site_contents (ver App\Models\SiteContent). Solo las editan los
 * administradores: los profes no tocan la página pública.
 *
 * @property-read Schema $form
 */
abstract class SiteSectionPage extends Page
{
    // Solo para administradores.
    use AdminOnly;

    protected string $view = 'filament.pages.site-section';

    protected static string|UnitEnum|null $navigationGroup = SiteSectionEnum::NAVIGATION_GROUP;

    /** Peso máximo de un video, en KB: el tope de subida de Livewire (12 MB). */
    public const int MAX_VIDEO_KB = 12288;

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
        // Por ejemplo "Horarios y ubicación".
        return static::section()->getLabel();
    }

    /**
     * Ícono de la pantalla en el menú: el de la sección.
     *
     * @return string|BackedEnum|null Ícono.
     */
    public static function getNavigationIcon(): string|BackedEnum|null
    {
        // Uno distinto por sección.
        return static::section()->getIcon();
    }

    /**
     * Lugar en el menú: el que le corresponde a la sección dentro de "Admin del sitio".
     *
     * @return int|null Orden.
     */
    public static function getNavigationSort(): ?int
    {
        // Orden de la sección dentro del grupo.
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
     * Subtítulo de la pantalla: qué se cambia en esta sección.
     *
     * @return string Explicación corta.
     */
    public function getSubheading(): string
    {
        // Una oración por sección.
        return static::section()->getDescription();
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
     * Campo de subida de un archivo público de la sección (video o imagen).
     *
     * Precondiciones:
     * - El disco "public" está configurado y enlazado (php artisan storage:link).
     *
     * Postcondiciones:
     * - Devuelve el campo, que guarda en el disco "public", carpeta site/{sección}.
     *
     * @param  string  $name  Clave del contenido donde se guarda la ruta.
     * @param  string  $label  Texto sobre el campo.
     * @return FileUpload El campo.
     */
    protected function mediaUpload(string $name, string $label): FileUpload
    {
        // Archivo público: lo ve cualquiera que abra el sitio.
        return FileUpload::make($name)
            ->label($label)
            ->disk('public')
            ->directory('site/'.static::section()->value)
            ->visibility('public');
    }

    /**
     * Campo de subida de una imagen pública de la sección.
     *
     * Precondiciones:
     * - Las mismas que mediaUpload().
     *
     * Postcondiciones:
     * - Acepta JPG, PNG o WebP (nunca SVG, que puede traer código) de hasta 10 MB, achicados en
     *   el navegador a $width píxeles de ancho antes de subir (los 10 MB se miden sobre el
     *   original, antes de achicarlo, para que entre una foto sacada con el celular).
     *
     * @param  string  $name  Clave del contenido donde se guarda la ruta.
     * @param  string  $label  Texto sobre el campo.
     * @param  int  $width  Ancho máximo, en píxeles.
     * @return FileUpload El campo.
     */
    protected function imageUpload(string $name, string $label, int $width = 1600): FileUpload
    {
        // Imagen común, achicada antes de subir.
        return $this->mediaUpload($name, $label)
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            // Límite del original, antes de achicarlo.
            ->maxSize(10240)
            // Achicada en el navegador y liviana en el servidor (ver ImageOptimizer).
            ->when(true, fn (FileUpload $field) => ImageOptimizer::configure($field, $width));
    }

    /**
     * Campo de subida de un video de fondo de la sección.
     *
     * Precondiciones:
     * - Las mismas que mediaUpload().
     *
     * Postcondiciones:
     * - Acepta solo MP4 de hasta MAX_VIDEO_KB (el tope de subida de Livewire).
     *
     * @param  string  $name  Clave del contenido donde se guarda la ruta.
     * @param  string  $label  Texto sobre el campo.
     * @return FileUpload El campo.
     */
    protected function videoUpload(string $name, string $label): FileUpload
    {
        // MP4 liviano.
        return $this->mediaUpload($name, $label)
            ->acceptedFileTypes(['video/mp4'])
            ->maxSize(self::MAX_VIDEO_KB)
            ->helperText('MP4 de hasta 12 MB. Conviene comprimirlo a 1280 px de ancho y sin sonido.');
    }

    /**
     * Texto sin espacios en las puntas, o null si quedó vacío.
     *
     * @param  mixed  $value  Valor del formulario.
     * @return string|null Texto limpio, o null.
     */
    protected static function cleanText(mixed $value): ?string
    {
        // Vacío pasa a null; si no, se recorta.
        return filled($value) ? trim((string) $value) : null;
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
