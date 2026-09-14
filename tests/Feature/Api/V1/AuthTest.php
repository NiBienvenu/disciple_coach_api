<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AppRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_register_and_receives_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Disciple One',
            'email' => 'disciple@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'preferred_language' => 'rn',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'disciple@example.com')
            ->assertJsonPath('data.user.preferred_language', 'rn')
            ->assertJsonPath('data.user.roles', []);

        $this->assertDatabaseHas('users', ['email' => 'disciple@example.com']);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'coach@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'coach@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'coach@example.com')
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'coach@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'coach@example.com',
            'password' => 'wrong',
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_user_can_view_and_update_profile(): void
    {
        $user = User::factory()->create(['preferred_language' => 'fr']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->patchJson('/api/v1/me', [
            'preferred_language' => 'en',
            'name' => 'Updated Name',
        ])->assertOk()
            ->assertJsonPath('data.preferred_language', 'en')
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_user_can_self_assign_disciple_and_coach_roles(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/roles', [
            'roles' => ['disciple', 'coach'],
        ])->assertOk()
            ->assertJsonPath('data.roles', ['disciple', 'coach']);
    }

    public function test_user_cannot_self_assign_staff_roles(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/roles', [
            'roles' => [AppRole::Admin->value],
        ])->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ])->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_reset_password(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }
}
