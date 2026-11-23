<?php

namespace App\Filament\Pages;

use App\Models\AccessPoint;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Filament\Pages\Page;
use GdImage;
use Livewire\Attributes\Url;

/**
 * Cartel imprimible con el QR de un punto de acceso.
 *
 * El QR lleva la dirección /ingreso/{token} del punto (ver AccessPoint::checkInUrl()) y la calavera
 * del gimnasio en el centro. El socio lo abre con la cámara del celular, o con "Escanear QR" del
 * portal. Se llega desde la lista de puntos de acceso (QR de ingreso).
 */
class CheckInQr extends Page
{
    protected string $view = 'filament.pages.check-in-qr';

    // URL en castellano: /admin/qr-de-ingreso/imprimir.
    protected static ?string $slug = 'qr-de-ingreso/imprimir';

    protected static ?string $title = 'Imprimir QR de ingreso';

    // No va en el menú: se abre desde la lista de puntos de acceso.
    protected static bool $shouldRegisterNavigation = false;

    // Lado, en módulos del QR, del hueco central donde va la calavera (impar, para que quede centrado).
    private const int LOGO_MODULES = 9;

    // Píxeles por módulo: un QR grande para que se imprima nítido.
    private const int MODULE_PIXELS = 24;

    // Punto de acceso a imprimir (viene en la dirección: ?punto=1).
    #[Url]
    public ?int $punto = null;

    /**
     * Datos que recibe la vista: el punto de acceso y su QR dibujado.
     *
     * Precondiciones:
     * - Existe public/images/icons/logo-qr.png (la calavera, negra sobre blanco).
     *
     * Postcondiciones:
     * - Usa el punto activo pedido en ?punto=; si no existe o está apagado, el primer punto
     *   activo. Sin ninguno activo, "accessPoint", "qr" y "url" son null.
     * - "qr" es la imagen PNG del QR con la calavera, como data URI lista para un <img>, y "url"
     *   la dirección que lleva adentro.
     * - No guarda nada: el QR se dibuja en cada visita.
     *
     * @return array{accessPoint: ?AccessPoint, qr: ?string, url: ?string}
     */
    protected function getViewData(): array
    {
        // Punto pedido, si está activo; si no, el primero activo.
        $accessPoint = AccessPoint::query()->where('activo', true)->find($this->punto)
            ?? AccessPoint::query()->where('activo', true)->orderBy('id')->first();

        // Sin puntos activos no hay QR que imprimir.
        if ($accessPoint === null) {
            return ['accessPoint' => null, 'qr' => null, 'url' => null];
        }

        // Dirección que lleva el QR.
        $url = $accessPoint->checkInUrl();

        // Punto, imagen y dirección.
        return [
            'accessPoint' => $accessPoint,
            // QR con la calavera, como imagen PNG (se ve igual en pantalla y al imprimir).
            'qr' => 'data:image/png;base64,'.base64_encode($this->renderQrWithLogo($url)),
            'url' => $url,
        ];
    }

    /**
     * Dibuja el QR con la calavera del gimnasio en el centro.
     *
     * Precondiciones:
     * - $content no está vacío.
     * - Existe public/images/icons/logo-qr.png.
     *
     * Postcondiciones:
     * - Devuelve los bytes de un PNG: el QR negro sobre blanco con un hueco central de
     *   LOGO_MODULES módulos de lado y la calavera adentro.
     * - El QR usa la corrección de errores más alta (30 %), así se sigue leyendo aunque el
     *   centro esté tapado por el logo.
     * - No escribe ningún archivo.
     *
     * @param  string  $content  Texto que lleva el QR.
     * @return string Contenido binario del PNG.
     */
    private function renderQrWithLogo(string $content): string
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
        $qr = (new QRCode($options))->render($content);
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
