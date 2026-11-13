<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * Página única de anuncios: el listado, con el alta y la edición en ventanas modales.
 */
class ManageAnnouncements extends ManageRecords
{
    protected static string $resource = AnnouncementResource::class;

    /**
     * Botones del encabezado de la página.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Ofrece "Crear anuncio", que abre el formulario en un modal con la cabecera en el color
     *   principal, sin el botón "Crear y crear otro".
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        // Única acción: crear.
        return [
            // Alta en una ventana modal.
            CreateAction::make()
                // Ícono del botón.
                ->icon(Heroicon::OutlinedPlus)
                // Sin "Crear y crear otro".
                ->createAnother(false)
                // Ícono que acompaña al título del modal.
                ->modalIcon(Heroicon::OutlinedMegaphone)
                // Explica qué se puede cargar.
                ->modalDescription('Puede llevar texto, imagen o las dos cosas. Los socios lo ven mientras esté activo y dentro de sus fechas.')
                // Modal ancho: tiene editor de texto e imagen.
                ->modalWidth(Width::ThreeExtraLarge)
                // Botones del pie alineados a la derecha.
                ->modalFooterActionsAlignment(Alignment::End)
                // Cabecera en el color principal (ver resources/views/filament/admin/styles.blade.php).
                ->extraModalWindowAttributes(['class' => 'orions-modal']),
        ];
    }
}
