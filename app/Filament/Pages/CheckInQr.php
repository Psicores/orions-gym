<?php

namespace App\Filament\Pages;

use BackedEnum;
use chillerlan\QRCode\QRCode;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Pantalla "QR de ingreso" del panel: muestra el código QR de recepción listo para imprimir.
 *
 * El QR lleva el texto de config('gym.checkin_token'). Es el que los socios escanean desde su
 * portal para registrar el ingreso (ver App\Livewire\MemberScan).
 */
class CheckInQr extends Page
{
    protected string $view = 'filament.pages.check-in-qr';

    // URL en castellano: /admin/qr-de-ingreso.
    protected static ?string $slug = 'qr-de-ingreso';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'QR de ingreso';

    protected static ?string $title = 'QR de ingreso';

    // En el menú va después de Clientes (que usa el orden 1).
    protected static ?int $navigationSort = 2;

    /**
     * Datos que recibe la vista: el QR dibujado y el texto que lleva adentro.
     *
     * Precondiciones:
     * - config('gym.checkin_token') tiene un texto no vacío.
     *
     * Postcondiciones:
     * - Devuelve "qr": la imagen SVG del código como data URI, lista para un <img>; y "token":
     *   el texto que contiene.
     * - No guarda nada: el QR se dibuja en cada visita a partir de la configuración.
     *
     * @return array{qr: string, token: string}
     */
    protected function getViewData(): array
    {
        // Texto que va dentro del QR.
        $token = (string) config('gym.checkin_token');

        // Devuelve la imagen y el texto.
        return [
            // QR en SVG (se imprime nítido a cualquier tamaño), como data URI.
            'qr' => (new QRCode)->render($token),
            // Texto del código, para mostrarlo debajo.
            'token' => $token,
        ];
    }
}
