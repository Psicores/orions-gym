<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Mueve las fotos y los certificados de salud ya subidos del disco public
 * (storage/app/public, accesible por URL) al disco privado local (storage/app/private).
 *
 * Las rutas guardadas en la base (foto_path, certificado_salud_path) no cambian: son
 * relativas al disco, así que el mismo valor vale en el disco nuevo.
 */
return new class extends Migration
{
    /**
     * Mueve los archivos de los clientes al disco privado.
     *
     * Precondiciones:
     * - Existe la tabla clients con foto_path y certificado_salud_path.
     * - Los discos public y local están configurados.
     *
     * Postcondiciones:
     * - Cada archivo referenciado por un cliente (incluidos los dados de baja) que estaba en
     *   public queda en local con la misma ruta, y ya no está en public.
     * - Si un archivo ya estaba en local, o no existe en public, no se toca.
     * - Correrla de nuevo no cambia nada.
     */
    public function up(): void
    {
        // Mueve de public a local.
        $this->moveClientFiles(from: 'public', to: 'local');
    }

    /**
     * Devuelve los archivos al disco public (deshace up()).
     *
     * Precondiciones:
     * - Las mismas que up().
     *
     * Postcondiciones:
     * - Cada archivo referenciado que estaba en local vuelve a public con la misma ruta.
     */
    public function down(): void
    {
        // Mueve de local a public.
        $this->moveClientFiles(from: 'local', to: 'public');
    }

    /**
     * Mueve entre discos todos los archivos referenciados por los clientes.
     *
     * Precondiciones:
     * - $from y $to son nombres de discos configurados.
     *
     * Postcondiciones:
     * - Cada ruta no vacía de foto_path o certificado_salud_path que existe en $from y no en $to
     *   queda copiada en $to y borrada de $from. El resto no se toca.
     */
    private function moveClientFiles(string $from, string $to): void
    {
        // Disco de origen.
        $source = Storage::disk($from);
        // Disco de destino.
        $target = Storage::disk($to);

        // Recorre los clientes de a tandas (con la tabla directa: no depende del modelo ni de la baja lógica).
        DB::table('clients')
            // Solo los que tienen algún archivo.
            ->where(fn ($query) => $query->whereNotNull('foto_path')->orWhereNotNull('certificado_salud_path'))
            // Orden estable para recorrer por tandas.
            ->orderBy('id')
            // Procesa de a 100 clientes.
            ->each(function (object $client) use ($source, $target, $from, $to): void {
                // Revisa las dos columnas de archivos.
                foreach ([$client->foto_path, $client->certificado_salud_path] as $path) {
                    // Saltea columnas vacías, archivos que no están en el origen o que ya están en el destino.
                    if (blank($path) || ! $source->exists($path) || $target->exists($path)) {
                        // Pasa al siguiente.
                        continue;
                    }

                    // Copia el archivo al destino con la misma ruta (por stream, sin cargarlo entero en memoria).
                    $copied = $target->writeStream($path, $source->readStream($path));

                    // Si la copia no quedó confirmada, conserva el original y avisa.
                    if (! $copied || ! $target->exists($path)) {
                        // Corta la migración sin perder el archivo.
                        throw new RuntimeException("No se pudo copiar {$path} del disco {$from} al disco {$to}.");
                    }

                    // Lo borra del origen recién después de confirmar la copia.
                    $source->delete($path);
                }
            });
    }
};
