<?php

namespace Tests\Feature;

use App\Enums\SiteSectionEnum;
use App\Models\Plan;
use App\Models\SiteContent;
use Database\Seeders\DemoSiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use RefreshDatabase;

    private const string MAP = 'https://www.google.com/maps/embed?pb=!1m18!ejemplo';

    public function test_with_nothing_loaded_the_landing_shows_no_invented_data(): void
    {
        Plan::factory()->create();
        $this->get(route('inicio'))
            ->assertOk()
            ->assertDontSee('Socio de ejemplo')
            ->assertDontSee('Dirección')
            ->assertDontSee('id="titulo-opiniones"', escape: false)
            ->assertDontSee('id="ubicacion"', escape: false)
            ->assertDontSee('href="#ubicacion"', escape: false)
            ->assertDontSee('<iframe', escape: false)
            ->assertDontSee('Mapa próximamente');
    }

    public function test_the_landing_shows_the_schedule_address_and_map_loaded_from_the_panel(): void
    {
        SiteContent::query()->create(['section' => SiteSectionEnum::Horarios, 'data' => [
            'horarios' => [['dias' => 'Lunes a viernes', 'horas' => '07:00 a 22:00']],
            'direccion' => 'San Martín 450',
            'mapa_url' => self::MAP,
        ]]);

        $this->get(route('inicio'))
            ->assertOk()
            ->assertSee('id="ubicacion"', escape: false)
            ->assertSeeInOrder(['Horarios', 'Lunes a viernes', '07:00 a 22:00', 'Ubicación', 'San Martín 450'])
            ->assertSee('src="'.e(self::MAP).'"', escape: false);
    }

    public function test_each_part_of_the_location_section_appears_only_when_it_has_data(): void
    {
        Plan::factory()->create();
        SiteContent::query()->create(['section' => SiteSectionEnum::Horarios, 'data' => [
            'horarios' => [],
            'direccion' => 'San Martín 450',
            'mapa_url' => null,
        ]]);

        $this->get(route('inicio'))
            ->assertSee('San Martín 450')
            ->assertSee('href="#ubicacion"', escape: false)
            ->assertDontSee('<dl class="mt-8 divide-y', escape: false)
            ->assertDontSee('<iframe', escape: false);
    }

    public function test_a_map_that_is_not_a_google_maps_embed_is_never_shown(): void
    {
        SiteContent::query()->create(['section' => SiteSectionEnum::Horarios, 'data' => [
            'horarios' => [],
            'direccion' => null,
            'mapa_url' => 'https://sitio-malicioso.example/mapa',
        ]]);

        $this->get(route('inicio'))
            ->assertDontSee('sitio-malicioso.example')
            ->assertDontSee('id="ubicacion"', escape: false);

        $this->assertTrue(SiteContent::isAllowedMapUrl(self::MAP));
        $this->assertFalse(SiteContent::isAllowedMapUrl('https://www.google.com.evil.example/maps/embed'));
        $this->assertFalse(SiteContent::isAllowedMapUrl(null));
    }

    public function test_the_landing_shows_the_reviews_with_their_stars(): void
    {
        SiteContent::query()->create(['section' => SiteSectionEnum::Opiniones, 'data' => ['opiniones' => [
            ['autor' => 'Lucía', 'texto' => 'Excelente lugar.', 'estrellas' => 4],
        ]]]);

        $this->get(route('inicio'))
            ->assertSee('Lo que dicen los socios')
            ->assertSeeInOrder(['4 de 5 estrellas', 'Excelente lugar.', 'Lucía'])
            ->assertDontSee('Lo que dicen en Google');
    }

    public function test_review_text_is_escaped(): void
    {
        SiteContent::query()->create(['section' => SiteSectionEnum::Opiniones, 'data' => ['opiniones' => [
            ['autor' => '<script>alert(1)</script>', 'texto' => '<b>hola</b>', 'estrellas' => 5],
        ]]]);

        $this->get(route('inicio'))
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('&lt;b&gt;hola&lt;/b&gt;', escape: false);
    }

    public function test_section_data_is_completed_with_empty_values_and_ignores_unknown_keys(): void
    {
        $this->assertSame(['horarios' => [], 'direccion' => null, 'mapa_url' => null], SiteContent::dataFor(SiteSectionEnum::Horarios));

        SiteContent::query()->create(['section' => SiteSectionEnum::Horarios, 'data' => ['direccion' => 'Calle 1', 'otra' => 'x']]);

        $this->assertSame(['horarios' => [], 'direccion' => 'Calle 1', 'mapa_url' => null], SiteContent::dataFor(SiteSectionEnum::Horarios));
    }

    public function test_the_demo_content_is_loaded_only_when_asked_and_never_overwrites_the_real_one(): void
    {
        Storage::fake('public');
        config(['app.admin' => ['name' => 'Dueño', 'email' => 'dueno@example.com', 'password' => 'secreto-123']]);

        $this->seed();
        $this->assertSame(0, SiteContent::query()->count());

        SiteContent::query()->create(['section' => SiteSectionEnum::Horarios, 'data' => ['direccion' => 'La real 10']]);
        config(['gym.seed_demo_data' => true]);
        $this->seed();

        $this->assertSame(['direccion' => 'La real 10'], SiteContent::query()->where('section', SiteSectionEnum::Horarios)->value('data'));
        $this->assertSame(DemoSiteContentSeeder::CONTENT['opiniones'], SiteContent::query()->where('section', SiteSectionEnum::Opiniones)->value('data'));
    }

    public function test_removing_the_demo_data_deletes_only_the_untouched_demo_content(): void
    {
        Storage::fake('public');
        $this->seed(DemoSiteContentSeeder::class);
        $edited = DemoSiteContentSeeder::CONTENT['horarios'];
        $edited['direccion'] = 'San Martín 450';
        SiteContent::query()->where('section', SiteSectionEnum::Horarios)->update(['data' => json_encode($edited)]);

        $this->artisan('demo:remove', ['--force' => true])->assertSuccessful();

        $this->assertSame([SiteSectionEnum::Horarios], SiteContent::query()->pluck('section')->all());
    }
}
