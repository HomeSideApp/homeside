<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiResendOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_temp_token_can_resend_otp(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);

        $tempToken = $user->createToken('temp-otp', ['otp-verify'], now()->addMinutes(5));

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$tempToken->plainTextToken,
        ])->postJson(route('api.v1.auth.login.otp.resend'));

        $response->assertOk()
            ->assertJsonStructure(['message', 'temp_token']);

        // Old temp token should be revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tempToken->accessToken->id,
        ]);

        // A new temp token should exist
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_resend_otp_returns_different_temp_token(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);

        $oldTempToken = $user->createToken('temp-otp', ['otp-verify'], now()->addMinutes(5));

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$oldTempToken->plainTextToken,
        ])->postJson(route('api.v1.auth.login.otp.resend'));

        $response->assertOk();

        $newTempToken = $response->json('temp_token');
        $this->assertNotSame($oldTempToken->plainTextToken, $newTempToken);
    }

    public function test_auth_token_cannot_resend_otp(): void
    {
        $user = User::factory()->create();
        $authToken = $user->createToken('auth-token');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$authToken->plainTextToken,
        ])->postJson(route('api.v1.auth.login.otp.resend'))
            ->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_resend_otp(): void
    {
        $this->postJson(route('api.v1.auth.login.otp.resend'))
            ->assertUnauthorized();
    }
}
