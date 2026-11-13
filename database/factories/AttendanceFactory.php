<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Client;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            // La membresía es del mismo cliente que la asistencia.
            'membership_id' => fn (array $attributes): Factory => Membership::factory()->state([
                'client_id' => $attributes['client_id'],
            ]),
            'fecha_hora' => now(),
        ];
    }
}
