<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            // Socio que ingresó. No se puede borrar un cliente con asistencias (igual que con sus membresías).
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            // Membresía con la que ingresó: la que perdió el crédito.
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            // Momento del ingreso. Con índice: el historial y el control de repetidos buscan por fecha.
            $table->timestamp('fecha_hora')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
