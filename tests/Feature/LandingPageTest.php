<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La portada responde.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_the_landing_links_to_the_curev_hue_coming_soon_page(): void
    {
        $this->get('/')->assertOk()->assertSee(route('curev-hue'));

        $this->get(route('curev-hue'))
            ->assertOk()
            ->assertSee('Próximamente')
            ->assertSee('images/video-windsurf.mp4');
    }
}
