<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminTokenStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_returns_token_and_persists_it_in_dedicated_store(): void
    {
        config()->set('cache.default', 'database');
        config()->set('auth.admin_token_store', 'array');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'is_admin' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'role' => 'admin',
                'user' => [
                    'id' => $admin->id,
                    'email' => $admin->email,
                ],
            ])
            ->assertJsonStructure(['token']);

        $token = $response->json('token');

        $this->assertSame($admin->id, AdminTokenStore::get($token));
    }

    public function test_admin_login_with_remember_me_returns_extended_session_duration(): void
    {
        config()->set('auth.admin_token_store', 'array');

        $admin = User::create([
            'name' => 'Admin Remember',
            'email' => 'remember-admin@example.com',
            'password' => 'secret123',
            'is_admin' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'secret123',
            'remember' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('role', 'admin');

        $this->assertGreaterThan(40000, $response->json('expires_in_minutes'));
        $this->assertSame($admin->id, AdminTokenStore::get($response->json('token')));
    }
}
