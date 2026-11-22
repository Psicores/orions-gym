<?php

namespace Tests\Feature;

use App\Enums\ConsentOriginEnum;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Livewire\MemberAccount;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ClientConsentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function clientData(): array
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

    public function test_nobody_is_subscribed_without_asking(): void
    {
        $client = Client::factory()->create()->refresh();

        $this->assertFalse($client->acepta_avisos_whatsapp);
        $this->assertFalse($client->acepta_novedades);
        $this->assertNull($client->acepta_avisos_whatsapp_at);
        $this->assertNull($client->acepta_novedades_at);
        $this->assertNull($client->consentimiento_origen);
    }

    public function test_the_create_modal_starts_with_both_boxes_unchecked(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListClients::class)
            ->mountAction(CreateAction::class)
            ->assertSchemaStateSet(['acepta_avisos_whatsapp' => false, 'acepta_novedades' => false]);
    }

    public function test_checking_a_box_when_creating_records_when_and_where_it_was_given(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-11-22 10:30:00');

        Livewire::test(ListClients::class)
            ->callAction(CreateAction::class, [...$this->clientData(), 'acepta_avisos_whatsapp' => true])
            ->assertHasNoFormErrors();

        $client = Client::query()->sole();
        $this->assertTrue($client->acepta_avisos_whatsapp);
        $this->assertSame('2026-11-22 10:30:00', $client->acepta_avisos_whatsapp_at->format('Y-m-d H:i:s'));
        $this->assertFalse($client->acepta_novedades);
        $this->assertNull($client->acepta_novedades_at);
        $this->assertSame(ConsentOriginEnum::AltaPanel, $client->consentimiento_origen);
    }

    public function test_creating_without_checking_anything_records_no_origin(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListClients::class)
            ->callAction(CreateAction::class, $this->clientData())
            ->assertHasNoFormErrors();

        $this->assertNull(Client::query()->sole()->consentimiento_origen);
    }

    public function test_unchecking_in_the_panel_removes_the_date_and_editing_other_data_keeps_the_consents(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::factory()->create();
        $client->updateConsents(true, true, ConsentOriginEnum::Portal);
        $grantedAt = $client->refresh()->acepta_novedades_at;

        $this->travel(2)->days();
        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['nombre' => 'Otro nombre'])
            ->call('save')
            ->assertHasNoFormErrors();

        $client->refresh();
        $this->assertSame(ConsentOriginEnum::Portal, $client->consentimiento_origen);
        $this->assertEquals($grantedAt, $client->acepta_novedades_at);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['acepta_avisos_whatsapp' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $client->refresh();
        $this->assertFalse($client->acepta_avisos_whatsapp);
        $this->assertNull($client->acepta_avisos_whatsapp_at);
        $this->assertTrue($client->acepta_novedades);
        $this->assertEquals($grantedAt, $client->acepta_novedades_at);
        $this->assertSame(ConsentOriginEnum::AltaPanel, $client->consentimiento_origen);
    }

    public function test_the_member_changes_their_consents_from_their_account(): void
    {
        $client = Client::factory()->create();
        $this->travelTo('2026-11-22 18:00:00');

        Livewire::actingAs($client, 'client')
            ->test(MemberAccount::class)
            ->assertSet('acepta_novedades', false)
            ->set('acepta_novedades', true)
            ->call('updateConsents')
            ->assertSet('consentsSaved', true)
            ->assertSee('guardamos tus preferencias');

        $client->refresh();
        $this->assertTrue($client->acepta_novedades);
        $this->assertSame('2026-11-22 18:00:00', $client->acepta_novedades_at->format('Y-m-d H:i:s'));
        $this->assertFalse($client->acepta_avisos_whatsapp);
        $this->assertSame(ConsentOriginEnum::Portal, $client->consentimiento_origen);
    }

    public function test_saving_the_same_consents_again_changes_nothing(): void
    {
        $client = Client::factory()->create();
        $client->updateConsents(true, false, ConsentOriginEnum::AltaPanel);
        $before = DB::table('clients')->where('id', $client->id)->first();

        $this->travel(1)->day();
        Livewire::actingAs($client->refresh(), 'client')
            ->test(MemberAccount::class)
            ->assertSet('acepta_avisos_whatsapp', true)
            ->call('updateConsents');

        $after = DB::table('clients')->where('id', $client->id)->first();
        $this->assertSame($before->acepta_avisos_whatsapp_at, $after->acepta_avisos_whatsapp_at);
        $this->assertSame('alta_panel', $after->consentimiento_origen);
    }

    public function test_the_account_screen_shows_the_consent_boxes(): void
    {
        $this->actingAs(Client::factory()->create(), 'client')
            ->get(route('member.account'))
            ->assertOk()
            ->assertSeeInOrder(['Avisos', 'Avisos de mi cuenta por WhatsApp', 'Novedades del gimnasio', 'Guardar avisos', 'Cambiar contraseña']);
    }

    public function test_the_client_page_summarizes_each_consent(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-11-22 09:00:00');
        $client = Client::factory()->create();
        $client->updateConsents(true, false, ConsentOriginEnum::Portal);

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])
            ->assertSeeInOrder(['Avisos por WhatsApp', 'Sí, desde el 22/11/2026 · registrado en el portal', 'Novedades', 'No']);
    }
}
