<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use HomeSide\AiAgents\Providers\AiProviderEndpointPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiProviderEndpointPolicyTest extends TestCase
{
    private AiProviderEndpointPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AiProviderEndpointPolicy;
    }

    #[Test]
    public function validates_public_https_url_in_saas_mode(): void
    {
        // No exception = pass
        $this->policy->validate('https://api.openai.com/v1', 'saas');
        $this->assertTrue(true);
    }

    #[Test]
    public function rejects_http_url_in_saas_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('solo se permiten URLs HTTPS');

        $this->policy->validate('http://api.openai.com/v1', 'saas');
    }

    #[Test]
    public function allows_http_url_in_self_hosted_mode(): void
    {
        // localhost es permitido en modo self-hosted
        $this->policy->validate('http://localhost:11434/api', 'self-hosted');
        $this->assertTrue(true);
    }

    #[Test]
    public function rejects_loopback_in_saas_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IP privada');

        $this->policy->validate('https://127.0.0.1:11434/api', 'saas');
    }

    #[Test]
    public function rejects_private_ip_in_saas_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IP privada');

        $this->policy->validate('https://192.168.1.100:11434/api', 'saas');
    }

    #[Test]
    public function rejects_cloud_metadata_endpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('metadata cloud');

        $this->policy->validate('https://169.254.169.254/latest/meta-data/', 'saas');
    }

    #[Test]
    public function rejects_dangerous_port(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Puerto no permitido');

        $this->policy->validate('https://example.com:22/path', 'saas');
    }

    #[Test]
    public function rejects_invalid_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URL inválida');

        $this->policy->validate('not-a-url', 'saas');
    }
}
