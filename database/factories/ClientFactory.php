<?php

namespace Database\Factories;

use App\Enums\ClientStatusEnum;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dni' => fake()->unique()->numerify('########'),
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'fecha_nacimiento' => fake()->dateTimeBetween('-70 years', '-14 years')->format('Y-m-d'),
            'cel' => fake()->numerify('11########'),
            'email' => fake()->unique()->safeEmail(),
            // Contraseña propia: el socio ya la cambió y puede usar todo el portal.
            'password' => 'clave-propia-1',
            'estado' => ClientStatusEnum::Inactivo,
            'fecha_vencimiento' => null,
        ];
    }

    /**
     * Indicate that the client never chose a password: it is still their DNI.
     */
    public function withDniAsPassword(): static
    {
        return $this->state(fn (array $attributes): array => [
            'password' => null,
        ]);
    }

    /**
     * Indicate that the client is up to date with their monthly fee.
     */
    public function entrenando(): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => ClientStatusEnum::Entrenando,
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
        ]);
    }
}
