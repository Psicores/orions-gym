<?php

namespace Tests\Feature;

use App\Enums\SiteSectionEnum;
use App\Filament\Pages\SiteReviews;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_the_screen_is_in_the_site_menu(): void
    {
        $this->get(SiteReviews::getUrl())
            ->assertOk()
            ->assertSee('Opiniones de socios')
            ->assertSee('Agregar opinión')
            ->assertDontSee('Próximamente');
    }

    public function test_saved_reviews_appear_on_the_landing_in_order_with_their_stars(): void
    {
        Livewire::test(SiteReviews::class)
            ->fillForm(['opiniones' => [
                ['autor' => ' Lucía F. ', 'estrellas' => 5, 'texto' => ' Excelente ambiente. '],
                ['autor' => 'Martín G.', 'estrellas' => '3', 'texto' => 'Muy completo.'],
            ]])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Cambios guardados');

        $this->assertSame(['opiniones' => [
            ['autor' => 'Lucía F.', 'texto' => 'Excelente ambiente.', 'estrellas' => 5],
            ['autor' => 'Martín G.', 'texto' => 'Muy completo.', 'estrellas' => 3],
        ]], SiteContent::dataFor(SiteSectionEnum::Opiniones));

        $this->get(route('inicio'))
            ->assertSeeInOrder(['5 de 5 estrellas', 'Excelente ambiente.', 'Lucía F.', '3 de 5 estrellas', 'Muy completo.', 'Martín G.']);
    }

    public function test_each_review_needs_a_name_and_a_text(): void
    {
        Livewire::test(SiteReviews::class)
            ->fillForm(['opiniones' => [['autor' => '', 'estrellas' => 5, 'texto' => '']]])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame(0, SiteContent::query()->where('section', SiteSectionEnum::Opiniones)->count());
    }

    public function test_reviews_cannot_be_too_long(): void
    {
        Livewire::test(SiteReviews::class)
            ->fillForm(['opiniones' => [['autor' => 'Ana', 'estrellas' => 5, 'texto' => str_repeat('a', 301)]]])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame(0, SiteContent::query()->where('section', SiteSectionEnum::Opiniones)->count());
    }

    public function test_removing_every_review_hides_the_section_on_the_landing(): void
    {
        SiteContent::query()->create(['section' => SiteSectionEnum::Opiniones, 'data' => ['opiniones' => [
            ['autor' => 'Ana', 'texto' => 'Bien.', 'estrellas' => 5],
        ]]]);

        Livewire::test(SiteReviews::class)
            ->assertSee('Ana')
            ->fillForm(['opiniones' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get(route('inicio'))->assertDontSee('id="titulo-opiniones"', escape: false);
    }
}
