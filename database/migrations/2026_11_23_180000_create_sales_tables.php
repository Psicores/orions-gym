<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea las ventas del kiosco, sus renglones y sus cobros.
     *
     * Precondiciones:
     * - Existen clients, users, products y stock_movements.
     *
     * Postcondiciones:
     * - sales: socio opcional, quién vendió, estado (pendiente, pagada, anulada), subtotal a precio
     *   de lista, descuento y total; datos de la anulación; caja opcional para más adelante.
     * - sale_items: copia del nombre y de los precios al momento de vender (cambiar el precio del
     *   producto después no cambia la venta).
     * - sale_payments: cobros de cada venta, genéricos desde el inicio: medio, monto, estado
     *   (pendiente, aprobado, rechazado, reintegrado), proveedor y referencia externa (únicas juntas,
     *   así un aviso repetido de una pasarela no registra dos veces el mismo pago), datos crudos y
     *   cuándo se acreditó.
     * - stock_movements.sale_item_id pasa a tener clave foránea.
     * - Nada se borra en cascada: las ventas no se borran, se anulan.
     */
    public function up(): void
    {
        // Ventas.
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            // Socio al que se le vendió (opcional: la mayoría son ventas de mostrador).
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            // Quién vendió.
            $table->foreignId('vendido_por_user_id')->constrained('users')->restrictOnDelete();
            // Pendiente, pagada o anulada (ver SaleStatusEnum).
            $table->string('estado', 20)->index();
            // Suma a precio de lista.
            $table->decimal('subtotal', 10, 2);
            // Descuento total (socio o efectivo).
            $table->decimal('descuento', 10, 2)->default(0);
            // Lo que se cobra.
            $table->decimal('total', 10, 2);
            // Caja en la que entró (para cuando haya apertura y cierre de caja).
            $table->unsignedBigInteger('cash_session_id')->nullable()->index();
            // Anulación.
            $table->timestamp('anulada_at')->nullable();
            $table->foreignId('anulada_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();
            // Reportes por día.
            $table->index('created_at');
        });

        // Renglones de cada venta.
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            // Venta.
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            // Producto vendido.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Nombre del producto al momento de vender.
            $table->string('nombre', 120);
            // Unidades.
            $table->unsignedInteger('cantidad');
            // Precio de lista unitario al momento de vender.
            $table->decimal('precio_lista', 10, 2);
            // Precio unitario cobrado (con descuento si correspondió).
            $table->decimal('precio_unitario', 10, 2);
            // precio_unitario × cantidad.
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });

        // Cobros de cada venta.
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            // Venta.
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            // Medio de pago (ver PaymentMethodEnum).
            $table->string('metodo', 30);
            // Importe.
            $table->decimal('monto', 10, 2);
            // Pendiente, aprobado, rechazado o reintegrado (ver SalePaymentStatusEnum).
            $table->string('estado', 20)->index();
            // Pasarela que lo procesó (null = cobro manual en el mostrador).
            $table->string('proveedor', 40)->nullable();
            // Identificador del pago en la pasarela.
            $table->string('referencia_externa', 120)->nullable();
            // Datos crudos que mandó la pasarela.
            $table->json('payload')->nullable();
            // Cuándo se acreditó.
            $table->timestamp('pagado_at')->nullable();
            // Quién lo registró (null si lo confirmó una pasarela sola).
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            // Caja en la que entró (para más adelante).
            $table->unsignedBigInteger('cash_session_id')->nullable()->index();
            $table->timestamps();
            // Un mismo pago de una pasarela no se registra dos veces.
            $table->unique(['proveedor', 'referencia_externa']);
        });

        // El renglón de venta de cada movimiento de stock, ahora que existe la tabla.
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->restrictOnDelete();
        });
    }

    /**
     * Borra las tres tablas y la clave foránea de los movimientos.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Quita solo la clave foránea; la columna queda.
            $table->dropForeign(['sale_item_id']);
        });

        // En orden inverso por las claves foráneas.
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
