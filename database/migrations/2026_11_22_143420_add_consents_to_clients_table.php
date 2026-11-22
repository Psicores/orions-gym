<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega a los clientes los dos permisos de aviso, por separado, con cuándo se dio cada uno y
     * desde dónde se registró el último cambio.
     *
     * Precondiciones:
     * - Existe la tabla clients.
     *
     * Postcondiciones:
     * - acepta_avisos_whatsapp (avisos de su cuenta: vencimiento, pago recibido, créditos
     *   agotados) y acepta_novedades (promociones, eventos, novedades): booleanos, en false para
     *   todos los clientes existentes. Nadie queda suscripto sin haberlo pedido.
     * - acepta_avisos_whatsapp_at y acepta_novedades_at: cuándo se dio cada permiso (null si no).
     * - consentimiento_origen: de dónde vino el último cambio (ver ConsentOriginEnum), null si
     *   nunca se tocaron.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Avisos de la cuenta por WhatsApp.
            $table->boolean('acepta_avisos_whatsapp')->default(false)->after('observaciones_dolencias');
            $table->timestamp('acepta_avisos_whatsapp_at')->nullable()->after('acepta_avisos_whatsapp');
            // Promociones, eventos y novedades.
            $table->boolean('acepta_novedades')->default(false)->after('acepta_avisos_whatsapp_at');
            $table->timestamp('acepta_novedades_at')->nullable()->after('acepta_novedades');
            // Desde dónde se registró el último cambio.
            $table->string('consentimiento_origen', 30)->nullable()->after('acepta_novedades_at');
        });
    }

    /**
     * Quita las cinco columnas (se pierden los permisos registrados).
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'acepta_avisos_whatsapp',
                'acepta_avisos_whatsapp_at',
                'acepta_novedades',
                'acepta_novedades_at',
                'consentimiento_origen',
            ]);
        });
    }
};
