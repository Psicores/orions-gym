<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class StaffAccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['email' => 'dueno@example.com']);
        $this->actingAs($this->admin);
    }

    public function test_only_admins_see_the_staff_screen(): void
    {
        $this->get(UserResource::getUrl())->assertOk()->assertSee('dueno@example.com');

        $this->actingAs(User::factory()->create())
            ->get(UserResource::getUrl())
            ->assertForbidden();
    }

    public function test_an_admin_creates_a_profe_who_can_log_in_right_away(): void
    {
        Livewire::test(ManageUsers::class)
            ->callAction(CreateAction::class, ['name' => 'Ana Profe', 'email' => 'ana@example.com', 'password' => 'clave-ana-1'])
            ->assertHasNoFormErrors();

        $profe = User::query()->where('email', 'ana@example.com')->sole();
        $this->assertSame(UserRoleEnum::Profe, $profe->role);
        $this->assertTrue($profe->hasVerifiedEmail());

        Auth::guard('web')->logout();
        $this->post(route('login.store'), ['usuario' => 'ana@example.com', 'password' => 'clave-ana-1'])
            ->assertRedirect(Filament::getPanel('admin')->getUrl());
        $this->assertAuthenticatedAs($profe, 'web');
    }

    public function test_creating_requires_a_good_password_and_an_unused_email(): void
    {
        $gone = User::factory()->create(['email' => 'ex@example.com']);
        $gone->delete();

        Livewire::test(ManageUsers::class)
            ->callAction(CreateAction::class, ['name' => 'X', 'email' => 'ex@example.com', 'password' => 'corta'])
            ->assertHasFormErrors(['email' => 'unique', 'password']);

        Livewire::test(ManageUsers::class)
            ->callAction(CreateAction::class, ['name' => 'X', 'email' => 'x@example.com', 'password' => null])
            ->assertHasFormErrors(['password' => 'required']);
    }

    public function test_editing_without_a_password_keeps_the_old_one_and_can_promote(): void
    {
        $profe = User::factory()->create(['password' => 'clave-vieja-1']);

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make(EditAction::class)->table($profe), ['name' => 'Nuevo nombre', 'password' => '', 'role' => UserRoleEnum::Admin->value])
            ->assertHasNoFormErrors();

        $profe->refresh();
        $this->assertSame('Nuevo nombre', $profe->name);
        $this->assertTrue(Hash::check('clave-vieja-1', $profe->password));
        $this->assertSame(UserRoleEnum::Admin, $profe->role);
    }

    public function test_an_admin_cannot_change_their_own_role_or_remove_themselves(): void
    {
        Livewire::test(ManageUsers::class)
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($this->admin))
            ->callAction(TestAction::make(EditAction::class)->table($this->admin), ['role' => UserRoleEnum::Profe->value])
            ->assertHasNoFormErrors();

        $this->assertSame(UserRoleEnum::Admin, $this->admin->refresh()->role);
    }

    public function test_a_removed_profe_cannot_log_in_but_still_appears_on_their_payments(): void
    {
        $profe = User::factory()->create(['name' => 'Ana Profe', 'email' => 'ana@example.com', 'password' => 'clave-ana-1']);
        $payment = Payment::factory()->create(['registrado_por_user_id' => $profe->id]);

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($profe));

        $this->assertSoftDeleted($profe);
        $this->assertSame('Ana Profe', $payment->refresh()->registradoPor->name);

        Auth::guard('web')->logout();
        $this->post(route('login.store'), ['usuario' => 'ana@example.com', 'password' => 'clave-ana-1'])
            ->assertSessionHasErrors();
        $this->assertGuest('web');
    }

    public function test_a_removed_profe_loses_an_open_session(): void
    {
        $profe = User::factory()->create(['email' => 'ana@example.com', 'password' => 'clave-ana-1']);
        Auth::guard('web')->logout();
        $this->post(route('login.store'), ['usuario' => 'ana@example.com', 'password' => 'clave-ana-1']);
        $this->app['auth']->forgetGuards();
        $this->get(Filament::getPanel('admin')->getUrl())->assertOk();

        $profe->delete();
        $this->app['auth']->forgetGuards();

        $this->get(Filament::getPanel('admin')->getUrl())->assertRedirect();
        $this->assertGuest('web');
    }
}
