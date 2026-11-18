<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\ResolveEnrolmentUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The shared next-step decision every invitation completion funnels through.
 *
 * Both the password path and the Google path must answer with the same target
 * for the same account, so the decision lives in one collaborator.
 */
class ResolveEnrolmentUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_an_account_without_a_second_factor_is_sent_to_enrolment(): void
    {
        $user = User::factory()->create();

        $url = app(ResolveEnrolmentUrl::class)->execute($user);

        $this->assertStringContainsString('auth/bootstrap', $url);
        $this->get($url)->assertOk();
    }

    public function test_an_account_with_a_second_factor_is_sent_to_login(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->assertSame(route('login'), app(ResolveEnrolmentUrl::class)->execute($user));
    }
}
