<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodEnum;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Plans\Pages\ManagePlans;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_a_plan_price_depends_on_the_payment_method(): void
    {
        $plan = Plan::factory()->create(['precio' => 55000, 'precio_efectivo' => 53000]);
        $withoutCashPrice = Plan::factory()->create(['precio' => 55000, 'precio_efectivo' => null]);

        $this->assertSame('53000.00', $plan->priceFor(PaymentMethodEnum::Efectivo));
        $this->assertSame('53000.00', $plan->priceFor('efectivo'));
        $this->assertSame('55000.00', $plan->priceFor(PaymentMethodEnum::Transferencia));
        $this->assertSame('55000.00', $plan->priceFor(null));
        $this->assertSame('55000.00', $withoutCashPrice->priceFor(PaymentMethodEnum::Efectivo));
    }

    public function test_the_cash_price_is_optional_when_creating_a_plan(): void
    {
        Livewire::test(ManagePlans::class)
            ->callAction(CreateAction::class, ['nombre' => 'Libre', 'precio' => 55000, 'precio_efectivo' => 53000, 'creditos_otorgados' => 999])
            ->assertHasNoFormErrors()
            ->callAction(CreateAction::class, ['nombre' => '3 días', 'precio' => 50000, 'creditos_otorgados' => 12])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Plan::class, ['nombre' => 'Libre', 'precio' => 55000, 'precio_efectivo' => 53000]);
        $this->assertDatabaseHas(Plan::class, ['nombre' => '3 días', 'precio_efectivo' => null]);
    }

    public function test_the_payment_modal_suggests_the_price_of_the_chosen_method(): void
    {
        $client = Client::factory()->entrenando()->create();
        $membership = Membership::factory()->for($client)
            ->for(Plan::factory()->create(['precio' => 55000, 'precio_efectivo' => 53000]))
            ->create();

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])
            ->mountAction('registerPayment')
            ->assertSchemaStateSet(['monto' => '53000.00'])
            ->fillForm(['metodo_pago' => PaymentMethodEnum::Transferencia->value])
            ->assertSchemaStateSet(['monto' => '55000.00'])
            ->fillForm(['metodo_pago' => PaymentMethodEnum::Efectivo->value])
            ->assertSchemaStateSet(['monto' => '53000.00'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Payment::class, [
            'membership_id' => $membership->id,
            'monto' => 53000,
            'metodo_pago' => PaymentMethodEnum::Efectivo,
        ]);
    }

    public function test_the_payment_modal_keeps_the_list_price_in_cash_when_the_plan_has_no_cash_price(): void
    {
        $client = Client::factory()->entrenando()->create();
        Membership::factory()->for($client)->for(Plan::factory()->create(['precio' => 50000]))->create();

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])
            ->mountAction('registerPayment')
            ->assertSchemaStateSet(['monto' => '50000.00'])
            ->fillForm(['metodo_pago' => PaymentMethodEnum::Transferencia->value])
            ->assertSchemaStateSet(['monto' => '50000.00']);
    }

    public function test_the_new_membership_modal_suggests_the_price_of_the_chosen_method(): void
    {
        $client = Client::factory()->create();
        $plan = Plan::factory()->create(['precio' => 55000, 'precio_efectivo' => 53000]);

        Livewire::test(ListClients::class)
            ->mountAction(TestAction::make('createMembership')->arguments(['client' => $client->getKey()]))
            ->fillForm(['plan_id' => $plan->getKey()])
            ->assertSchemaStateSet(['monto' => '53000.00'])
            ->fillForm(['metodo_pago' => PaymentMethodEnum::Transferencia->value])
            ->assertSchemaStateSet(['monto' => '55000.00']);
    }
}
