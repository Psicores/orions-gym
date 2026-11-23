<?php

namespace App\Support;

use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use GdImage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Deja livianas las imágenes que se suben desde el panel.
 *
 * Trabaja en dos pasos: el navegador achica la imagen antes de subirla (rápido y ahorra datos), y
 * el servidor la vuelve a revisar al guardarla. Si quedó más grande que lo que se muestra o pesa
 * demasiado, la achica, la comprime en WebP y descarta la original. Así, aunque el navegador no
 * haya podido achicarla, en el disco nunca queda una foto pesada.
 *
 * Usa GD, la extensión de imágenes que trae PHP: no agrega dependencias.
 */
class ImageOptimizer
{
    /** Peso máximo, en bytes, de una imagen que se guarda tal como vino (300 KB). */
    public const int MAX_BYTES = 300 * 1024;

    /** Calidades WebP que prueba, de mejor a peor, hasta que la imagen pesa menos que MAX_BYTES. */
    private const array QUALITIES = [82, 72, 62];

    /** Píxeles máximos que intenta procesar (50 megapíxeles), para no agotar la memoria del servidor. */
    private const int MAX_PIXELS = 50_000_000;

    /**
     * Configura un campo de subida para que la imagen se achique en el navegador y se comprima
     * en el servidor.
     *
     * Precondiciones:
     * - $field es un campo de subida de imágenes (o de imágenes y PDF: los PDF no se tocan).
     *
     * Postcondiciones:
     * - El navegador achica la imagen para que entre en $maxWidth × $maxHeight (sin recortar ni
     *   agrandar) antes de subirla.
     * - Al guardar, el servidor la optimiza con optimize(); si no hace falta, la guarda tal cual.
     * - Devuelve el mismo campo, para seguir encadenando métodos.
     *
     * @param  FileUpload  $field  Campo a configurar.
     * @param  int  $maxWidth  Ancho máximo, en píxeles.
     * @param  int|null  $maxHeight  Alto máximo, en píxeles (null: sin límite de alto).
     * @return FileUpload El campo configurado.
     */
    public static function configure(FileUpload $field, int $maxWidth, ?int $maxHeight = null): FileUpload
    {
        // Achicado en el navegador, antes de subir.
        $field
            // Entra completa en el recuadro, sin recortarse.
            ->automaticallyResizeImagesMode('contain')
            // Ancho máximo.
            ->automaticallyResizeImagesToWidth((string) $maxWidth)
            // Alto máximo (null: solo limita el ancho).
            ->automaticallyResizeImagesToHeight($maxHeight !== null ? (string) $maxHeight : null)
            // Una imagen chica no se agranda.
            ->automaticallyUpscaleImagesWhenResizing(false);

        // Revisión en el servidor al guardar.
        return $field->saveUploadedFileUsing(
            fn (BaseFileUpload $component, TemporaryUploadedFile $file): ?string => self::store($component, $file, $maxWidth, $maxHeight),
        );
    }

    /**
     * Guarda un archivo subido, optimizado si es una imagen que lo necesita.
     *
     * Precondiciones:
     * - $file es el archivo temporal que subió Livewire para $component.
     *
     * Postcondiciones:
     * - Si optimize() devuelve una versión nueva: la guarda en la carpeta y el disco del campo,
     *   con extensión .webp (o la que corresponda), borra el temporal original y devuelve la ruta.
     * - Si no (no es imagen, ya es liviana o no se puede procesar): la guarda como Filament,
     *   sin cambios.
     *
     * @param  BaseFileUpload  $component  Campo de subida.
     * @param  TemporaryUploadedFile  $file  Archivo subido.
     * @param  int  $maxWidth  Ancho máximo, en píxeles.
     * @param  int|null  $maxHeight  Alto máximo, en píxeles.
     * @return string|null Ruta guardada dentro del disco del campo.
     */
    public static function store(BaseFileUpload $component, TemporaryUploadedFile $file, int $maxWidth, ?int $maxHeight): ?string
    {
        // Contenido del archivo subido (null si no se puede leer).
        $contents = rescue(fn (): ?string => $file->get(), report: false);

        // Versión optimizada, o null si no hace falta o no se puede.
        $optimized = is_string($contents) ? self::optimize($contents, $maxWidth, $maxHeight) : null;

        // Sin versión nueva, la guarda como siempre.
        if ($optimized === null) {
            return $component->saveUploadedFile($file);
        }

        // Mismo nombre al azar que usaría Filament, con la extensión nueva.
        $path = trim($component->getDirectory().'/'.pathinfo($file->hashName(), PATHINFO_FILENAME).'.'.$optimized['extension'], '/');

        // Guarda la versión optimizada con la visibilidad del campo.
        $component->getDisk()->put($path, $optimized['contents'], $component->getVisibility() === 'public' ? ['visibility' => 'public'] : []);

        // Descarta la original.
        rescue(fn () => $file->delete(), report: false);

        // Ruta que queda en la base.
        return $path;
    }

