<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Código del QR de desarrollo que traía config/gym.php: es público, nunca se importa. */
    private const string DEVELOPMENT_TOKEN = 'orions-ingreso-sede-1';

    /**
     * Crea los puntos de acceso (cada uno con su QR) y anota en cada ingreso por cuál entró.
     *
     * Precondiciones:
     * - Existe la tabla attendances.
     *
     * Postcondiciones:
     * - access_points: nombre, token único (lo que lleva el QR), activo y activity_id opcional
     *   (para cuando haya actividades; hoy no tiene clave foránea porque la tabla no existe).
     * - Hay un primer punto "Recepción". Si GYM_CHECKIN_TOKEN tenía un código seguro (16
     *   caracteres o más y no el de desarrollo), lo conserva: el QR ya impreso sigue sirviendo
     *   desde el escáner del portal. Si no, recibe un código nuevo al azar.
     * - attendances.access_point_id: opcional (los ingresos anteriores quedan en null); si se
     *   borra el punto, queda en null.
     */
    public function up(): void
    {
        // Puntos de acceso.
        Schema::create('access_points', function (Blueprint $table) {
            $table->id();
            // Nombre que ve el personal (por ejemplo "Recepción" o "Salón de arriba").
            $table->string('nombre', 80);
            // Código secreto que lleva el QR.
            $table->string('token', 64)->unique();
            // Apagado, el QR deja de servir.
            $table->boolean('activo')->default(true);
            // Actividad a la que corresponde (Curev Hue, etc.), para más adelante.
            $table->unsignedBigInteger('activity_id')->nullable();
            $table->timestamps();
        });

        // Por qué punto entró cada socio.
        Schema::table('attendances', function (Blueprint $table) {
            // Punto de acceso usado (null en los ingresos anteriores).
            $table->foreignId('access_point_id')->nullable()->after('membership_id')->constrained()->nullOnDelete();
        });

        // Código configurado hasta ahora en el .env.
        $current = trim((string) config('gym.checkin_token'));
        // Solo se conserva si es seguro.
        $isSafe = $current !== self::DEVELOPMENT_TOKEN && mb_strlen($current) >= 16 && mb_strlen($current) <= 64;

        // Primer punto de acceso.
        DB::table('access_points')->insert([
            'nombre' => 'Recepción',
            // El de siempre, o uno nuevo al azar.
            'token' => $isSafe ? $current : Str::random(40),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Quita la columna de los ingresos y la tabla de puntos de acceso.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Borra la clave foránea y la columna.
            $table->dropConstrainedForeignId('access_point_id');
        });

        // Borra los puntos.
        Schema::dropIfExists('access_points');
    }
};
