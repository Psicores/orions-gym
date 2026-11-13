<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => fake()->sentence(4),
            'contenido' => '<p>'.fake()->paragraph().'</p>',
            'imagen_path' => null,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * Indicate that the announcement was switched off by hand.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the announcement has not started yet.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->addDay(),
        ]);
    }

    /**
     * Indicate that the announcement already reached its end date.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ends_at' => now()->subDay(),
        ]);
    }
}
