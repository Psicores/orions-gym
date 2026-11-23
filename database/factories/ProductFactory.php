<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Producto de ejemplo activo, sin categoría, sin códigos y con stock controlado.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Nombre de ejemplo.
            'nombre' => ucfirst(fake()->words(2, true)),
            // Precio redondo entre $500 y $30.000.
            'precio' => fake()->numberBetween(5, 300) * 100,
            // Controla stock.
            'controla_stock' => true,
            // Activo para vender.
            'activo' => true,
            // No destacado.
            'destacado' => false,
        ];
    }

    /**
     * Producto con código de barras EAN-13 de ejemplo.
     */
    public function withBarcode(): static
    {
        // 13 dígitos que no se repiten.
        return $this->state(fn (): array => ['barcode' => (string) fake()->unique()->numberBetween(7790000000000, 7799999999999)]);
    }

    /**
     * Producto apagado (no se ofrece para vender).
     */
    public function inactive(): static
    {
        return $this->state(['activo' => false]);
    }
}
