<?php

namespace Tests\Feature\E2E;

use App\Support\Auth\GoogleSimulator\SimulatorState;
use Tests\TestCase;

/**
 * The simulator keeps its state in a file because `php -S` runs the router
 * script from scratch on every request. The two properties the scenarios rely
 * on are that an authorization code is single-use and that the call counters
 * are observable, so both are pinned here without starting a server.
 */
class GoogleSimulatorStateTest extends TestCase
{
    private string $path;

    private SimulatorState $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/homeside-simulator-state-'.bin2hex(random_bytes(6)).'.json';
        $this->state = new SimulatorState($this->path);
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_it_round_trips_the_scenario_identity(): void
    {
        $this->state->setScenario(['email' => 'new@homeside.test', 'sub' => 'sub-new']);

        $this->assertSame('new@homeside.test', $this->state->scenario()['email'] ?? null);

        $this->state->reset();

        $this->assertNull($this->state->scenario());
    }

    public function test_an_authorization_code_is_single_use(): void
    {
        $code = $this->state->issueCode(['scenario' => ['sub' => 'sub-1']]);

        $this->assertSame(['scenario' => ['sub' => 'sub-1']], $this->state->consumeCode($code));
        $this->assertNull($this->state->consumeCode($code));
        $this->assertNull($this->state->consumeCode('unknown-code'));
    }

    public function test_tokens_resolve_to_their_identity(): void
    {
        $token = $this->state->issueToken(['sub' => 'sub-2', 'email' => 'two@homeside.test']);

        $this->assertSame('two@homeside.test', $this->state->resolveToken($token)['email'] ?? null);
        $this->assertNull($this->state->resolveToken('unknown-token'));
    }

    public function test_it_counts_every_call_and_exposes_the_last_context(): void
    {
        $this->state->recordCall('auth', ['state' => 'state-1']);
        $this->state->recordCall('auth', ['state' => 'state-2']);
        $this->state->recordCall('token');

        $snapshot = $this->state->snapshot();

        $this->assertSame(2, $snapshot['calls']['auth']);
        $this->assertSame(1, $snapshot['calls']['token']);
        $this->assertSame(0, $snapshot['calls']['userinfo']);
        $this->assertSame('state-2', $snapshot['calls']['last_auth']['state']);
    }
}
