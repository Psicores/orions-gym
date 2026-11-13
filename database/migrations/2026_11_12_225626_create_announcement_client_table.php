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
        Schema::create('announcement_client', function (Blueprint $table) {
            $table->id();
            // Anuncio cerrado. Si se borra el anuncio, se borran sus marcas.
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            // Socio que lo cerró. Los socios son el modelo Client (los users son el personal del panel).
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // Cuándo lo cerró (leyó) en su portal.
            $table->timestamp('read_at')->nullable();
            // Una sola marca por socio y anuncio.
            $table->unique(['announcement_id', 'client_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_client');
    }
};
