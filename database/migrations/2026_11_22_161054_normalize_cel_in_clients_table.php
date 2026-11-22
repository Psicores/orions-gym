<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hace opcional el celular de los clientes y pasa los ya cargados al formato único +549...
     *
     * Precondiciones:
     * - Existe la tabla clients con la columna cel.
     *
     * Postcondiciones:
     * - cel acepta null: el alta no lo exige y el socio lo puede completar en su portal.
     * - Cada celular que es un celular argentino válido queda en formato E.164 (ver
     *   Client::normalizeCel()); los vacíos quedan en null.
     * - Los que no se pudieron interpretar quedan como estaban y se anotan en el log (solo los
     *   id de cliente): en su ficha aparecen en ámbar para revisarlos.
     * - Incluye a los clientes dados de baja. No toca ninguna otra columna.
     */
    public function up(): void
    {
        // La columna pasa a aceptar null.
        Schema::table('clients', function (Blueprint $table) {
            // Mismo tipo, ahora opcional.
            $table->string('cel')->nullable()->change();
        });

        // Clientes cuyo celular no se pudo normalizar.
        $invalid = [];

        // Recorre los clientes de a 200, directo en la tabla (sin eventos del modelo).
        DB::table('clients')->select(['id', 'cel'])->orderBy('id')->chunkById(200, function ($clients) use (&$invalid): void {
            // Revisa cada uno.
            foreach ($clients as $client) {
                // Formato único, o null si no es un celular válido.
                $normalized = Client::normalizeCel($client->cel);

                // Vacío o inválido: se deja null si estaba vacío, y se anota si tenía algo.
                if ($normalized === null) {
                    // Un texto en blanco pasa a null.
                    if (blank($client->cel) && $client->cel !== null) {
                        DB::table('clients')->where('id', $client->id)->update(['cel' => null]);
                    }

                    // Tenía algo que no es un celular: queda para revisar.
                    if (filled($client->cel)) {
                        $invalid[] = $client->id;
                    }

                    // Sigue con el próximo.
                    continue;
                }

                // Solo escribe si cambió.
                if ($normalized !== $client->cel) {
                    DB::table('clients')->where('id', $client->id)->update(['cel' => $normalized]);
                }
            }
        });

        // Deja constancia de los que hay que revisar a mano.
        if ($invalid !== []) {
            // Solo los id, sin los números.
            Log::warning('Celulares que no se pudieron normalizar; revisarlos en la ficha del cliente', ['clientes' => $invalid]);
        }
    }

    /**
     * Vuelve a hacer obligatorio el celular. Los celulares quedan en el formato nuevo (no se
     * puede saber cómo estaban escritos) y los vacíos pasan a texto vacío.
     */
    public function down(): void
    {
        // La columna obligatoria no admite null: los vacíos quedan como texto vacío.
        DB::table('clients')->whereNull('cel')->update(['cel' => '']);

        // Vuelve a ser obligatoria.
        Schema::table('clients', function (Blueprint $table) {
            // Mismo tipo, sin null.
            $table->string('cel')->nullable(false)->change();
        });
    }
};
