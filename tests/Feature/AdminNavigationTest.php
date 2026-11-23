<?php

namespace Tests\Feature;

use App\Enums\SiteSectionEnum;
use App\Filament\Pages\SiteCommunity;
use App\Filament\Pages\SiteHero;
use App\Filament\Pages\SiteReviews;
use App\Filament\Pages\SiteSchedule;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_the_sidebar_lists_the_site_group_after_the_dashboard_and_clients_with_plans_first(): void
    {
        $this->get(ClientResource::getUrl('index'))
            ->assertOk()
            ->assertSeeInOrder([
                Dashboard::getUrl(),
                ClientResource::getUrl('index'),
                'Admin del sitio',
                PlanResource::getUrl('index'),
                AnnouncementResource::getUrl('index'),
                SiteHero::getUrl(),
                SiteSchedule::getUrl(),
                SiteCommunity::getUrl(),
                SiteReviews::getUrl(),
            ], escape: false);
    }

    public function test_the_sidebar_styles_are_loaded(): void
    {
        $this->get(ClientResource::getUrl('index'))
            ->assertSee('.fi-sidebar-item.fi-active > .fi-sidebar-item-btn', escape: false);
    }

    public function test_the_admin_titles_use_the_landing_display_font(): void
    {
        $this->get(ClientResource::getUrl('index'))
            ->assertOk()
            ->assertSee('font-family: "Anton"', escape: false)
            ->assertSee('.fi-header-heading,', escape: false)
            ->assertDontSee('font-family: "Instrument Sans"', escape: false);
    }

    #[DataProvider('siteSections')]
    public function test_each_site_section_has_its_own_screen_with_what_it_changes(SiteSectionEnum $section): void
    {
        $this->get($section->editPage()::getUrl())
            ->assertOk()
            ->assertSee($section->getLabel())
            ->assertSee($section->getDescription())
            ->assertSee('Ver en el sitio')
            ->assertDontSee('Próximamente');
    }

    public function test_only_the_section_being_viewed_is_marked_as_active_in_the_sidebar(): void
    {
        $html = $this->get(SiteSchedule::getUrl())->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<li[^>]*class="[^"]*fi-sidebar-item fi-active[^"]*"[^>]*>\s*<a[^>]*href="([^"]+)"/', $html, $matches));
        $this->assertSame(SiteSchedule::getUrl(), $matches[1][0]);
    }

    public function test_the_old_coming_soon_addresses_no_longer_exist(): void
    {
        $this->get('/admin/sitio/portada')->assertNotFound();
    }

    public function test_the_site_section_screens_require_login(): void
    {
        auth()->logout();

        $this->get(SiteHero::getUrl())->assertRedirect(route('login'));
    }

    /**
     * @return array<string, array{SiteSectionEnum}>
     */
    public static function siteSections(): array
    {
        return collect(SiteSectionEnum::cases())
            ->mapWithKeys(fn (SiteSectionEnum $section): array => [$section->value => [$section]])
            ->all();
    }
}
