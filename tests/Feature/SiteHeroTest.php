<?php

namespace Tests\Feature;

use App\Enums\SiteSectionEnum;
use App\Filament\Pages\SiteHero;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SiteHeroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function emptyHero(array $overrides = []): void
    {
        SiteContent::query()->where('section', SiteSectionEnum::Portada)->update([
            'data' => json_encode([...SiteSectionEnum::Portada->emptyContent(), ...$overrides]),
        ]);
    }

    public function test_the_migration_loads_what_the_landing_used_to_show(): void
    {
        $content = SiteContent::dataFor(SiteSectionEnum::Portada);

        $this->assertSame('Fierros', $content['titulo']);
        $this->assertSame('site/portada/video-portada.mp4', $content['video_path']);
        Storage::disk('public')->assertExists('site/portada/video-portada.mp4');
        Storage::disk('public')->assertExists('site/portada/video-portada.webp');

        $this->get(route('inicio'))
            ->assertSee('<title>Orions · Gimnasio</title>', escape: false)
            ->assertSee('content="Orions: gimnasio de fuerza y comunidad.', escape: false)
            ->assertSeeInOrder(['Gimnasio · Fuerza · Comunidad', 'Fierros', 'y constancia', 'Entrenamiento serio, sin vueltas.'])
            ->assertSee(Storage::disk('public')->url('site/portada/video-portada.mp4'), escape: false)
            ->assertSee('poster="'.Storage::disk('public')->url('site/portada/video-portada.webp').'"', escape: false);
    }

    public function test_the_migration_never_overwrites_content_loaded_from_the_panel(): void
    {
        $this->emptyHero(['titulo' => 'Editado']);

        (require database_path('migrations/2026_11_22_141241_add_initial_hero_content_to_site_contents_table.php'))->up();

        $this->assertSame('Editado', SiteContent::dataFor(SiteSectionEnum::Portada)['titulo']);
    }

    public function test_the_screen_is_in_the_site_menu(): void
    {
        $this->get(SiteHero::getUrl())
            ->assertOk()
            ->assertSee('Textos de bienvenida')
            ->assertSee('Pestaña del navegador y Google');
    }

    public function test_saving_publishes_the_new_texts_background_and_search_data(): void
    {
        Storage::fake('public');

        Livewire::test(SiteHero::class)
            ->fillForm([
                'etiqueta' => 'Abrimos en marzo',
                'titulo' => ' Más fuerza ',
                'titulo_destacado' => 'cada día',
                'texto' => 'Vení a probar una clase.',
                'video_path' => UploadedFile::fake()->create('fondo.mp4', 900, 'video/mp4'),
                'poster_path' => UploadedFile::fake()->image('fondo.jpg', 1920, 1080),
                'titulo_pagina' => 'Gimnasio Ejemplo',
                'descripcion' => 'Fuerza y comunidad en el centro.',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Cambios guardados');

        $content = SiteContent::dataFor(SiteSectionEnum::Portada);
        $this->assertSame('Más fuerza', $content['titulo']);
        Storage::disk('public')->assertExists($content['video_path']);
        Storage::disk('public')->assertExists($content['poster_path']);

        $this->get(route('inicio'))
            ->assertSee('<title>Gimnasio Ejemplo</title>', escape: false)
            ->assertSee('<meta name="description" content="Fuerza y comunidad en el centro.">', escape: false)
            ->assertSeeInOrder(['Abrimos en marzo', 'Más fuerza', 'cada día', 'Vení a probar una clase.'])
            ->assertSee(Storage::disk('public')->url($content['video_path']), escape: false);
    }

    public function test_without_video_the_image_is_the_background(): void
    {
        $this->emptyHero(['poster_path' => 'site/portada/video-portada.webp', 'titulo' => 'Hola']);

        $html = $this->get(route('inicio'))->assertOk()->getContent();

        $this->assertStringNotContainsString('video-portada.mp4', $html);
        $this->assertMatchesRegularExpression('#<img src="[^"]*/storage/site/portada/video-portada\.webp" alt="" aria-hidden="true"#', $html);
    }

    public function test_an_empty_hero_still_shows_the_menu_and_keeps_a_title_for_search_engines(): void
    {
        config(['app.name' => 'Gimnasio']);
        $this->emptyHero();

        $this->get(route('inicio'))
            ->assertOk()
            ->assertSee('<title>Gimnasio</title>', escape: false)
            ->assertSee('<h1 class="sr-only">Gimnasio</h1>', escape: false)
            ->assertSee('Ver planes')
            ->assertSee('Socios')
            ->assertDontSee('Fierros');
    }

    public function test_texts_cannot_exceed_their_limits(): void
    {
        Livewire::test(SiteHero::class)
            ->fillForm(['descripcion' => str_repeat('a', 161)])
            ->call('save')
            ->assertHasFormErrors(['descripcion']);

        $this->assertSame('Fierros', SiteContent::dataFor(SiteSectionEnum::Portada)['titulo']);
    }
}
