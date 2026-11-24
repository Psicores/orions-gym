<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    /**
     * Categoría de ejemplo con nombre único.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Nombre que no se repite.
            'nombre' => fake()->unique()->words(2, true),
            // Posición al azar.
            'orden' => fake()->numberBetween(0, 20),
        ];
    }
}
