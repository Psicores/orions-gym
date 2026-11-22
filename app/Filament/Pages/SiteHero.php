<?php

namespace App\Filament\Pages;

use App\Enums\SiteSectionEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

/**
 * Pantalla "Portada" del panel: el video de fondo, los textos de bienvenida y cómo aparece el sitio
 * en la pestaña del navegador y en Google.
 */
class SiteHero extends SiteSectionPage
{
    // URL en castellano: /admin/portada.
    protected static ?string $slug = 'portada';

    /**
     * Sección que edita: portada.
     *
     * @return SiteSectionEnum La sección.
     */
    public static function section(): SiteSectionEnum
    {
        // Lo primero que se ve al entrar.
        return SiteSectionEnum::Portada;
    }

    /**
     * Campos: textos de bienvenida, fondo y datos para buscadores.
     *
     * Precondiciones:
     * - El disco "public" está configurado y enlazado (php artisan storage:link).
     *
     * Postcondiciones:
     * - Todo es opcional; lo que queda vacío no se muestra. La portada (con el menú y el botón
     *   "Ver planes") aparece siempre.
     * - El fondo es un MP4 de hasta 12 MB; la imagen fija (JPG, PNG o WebP) se ve mientras carga
     *   el video, o de fondo si no hay video. Se achica a 1920 px antes de subir.
     * - "titulo_pagina" va a la pestaña del navegador y "descripcion" al resultado de Google; sin
     *   ellos se usa el nombre de la aplicación.
     *
     * @return array<Component> Campos del formulario.
     */
    protected function fields(): array
    {
        // Tres bloques: textos, fondo y buscadores.
        return [
            Section::make('Textos de bienvenida')
                ->schema([
                    // Rótulo chico arriba del título.
                    TextInput::make('etiqueta')
                        ->label('Rótulo')
                        ->placeholder('Ej.: Gimnasio · Fuerza · Comunidad')
                        ->maxLength(60)
                        ->columnSpanFull(),
                    // Título grande.
                    TextInput::make('titulo')
                        ->label('Título')
                        ->placeholder('Ej.: Fierros')
                        ->maxLength(40),
                    // Continuación del título, en verde.
                    TextInput::make('titulo_destacado')
                        ->label('Continuación resaltada')
                        ->placeholder('Ej.: y constancia')
                        ->maxLength(40),
                    // Texto debajo del título.
                    Textarea::make('texto')
                        ->label('Texto')
                        ->rows(2)
                        ->maxLength(200)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Fondo')
                ->description('Se oscurece automáticamente para que el texto se lea.')
                ->schema([
                    // Video de fondo.
                    $this->videoUpload('video_path', 'Video de fondo (MP4)'),
                    // Imagen fija.
                    $this->imageUpload('poster_path', 'Imagen de fondo', 1920)
                        ->helperText('Se ve mientras carga el video. Sin video, queda como fondo.'),
                ])
                ->columns(2),
            Section::make('Pestaña del navegador y Google')
                ->schema([
                    // Título de la pestaña y del resultado de búsqueda.
                    TextInput::make('titulo_pagina')
                        ->label('Título de la página')
                        ->placeholder('Ej.: Orions · Gimnasio')
                        ->helperText('Aparece en la pestaña del navegador y como título en Google.')
                        ->maxLength(60),
                    // Descripción del resultado de búsqueda.
                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->helperText('El texto que muestra Google debajo del título. Hasta 160 caracteres.')
                        ->maxLength(160),
                ]),
        ];
    }

    /**
     * Limpia el contenido antes de guardarlo.
     *
     * Precondiciones:
     * - $data es el estado validado del formulario.
     *
     * Postcondiciones:
     * - Los textos y rutas quedan sin espacios en las puntas, y null si están vacíos.
     *
     * @param  array<string, mixed>  $data  Estado del formulario.
     * @return array<string, ?string> Contenido a guardar.
     */
    protected function prepareForSaving(array $data): array
    {
        // Cada clave de la portada, limpia.
        return array_map(
            fn (mixed $value): ?string => static::cleanText($value),
            array_intersect_key($data, SiteSectionEnum::Portada->emptyContent()),
        );
    }
}
