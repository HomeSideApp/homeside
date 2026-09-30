<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use HomeSide\AiAgents\Execution\AiExecutionContextData;
use HomeSide\AiAgents\Prompting\PromptBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromptBuilderTest extends TestCase
{
    private PromptBuilder $builder;

    private FakeAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PromptBuilder;
        $this->agent = new FakeAgent;
    }

    #[Test]
    public function builds_prompt_with_core_instructions_only(): void
    {
        $result = $this->builder->build(
            agent: $this->agent,
            userMessage: 'Hello',
        );

        $this->assertStringContainsString('fake agent for testing', $result['system']);
        $this->assertSame('Hello', $result['user']);
    }

    #[Test]
    public function builds_prompt_with_all_instruction_layers(): void
    {
        $result = $this->builder->build(
            agent: $this->agent,
            userMessage: 'Genera una receta',
            instructions: [
                'installation' => 'Instalación global de HomeSide.',
                'module' => 'Módulo de recetas configurado por admin.',
                'household' => 'Hogar de la familia García.',
                'user' => 'Prefiero comidas ligeras.',
            ],
        );

        $this->assertStringContainsString('fake agent for testing', $result['system']);
        $this->assertStringContainsString('Instalación global de HomeSide', $result['system']);
        $this->assertStringContainsString('Módulo de recetas configurado por admin', $result['system']);
        $this->assertStringContainsString('Hogar de la familia García', $result['system']);
        $this->assertStringContainsString('Prefiero comidas ligeras', $result['system']);
        $this->assertSame('Genera una receta', $result['user']);
    }

    #[Test]
    public function builds_prompt_with_domain_context(): void
    {
        $result = $this->builder->build(
            agent: $this->agent,
            userMessage: 'Test',
            domainContext: [
                'products' => [['name' => 'Tomate'], ['name' => 'Cebolla']],
            ],
        );

        $this->assertStringContainsString('Contexto del dominio', $result['system']);
        $this->assertStringContainsString('Tomate', $result['system']);
        $this->assertStringContainsString('Cebolla', $result['system']);
    }

    #[Test]
    public function builds_prompt_with_runtime_context(): void
    {
        $context = new AiExecutionContextData(
            userId: 'user-1',
            householdId: 'hh-1',
            locale: 'es',
            timezone: 'Europe/Madrid',
        );

        $result = $this->builder->build(
            agent: $this->agent,
            userMessage: 'Test',
            executionContext: $context,
        );

        $this->assertStringContainsString('Contexto de ejecución', $result['system']);
        $this->assertStringContainsString('Locale: es', $result['system']);
        $this->assertStringContainsString('Timezone: Europe/Madrid', $result['system']);
        $this->assertStringContainsString('Household ID: hh-1', $result['system']);
    }

    #[Test]
    public function instruction_layers_are_in_correct_order(): void
    {
        $result = $this->builder->build(
            agent: $this->agent,
            userMessage: 'Test',
            instructions: [
                'installation' => 'LAYER_INSTALLATION',
                'module' => 'LAYER_MODULE',
                'household' => 'LAYER_HOUSEHOLD',
                'user' => 'LAYER_USER',
            ],
        );

        $system = $result['system'];
        $posInstallation = strpos($system, 'LAYER_INSTALLATION');
        $posModule = strpos($system, 'LAYER_MODULE');
        $posHousehold = strpos($system, 'LAYER_HOUSEHOLD');
        $posUser = strpos($system, 'LAYER_USER');

        $this->assertNotFalse($posInstallation);
        $this->assertGreaterThan($posInstallation, $posModule);
        $this->assertGreaterThan($posModule, $posHousehold);
        $this->assertGreaterThan($posHousehold, $posUser);
    }

    #[Test]
    public function empty_instructions_are_skipped(): void
    {
        $result = $this->builder->build(
            agent: $this->agent,
            userMessage: 'Test',
            instructions: [
                'installation' => '',
                'module' => 'Module prompt',
                'household' => null,
            ],
        );

        $this->assertStringNotContainsString('Installation prompt', $result['system']);
        $this->assertStringContainsString('Module prompt', $result['system']);
    }
}
