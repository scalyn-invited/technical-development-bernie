<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_vue_entry_routes_render_without_exposing_private_data(): void
    {
        $this->withoutVite();
        foreach (['/', '/login', '/workspace'] as $route) {
            $this->get($route)->assertOk()->assertSee('id="app"', false);
        }
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_login_identity_and_logout_with_real_bearer_token(): void
    {
        $user = User::factory()->create(['password' => 'test-password']);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'test-password'])->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
        // Discard request-local guard identity before testing the revoked token.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_expired_token_returns_401(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('expiry-test', ['*'], now()->subMinute());
        $this->withToken($token->plainTextToken)->getJson('/api/me')
            ->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }
}
