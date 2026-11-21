<?php

namespace Tests\Feature\Base;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_can_login_and_fetch_profile(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.roles.0', 'super-admin');

        $this->withToken($response->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'salah'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create(['email' => 'off@example.com', 'is_active' => false]);

        $this->postJson('/api/auth/login', ['email' => 'off@example.com', 'password' => 'password'])
            ->assertUnprocessable();
    }

    public function test_logout_revokes_token(): void
    {
        $token = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/menu')->assertUnauthorized();
    }
}
