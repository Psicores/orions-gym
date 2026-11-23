<?php

namespace Tests\Feature;

use App\Enums\SiteSectionEnum;
use App\Filament\Pages\CheckInQr;
use App\Filament\Pages\UserManual;
use App\Filament\Resources\AccessPoints\AccessPointResource;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Qué puede usar un profe en el panel: todo lo de atención a socios, el QR, los planes y los
 * anuncios. La página pública, el personal y el manual son solo de los administradores.
 */
class StaffPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{callable(): string}>
     */
    public static function profeScreens(): array
    {
        return [
            'inicio' => [fn (): string => Filament::getPanel('admin')->getUrl()],
            'clientes' => [fn (): string => ClientResource::getUrl()],
            'ficha de un cliente' => [fn (): string => ClientResource::getUrl('view', ['record' => Client::factory()->create()])],
            'QR de ingreso' => [fn (): string => AccessPointResource::getUrl()],
            'imprimir QR' => [fn (): string => CheckInQr::getUrl()],
            'planes' => [fn (): string => PlanResource::getUrl()],
            'anuncios' => [fn (): string => AnnouncementResource::getUrl()],
        ];
    }

    #[DataProvider('profeScreens')]
    public function test_a_profe_can_use_the_member_service_screens(callable $url): void
    {
        $this->actingAs(User::factory()->create())
            ->get($url())
            ->assertOk();
    }

    /**
     * @return array<string, array{callable(): string}>
     */
    public static function adminScreens(): array
    {
        $screens = [
            'personal' => [fn (): string => UserResource::getUrl()],
            'manual' => [fn (): string => UserManual::getUrl()],
        ];

        foreach (SiteSectionEnum::cases() as $section) {
            $screens['sitio: '.$section->value] = [fn (): string => $section->editPage()::getUrl()];
        }

        return $screens;
    }

    #[DataProvider('adminScreens')]
    public function test_a_profe_gets_forbidden_on_admin_only_screens(callable $url): void
    {
        $this->actingAs(User::factory()->create())
            ->get($url())
            ->assertForbidden();
    }

    #[DataProvider('adminScreens')]
    public function test_an_admin_can_open_every_admin_screen(callable $url): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get($url())
            ->assertOk();
    }

    public function test_the_profe_menu_hides_what_they_cannot_open(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(ClientResource::getUrl())
            ->assertOk()
            ->assertSee(PlanResource::getUrl(), escape: false)
            ->assertSee(AnnouncementResource::getUrl(), escape: false)
            ->assertSee(AccessPointResource::getUrl(), escape: false)
            ->assertDontSee(UserResource::getUrl(), escape: false)
            ->assertDontSee(UserManual::getUrl(), escape: false)
            ->assertDontSee(SiteSectionEnum::Portada->editPage()::getUrl(), escape: false);
    }
}
