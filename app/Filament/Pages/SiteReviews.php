<?php

namespace App\Filament\Pages;

use App\Enums\SiteSectionEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

/**
 * Pantalla "Opiniones" del panel: las reseñas de socios que la página pública muestra en la cinta
 * "Lo que dicen los socios". Se cargan a mano.
 */
class SiteReviews extends SiteSectionPage
{
    // URL en castellano: /admin/opiniones.
    protected static ?string $slug = 'opiniones';

    /** Cantidad máxima de opiniones: la cinta se vuelve larga y repetitiva con más. */
    public const int MAX_REVIEWS = 20;

    /**
     * Sección que edita: opiniones.
     *
     * @return SiteSectionEnum La sección.
     */
    public static function section(): SiteSectionEnum
    {
        // Reseñas de socios.
        return SiteSectionEnum::Opiniones;
    }

    /**
     * Campos: la lista de opiniones.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - "opiniones": lista ordenable de hasta MAX_REVIEWS, cada una con quién la escribió, la
     *   cantidad de estrellas (1 a 5, 5 al agregarla) y el texto; los tres obligatorios.
     * - Sin opiniones, el sitio no muestra la sección.
     *
     * @return array<Component> Campos del formulario.
     */
    protected function fields(): array
    {
        // Un solo bloque con la lista.
        return [
            Section::make('Opiniones de socios')
                ->description('Se muestran en el inicio, en el orden de esta lista. Sin opiniones cargadas, el sitio no muestra la sección.')
                ->schema([
                    Repeater::make('opiniones')
                        ->hiddenLabel()
                        ->schema([
                            // Quién la escribió.
                            TextInput::make('autor')
                                ->label('Nombre')
                                ->placeholder('Ej.: Lucía F.')
                                ->required()
                                ->maxLength(60),
                            // Puntaje.
                            ToggleButtons::make('estrellas')
                                ->label('Estrellas')
                                ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                                ->default(5)
                                ->inline()
                                ->required(),
                            // La reseña.
                            Textarea::make('texto')
                                ->label('Opinión')
                                ->rows(3)
                                ->required()
                                ->maxLength(300)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        // Empieza sin filas.
                        ->defaultItems(0)
                        ->addActionLabel('Agregar opinión')
                        // Se reordenan arrastrando y se pueden plegar para ver la lista entera.
                        ->reorderable()
                        ->collapsible()
                        // Cada fila se titula con el nombre.
                        ->itemLabel(fn (array $state): ?string => $state['autor'] ?? null)
                        ->maxItems(self::MAX_REVIEWS),
                ]),
        ];
    }

    /**
     * Limpia las opiniones antes de guardarlas.
     *
     * Precondiciones:
     * - $data es el estado validado del formulario.
     *
     * Postcondiciones:
     * - "opiniones" queda como lista simple en el orden de la pantalla, con los textos sin
     *   espacios en las puntas y las estrellas como número entre 1 y 5.
     *
     * @param  array<string, mixed>  $data  Estado del formulario.
     * @return array{opiniones: list<array{autor: string, texto: string, estrellas: int}>}
     */
    protected function prepareForSaving(array $data): array
    {
        // Contenido listo para guardar.
        return [
            'opiniones' => array_values(array_map(fn (array $opinion): array => [
                // Nombre recortado.
                'autor' => trim((string) $opinion['autor']),
                // Reseña recortada.
                'texto' => trim((string) $opinion['texto']),
                // Puntaje dentro de 1 a 5.
                'estrellas' => max(1, min(5, (int) $opinion['estrellas'])),
            ], $data['opiniones'] ?? [])),
        ];
    }
}
