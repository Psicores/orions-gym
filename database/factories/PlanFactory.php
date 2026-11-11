<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['2x semana', '3x semana', 'Libre']).' '.fake()->unique()->numberBetween(1, 9999),
            'precio' => fake()->randomFloat(2, 10000, 60000),
            'creditos_otorgados' => fake()->randomElement([8, 12, 999]),
        ];
    }
}
