<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiRefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_refresh_token(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('auth-token');

        Sanctum::actingAs($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$oldToken->plainTextToken,
        ])->postJson(route('api.v1.auth.refresh'));

        $response->assertOk()
            ->assertJsonStructure(['message', 'token', 'user' => ['id', 'name', 'email']]);

        // Old token should be revoked from DB
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $oldToken->accessToken->id,
        ]);

        // A new token should exist (the one created during refresh)
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_refresh_returns_new_token_value(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('auth-token');
        $oldTokenValue = $oldToken->plainTextToken;

        Sanctum::actingAs($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$oldTokenValue,
        ])->postJson(route('api.v1.auth.refresh'));

        $response->assertOk();

        $newTokenValue = $response->json('token');
        $this->assertNotSame($oldTokenValue, $newTokenValue);
    }

    public function test_unauthenticated_user_cannot_refresh(): void
    {
        $this->postJson(route('api.v1.auth.refresh'))
            ->assertUnauthorized();
    }
}
