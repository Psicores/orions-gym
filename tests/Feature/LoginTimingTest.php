<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Hashing\HashManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTimingTest extends TestCase
{
    use RefreshDatabase;

    private Hasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();

        Client::factory()->create(['dni' => '30111111', 'email' => 'propia@example.com', 'password' => 'clave-propia-1']);
        Client::factory()->withDniAsPassword()->create(['dni' => '30222222', 'email' => 'dni@example.com']);
        User::factory()->create(['email' => 'profe@example.com', 'password' => 'clave-segura-1']);

        // Cuenta las verificaciones de contraseña cifrada sin cambiar lo que hacen.
        $this->hasher = new class(app('hash')) implements Hasher
        {
            public int $checks = 0;

            public function __construct(private HashManager $inner) {}

            public function info($hashedValue): array
            {
                return $this->inner->info($hashedValue);
            }

            public function make(#[\SensitiveParameter] $value, array $options = []): string
            {
                return $this->inner->make($value, $options);
            }

            public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []): bool
            {
                $this->checks++;

                return $this->inner->check($value, $hashedValue, $options);
            }

            public function needsRehash($hashedValue, array $options = []): bool
            {
                return $this->inner->needsRehash($hashedValue, $options);
            }

            public function __call(string $method, array $arguments): mixed
            {
                return $this->inner->{$method}(...$arguments);
            }
        };

        $this->app->instance('hash', $this->hasher);
        Hash::clearResolvedInstance('hash');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function failedLogins(): array
    {
        return [
            'a dni that belongs to no one' => ['39999999'],
            'a member with their own password' => ['30111111'],
            'the email of a member with their own password' => ['propia@example.com'],
            'a member who still uses the dni' => ['30222222'],
            'a staff email' => ['profe@example.com'],
            'an email that belongs to no one' => ['nadie@example.com'],
        ];
    }

    #[DataProvider('failedLogins')]
    public function test_a_failed_login_does_the_same_work_whether_the_account_exists_or_not(string $usuario): void
    {
        $this->post(route('login.store'), ['usuario' => $usuario, 'password' => 'equivocada-123'])
            ->assertSessionHasErrors(['usuario' => 'El DNI o la contraseña no son correctos.']);

        $this->assertSame(2, $this->hasher->checks);
        $this->assertGuest('client');
        $this->assertGuest('web');
    }

    public function test_members_and_staff_still_log_in_with_the_right_password(): void
    {
        $this->post(route('login.store'), ['usuario' => '30111111', 'password' => 'clave-propia-1'])
            ->assertRedirect(route('member.dashboard'));
        $this->assertAuthenticated('client');
        auth('client')->logout();
        RateLimiter::clear('login-ip-minute|127.0.0.1');

        $this->post(route('login.store'), ['usuario' => '30.222.222', 'password' => '30222222'])
            ->assertRedirect(route('member.dashboard'));
        auth('client')->logout();

        $this->post(route('login.store'), ['usuario' => ' profe@example.com ', 'password' => 'clave-segura-1'])
            ->assertRedirect(Filament::getPanel('admin')->getUrl());
        $this->assertAuthenticated('web');
    }

    public function test_the_filler_hash_is_reused_and_rebuilt_when_the_hashing_cost_changes(): void
    {
        $this->post(route('login.store'), ['usuario' => '39999999', 'password' => 'x']);
        $first = cache('login-dummy-password-hash');

        $this->post(route('login.store'), ['usuario' => '39999998', 'password' => 'x']);
        $this->assertSame($first, cache('login-dummy-password-hash'));

        // Un hash guardado con otro costo (por ejemplo, después de cambiar BCRYPT_ROUNDS) se rehace.
        $oldCost = password_hash('x', PASSWORD_BCRYPT, ['cost' => 5]);
        cache()->forever('login-dummy-password-hash', $oldCost);
        $this->post(route('login.store'), ['usuario' => '39999997', 'password' => 'x']);
        $this->assertNotSame($oldCost, cache('login-dummy-password-hash'));
        $this->assertFalse(Hash::needsRehash(cache('login-dummy-password-hash')));
    }
}
