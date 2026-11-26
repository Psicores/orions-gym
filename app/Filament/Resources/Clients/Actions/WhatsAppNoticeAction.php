<?php

namespace App\Filament\Resources\Clients\Actions;

use App\Models\Client;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Acción "Avisar por WhatsApp": abre WhatsApp con el aviso de la cuota ya escrito para el socio
 * (recordatorio si todavía no venció, cuota vencida si ya pasó; ver Client::whatsAppNoticeMessage()).
 *
 * No usa la API oficial de WhatsApp: es un enlace wa.me que se abre en otra pestaña, en la app o
 * en WhatsApp Web de quien lo toca, y esa persona envía el mensaje desde su propio WhatsApp.
 *
 * Se monta sobre un Client (encabezado de la ficha y filas de "Para cobrar").
 */
class WhatsAppNoticeAction extends Action
{
    /**
     * Nombre interno de la acción (Filament lo usa para identificarla en la página).
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve "whatsAppNotice", así WhatsAppNoticeAction::make() no necesita nombre.
     *
     * @return string|null Nombre por defecto de la acción.
     */
    public static function getDefaultName(): ?string
    {
        // Nombre fijo de la acción.
        return 'whatsAppNotice';
    }

    /**
     * Configura el botón y su enlace.
     *
     * Precondiciones:
     * - La acción se monta sobre un registro Client.
     *
     * Postcondiciones:
     * - El botón abre, en otra pestaña, el enlace de Client::whatsAppNoticeUrl().
     * - Si el socio no aceptó avisos por WhatsApp o no tiene un celular válido, el botón queda
     *   deshabilitado y su aviso explica por qué (onlyWhenAvailable() lo oculta en su lugar).
     * - No guarda nada: el envío lo hace la persona desde WhatsApp.
     */
    protected function setUp(): void
    {
        // Configuración base de Filament.
        parent::setUp();

        // Texto del botón.
        $this->label('Avisar por WhatsApp')
            // Ícono de globo de chat.
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            // Gris: es un aviso, no compite con el cobro.
            ->color('gray')
            // Enlace wa.me con el mensaje (null si no se le puede escribir).
            ->url(fn (Client $record): ?string => $record->whatsAppNoticeUrl())
            // En otra pestaña, para no salir del panel.
            ->openUrlInNewTab()
            // Sin consentimiento o sin celular válido, el botón no funciona.
            ->disabled(fn (Client $record): bool => ! $record->canReceiveWhatsAppNotices())
            // Explica qué hace o por qué no está disponible.
            ->tooltip(fn (Client $record): string => self::getAvailabilityHint($record));
    }

    /**
     * Deja la acción visible solo cuando se le puede escribir al socio, en vez de deshabilitada.
     *
     * Precondiciones:
     * - La acción se monta sobre un registro Client.
     *
     * Postcondiciones:
     * - El botón no aparece para los socios que no aceptaron avisos o no tienen un celular
     *   válido. Sirve para las filas de una lista, donde un botón gris en cada fila estorba.
     *
     * @return static La misma acción, para seguir encadenando.
     */
    public function onlyWhenAvailable(): static
    {
        // Oculta el botón cuando no hay a quién escribir.
        return $this->visible(fn (Client $record): bool => $record->canReceiveWhatsAppNotices());
    }

    /**
     * Texto del aviso que aparece al pasar sobre el botón.
     *
     * Precondiciones:
     * - $record es un cliente existente.
     *
     * Postcondiciones:
     * - Si no aceptó avisos: lo dice y aclara que se cambia desde Editar o desde su portal.
     * - Si aceptó pero el celular no es válido: pide corregirlo desde Editar.
     * - Si se le puede escribir: explica que abre WhatsApp con el mensaje ya escrito.
     * - No modifica nada.
     *
     * @param  Client  $record  Socio al que se le escribe.
     * @return string Texto del aviso.
     */
    private static function getAvailabilityHint(Client $record): string
    {
        // Elige el texto según qué falta.
        return match (true) {
            // No dio su consentimiento.
            ! $record->acepta_avisos_whatsapp => 'No aceptó avisos por WhatsApp (se cambia en Editar o desde su portal).',
            // Aceptó, pero no hay a qué número escribirle.
            ! $record->hasValidCel() => 'El celular no es válido: corregilo en Editar.',
            // Todo en orden.
            default => 'Abre WhatsApp con el aviso de la cuota ya escrito.',
        };
    }
}
