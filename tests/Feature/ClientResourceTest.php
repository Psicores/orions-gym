<?php

namespace Tests\Feature;

use App\Enums\ClientStatusEnum;
use App\Enums\MembershipStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
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

class ClientResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_clients_have_no_create_page_and_the_list_still_loads(): void
    {
        $this->assertArrayNotHasKey('create', ClientResource::getPages());

        $this->get(ClientResource::getUrl('index'))->assertOk();
    }

    public function test_a_client_is_created_from_the_modal_with_only_the_required_fields(): void
    {
        Livewire::test(ListClients::class)
            ->callAction(CreateAction::class, $this->requiredClientData())
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Client::class, [
            'dni' => '30123456',
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'estado' => ClientStatusEnum::Inactivo,
            'foto_path' => null,
            'certificado_salud_path' => null,
            'observaciones_dolencias' => null,
        ]);
    }

    public function test_creating_a_client_opens_the_membership_step_for_that_client(): void
    {
        Livewire::test(ListClients::class)
            ->callAction(CreateAction::class, $this->requiredClientData())
            ->assertActionMounted(
                TestAction::make('createMembership')->arguments(['client' => Client::query()->sole()->getKey()]),
            );
    }

    public function test_the_membership_step_creates_the_membership_registers_the_payment_and_sets_the_client_to_entrenando(): void
    {
        $this->freezeTime();
        $client = Client::factory()->create(['estado' => ClientStatusEnum::Inactivo]);
        $plan = Plan::factory()->create(['creditos_otorgados' => 12]);

        Livewire::test(ListClients::class)
            ->callAction(
                TestAction::make('createMembership')->arguments(['client' => $client->getKey()]),
                [
                    'plan_id' => $plan->getKey(),
                    'monto' => 25000.50,
                    'metodo_pago' => PaymentMethodEnum::Transferencia->value,
                ],
            )
            ->assertHasNoFormErrors()
            ->assertRedirect(ClientResource::getUrl('view', ['record' => $client]));

        $this->assertDatabaseHas(Membership::class, [
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'fecha_inicio' => today(),
            'fecha_vencimiento' => today()->addMonth(),
            'estado' => MembershipStatusEnum::Entrenando,
            'creditos_disponibles' => 12,
        ]);
        $this->assertDatabaseHas(Payment::class, [
            'client_id' => $client->id,
            'membership_id' => Membership::query()->sole()->id,
            'monto' => 25000.50,
            'metodo_pago' => PaymentMethodEnum::Transferencia,
            'fecha_pago' => now(),
            'registrado_por_user_id' => auth()->id(),
        ]);
        $this->assertDatabaseHas(Client::class, [
            'id' => $client->id,
            'estado' => ClientStatusEnum::Entrenando,
            'fecha_vencimiento' => today()->addMonth(),
        ]);
    }

    public function test_the_membership_step_suggests_the_plan_price_and_cash_as_the_payment(): void
    {
        $client = Client::factory()->create();
        $plan = Plan::factory()->create(['precio' => 30000]);

        Livewire::test(ListClients::class)
            ->mountAction(TestAction::make('createMembership')->arguments(['client' => $client->getKey()]))
            ->fillForm(['plan_id' => $plan->getKey()])
            ->assertSchemaStateSet([
                'monto' => '30000.00',
                'metodo_pago' => PaymentMethodEnum::Efectivo,
            ]);
    }

    public function test_skipping_the_membership_step_leaves_the_client_without_membership(): void
    {
        $client = Client::factory()->create(['estado' => ClientStatusEnum::Inactivo]);

        Livewire::test(ListClients::class)
            ->mountAction(TestAction::make('createMembership')->arguments(['client' => $client->getKey()]))
            ->unmountAction()
            ->assertNoRedirect();

        $this->assertDatabaseCount(Membership::class, 0);
        $this->assertDatabaseCount(Payment::class, 0);
        $this->assertDatabaseHas(Client::class, [
            'id' => $client->id,
            'estado' => ClientStatusEnum::Inactivo,
        ]);
    }

    public function test_the_membership_step_requires_a_plan_and_an_amount_and_rejects_an_expiration_before_the_start(): void
    {
        $client = Client::factory()->create(['estado' => ClientStatusEnum::Inactivo]);

        Livewire::test(ListClients::class)
            ->callAction(
                TestAction::make('createMembership')->arguments(['client' => $client->getKey()]),
                [
                    'plan_id' => null,
                    'fecha_inicio' => '2026-11-12',
                    'fecha_vencimiento' => '2026-11-01',
                    'monto' => null,
                ],
            )
            ->assertHasFormErrors([
                'plan_id' => 'required',
                'fecha_vencimiento' => 'after_or_equal',
                'monto' => 'required',
            ]);

        $this->assertDatabaseCount(Membership::class, 0);
        $this->assertDatabaseCount(Payment::class, 0);
        $this->assertDatabaseHas(Client::class, [
            'id' => $client->id,
            'estado' => ClientStatusEnum::Inactivo,
        ]);
    }

    public function test_the_membership_step_does_not_offer_deleted_plans(): void
    {
        $client = Client::factory()->create(['estado' => ClientStatusEnum::Inactivo]);
        $deletedPlan = Plan::factory()->trashed()->create();

        Livewire::test(ListClients::class)
            ->callAction(
                TestAction::make('createMembership')->arguments(['client' => $client->getKey()]),
                ['plan_id' => $deletedPlan->getKey(), 'monto' => 1000],
            )
            ->assertHasFormErrors(['plan_id']);

        $this->assertDatabaseCount(Membership::class, 0);
    }

    public function test_the_optional_observations_are_saved_from_the_modal(): void
    {
        Livewire::test(ListClients::class)
            ->callAction(CreateAction::class, [
                ...$this->requiredClientData(),
                'observaciones_dolencias' => 'Lesión de rodilla derecha.',
            ])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Client::class, [
            'dni' => '30123456',
            'observaciones_dolencias' => 'Lesión de rodilla derecha.',
        ]);
    }

    public function test_the_create_modal_rejects_a_client_without_required_fields(): void
    {
        Livewire::test(ListClients::class)
            ->callAction(CreateAction::class, [
                'nombre' => null,
                'apellido' => null,
                'dni' => null,
                'fecha_nacimiento' => null,
                'cel' => null,
                'email' => null,
            ])
            ->assertHasFormErrors([
                'nombre' => 'required',
                'apellido' => 'required',
                'dni' => 'required',
                'fecha_nacimiento' => 'required',
                'cel' => 'required',
                'email' => 'required',
            ]);

        $this->assertDatabaseCount(Client::class, 0);
    }

    public function test_the_create_modal_rejects_a_dni_that_another_client_already_has(): void
    {
        Client::factory()->create(['dni' => '30123456']);

        Livewire::test(ListClients::class)
            ->callAction(CreateAction::class, $this->requiredClientData())
            ->assertHasFormErrors(['dni' => 'unique']);

        $this->assertDatabaseCount(Client::class, 1);
    }

    public function test_the_edit_page_still_saves_the_client(): void
    {
        $client = Client::factory()->create(['nombre' => 'Juan']);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['nombre' => 'Juan Carlos'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Client::class, [
            'id' => $client->id,
            'nombre' => 'Juan Carlos',
        ]);
    }

    /**
     * @return array{nombre: string, apellido: string, dni: string, fecha_nacimiento: string, cel: string, email: string}
     */
    private function requiredClientData(): array
    {
        return [
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'dni' => '30123456',
            'fecha_nacimiento' => '1995-03-14',
            'cel' => '1155550000',
            'email' => 'juan.perez@example.com',
        ];
    }
}
