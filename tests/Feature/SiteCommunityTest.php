<?php

namespace Tests\Feature;

use App\Enums\SiteSectionEnum;
use App\Filament\Pages\ComingSoon;
use App\Filament\Pages\SiteCommunity;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SiteCommunityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function runInitialContentMigration(): void
    {
        (require database_path('migrations/2026_11_22_135716_add_initial_community_content_to_site_contents_table.php'))->up();
    }

    public function test_the_migration_loads_what_the_landing_used_to_show_so_the_section_never_disappears(): void
    {
        $content = SiteContent::dataFor(SiteSectionEnum::Comunidad);

        $this->assertSame('Nadie entrena solo', $content['titulo']);
        $this->assertSame('site/comunidad/video-comunidad.mp4', $content['video_path']);
        $this->assertCount(2, $content['fotos']);
        foreach (['video-comunidad.mp4', 'video-comunidad.webp', 'barra-olimpica.webp', 'kettlebell.webp'] as $file) {
            Storage::disk('public')->assertExists('site/comunidad/'.$file);
        }

        $this->get(route('inicio'))
            ->assertSee('href="#comunidad"', escape: false)
            ->assertSeeInOrder(['La comunidad', 'Nadie entrena solo', 'Orions es un lugar para venir a trabajar', 'Venís por el físico.', 'Te quedás por la gente.'])
            ->assertSee('/storage/site/comunidad/video-comunidad.mp4', escape: false)
            ->assertSee('poster="'.Storage::disk('public')->url('site/comunidad/video-comunidad.webp').'"', escape: false)
            ->assertSee('alt="Barra olímpica cargada sobre el piso del gimnasio"', escape: false);
    }

    public function test_the_migration_never_overwrites_content_loaded_from_the_panel(): void
    {
        SiteContent::query()->where('section', SiteSectionEnum::Comunidad)->update(['data' => json_encode(['titulo' => 'Editado'])]);

        $this->runInitialContentMigration();

        $this->assertSame('Editado', SiteContent::dataFor(SiteSectionEnum::Comunidad)['titulo']);
        $this->assertSame(1, SiteContent::query()->where('section', SiteSectionEnum::Comunidad)->count());
    }

    public function test_the_screen_replaces_coming_soon_in_the_site_menu(): void
    {
        $this->get(SiteCommunity::getUrl())
            ->assertOk()
            ->assertSee('Video (MP4)')
            ->assertDontSee('Próximamente');

        $this->get(ComingSoon::getUrl(['section' => 'comunidad']))->assertRedirect(SiteCommunity::getUrl());
    }

    public function test_saving_without_touching_anything_keeps_the_texts_and_files(): void
    {
        $before = SiteContent::dataFor(SiteSectionEnum::Comunidad);

        Livewire::test(SiteCommunity::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($before, SiteContent::dataFor(SiteSectionEnum::Comunidad));
    }

    public function test_new_texts_video_and_photo_are_published_on_the_landing(): void
    {
        Storage::fake('public');

        Livewire::test(SiteCommunity::class)
            ->fillForm([
                'etiqueta' => 'El equipo',
                'titulo' => ' Entrenamos juntos ',
                'texto' => "Primera línea.\nSegunda línea.",
                'video_path' => UploadedFile::fake()->create('nuevo.mp4', 800, 'video/mp4'),
                'poster_path' => null,
                'frase' => 'Arriba',
                'frase_destacada' => 'siempre.',
                'fotos' => [['path' => [UploadedFile::fake()->image('salon.jpg', 1600, 900)], 'alt' => 'Salón principal']],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Cambios guardados');

        $content = SiteContent::dataFor(SiteSectionEnum::Comunidad);
        $this->assertSame('Entrenamos juntos', $content['titulo']);
        $this->assertNull($content['poster_path']);
        $this->assertStringStartsWith('site/comunidad/', $content['video_path']);
        Storage::disk('public')->assertExists($content['video_path']);
        Storage::disk('public')->assertExists($content['fotos'][0]['path']);

        $html = $this->get(route('inicio'))
            ->assertSeeInOrder(['El equipo', 'Entrenamos juntos', 'Primera línea.', 'Arriba', 'siempre.'])
            ->assertSee(Storage::disk('public')->url($content['video_path']), escape: false)
            ->assertSee('alt="Salón principal"', escape: false)
            ->getContent();

        // Con una sola foto, ocupa todo el ancho; sin imagen fija, el video no lleva poster.
        $this->assertMatchesRegularExpression('#alt="Salón principal"[^>]*class="[^"]*sm:col-span-3#s', $html);
        $this->assertDoesNotMatchRegularExpression('#data-reproducir-al-ver[^>]*poster=#', $html);
    }

    public function test_each_photo_needs_a_description_and_cannot_be_an_svg(): void
    {
        Storage::fake('public');

        Livewire::test(SiteCommunity::class)
            ->fillForm(['fotos' => [['path' => [UploadedFile::fake()->image('salon.jpg')], 'alt' => '']]])
            ->call('save')
            ->assertHasFormErrors();

        Livewire::test(SiteCommunity::class)
            ->fillForm(['fotos' => [['path' => [UploadedFile::fake()->createWithContent('dibujo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')], 'alt' => 'Dibujo']]])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame('Nadie entrena solo', SiteContent::dataFor(SiteSectionEnum::Comunidad)['titulo']);
    }

    public function test_only_mp4_videos_are_accepted(): void
    {
        Storage::fake('public');

        Livewire::test(SiteCommunity::class)
            ->fillForm(['video_path' => UploadedFile::fake()->create('pelicula.mov', 500, 'video/quicktime'), 'fotos' => []])
            ->call('save')
            ->assertHasFormErrors(['video_path']);
    }

    public function test_emptying_the_section_hides_it_and_the_hero_link(): void
    {
        Livewire::test(SiteCommunity::class)
            ->fillForm([
                'etiqueta' => '', 'titulo' => '', 'texto' => '', 'video_path' => null, 'poster_path' => null,
                'frase' => '', 'frase_destacada' => '', 'fotos' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get(route('inicio'))
            ->assertOk()
            ->assertDontSee('id="comunidad"', escape: false)
            ->assertDontSee('href="#comunidad"', escape: false);
    }
}
