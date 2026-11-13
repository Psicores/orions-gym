<?php

namespace Tests\Feature;

use App\Enums\SiteSectionEnum;
use App\Filament\Pages\ComingSoon;
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

        $this->actingAs(User::factory()->create());
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
                ComingSoon::getUrl(['section' => 'portada']),
                ComingSoon::getUrl(['section' => 'horarios']),
                ComingSoon::getUrl(['section' => 'comunidad']),
                ComingSoon::getUrl(['section' => 'opiniones']),
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
    public function test_each_future_site_section_shows_the_coming_soon_screen(SiteSectionEnum $section): void
    {
        $this->get(ComingSoon::getUrl(['section' => $section->value]))
            ->assertOk()
            ->assertSee('Próximamente')
            ->assertSee($section->getLabel())
            ->assertSee($section->getDescription())
            ->assertSee(route('inicio'), escape: false);
    }

    public function test_only_the_section_being_viewed_is_marked_as_active_in_the_sidebar(): void
    {
        $html = $this->get(ComingSoon::getUrl(['section' => 'horarios']))->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<li[^>]*class="[^"]*fi-sidebar-item fi-active[^"]*"[^>]*>\s*<a[^>]*href="([^"]+)"/', $html, $matches));
        $this->assertSame(ComingSoon::getUrl(['section' => 'horarios']), $matches[1][0]);
    }

    public function test_an_unknown_site_section_is_not_found(): void
    {
        $this->get(ComingSoon::getUrl(['section' => 'no-existe']))->assertNotFound();
    }

    public function test_the_coming_soon_screen_requires_login(): void
    {
        auth()->logout();

        $this->get(ComingSoon::getUrl(['section' => 'portada']))->assertRedirect(filament()->getLoginUrl());
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
