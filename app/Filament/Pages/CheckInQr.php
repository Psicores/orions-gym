<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use BackedEnum;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use GdImage;
use Illuminate\Support\Str;

/**
 * Pantalla "QR de ingreso" del panel: muestra el código QR de recepción listo para imprimir.
 *
 * El QR lleva el texto de config('gym.checkin_token') y la calavera del gimnasio en el centro. Es el que los socios escanean desde su
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

    // Lado, en módulos del QR, del hueco central donde va la calavera (impar, para que quede centrado).
    private const int LOGO_MODULES = 9;

    // Píxeles por módulo: un QR grande para que se imprima nítido.
    private const int MODULE_PIXELS = 24;

    /**
     * Datos que recibe la vista: el QR dibujado y el texto que lleva adentro.
     *
     * Precondiciones:
     * - Existe public/images/icons/logo-qr.png (la calavera, negra sobre blanco).
     *
     * Postcondiciones:
     * - Con un código usable (ver Attendance::receptionToken()): "qr" es la imagen PNG del
     *   código, con la calavera en el centro, como data URI lista para un <img>, y "token" el
     *   texto que contiene; "sugerencia" es null.
     * - Sin código usable: "qr" y "token" son null y "sugerencia" trae un código al azar de
     *   32 caracteres para copiar al .env (cambia en cada visita y no se guarda).
     * - No guarda nada: el QR se dibuja en cada visita a partir de la configuración.
     *
     * @return array{qr: ?string, token: ?string, sugerencia: ?string}
     */
    protected function getViewData(): array
    {
        // Texto que va dentro del QR (null si falta configurarlo).
        $token = Attendance::receptionToken();

        // Sin código usable no hay QR para imprimir: la pantalla explica cómo configurarlo.
        if ($token === null) {
            // Propone un código al azar listo para copiar.
            return ['qr' => null, 'token' => null, 'sugerencia' => Str::random(32)];
        }

        // Devuelve la imagen y el texto.
        return [
            // No hace falta sugerir nada.
            'sugerencia' => null,
            // QR con la calavera, como imagen PNG (se ve igual en pantalla y al imprimir).
            'qr' => 'data:image/png;base64,'.base64_encode($this->renderQrWithLogo($token)),
            // Texto del código, para mostrarlo debajo.
            'token' => $token,
        ];
    }

    /**
     * Dibuja el QR con la calavera del gimnasio en el centro.
     *
     * Precondiciones:
     * - $token no está vacío.
     * - Existe public/images/icons/logo-qr.png.
     *
     * Postcondiciones:
     * - Devuelve los bytes de un PNG: el QR negro sobre blanco con un hueco central de
     *   LOGO_MODULES módulos de lado y la calavera adentro.
     * - El QR usa la corrección de errores más alta (30 %), así se sigue leyendo aunque el
     *   centro esté tapado por el logo.
     * - No escribe ningún archivo.
     *
     * @param  string  $token  Texto que lleva el QR.
     * @return string Contenido binario del PNG.
     */
    private function renderQrWithLogo(string $token): string
    {
        // Configuración del QR.
        $options = new QROptions([
            // Salida como imagen PNG hecha con GD.
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            // Devuelve la imagen en memoria (no el PNG ya armado) para poder pegarle el logo.
            'returnResource' => true,
            // Máxima tolerancia a errores: hace falta porque el logo tapa el centro.
            'eccLevel' => EccLevel::H,
            // Tamaño de cada módulo en píxeles.
            'scale' => self::MODULE_PIXELS,
            // Deja libre un cuadrado en el centro para el logo.
            'addLogoSpace' => true,
            'logoSpaceWidth' => self::LOGO_MODULES,
            'logoSpaceHeight' => self::LOGO_MODULES,
        ]);

        /** @var GdImage $qr Imagen del QR con el hueco central en blanco. */
        $qr = (new QRCode($options))->render($token);
        // Calavera negra sobre blanco.
        $logo = imagecreatefrompng(public_path('images/icons/logo-qr.png'));

        // Lado del hueco en píxeles, con un margen de medio módulo por lado para que el logo no toque el código.
        $space = (self::LOGO_MODULES - 1) * self::MODULE_PIXELS;
        // Escala para que el logo entre completo en el hueco sin deformarse.
        $ratio = min($space / imagesx($logo), $space / imagesy($logo));
        // Tamaño final del logo.
        $width = (int) round(imagesx($logo) * $ratio);
        $height = (int) round(imagesy($logo) * $ratio);

        // Pega el logo centrado sobre el QR.
        imagecopyresampled(
            $qr,
            $logo,
            intdiv(imagesx($qr) - $width, 2),
            intdiv(imagesy($qr) - $height, 2),
            0,
            0,
            $width,
            $height,
            imagesx($logo),
            imagesy($logo),
        );

        // Captura el PNG en memoria en lugar de escribirlo a disco.
        ob_start();
        imagepng($qr);

        // Devuelve los bytes del PNG.
        return (string) ob_get_clean();
    }
}
