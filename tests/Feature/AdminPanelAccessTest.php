<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_admin_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    public function test_the_admin_login_page_renders(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
    }

    public function test_verified_users_can_access_the_admin_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    public function test_unverified_users_cannot_access_the_admin_panel(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }
}
