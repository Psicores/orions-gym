<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyCreditTest extends TestCase
{
    use RefreshDatabase;

    private const array CREDIT = ['Desarrollado por', 'href="https://devkorestudio.com"', 'DevKore Studio'];

    public function test_the_landing_footer_shows_the_copyright_and_then_the_agency_credit(): void
    {
        $this->get(route('inicio'))
            ->assertOk()
            ->assertSeeInOrder(['© '.date('Y').' Orions', ...self::CREDIT], escape: false);
    }

    public function test_the_member_portal_shows_the_agency_credit_above_the_bottom_navigation(): void
    {
        $this->actingAs(Client::factory()->create(), 'client')
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([...self::CREDIT, 'aria-label="Portal del socio"'], escape: false);
    }

    public function test_the_admin_panel_shows_the_agency_credit_on_its_pages_and_on_its_login(): void
    {
        $this->get(filament()->getPanel('admin')->getLoginUrl())
            ->assertOk()
            ->assertSeeInOrder(self::CREDIT, escape: false);

        $this->actingAs(User::factory()->create())
            ->get(ClientResource::getUrl('index'))
            ->assertOk()
            ->assertSeeInOrder(self::CREDIT, escape: false);
    }
}
