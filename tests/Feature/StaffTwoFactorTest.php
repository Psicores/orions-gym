<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class StaffTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'JBSWY3DPEHPK3PXP';

    private function staffWithTwoFactor(): User
    {
        $user = User::factory()->create(['email' => 'profe@example.com', 'password' => 'clave-segura-1']);
        $user->saveAppAuthenticationSecret(self::SECRET);

        return $user;
    }

    public function test_staff_without_two_factor_still_log_in_from_the_shared_login(): void
    {
        $user = User::factory()->create(['email' => 'profe@example.com', 'password' => 'clave-segura-1']);

        $this->post(route('login.store'), ['usuario' => 'profe@example.com', 'password' => 'clave-segura-1'])
            ->assertRedirect(Filament::getPanel('admin')->getUrl());

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_staff_with_two_factor_finish_the_shared_login_with_the_app_code(): void
    {
        $user = $this->staffWithTwoFactor();

        $this->post(route('login.store'), ['usuario' => 'profe@example.com', 'password' => 'clave-segura-1'])
            ->assertRedirect(route('login.code'));
        $this->assertGuest('web');
        $this->get(Filament::getPanel('admin')->getUrl())->assertRedirect();
        $this->assertGuest('web');

        $this->get(route('login.code'))->assertOk()->assertSee('Código');

        $this->post(route('login.code.store'), ['codigo' => app(Google2FA::class)->getCurrentOtp(self::SECRET)])
            ->assertRedirect(Filament::getPanel('admin')->getUrl());
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_a_wrong_code_does_not_open_the_session_and_is_throttled(): void
    {
        $this->staffWithTwoFactor();
        $this->post(route('login.store'), ['usuario' => 'profe@example.com', 'password' => 'clave-segura-1']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.code.store'), ['codigo' => '000000'])->assertSessionHasErrors(['codigo' => 'El código no es correcto.']);
        }

        $this->post(route('login.code.store'), ['codigo' => app(Google2FA::class)->getCurrentOtp(self::SECRET)])
            ->assertSessionHasErrors('codigo');
        $this->assertGuest('web');
    }

    public function test_a_recovery_code_finishes_the_login_once(): void
    {
        $user = $this->staffWithTwoFactor();
        $provider = Filament::getPanel('admin')->getMultiFactorAuthenticationProviders()['app'];
        $provider->saveRecoveryCodes($user, ['abcde-fghij', 'klmno-pqrst']);

        $this->post(route('login.store'), ['usuario' => 'profe@example.com', 'password' => 'clave-segura-1']);
        $this->post(route('login.code.store'), ['codigo' => 'abcde-fghij'])
            ->assertRedirect(Filament::getPanel('admin')->getUrl());
        $this->assertAuthenticatedAs($user, 'web');

        auth()->guard('web')->logout();
        $this->flushSession();

        $this->post(route('login.store'), ['usuario' => 'profe@example.com', 'password' => 'clave-segura-1']);
        $this->post(route('login.code.store'), ['codigo' => 'abcde-fghij'])->assertSessionHasErrors('codigo');
        $this->assertGuest('web');
    }

    public function test_the_code_screen_needs_the_password_first_and_expires(): void
    {
        $this->staffWithTwoFactor();

        $this->get(route('login.code'))->assertRedirect(route('login'));
        $this->post(route('login.code.store'), ['codigo' => app(Google2FA::class)->getCurrentOtp(self::SECRET)])
            ->assertRedirect(route('login'));

        $this->post(route('login.store'), ['usuario' => 'profe@example.com', 'password' => 'clave-segura-1']);
        $this->travel(11)->minutes();

        $this->post(route('login.code.store'), ['codigo' => app(Google2FA::class)->getCurrentOtp(self::SECRET)])
            ->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_the_two_factor_secret_is_stored_encrypted_and_never_serialized(): void
    {
        $user = User::factory()->create();
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $user->saveAppAuthenticationRecoveryCodes(['codigo-uno', 'codigo-dos']);

        $this->assertNotSame('JBSWY3DPEHPK3PXP', $user->getRawOriginal('app_authentication_secret'));
        $this->assertStringNotContainsString('codigo-uno', $user->getRawOriginal('app_authentication_recovery_codes'));
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->getAppAuthenticationSecret());
        $this->assertArrayNotHasKey('app_authentication_secret', $user->toArray());
        $this->assertArrayNotHasKey('app_authentication_recovery_codes', $user->toArray());
    }

    public function test_staff_can_open_their_profile_to_manage_two_factor(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(Filament::getPanel('admin')->getProfileUrl())->assertOk();
        Livewire::test(EditProfile::class)->assertOk();
    }

    public function test_the_panel_login_asks_for_the_code_before_opening_the_session(): void
    {
        $user = User::factory()->create(['email' => 'profe@example.com', 'password' => 'clave-segura-1']);
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'profe@example.com', 'password' => 'clave-segura-1'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertNotSet('userUndertakingMultiFactorAuthentication', null);

        $this->assertGuest('web');
    }

    public function test_the_console_command_disables_two_factor_for_a_locked_out_account(): void
    {
        $user = User::factory()->create(['email' => 'profe@example.com']);
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $user->saveAppAuthenticationRecoveryCodes(['codigo-uno']);

        $this->artisan('staff:disable-two-factor', ['email' => 'profe@example.com'])->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->getAppAuthenticationSecret());
        $this->assertNull($user->getAppAuthenticationRecoveryCodes());

        $this->artisan('staff:disable-two-factor', ['email' => 'nadie@example.com'])->assertFailed();
    }
}
