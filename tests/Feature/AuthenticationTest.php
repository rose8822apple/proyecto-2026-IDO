<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_before_opening_the_dashboard(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));

        $this->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_default_admin_can_log_in_and_open_the_dashboard(): void
    {
        $admin = User::query()->where('email', 'admin@redsalud.local')->firstOrFail();

        $this->post(route('login.store'), [
            'username' => 'admin',
            'password' => 'admin',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->get('/dashboard')->assertOk();
    }

    public function test_invalid_credentials_do_not_authenticate(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => 'admin',
                'password' => 'incorrecta',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $admin = User::query()->where('email', 'admin@redsalud.local')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
