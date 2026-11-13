<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cifra las observaciones de salud que ya estaban guardadas como texto plano.
     *
     * Precondiciones:
     * - La aplicación tiene configurada la misma APP_KEY que va a usar siempre: sin esa clave
     *   las observaciones cifradas no se pueden volver a leer. Conviene un backup de la base
     *   antes de correrla.
     *
     * Postcondiciones:
     * - Toda observación no vacía de la tabla clients (incluidos los clientes dados de baja)
     *   queda cifrada con la clave de la aplicación; el modelo Client la sigue leyendo igual.
     * - Las que ya estaban cifradas no se tocan: correrla dos veces no cifra dos veces.
     * - No cambia ningún otro dato ni las fechas de modificación.
     */
    public function up(): void
    {
        // Recorre de a tandas los clientes que tienen alguna observación (sin pasar por el modelo).
        DB::table('clients')->whereNotNull('observaciones_dolencias')->where('observaciones_dolencias', '!=', '')
            ->chunkById(200, function ($clients): void {
                // Revisa cada cliente de la tanda.
                foreach ($clients as $client) {
                    // Si ya se puede descifrar, ya estaba cifrada: no la toca.
                    if ($this->decryptOrNull($client->observaciones_dolencias) !== null) {
                        continue;
                    }

                    // Guarda el texto cifrado en lugar del plano.
                    DB::table('clients')->where('id', $client->id)->update([
                        'observaciones_dolencias' => Crypt::encryptString($client->observaciones_dolencias),
                    ]);
                }
            });
    }

    /**
     * Vuelve a dejar las observaciones como texto plano.
     *
     * Precondiciones:
     * - La APP_KEY es la misma con la que se cifraron.
     *
     * Postcondiciones:
     * - Toda observación cifrada queda guardada como texto plano.
     * - Las que no se pueden descifrar (ya eran texto plano) no se tocan.
     */
    public function down(): void
    {
        // Recorre de a tandas los clientes que tienen alguna observación.
        DB::table('clients')->whereNotNull('observaciones_dolencias')->where('observaciones_dolencias', '!=', '')
            ->chunkById(200, function ($clients): void {
                // Revisa cada cliente de la tanda.
                foreach ($clients as $client) {
                    // Texto original, o null si no estaba cifrada.
                    $plain = $this->decryptOrNull($client->observaciones_dolencias);

                    // Ya era texto plano: no la toca.
                    if ($plain === null) {
                        continue;
                    }

                    // Guarda el texto plano.
                    DB::table('clients')->where('id', $client->id)->update(['observaciones_dolencias' => $plain]);
                }
            });
    }

    /**
     * Intenta descifrar un valor guardado.
     *
     * Precondiciones:
     * - $value es el contenido crudo de la columna.
     *
     * Postcondiciones:
     * - Devuelve el texto original si $value estaba cifrado con la clave de la aplicación.
     * - Devuelve null si no lo estaba (es texto plano). No modifica nada.
     *
     * @param  string  $value  Contenido crudo de la columna.
     * @return string|null Texto descifrado, o null si no estaba cifrado.
     */
    private function decryptOrNull(string $value): ?string
    {
        // Prueba descifrarlo.
        try {
            // Si funciona, estaba cifrado.
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Si falla, era texto plano.
            return null;
        }
    }
};
