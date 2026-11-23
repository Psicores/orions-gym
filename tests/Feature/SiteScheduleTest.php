<?php

namespace Tests\Feature;

use App\Enums\SiteSectionEnum;
use App\Filament\Pages\SiteSchedule;
use App\Models\Client;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteScheduleTest extends TestCase
{
    use RefreshDatabase;

    private const string MAP = 'https://www.google.com/maps/embed?pb=!1m18!1m12&amp;x=1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_the_screen_is_in_the_site_menu(): void
    {
        $this->get(SiteSchedule::getUrl())
            ->assertOk()
            ->assertSee('Horarios y ubicación')
            ->assertSee('Agregar franja horaria')
            ->assertDontSee('Próximamente');
    }

    public function test_saving_publishes_the_schedule_address_and_map_on_the_landing(): void
    {
        Livewire::test(SiteSchedule::class)
            ->fillForm([
                'horarios' => [
                    ['dias' => '  Lunes a viernes ', 'horas' => '07:00 a 22:00'],
                    ['dias' => 'Domingos', 'horas' => 'Cerrado'],
                ],
                'direccion' => ' San Martín 450, Trelew ',
                'mapa_url' => '<iframe src="'.self::MAP.'" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Cambios guardados');

        $this->assertSame([
            'horarios' => [
                ['dias' => 'Lunes a viernes', 'horas' => '07:00 a 22:00'],
                ['dias' => 'Domingos', 'horas' => 'Cerrado'],
            ],
            'direccion' => 'San Martín 450, Trelew',
            'mapa_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12&x=1',
        ], SiteContent::dataFor(SiteSectionEnum::Horarios));

        $this->get(route('inicio'))
            ->assertSeeInOrder(['Lunes a viernes', '07:00 a 22:00', 'Domingos', 'Cerrado', 'San Martín 450, Trelew'])
            ->assertSee('<iframe', escape: false);
    }

    public function test_the_form_shows_what_is_already_saved(): void
    {
        SiteContent::query()->create(['section' => SiteSectionEnum::Horarios, 'data' => [
            'horarios' => [['dias' => 'Sábados', 'horas' => '10 a 13']],
            'direccion' => 'Calle 1',
            'mapa_url' => null,
        ]]);

        Livewire::test(SiteSchedule::class)
            ->assertSchemaStateSet(['direccion' => 'Calle 1'])
            ->assertSee('Sábados');
    }

    public function test_only_a_google_maps_embed_is_accepted_as_map(): void
    {
        Livewire::test(SiteSchedule::class)
            ->fillForm(['mapa_url' => 'https://sitio-malicioso.example/mapa'])
            ->call('save')
            ->assertHasFormErrors(['mapa_url']);

        Livewire::test(SiteSchedule::class)
            ->fillForm(['mapa_url' => '<iframe src="https://evil.example/maps/embed"></iframe>'])
            ->call('save')
            ->assertHasFormErrors(['mapa_url']);

        $this->assertSame(0, SiteContent::query()->where('section', SiteSectionEnum::Horarios)->count());
    }

    public function test_each_time_slot_needs_days_and_hours(): void
    {
        Livewire::test(SiteSchedule::class)
            ->fillForm(['horarios' => [['dias' => 'Lunes', 'horas' => '']]])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame(0, SiteContent::query()->where('section', SiteSectionEnum::Horarios)->count());
    }

    public function test_clearing_everything_hides_the_section_on_the_landing(): void
    {
        SiteContent::query()->create(['section' => SiteSectionEnum::Horarios, 'data' => [
            'horarios' => [['dias' => 'Sábados', 'horas' => '10 a 13']],
            'direccion' => 'Calle 1',
            'mapa_url' => null,
        ]]);

        Livewire::test(SiteSchedule::class)
            ->fillForm(['horarios' => [], 'direccion' => '', 'mapa_url' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['horarios' => [], 'direccion' => null, 'mapa_url' => null], SiteContent::dataFor(SiteSectionEnum::Horarios));
        $this->get(route('inicio'))->assertDontSee('id="ubicacion"', escape: false);
    }

    public function test_members_cannot_open_the_screen(): void
    {
        auth()->logout();

        $this->actingAs(Client::factory()->create(), 'client')
            ->get(SiteSchedule::getUrl())
            ->assertRedirect(route('login'));
    }
}
