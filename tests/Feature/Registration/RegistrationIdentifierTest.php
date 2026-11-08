<?php

namespace Tests\Feature\Registration;

use App\Support\Registration\RegistrationIdentifier;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers identity normalisation and the dedicated-key HMAC.
 *
 * These tests intentionally live in the Feature suite: the project's
 * phpunit.xml only registers `tests/Feature`, so a `tests/Unit` file here
 * would never run.
 */
class RegistrationIdentifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'registration.block.key' => 'dedicated-block-key',
            'registration.block.key_id' => 'v1',
        ]);
    }

    public function test_it_normalises_by_trimming_and_lowercasing(): void
    {
        $this->assertSame(
            'user@example.com',
            app(RegistrationIdentifier::class)->normalize('  User@Example.COM  '),
        );
    }

    public function test_the_hash_is_deterministic_and_normalisation_insensitive(): void
    {
        $identifier = app(RegistrationIdentifier::class);

        $this->assertSame(
            $identifier->hash('email', 'User@Example.com'),
            $identifier->hash('email', '  user@example.com  '),
        );
    }

    public function test_a_different_key_produces_a_different_hash(): void
    {
        $first = app(RegistrationIdentifier::class)->hash('email', 'user@example.com');

        config(['registration.block.key' => 'rotated-block-key']);
        $second = app(RegistrationIdentifier::class)->hash('email', 'user@example.com');

        $this->assertNotSame($first, $second, 'Rotation must change every computed hash.');
    }

    public function test_the_hash_is_not_a_plain_hash_of_the_email(): void
    {
        $hash = app(RegistrationIdentifier::class)->hash('email', 'user@example.com');

        $this->assertNotSame(hash('sha256', 'user@example.com'), $hash);
        $this->assertSame(
            hash_hmac('sha256', "email\0user@example.com", 'dedicated-block-key'),
            $hash,
        );
    }

    public function test_the_hash_does_not_use_the_application_key(): void
    {
        config(['app.key' => 'base64:'.base64_encode('application-key-material')]);

        $hash = app(RegistrationIdentifier::class)->hash('email', 'user@example.com');

        $this->assertNotSame(
            hash_hmac('sha256', "email\0user@example.com", 'application-key-material'),
            $hash,
        );
        $this->assertNotSame(
            hash_hmac('sha256', 'user@example.com', (string) config('app.key')),
            $hash,
        );
    }

    public function test_identifier_types_are_domain_separated(): void
    {
        $identifier = app(RegistrationIdentifier::class);

        $this->assertNotSame(
            $identifier->hash('email', 'shared-value'),
            $identifier->hash('phone', 'shared-value'),
        );
    }

    public function test_a_missing_key_fails_closed_instead_of_hashing_plainly(): void
    {
        config(['registration.block.key' => null]);

        $this->expectException(RuntimeException::class);

        app(RegistrationIdentifier::class)->hash('email', 'user@example.com');
    }

    public function test_the_key_id_is_reported_for_future_rotation(): void
    {
        $this->assertSame('v1', app(RegistrationIdentifier::class)->keyId());
    }
}
