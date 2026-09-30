<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Context\ContextBuilder;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContextBuilderTest extends TestCase
{
    #[Test]
    public function builds_context_from_agent_providers(): void
    {
        $provider = new FakeContextProvider('products', ['count' => 5]);
        $builder = new ContextBuilder([$provider]);

        $context = new AiExecutionContextData(userId: 'user-1');
        $agent = new FakeAgentWithContextProviders(['products']);

        $result = $builder->build($agent, $context);

        $this->assertArrayHasKey('products', $result);
        $this->assertSame(['count' => 5], $result['products']);
    }

    #[Test]
    public function only_invokes_providers_declared_by_agent(): void
    {
        $productsProvider = new FakeContextProvider('products', [1, 2, 3]);
        $recipesProvider = new FakeContextProvider('recipes', [4, 5, 6]);
        $builder = new ContextBuilder([$productsProvider, $recipesProvider]);

        $context = new AiExecutionContextData(userId: 'user-1');
        $agent = new FakeAgentWithContextProviders(['products']); // Solo products

        $result = $builder->build($agent, $context);

        $this->assertArrayHasKey('products', $result);
        $this->assertArrayNotHasKey('recipes', $result);
    }

    #[Test]
    public function silently_skips_unregistered_providers(): void
    {
        $builder = new ContextBuilder([]);

        $context = new AiExecutionContextData(userId: 'user-1');
        $agent = new FakeAgentWithContextProviders(['nonexistent']);

        $result = $builder->build($agent, $context);

        $this->assertSame([], $result);
    }

    #[Test]
    public function register_adds_new_provider(): void
    {
        $builder = new ContextBuilder([]);
        $provider = new FakeContextProvider('test', 'data');

        $builder->register($provider);

        $this->assertCount(1, $builder->all());
        $this->assertTrue($builder->all()->has('test'));
    }

    #[Test]
    public function builds_empty_context_when_agent_has_no_providers(): void
    {
        $provider = new FakeContextProvider('test', 'data');
        $builder = new ContextBuilder([$provider]);

        $context = new AiExecutionContextData(userId: 'user-1');
        $agent = new FakeAgent; // contextProviders() returns []

        $result = $builder->build($agent, $context);

        $this->assertSame([], $result);
    }
}

/**
 * FakeAgent con contextProviders configurables para tests.
 */
final class FakeAgentWithContextProviders extends FakeAgent
{
    /** @var list<string> */
    private array $providers;

    /** @param  list<string>  $providers */
    public function __construct(array $providers = [])
    {
        $this->providers = $providers;
    }

    public function contextProviders(): array
    {
        return $this->providers;
    }
}
