<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Livewire\MemberAccount;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientCelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function writtenNumbers(): array
    {
        return [
            'con 15 y espacios' => ['280 15 412-3456', '+5492804123456'],
            'con 0 y 15' => ['0280-154123456', '+5492804123456'],
            'diez dígitos sin 15' => ['2804123456', '+5492804123456'],
            'con 0, sin 15' => ['02804123456', '+5492804123456'],
            'Buenos Aires con 15' => ['11 15 2345-6789', '+5491123456789'],
            'Buenos Aires sin 15' => ['1123456789', '+5491123456789'],
            'característica de 4 dígitos' => ['2945 15 41-2345', '+5492945412345'],
            'internacional con 9' => ['+54 9 351 234-5678', '+5493512345678'],
            'internacional sin 9' => ['+54 351 234-5678', '+5493512345678'],
            'internacional sin +' => ['5491123456789', '+5491123456789'],
        ];
    }

    #[DataProvider('writtenNumbers')]
    public function test_any_way_of_writing_a_mobile_ends_in_the_same_format(string $written, string $expected): void
    {
        $this->assertSame($expected, Client::normalizeCel($written));
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'vacío' => [''],
            'null' => [null],
            'sin característica' => ['15-4123456'],
            'muy corto' => ['4123456'],
            'letras' => ['no tiene'],
            'de otro país' => ['+1 415 555 2671'],
            'dígitos de más' => ['11 2345 678901'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_what_is_not_an_argentine_mobile_is_rejected(?string $written): void
    {
        $this->assertNull(Client::normalizeCel($written));
    }

    public function test_the_model_stores_the_normalized_mobile_and_shows_it_readable(): void
    {
        $client = Client::factory()->create(['cel' => '280 15 412-3456']);

        $this->assertSame('+5492804123456', DB::table('clients')->where('id', $client->id)->value('cel'));
        $this->assertTrue($client->hasValidCel());
        $this->assertSame('+54 9 280 412-3456', Client::formatCel($client->cel));
    }

    public function test_the_panel_saves_a_client_without_mobile_and_normalizes_the_one_written(): void
    {
        $this->actingAs(User::factory()->create());
        $data = ['nombre' => 'Juan', 'apellido' => 'Pérez', 'dni' => '30123456', 'fecha_nacimiento' => '1995-03-14', 'email' => 'juan@example.com'];

        Livewire::test(ListClients::class)
            ->callAction(CreateAction::class, [...$data, 'cel' => null])
            ->assertHasNoFormErrors();
        $this->assertNull(Client::query()->sole()->cel);

        Livewire::test(ListClients::class)
            ->callAction(CreateAction::class, [...$data, 'dni' => '30123457', 'email' => 'otro@example.com', 'cel' => '0280 15 412-3456'])
            ->assertHasNoFormErrors();
        $this->assertSame('+5492804123456', Client::query()->where('dni', '30123457')->value('cel'));
    }

    public function test_the_panel_rejects_something_that_is_not_a_mobile(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::factory()->create();

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['cel' => '15-4123456'])
            ->call('save')
            ->assertHasFormErrors(['cel']);
    }

    public function test_the_edit_form_shows_the_mobile_readable(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::factory()->create(['cel' => '+5492804123456']);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->assertSchemaStateSet(['cel' => '+54 9 280 412-3456']);
    }

    public function test_the_client_page_marks_an_old_invalid_mobile_in_amber(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::factory()->create();
        DB::table('clients')->where('id', $client->id)->update(['cel' => '4123456']);

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])
            ->assertSee('4123456')
            ->assertSee('Revisar: no es un celular válido');
    }

    public function test_the_member_completes_their_mobile_from_the_portal(): void
    {
        $client = Client::factory()->create(['cel' => null]);

        $this->actingAs($client, 'client')
            ->get(route('member.dashboard'))
            ->assertSee('Completá tu celular');

        Livewire::actingAs($client, 'client')
            ->test(MemberAccount::class)
            ->assertSee('Todavía no tenemos tu celular.')
            ->set('cel', '15-4123456')
            ->call('updateCel')
            ->assertHasErrors(['cel'])
            ->set('cel', '280 15 412-3456')
            ->call('updateCel')
            ->assertHasNoErrors()
            ->assertSet('cel', '+54 9 280 412-3456')
            ->assertSet('celSaved', true)
            ->assertSee('guardamos tu celular');

        $this->assertSame('+5492804123456', $client->refresh()->cel);

        $this->actingAs($client, 'client')
            ->get(route('member.dashboard'))
            ->assertDontSee('Completá tu celular');
    }

    public function test_the_member_cannot_save_an_empty_mobile(): void
    {
        $client = Client::factory()->create();

        Livewire::actingAs($client, 'client')
            ->test(MemberAccount::class)
            ->set('cel', '')
            ->call('updateCel')
            ->assertHasErrors(['cel' => 'required']);

        $this->assertNotNull($client->refresh()->cel);
    }

    public function test_the_migration_normalizes_loaded_mobiles_and_logs_the_ones_it_cannot(): void
    {
        $good = Client::factory()->create();
        $bad = Client::factory()->create();
        $blank = Client::factory()->create();
        DB::table('clients')->where('id', $good->id)->update(['cel' => '280 15 412-3456']);
        DB::table('clients')->where('id', $bad->id)->update(['cel' => '4123456']);
        DB::table('clients')->where('id', $blank->id)->update(['cel' => '  ']);
        Log::spy();

        (require database_path('migrations/2026_11_22_161054_normalize_cel_in_clients_table.php'))->up();

        $this->assertSame('+5492804123456', DB::table('clients')->where('id', $good->id)->value('cel'));
        $this->assertSame('4123456', DB::table('clients')->where('id', $bad->id)->value('cel'));
        $this->assertNull(DB::table('clients')->where('id', $blank->id)->value('cel'));
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context === ['clientes' => [$bad->id]]);
    }
}
