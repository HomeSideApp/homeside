<?php

namespace Tests\Unit;

use App\Http\Middleware\SecureHttpMiddleware;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SecureHttpMiddlewareTest extends TestCase
{
    public function test_it_blocks_requests_to_private_ip_addresses(): void
    {
        $middleware = new SecureHttpMiddleware;
        $handler = $middleware(fn () => throw new RuntimeException('The HTTP handler should not be called.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('resolves to private/internal IP');

        $handler(new Request('GET', 'http://127.0.0.1/recipe'), []);
    }
}
