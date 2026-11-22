<?php

namespace Tests\Feature;

use App\Filament\Pages\CheckInQr;
use App\Livewire\MemberScan;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Membership;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecureDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private const string STRONG_TOKEN = 'k3X9vQ2mL7pR4tZ8wB6nC1dF';

    private function inProduction(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function unsafeProductionTokens(): array
    {
        return [
            'not configured' => [null],
            'empty' => [''],
            'the public development token' => [Attendance::DEVELOPMENT_TOKEN],
            'too short' => ['corto-123'],
        ];
    }

    #[DataProvider('unsafeProductionTokens')]
    public function test_production_refuses_an_unsafe_qr_token(?string $token): void
    {
        config(['gym.checkin_token' => $token]);
        $this->inProduction();

        $this->assertNull(Attendance::receptionToken());
    }

    public function test_production_accepts_a_long_random_qr_token(): void
    {
        config(['gym.checkin_token' => '  '.self::STRONG_TOKEN.'  ']);
        $this->inProduction();

        $this->assertSame(self::STRONG_TOKEN, Attendance::receptionToken());
    }

    public function test_outside_production_an_empty_token_falls_back_to_the_development_one(): void
    {
        config(['gym.checkin_token' => '']);
        $this->assertSame(Attendance::DEVELOPMENT_TOKEN, Attendance::receptionToken());

        config(['gym.checkin_token' => 'corto']);
        $this->assertSame('corto', Attendance::receptionToken());
    }

    public function test_without_a_usable_token_no_one_can_check_in_not_even_with_the_development_code(): void
    {
        config(['gym.checkin_token' => null]);
        $this->inProduction();
        $client = Client::factory()->create();
        $membership = Membership::factory()->for($client)->create(['creditos_disponibles' => 12]);

        Livewire::actingAs($client, 'client')
            ->test(MemberScan::class)
            ->call('procesarIngreso', Attendance::DEVELOPMENT_TOKEN)
            ->assertSet('resultado', 'error')
            ->assertSee('El ingreso por QR todavía no está habilitado.')
            ->assertDispatched('ingreso-rechazado');

        $this->assertDatabaseCount(Attendance::class, 0);
        $this->assertSame(12, $membership->refresh()->creditos_disponibles);
    }

    public function test_the_qr_page_explains_how_to_configure_the_token_instead_of_printing_an_unsafe_one(): void
    {
        config(['gym.checkin_token' => null]);
        $this->inProduction();

        $this->actingAs(User::factory()->create())
            ->get(CheckInQr::getUrl())
            ->assertOk()
            ->assertSee('Falta configurar el código del QR')
            ->assertSee('GYM_CHECKIN_TOKEN=')
            ->assertSee('php artisan config:cache')
            ->assertDontSee('src="data:image/png;base64,', escape: false)
            ->assertDontSee('Imprimir');
    }

    public function test_the_qr_page_prints_the_qr_once_a_strong_token_is_configured(): void
    {
        config(['gym.checkin_token' => self::STRONG_TOKEN]);
        $this->inProduction();

        $this->actingAs(User::factory()->create())
            ->get(CheckInQr::getUrl())
            ->assertOk()
            ->assertSee('src="data:image/png;base64,', escape: false)
            ->assertDontSee('Falta configurar el código del QR');
    }

    /**
     * @return array<string, array{bool, string, bool}>
     */
    public static function twoFactorSettings(): array
    {
        return [
            'not set, production' => [false, 'production', false],
            'not set, development' => [false, 'local', false],
            'switched on in production' => [true, 'production', true],
        ];
    }

    #[DataProvider('twoFactorSettings')]
    public function test_staff_two_factor_is_optional_unless_switched_on(bool $setting, string $environment, bool $required): void
    {
        config(['app.staff_two_factor_required' => $setting]);
        $this->app->detectEnvironment(fn (): string => $environment);

        $this->assertSame($required, Filament::getPanel('admin')->isMultiFactorAuthenticationRequired());
    }

    public function test_responses_do_not_announce_the_php_version(): void
    {
        $this->get(route('login'))->assertOk()->assertHeaderMissing('X-Powered-By');
    }
}
