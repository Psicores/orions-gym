<?php

namespace Database\Factories;

use App\Enums\MembershipStatusEnum;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * creditos_disponibles is left out on purpose: the model copies it from the plan.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'plan_id' => Plan::factory(),
            'fecha_inicio' => now()->toDateString(),
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'estado' => MembershipStatusEnum::Entrenando,
        ];
    }
}
