<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea el registro de movimientos de stock del kiosco.
     *
     * Precondiciones:
     * - Existen las tablas products y users.
     *
     * Postcondiciones:
     * - stock_movements: producto, tipo (entrada, venta, ajuste, anulación), cantidad con signo
     *   (+ entra, − sale), stock que quedó después del movimiento, renglón de venta opcional (la
     *   clave foránea se agrega cuando exista la tabla de ventas), quién lo registró y una nota.
     * - Productos y usuarios no se pueden borrar definitivamente si tienen movimientos (restrict):
     *   los dos usan baja lógica.
     */
    public function up(): void
    {
        // Movimientos de stock.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            // Producto que se movió.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Entrada, venta, ajuste o anulación (ver StockMovementTypeEnum).
            $table->string('tipo', 20);
            // Unidades con signo: positivo entra, negativo sale.
            $table->integer('cantidad');
            // Stock del producto después de este movimiento.
            $table->integer('stock_resultante');
            // Renglón de venta que lo generó (KIO-03); sin clave foránea hasta que exista la tabla.
            $table->unsignedBigInteger('sale_item_id')->nullable()->index();
            // Quién lo registró.
            $table->foreignId('registrado_por_user_id')->constrained('users')->restrictOnDelete();
            // Motivo o detalle (obligatorio en los ajustes).
            $table->string('nota', 255)->nullable();
            $table->timestamps();
            // Para listar los movimientos de un producto del más nuevo al más viejo.
            $table->index(['product_id', 'created_at']);
        });
    }

    /**
     * Borra la tabla de movimientos.
     */
    public function down(): void
    {
        // Borra la tabla.
        Schema::dropIfExists('stock_movements');
    }
};