    /**
     * Achica y comprime una imagen si es más grande o más pesada de lo necesario.
     *
     * Precondiciones:
     * - Ninguna: acepta cualquier contenido.
     *
     * Postcondiciones:
     * - Si no es una imagen que GD pueda leer (por ejemplo un PDF), o tiene más de 50
     *   megapíxeles, devuelve null.
     * - Si ya entra en $maxWidth × $maxHeight y pesa hasta MAX_BYTES, devuelve null: queda igual.
     * - Si no, devuelve la imagen derecha (según la orientación de la cámara), achicada para
     *   entrar en el recuadro sin agrandarse, en WebP con la mejor calidad que pese hasta
     *   MAX_BYTES (o la más baja que prueba). Mantiene la transparencia.
     * - Sin soporte de WebP, usa JPEG (o PNG si tiene transparencia).
     * - Si la versión nueva no pesa menos y no hubo que achicarla, devuelve null.
     * - No modifica nada.
     *
     * @param  string  $contents  Bytes del archivo.
     * @param  int  $maxWidth  Ancho máximo, en píxeles.
     * @param  int|null  $maxHeight  Alto máximo, en píxeles (null: sin límite).
     * @return array{contents: string, extension: string}|null La imagen nueva, o null.
     */
    public static function optimize(string $contents, int $maxWidth, ?int $maxHeight = null): ?array
    {
        // Sin GD no se puede procesar.
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        // Tamaño y tipo (false si no es una imagen).
        $info = @getimagesizefromstring($contents);

        // Solo JPEG, PNG y WebP; nada de SVG ni de otros formatos.
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }

        // Ancho y alto originales.
        [$width, $height] = $info;

        // Una imagen gigante podría agotar la memoria: se deja como está.
        if ($width * $height > self::MAX_PIXELS || ! self::hasMemoryFor($width * $height)) {
            return null;
        }

        // Giro que indica la cámara (las fotos de celular suelen venir "acostadas").
        $orientation = $info[2] === IMAGETYPE_JPEG ? self::exifOrientation($contents) : 1;
        // Con giro de 90° se intercambian ancho y alto.
        $turned = in_array($orientation, [5, 6, 7, 8], true);
        // Medidas tal como se ve la foto.
        [$shownWidth, $shownHeight] = $turned ? [$height, $width] : [$width, $height];

        // Escala para entrar en el recuadro, sin agrandar.
        $scale = min(1, $maxWidth / $shownWidth, $maxHeight !== null ? $maxHeight / $shownHeight : 1);
        // ¿Hay que achicarla?
        $mustResize = $scale < 1;

        // Si ya tiene buen tamaño, pesa poco y está derecha, queda igual.
        if (! $mustResize && strlen($contents) <= self::MAX_BYTES && $orientation === 1) {
            return null;
        }

        // Abre la imagen (false si está dañada).
        $source = @imagecreatefromstring($contents);

        // Dañada: se deja como está.
        if (! $source instanceof GdImage) {
            return null;
        }

        // La endereza según la cámara.
        $source = self::applyOrientation($source, $orientation);

        // Medidas finales.
        $targetWidth = max(1, (int) round($shownWidth * $scale));
        $targetHeight = max(1, (int) round($shownHeight * $scale));

