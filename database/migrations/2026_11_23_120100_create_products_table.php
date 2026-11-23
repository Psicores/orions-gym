<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea los productos del kiosco.
     *
     * Precondiciones:
     * - Existe la tabla product_categories.
     *
     * Postcondiciones:
     * - products: categoría opcional (si se borra la categoría queda en null), nombre, SKU y código
     *   de barras opcionales y únicos, precio de lista, precio en efectivo y costo opcionales,
     *   control de stock, stock actual (puede quedar negativo si se vende sin stock), stock mínimo
     *   opcional, imagen, destacado, activo y baja lógica.
     */
    public function up(): void
    {
        // Productos del kiosco.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Categoría (opcional); al borrarla, el producto queda sin categoría.
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            // Nombre que se ve en la pantalla de venta.
            $table->string('nombre', 120);
            // Código interno opcional; no se repite.
            $table->string('sku', 64)->nullable()->unique();
            // Código de barras del envase (EAN/UPC) opcional; no se repite. Es lo que lee el lector.
            $table->string('barcode', 64)->nullable()->unique();
            // Precio de lista.
            $table->decimal('precio', 10, 2);
            // Precio pagando en efectivo (opcional, como en los planes).
            $table->decimal('precio_efectivo', 10, 2)->nullable();
            // Lo que le cuesta al gimnasio (opcional; para ver ganancia más adelante).
            $table->decimal('costo', 10, 2)->nullable();
            // Si se lleva la cuenta del stock (false para cosas como "agua de la canilla" o servicios).
            $table->boolean('controla_stock')->default(true);
            // Unidades disponibles; con signo porque en modo flexible se puede vender sin stock.
            $table->integer('stock')->default(0);
            // Avisa "stock bajo" al llegar a este número (opcional).
            $table->unsignedInteger('stock_minimo')->nullable();
            // Foto del producto (disco public, carpeta products).
            $table->string('imagen_path')->nullable();
            // Aparece primero en la pantalla de venta.
            $table->boolean('destacado')->default(false);
            // Apagado, no se ofrece para vender pero sigue en el listado.
            $table->boolean('activo')->default(true);
            $table->timestamps();
            // Baja lógica: las ventas viejas siguen mostrando el producto.
            $table->softDeletes();
        });
    }

    /**
     * Borra la tabla de productos.
     */
    public function down(): void
    {
        // Borra la tabla.
        Schema::dropIfExists('products');
    }
};
