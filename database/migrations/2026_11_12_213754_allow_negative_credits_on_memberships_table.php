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
        Schema::table('memberships', function (Blueprint $table) {
            // Los créditos pasan a admitir negativos: quien entra sin créditos queda debiendo clases.
            $table->integer('creditos_disponibles')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            // Vuelve a no admitir negativos (falla si ya hay alguno guardado).
            $table->unsignedInteger('creditos_disponibles')->change();
        });
    }
};
