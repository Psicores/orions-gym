<?php

namespace Tests\Feature;

use App\Filament\Pages\CheckInQr;
use App\Models\AccessPoint;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecureDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function runAccessPointMigration(): void
    {
        $migration = require database_path('migrations/2026_11_23_000511_create_access_points_table.php');
        $migration->down();
        $migration->up();
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function unsafeOldTokens(): array
    {
        return [
            'not configured' => [null],
            'the public development token' => ['orions-ingreso-sede-1'],
            'too short' => ['corto-123'],
        ];
    }

    #[DataProvider('unsafeOldTokens')]
    public function test_an_unsafe_old_qr_code_is_never_imported(?string $token): void
    {
        config(['gym.checkin_token' => $token]);

        $this->runAccessPointMigration();

        $point = AccessPoint::query()->sole();
        $this->assertSame('Recepción', $point->nombre);
        $this->assertNotSame($token, $point->token);
        $this->assertSame(AccessPoint::TOKEN_LENGTH, strlen($point->token));
    }

    public function test_a_strong_old_qr_code_keeps_working_after_the_migration(): void
    {
        config(['gym.checkin_token' => ' k3X9vQ2mL7pR4tZ8wB6nC1dF ']);

        $this->runAccessPointMigration();

        $this->assertSame('Recepción', AccessPoint::findActiveFromQr('k3X9vQ2mL7pR4tZ8wB6nC1dF')?->nombre);
    }

    public function test_with_no_active_point_the_print_page_says_so_instead_of_printing(): void
    {
        AccessPoint::query()->update(['activo' => false]);

        $this->actingAs(User::factory()->create())
            ->get(CheckInQr::getUrl())
            ->assertOk()
            ->assertSee('No hay puntos de acceso activos')
            ->assertDontSee('src="data:image/png;base64,', escape: false);
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
