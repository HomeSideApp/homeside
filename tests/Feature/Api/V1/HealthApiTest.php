<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson(route('api.v1.health'));

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'app',
                'environment',
            ])
            ->assertJsonPath('status', 'ok');
    }

    public function test_health_endpoint_does_not_require_auth(): void
    {
        $this->getJson(route('api.v1.health'))
            ->assertOk();
    }
}