        // Lienzo nuevo, con transparencia.
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));

        // Copia achicada con buena calidad.
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($source), imagesy($source));
        imagedestroy($source);

        // Codifica con la mejor calidad que entre en el peso.
        $encoded = self::encode($target, $info[2] === IMAGETYPE_PNG);
        imagedestroy($target);

        // Sin cambio de tamaño ni de orientación, solo vale la pena si pesa menos.
        if ($encoded === null || (! $mustResize && $orientation === 1 && strlen($encoded['contents']) >= strlen($contents))) {
            return null;
        }

        // Imagen nueva.
        return $encoded;
    }

    /**
     * Codifica la imagen en el formato más liviano disponible.
     *
     * @param  GdImage  $image  Imagen a codificar.
     * @param  bool  $mayHaveAlpha  Si puede tener transparencia (viene de un PNG).
     * @return array{contents: string, extension: string}|null Bytes y extensión, o null si falla.
     */
    private static function encode(GdImage $image, bool $mayHaveAlpha): ?array
    {
        // WebP: el más liviano, con transparencia.
        if (function_exists('imagewebp')) {
            // Última versión probada.
            $contents = null;

            // Baja la calidad hasta que entre en el peso.
            foreach (self::QUALITIES as $quality) {
                // Codifica en memoria.
                ob_start();
                imagewebp($image, null, $quality);
                $contents = (string) ob_get_clean();

                // Ya entra: listo.
                if (strlen($contents) <= self::MAX_BYTES) {
                    break;
                }
            }

            // La que haya quedado.
            return $contents !== '' ? ['contents' => $contents, 'extension' => 'webp'] : null;
        }

        // Sin WebP: PNG si puede tener transparencia, JPEG si no.
        ob_start();
        $mayHaveAlpha ? imagepng($image, null, 9) : imagejpeg($image, null, 82);
        $contents = (string) ob_get_clean();

        // Bytes y extensión.
        return $contents !== '' ? ['contents' => $contents, 'extension' => $mayHaveAlpha ? 'png' : 'jpg'] : null;
    }

    /**
     * Indica si queda memoria para abrir, girar y achicar una imagen de esos píxeles.
     *
     * GD usa unos 4 bytes por píxel; con la copia girada y la achicada puede necesitar el doble.
     *
     * @param  int  $pixels  Ancho × alto de la imagen.
     * @return bool true si alcanza (también si PHP no tiene límite de memoria).
     */
    private static function hasMemoryFor(int $pixels): bool
    {
        // Límite configurado ("128M", "1G", "-1"...).
        $limit = ini_get('memory_limit');

        // Sin límite, siempre alcanza.
        if ($limit === false || $limit === '' || (int) $limit === -1) {
            return true;
        }

        // Límite en bytes.
        $bytes = (int) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        // Hace falta lo que ya se usa más unas dos copias de la imagen, con margen.
        return memory_get_usage() + $pixels * 4 * 2.5 < $bytes;
    }

    /**
     * Lee la orientación EXIF de una foto JPEG.
     *
     * @param  string  $contents  Bytes de la foto.
     * @return int Orientación de 1 a 8 (1 = derecha, también si no se puede leer).
     */
    private static function exifOrientation(string $contents): int
    {
        // Sin la extensión exif no se puede leer: se asume derecha.
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        // Datos EXIF de la foto (false si no tiene).
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));

        // Orientación válida, o 1.
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        // Solo los valores que define el estándar.
        return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    /**
     * Gira o refleja la imagen según la orientación EXIF, para que quede derecha.
     *
     * @param  GdImage  $image  Imagen tal como vino de la cámara.
     * @param  int  $orientation  Orientación EXIF (1 a 8).
     * @return GdImage La imagen derecha.
     */
    private static function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        // Giro según el caso (imagerotate gira en sentido antihorario).
        $degrees = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };

        // Gira si hace falta (si el giro falla, sigue con la original).
        if ($degrees !== 0) {
            // Imagen girada.
            $rotated = imagerotate($image, $degrees, 0);
            // Solo la reemplaza si salió bien.
            $image = $rotated instanceof GdImage ? $rotated : $image;
        }

        // Después del giro, el reflejo horizontal en 2, 4, 5 y 7.
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        // Imagen derecha.
        return $image;
    }
}
