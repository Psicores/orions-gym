<?php

namespace App\Filament\Pages;

use App\Enums\SiteSectionEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

/**
 * Pantalla "Comunidad" del panel: el título, el texto, el video y las fotos de la sección
 * Comunidad de la página pública.
 */
class SiteCommunity extends SiteSectionPage
{
    // URL en castellano: /admin/comunidad.
    protected static ?string $slug = 'comunidad';

    /** Carpeta del disco "public" donde se guardan los archivos de la sección. */
    public const string DIRECTORY = 'site/comunidad';

    /** Peso máximo del video en KB: el tope de subida de Livewire (12 MB). */
    public const int MAX_VIDEO_KB = 12288;

    /**
     * Sección que edita: comunidad.
     *
     * @return SiteSectionEnum La sección.
     */
    public static function section(): SiteSectionEnum
    {
        // Textos, video y fotos de Comunidad.
        return SiteSectionEnum::Comunidad;
    }

    /**
     * Campos: textos, video con su imagen fija y frase, y hasta dos fotos.
     *
     * Precondiciones:
     * - El disco "public" está configurado y enlazado (php artisan storage:link).
     *
     * Postcondiciones:
     * - Todo es opcional; lo que queda vacío no se muestra, y sin nada la sección no aparece.
     * - El video es un MP4 de hasta MAX_VIDEO_KB; su imagen fija y las fotos son JPG, PNG o WebP,
     *   y se achican en el navegador a 1600 px de ancho antes de subirse.
     * - Cada foto necesita una descripción (la leen los lectores de pantalla).
     *
     * @return array<Component> Campos del formulario.
     */
    protected function fields(): array
    {
        // Tres bloques: textos, video y fotos.
        return [
            Section::make('Textos')
                ->schema([
                    // Rótulo chico arriba del título.
                    TextInput::make('etiqueta')
                        ->label('Rótulo')
                        ->placeholder('Ej.: La comunidad')
                        ->maxLength(40),
                    // Título grande.
                    TextInput::make('titulo')
                        ->label('Título')
                        ->placeholder('Ej.: Nadie entrena solo')
                        ->maxLength(60),
                    // Párrafo al costado del título.
                    Textarea::make('texto')
                        ->label('Texto')
                        ->rows(4)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Video')
                ->description('Se reproduce sin sonido y en bucle. Conviene que pese poco: un MP4 comprimido de 1280 px de ancho.')
                ->schema([
                    // El video.
                    $this->mediaUpload('video_path', 'Video (MP4)')
                        ->acceptedFileTypes(['video/mp4'])
                        ->maxSize(self::MAX_VIDEO_KB)
                        ->helperText('Hasta 12 MB.'),
                    // Imagen fija mientras carga el video.
                    $this->imageUpload('poster_path', 'Imagen mientras carga')
                        ->helperText('Opcional. Un cuadro del video, para que no se vea negro mientras carga.'),
                    // Frase sobre el video.
                    TextInput::make('frase')
                        ->label('Frase sobre el video')
                        ->placeholder('Ej.: Venís por el físico.')
                        ->maxLength(80),
                    // Continuación resaltada en verde.
                    TextInput::make('frase_destacada')
                        ->label('Continuación resaltada')
                        ->placeholder('Ej.: Te quedás por la gente.')
                        ->maxLength(80),
                ])
                ->columns(2),
            Section::make('Fotos')
                ->description('Hasta dos, debajo del video: la primera ocupa el doble de ancho.')
                ->schema([
                    Repeater::make('fotos')
                        ->hiddenLabel()
                        ->schema([
                            // La foto.
                            $this->imageUpload('path', 'Foto')->required(),
                            // Descripción para lectores de pantalla.
                            TextInput::make('alt')
                                ->label('Descripción')
                                ->placeholder('Ej.: Barra olímpica cargada sobre el piso')
                                ->helperText('Qué se ve en la foto (la usan los lectores de pantalla).')
                                ->required()
                                ->maxLength(120),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Agregar foto')
                        ->reorderable()
                        ->maxItems(2),
                ]),
        ];
    }

    /**
     * Campo de subida de un archivo de la sección (video o imagen).
     *
     * @param  string  $name  Clave del contenido donde se guarda la ruta.
     * @param  string  $label  Texto sobre el campo.
     * @return FileUpload El campo, en el disco "public", carpeta DIRECTORY.
     */
    private function mediaUpload(string $name, string $label): FileUpload
    {
        // Archivo público: lo ve cualquiera que abra el sitio.
        return FileUpload::make($name)
            ->label($label)
            ->disk('public')
            ->directory(self::DIRECTORY)
            ->visibility('public');
    }

    /**
     * Campo de subida de una imagen de la sección.
     *
     * @param  string  $name  Clave del contenido donde se guarda la ruta.
     * @param  string  $label  Texto sobre el campo.
     * @return FileUpload El campo: JPG, PNG o WebP, achicado a 1600 px de ancho, hasta 2 MB.
     */
    private function imageUpload(string $name, string $label): FileUpload
    {
        // Imagen común (sin SVG), achicada en el navegador antes de subir.
        return $this->mediaUpload($name, $label)
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->automaticallyResizeImagesToWidth('1600')
            ->maxSize(2048);
    }

    /**
     * Limpia el contenido antes de guardarlo.
     *
     * Precondiciones:
     * - $data es el estado validado del formulario.
     *
     * Postcondiciones:
     * - Los textos quedan sin espacios en las puntas, y null si están vacíos.
     * - "fotos" queda como lista simple en el orden de la pantalla.
     *
     * @param  array<string, mixed>  $data  Estado del formulario.
     * @return array<string, mixed> Contenido a guardar.
     */
    protected function prepareForSaving(array $data): array
    {
        // Texto recortado o null.
        $text = fn (mixed $value): ?string => filled($value) ? trim((string) $value) : null;

        // Contenido listo para guardar.
        return [
            'etiqueta' => $text($data['etiqueta'] ?? null),
            'titulo' => $text($data['titulo'] ?? null),
            'texto' => $text($data['texto'] ?? null),
            'video_path' => $text($data['video_path'] ?? null),
            'poster_path' => $text($data['poster_path'] ?? null),
            'frase' => $text($data['frase'] ?? null),
            'frase_destacada' => $text($data['frase_destacada'] ?? null),
            'fotos' => array_values(array_map(fn (array $foto): array => [
                'path' => (string) $foto['path'],
                'alt' => trim((string) $foto['alt']),
            ], $data['fotos'] ?? [])),
        ];
    }
}
