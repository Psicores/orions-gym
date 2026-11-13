<?php

namespace Tests\Feature;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Models\Announcement;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_the_active_scope_returns_only_announcements_switched_on_and_inside_their_dates(): void
    {
        $this->travelTo('2026-11-12 12:00:00');
        $always = Announcement::factory()->create();
        $running = Announcement::factory()->create(['starts_at' => '2026-11-10 08:00:00', 'ends_at' => '2026-11-15 20:00:00']);
        $startedWithoutEnd = Announcement::factory()->create(['starts_at' => '2026-11-12 12:00:00']);
        $endsLater = Announcement::factory()->create(['ends_at' => '2026-11-12 12:00:00']);
        Announcement::factory()->inactive()->create();
        Announcement::factory()->scheduled()->create();
        Announcement::factory()->expired()->create();
        Announcement::factory()->inactive()->create(['starts_at' => '2026-11-10 08:00:00', 'ends_at' => '2026-11-15 20:00:00']);

        $this->assertEqualsCanonicalizing(
            [$always->id, $running->id, $startedWithoutEnd->id, $endsLater->id],
            Announcement::query()->active()->pluck('id')->all(),
        );
    }

    public function test_the_model_agrees_with_the_scope_and_names_each_situation(): void
    {
        $announcements = [
            'Visible' => Announcement::factory()->create(),
            'Apagado' => Announcement::factory()->inactive()->create(),
            'Programado' => Announcement::factory()->scheduled()->create(),
            'Finalizado' => Announcement::factory()->expired()->create(),
        ];
        $activeIds = Announcement::query()->active()->pluck('id');

        foreach ($announcements as $situacion => $announcement) {
            $this->assertSame($situacion, $announcement->situacion());
            $this->assertSame($activeIds->contains($announcement->id), $announcement->isVisibleNow());
        }
    }

    public function test_a_client_can_be_recorded_as_having_dismissed_an_announcement(): void
    {
        $this->freezeTime();
        $announcement = Announcement::factory()->create();
        $client = Client::factory()->create();

        $client->announcements()->attach($announcement, ['read_at' => now()]);

        $this->assertTrue($client->announcements()->first()->pivot->read_at !== null);
        $this->assertTrue($announcement->clients()->first()->is($client));
        $this->assertDatabaseHas('announcement_client', [
            'announcement_id' => $announcement->id,
            'client_id' => $client->id,
            'read_at' => now(),
        ]);
    }

    public function test_deleting_an_announcement_forgets_who_dismissed_it(): void
    {
        $announcement = Announcement::factory()->create();
        Client::factory()->create()->announcements()->attach($announcement, ['read_at' => now()]);

        $announcement->delete();

        $this->assertDatabaseCount('announcement_client', 0);
    }

    public function test_the_landing_shows_the_active_announcements_to_visitors_with_safe_content(): void
    {
        auth()->logout();
        Storage::fake('public');
        Announcement::factory()->create([
            'titulo' => 'Llegaron las barras nuevas',
            'contenido' => '<p onclick="robar()">Sumamos <strong>cuatro barras</strong>.</p><script>alert(1)</script>',
            'imagen_path' => 'announcements/banner.jpg',
        ]);
        Announcement::factory()->inactive()->create(['titulo' => 'Anuncio apagado']);
        Announcement::factory()->scheduled()->create(['titulo' => 'Anuncio futuro']);
        Announcement::factory()->expired()->create(['titulo' => 'Anuncio terminado']);

        $this->get(route('inicio'))
            ->assertOk()
            ->assertSeeInOrder(['Planes', 'Novedades', 'titulo-novedades', 'Llegaron las barras nuevas'], escape: false)
            ->assertSee('<p>Sumamos <strong>cuatro barras</strong>.</p>', escape: false)
            ->assertSee(Storage::disk('public')->url('announcements/banner.jpg'), escape: false)
            ->assertDontSee('robar()', escape: false)
            ->assertDontSee('alert(1)', escape: false)
            ->assertDontSee('Anuncio apagado')
            ->assertDontSee('Anuncio futuro')
            ->assertDontSee('Anuncio terminado');
    }

    public function test_the_landing_hides_the_news_button_when_there_is_nothing_to_announce(): void
    {
        Announcement::factory()->expired()->create();

        $this->get(route('inicio'))
            ->assertOk()
            ->assertDontSee('Novedades')
            ->assertDontSee('titulo-novedades', escape: false);
    }

    public function test_announcements_live_in_the_site_group_on_a_single_page(): void
    {
        $this->assertSame(['index'], array_keys(AnnouncementResource::getPages()));
        $this->assertSame('Admin del sitio', AnnouncementResource::getNavigationGroup());

        $this->get(AnnouncementResource::getUrl('index'))->assertOk();
    }

    public function test_a_text_only_announcement_is_created_from_the_modal_and_starts_active(): void
    {
        Livewire::test(ManageAnnouncements::class)
            ->callAction(CreateAction::class, [
                'titulo' => 'Feriado del lunes',
                'contenido' => '<p>Abrimos de 9 a 13.</p>',
            ])
            ->assertHasNoFormErrors();

        $announcement = Announcement::query()->sole();
        $this->assertSame('Feriado del lunes', $announcement->titulo);
        $this->assertStringContainsString('Abrimos de 9 a 13.', $announcement->contenido);
        $this->assertTrue($announcement->is_active);
        $this->assertNull($announcement->imagen_path);
        $this->assertNull($announcement->starts_at);
        $this->assertNull($announcement->ends_at);
    }

    public function test_an_image_only_announcement_with_dates_is_created_from_the_modal(): void
    {
        Storage::fake('public');

        Livewire::test(ManageAnnouncements::class)
            ->callAction(CreateAction::class, [
                'titulo' => 'Torneo de fuerza',
                'imagen_path' => UploadedFile::fake()->image('banner.jpg', 1200, 600),
                'starts_at' => '2026-11-20 08:00',
                'ends_at' => '2026-11-30 22:00',
            ])
            ->assertHasNoFormErrors();

        $announcement = Announcement::query()->sole();
        $this->assertStringStartsWith('announcements/', $announcement->imagen_path);
        Storage::disk('public')->assertExists($announcement->imagen_path);
        $this->assertSame('2026-11-20 08:00', $announcement->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-30 22:00', $announcement->ends_at->format('Y-m-d H:i'));
    }

    public function test_the_modal_requires_a_title_and_an_end_that_is_not_before_the_start(): void
    {
        Livewire::test(ManageAnnouncements::class)
            ->callAction(CreateAction::class, [
                'titulo' => null,
                'starts_at' => '2026-11-20 08:00',
                'ends_at' => '2026-11-19 08:00',
            ])
            ->assertHasFormErrors(['titulo' => 'required', 'ends_at' => 'after_or_equal']);

        $this->assertDatabaseCount(Announcement::class, 0);
    }

    public function test_the_table_shows_the_situation_and_can_switch_an_announcement_off(): void
    {
        $announcement = Announcement::factory()->create(['titulo' => 'Promo de verano']);
        Announcement::factory()->scheduled()->create();

        Livewire::test(ManageAnnouncements::class)
            ->assertCanSeeTableRecords(Announcement::all())
            ->assertSee(['Promo de verano', 'Visible', 'Programado'])
            ->call('updateTableColumnState', 'is_active', (string) $announcement->getKey(), false);

        $this->assertFalse($announcement->refresh()->is_active);
        $this->assertFalse($announcement->isVisibleNow());
    }

    public function test_an_announcement_is_edited_and_deleted_from_the_table(): void
    {
        $announcement = Announcement::factory()->create(['titulo' => 'Viejo']);

        Livewire::test(ManageAnnouncements::class)
            ->callAction(TestAction::make(EditAction::class)->table($announcement), ['titulo' => 'Nuevo'])
            ->assertHasNoFormErrors();
        $this->assertSame('Nuevo', $announcement->refresh()->titulo);

        Livewire::test(ManageAnnouncements::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($announcement));
        $this->assertDatabaseCount(Announcement::class, 0);
    }
}
