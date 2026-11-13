<?php

namespace Tests\Feature;

use App\Enums\ClientStatusEnum;
use App\Enums\MembershipStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_nightly_command_pauses_memberships_still_training_after_their_end_date(): void
    {
        $client = Client::factory()->entrenando()->create();
        $membership = Membership::factory()->for($client)->create([
            'estado' => MembershipStatusEnum::Entrenando,
            'fecha_vencimiento' => today()->subDay(),
        ]);

        $this->artisan('memberships:update-completed-cycles')
            ->expectsOutput('Membresías pasadas a En pausa: 1. Clientes pasados a En pausa: 1.')
            ->assertSuccessful();

        $this->assertSame(MembershipStatusEnum::EnPausa, $membership->refresh()->estado);
        $this->assertSame(ClientStatusEnum::EnPausa, $client->refresh()->estado);
    }

    public function test_the_nightly_command_leaves_memberships_that_end_today_or_later(): void
    {
        $client = Client::factory()->entrenando()->create();
        $membership = Membership::factory()->for($client)->create(['fecha_vencimiento' => today()]);

        $this->artisan('memberships:update-completed-cycles')->assertSuccessful();

        $this->assertSame(MembershipStatusEnum::Entrenando, $membership->refresh()->estado);
        $this->assertSame(ClientStatusEnum::Entrenando, $client->refresh()->estado);
    }

    public function test_the_nightly_command_does_not_touch_a_courtesy_even_after_the_end_date(): void
    {
        $client = Client::factory()->create(['estado' => ClientStatusEnum::ActivoTemporal]);
        $membership = Membership::factory()->for($client)->create([
            'estado' => MembershipStatusEnum::ActivoFlex,
            'fecha_vencimiento' => today()->subDays(10),
        ]);

        $this->artisan('memberships:update-completed-cycles')
            ->expectsOutput('Membresías pasadas a En pausa: 0. Clientes pasados a En pausa: 0.');

        $this->assertSame(MembershipStatusEnum::ActivoFlex, $membership->refresh()->estado);
        $this->assertSame(ClientStatusEnum::ActivoTemporal, $client->refresh()->estado);
    }

    public function test_the_nightly_command_keeps_the_client_training_when_another_membership_still_gives_access(): void
    {
        $client = Client::factory()->entrenando()->create();
        $old = Membership::factory()->for($client)->create(['fecha_vencimiento' => today()->subDay()]);
        Membership::factory()->for($client)->create(['fecha_vencimiento' => today()->addMonth()]);

        $this->artisan('memberships:update-completed-cycles')
            ->expectsOutput('Membresías pasadas a En pausa: 1. Clientes pasados a En pausa: 0.');

        $this->assertSame(MembershipStatusEnum::EnPausa, $old->refresh()->estado);
        $this->assertSame(ClientStatusEnum::Entrenando, $client->refresh()->estado);
    }

    public function test_both_commands_are_scheduled_in_the_early_morning(): void
    {
        $expressions = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn ($event): array => [str($event->command)->afterLast('artisan" ')->afterLast('artisan ')->toString() => $event->expression]);

        $this->assertSame('0 1 * * *', $expressions['memberships:update-completed-cycles']);
        $this->assertSame('5 1 * * *', $expressions['memberships:renew-credits']);
    }

    public function test_the_owner_can_give_a_courtesy_from_the_client_page(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::factory()->create(['estado' => ClientStatusEnum::EnPausa]);
        $membership = Membership::factory()->for($client)->create([
            'estado' => MembershipStatusEnum::EnPausa,
            'fecha_vencimiento' => today()->subDays(3),
            'creditos_disponibles' => 2,
        ]);

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])
            ->assertActionVisible('grantCourtesy')
            ->callAction('grantCourtesy')
            ->assertNotified('Cortesía otorgada');

        $membership->refresh();
        $this->assertSame(MembershipStatusEnum::ActivoFlex, $membership->estado);
        $this->assertTrue($membership->fecha_vencimiento->isSameDay(today()->subDays(3)));
        $this->assertSame(2, $membership->creditos_disponibles);
        $this->assertSame(ClientStatusEnum::ActivoTemporal, $client->refresh()->estado);
    }

    public function test_the_courtesy_button_is_hidden_when_the_membership_already_has_one(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::factory()->create();
        Membership::factory()->for($client)->create(['estado' => MembershipStatusEnum::ActivoFlex]);

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])
            ->assertActionHidden('grantCourtesy');
    }

    public function test_a_member_with_a_courtesy_can_check_in_after_the_end_date(): void
    {
        $client = Client::factory()->create(['estado' => ClientStatusEnum::EnPausa]);
        $membership = Membership::factory()->for($client)->create([
            'estado' => MembershipStatusEnum::EnPausa,
            'fecha_vencimiento' => today()->subDays(3),
        ]);

        $membership->grantCourtesy();
        $this->artisan('memberships:update-completed-cycles');
        $client->registerAttendance();

        $this->assertDatabaseCount(Attendance::class, 1);
    }

    public function test_paying_a_paused_membership_starts_the_new_month_with_the_plan_credits(): void
    {
        $client = Client::factory()->create(['estado' => ClientStatusEnum::EnPausa]);
        $membership = Membership::factory()->for($client)->for(Plan::factory()->create(['creditos_otorgados' => 12]))->create([
            'estado' => MembershipStatusEnum::EnPausa,
            'fecha_inicio' => today()->subDays(35),
            'fecha_vencimiento' => today()->subDays(5),
            'creditos_disponibles' => -2,
        ]);

        $client->registerPayment($membership, '25000', PaymentMethodEnum::Efectivo, User::factory()->create());

        $membership->refresh();
        $this->assertSame(MembershipStatusEnum::Entrenando, $membership->estado);
        $this->assertSame(12, $membership->creditos_disponibles);
        $this->assertTrue($membership->fecha_inicio->isSameDay(today()));
        $this->assertTrue($membership->fecha_vencimiento->isSameDay(today()->addMonthNoOverflow()));
        $this->assertSame(ClientStatusEnum::Entrenando, $client->estado);
        $this->assertTrue($client->fecha_vencimiento->isSameDay(today()->addMonthNoOverflow()));
    }

    public function test_paying_ends_a_courtesy_and_starts_the_new_month(): void
    {
        $client = Client::factory()->create(['estado' => ClientStatusEnum::ActivoTemporal]);
        $membership = Membership::factory()->for($client)->for(Plan::factory()->create(['creditos_otorgados' => 16]))->create([
            'estado' => MembershipStatusEnum::ActivoFlex,
            'fecha_vencimiento' => today()->addDays(2),
            'creditos_disponibles' => 1,
        ]);

        $client->registerPayment($membership, '30000', PaymentMethodEnum::Transferencia, User::factory()->create());

        $membership->refresh();
        $this->assertSame(MembershipStatusEnum::Entrenando, $membership->estado);
        $this->assertSame(16, $membership->creditos_disponibles);
        $this->assertSame(ClientStatusEnum::Entrenando, $client->refresh()->estado);
    }

    public function test_paying_a_membership_that_is_still_running_does_not_reset_its_credits_or_dates(): void
    {
        $client = Client::factory()->create();
        $membership = Membership::factory()->for($client)->for(Plan::factory()->create(['creditos_otorgados' => 12]))->create([
            'estado' => MembershipStatusEnum::Entrenando,
            'fecha_inicio' => today()->subDays(10),
            'fecha_vencimiento' => today()->addDays(20),
            'creditos_disponibles' => 7,
        ]);

        $client->registerPayment($membership, '25000', PaymentMethodEnum::Efectivo, User::factory()->create());

        $membership->refresh();
        $this->assertSame(7, $membership->creditos_disponibles);
        $this->assertTrue($membership->fecha_inicio->isSameDay(today()->subDays(10)));
        $this->assertTrue($membership->fecha_vencimiento->isSameDay(today()->addDays(20)));
    }

    public function test_the_renewal_command_renews_paid_memberships_that_were_left_without_a_new_cycle(): void
    {
        $this->travelTo('2026-11-12 09:00:00');
        $client = Client::factory()->create(['estado' => ClientStatusEnum::EnPausa]);
        $membership = Membership::factory()->for($client)->for(Plan::factory()->create(['creditos_otorgados' => 12]))->create([
            'estado' => MembershipStatusEnum::EnPausa,
            'fecha_inicio' => '2026-10-05',
            'fecha_vencimiento' => '2026-11-05',
            'creditos_disponibles' => 0,
        ]);
        Payment::factory()->for($client)->for($membership)->create(['fecha_pago' => '2026-11-10 18:00:00']);

        $this->artisan('memberships:renew-credits')->expectsOutput('Membresías renovadas: 1.')->assertSuccessful();

        $membership->refresh();
        $this->assertSame(MembershipStatusEnum::Entrenando, $membership->estado);
        $this->assertSame(12, $membership->creditos_disponibles);
        $this->assertSame('2026-11-10', $membership->fecha_inicio->toDateString());
        $this->assertSame('2026-12-10', $membership->fecha_vencimiento->toDateString());
        $this->assertSame(ClientStatusEnum::Entrenando, $client->refresh()->estado);

        $this->artisan('memberships:renew-credits')->expectsOutput('Membresías renovadas: 0.');
    }

    public function test_the_renewal_command_ignores_memberships_without_a_payment_after_their_end_date(): void
    {
        $this->travelTo('2026-11-12 09:00:00');
        $unpaid = Membership::factory()->create([
            'estado' => MembershipStatusEnum::EnPausa,
            'fecha_vencimiento' => '2026-11-05',
            'creditos_disponibles' => 3,
        ]);
        $paidOnTheLastDay = Membership::factory()->create([
            'estado' => MembershipStatusEnum::EnPausa,
            'fecha_vencimiento' => '2026-11-05',
            'creditos_disponibles' => 3,
        ]);
        Payment::factory()->for($paidOnTheLastDay->client)->for($paidOnTheLastDay)->create(['fecha_pago' => '2026-11-05 20:00:00']);
        $closed = Membership::factory()->create([
            'estado' => MembershipStatusEnum::Vencida,
            'fecha_vencimiento' => '2026-10-05',
            'creditos_disponibles' => 3,
        ]);
        Payment::factory()->for($closed->client)->for($closed)->create(['fecha_pago' => '2026-11-10 18:00:00']);

        $this->artisan('memberships:renew-credits')->expectsOutput('Membresías renovadas: 0.');

        $this->assertSame(MembershipStatusEnum::EnPausa, $unpaid->refresh()->estado);
        $this->assertSame(3, $paidOnTheLastDay->refresh()->creditos_disponibles);
        $this->assertSame(MembershipStatusEnum::Vencida, $closed->refresh()->estado);
    }
}
