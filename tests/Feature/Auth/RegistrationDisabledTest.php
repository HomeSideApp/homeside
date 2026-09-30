<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_route_does_not_exist()
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_registration_post_returns_404()
    {
        $response = $this->post('/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();
    }
}
