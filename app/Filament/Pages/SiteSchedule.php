<?php

namespace App\Filament\Pages;

use App\Enums\SiteSectionEnum;
use App\Models\SiteContent;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

/**
 * Pantalla "Horarios y ubicación" del panel: los días y horas de apertura, la dirección y el mapa
 * que muestra la página pública en "Dónde y cuándo".
 */
class SiteSchedule extends SiteSectionPage
{
    // URL en castellano: /admin/horarios-y-ubicacion.
    protected static ?string $slug = 'horarios-y-ubicacion';

    /**
     * Sección que edita: horarios y ubicación.
     *
     * @return SiteSectionEnum La sección.
     */
    public static function section(): SiteSectionEnum
    {
        // Horarios, dirección y mapa.
        return SiteSectionEnum::Horarios;
    }

    /**
     * Campos: las franjas horarias, la dirección y el mapa.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - "horarios": lista ordenable de franjas, cada una con días y horas (los dos obligatorios).
     * - "direccion": texto opcional.
     * - "mapa_url": opcional; acepta el código "Insertar un mapa" de Google Maps entero o solo su
     *   dirección, y guarda solo la dirección. Rechaza cualquier cosa que no sea un mapa de Google.
     * - Todo lo que se deja vacío no se muestra en el sitio.
     *
     * @return array<Component> Campos del formulario.
     */
    protected function fields(): array
    {
        // Dos bloques: horarios, y ubicación con su mapa.
        return [
            Section::make('Horarios')
                ->description('Sin franjas cargadas, el sitio no muestra horarios.')
                ->schema([
                    // Franjas: "Lunes a viernes" → "07:00 a 22:00".
                    Repeater::make('horarios')
                        ->hiddenLabel()
                        // Días y horas en la misma línea.
                        ->schema([
                            // Días de la franja.
                            TextInput::make('dias')
                                ->label('Días')
                                ->placeholder('Ej.: Lunes a viernes')
                                ->required()
                                ->maxLength(60),
                            // Horario de la franja.
                            TextInput::make('horas')
                                ->label('Horario')
                                ->placeholder('Ej.: 07:00 a 22:00 o Cerrado')
                                ->required()
                                ->maxLength(60),
                        ])
                        ->columns(2)
                        // Empieza sin filas: se agregan las que hagan falta.
                        ->defaultItems(0)
                        ->addActionLabel('Agregar franja horaria')
                        // Se pueden reordenar arrastrando.
                        ->reorderable()
                        // Cada fila se titula con sus días.
                        ->itemLabel(fn (array $state): ?string => $state['dias'] ?? null)
                        ->maxItems(10),
                ]),
            Section::make('Ubicación')
                ->description('Sin dirección ni mapa, el sitio no muestra la ubicación.')
                ->schema([
                    // Dirección tal como se lee en el sitio.
                    TextInput::make('direccion')
                        ->label('Dirección')
                        ->placeholder('Ej.: San Martín 450, Trelew')
                        ->maxLength(160),
                    // Mapa insertado de Google Maps.
                    Textarea::make('mapa_url')
                        ->label('Mapa')
                        ->rows(3)
                        ->placeholder('<iframe src="https://www.google.com/maps/embed?pb=..." ...></iframe>')
                        ->helperText('En Google Maps buscá el gimnasio, tocá Compartir → Insertar un mapa → Copiar HTML y pegalo acá.')
                        // Guarda solo la dirección del mapa, aunque se haya pegado el código entero.
                        ->dehydrateStateUsing(fn (?string $state): ?string => SiteContent::extractMapUrl($state))
                        // Tiene que ser un mapa de Google.
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            // Vacío está permitido; si hay algo, tiene que ser un mapa insertado de Google.
                            if (filled($value) && ! SiteContent::isAllowedMapUrl(SiteContent::extractMapUrl((string) $value))) {
                                // Explica qué pegar.
                                $fail('Pegá el código de "Insertar un mapa" de Google Maps (empieza con <iframe src="https://www.google.com/maps/embed...).');
                            }
                        }),
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
     * - "horarios" queda como lista simple (sin las claves internas del repetidor), con los textos
     *   sin espacios en las puntas.
     * - "direccion" vacía queda null.
     *
     * @param  array<string, mixed>  $data  Estado del formulario.
     * @return array{horarios: list<array{dias: string, horas: string}>, direccion: ?string, mapa_url: ?string}
     */
    protected function prepareForSaving(array $data): array
    {
        // Contenido listo para guardar.
        return [
            // Franjas en orden, con los textos recortados.
            'horarios' => array_values(array_map(fn (array $franja): array => [
                'dias' => trim((string) $franja['dias']),
                'horas' => trim((string) $franja['horas']),
            ], $data['horarios'] ?? [])),
            // Dirección o null.
            'direccion' => filled($data['direccion'] ?? null) ? trim((string) $data['direccion']) : null,
            // Mapa ya reducido a su dirección (o null).
            'mapa_url' => $data['mapa_url'] ?? null,
        ];
    }
}
