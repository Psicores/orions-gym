<?php

namespace Database\Factories;

use App\Enums\PaymentMethodEnum;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The membership is created for the same client, so the payment is always consistent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'membership_id' => fn (array $attributes): Factory => Membership::factory()->state([
                'client_id' => $attributes['client_id'],
            ]),
            'monto' => fake()->randomFloat(2, 10000, 60000),
            'metodo_pago' => fake()->randomElement(PaymentMethodEnum::cases()),
            'fecha_pago' => now(),
            'registrado_por_user_id' => User::factory(),
        ];
    }
}
