<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * AI module wiring.
 *
 * Agents, modules and context providers are registered by the package's own
 * AiServiceProvider from config('ai-agents.agents' | 'modules' |
 * 'context_providers'). Per-scope agent configuration lives on the
 * household (tenant-owned rows in module_ai_configurations), which the
 * package's AgentConfigurationResolver supports natively via the
 * user → tenant scope chain.
 */
class AiModuleServiceProvider extends ServiceProvider
{
    //
}
